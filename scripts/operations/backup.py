"""Stream DB/private storage directly to age. No plaintext dump/archive is written."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile
from datetime import datetime, timezone


def inside(path, parent):
    return path == parent or parent in path.parents


def binary(value):
    executable = shutil.which(value)
    if not executable:
        raise ValueError(f"Required executable is unavailable: {Path(value).name}")
    return executable


def encrypt_dump(producer, age, recipient, output):
    # Argument arrays, binary streams, no shell pipeline or credential-bearing URL.
    with tempfile.TemporaryFile() as errors:
        encryptor = subprocess.Popen([age, '-r', recipient, '-o', str(output)], stdin=subprocess.PIPE, stdout=subprocess.DEVNULL, stderr=errors)
        source = None
        try:
            source = subprocess.Popen(producer, stdout=encryptor.stdin, stderr=errors, env={**os.environ, 'PGCONNECT_TIMEOUT': '5'})
            encryptor.stdin.close()
            source_status = source.wait(timeout=3600)
            encrypted_status = encryptor.wait(timeout=3600)
            if source_status or encrypted_status:
                raise RuntimeError('Database encryption pipeline failed; no completed backup was published.')
        finally:
            for process in [source, encryptor]:
                if process is not None and process.poll() is None:
                    process.kill()
                    process.wait()


def encrypt_storage(root, age, recipient, output):
    with tempfile.TemporaryFile() as errors:
        process = subprocess.Popen([age, '-r', recipient, '-o', str(output)], stdin=subprocess.PIPE, stdout=subprocess.DEVNULL, stderr=errors)
        try:
            with tarfile.open(fileobj=process.stdin, mode='w|') as archive:
                def safe_member(member):
                    if not (member.isfile() or member.isdir()):
                        raise ValueError('Private storage contains a link/device; backup must be reviewed.')
                    return member
                archive.add(root, arcname='private', recursive=True, filter=safe_member)
            process.stdin.close()
            if process.wait(timeout=3600):
                raise RuntimeError('Storage encryption failed; no completed backup was published.')
        finally:
            if process.poll() is None:
                process.kill()
                process.wait()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--project', type=Path, default=Path(__file__).resolve().parents[2])
    parser.add_argument('--destination', type=Path, required=True)
    parser.add_argument('--recipient', required=True, help='age PUBLIC recipient; keep recovery identity off this host')
    parser.add_argument('--pg-service', required=True, help='Name in the operator-managed pg_service.conf / pgpass')
    parser.add_argument('--php', default='php')
    parser.add_argument('--pg-dump', default='pg_dump')
    parser.add_argument('--age', default='age')
    parser.add_argument('--preflight', action='store_true')
    parser.add_argument('--quiesced', action='store_true', help='Confirm dedicated worker/scheduler are stopped and writes drained')
    args = parser.parse_args()
    project = args.project.resolve(strict=True)
    destination = args.destination.resolve()
    if inside(destination, project):
        raise ValueError('Backup destination must be outside the application/web/repository tree.')
    if not re.fullmatch(r'age1[a-z0-9]{50,100}', args.recipient):
        raise ValueError('Provide an age public recipient, never a recovery secret.')
    if not re.fullmatch(r'[A-Za-z0-9_-]{1,80}', args.pg_service):
        raise ValueError('Invalid PostgreSQL service name.')
    php, pg_dump, age = [binary(v) for v in [args.php, args.pg_dump, args.age]]
    context_result = subprocess.run([php, 'artisan', 'hris:backup-context'], cwd=project, capture_output=True, env={**os.environ, 'PGCONNECT_TIMEOUT': '5'}, timeout=30)
    if context_result.returncode:
        raise RuntimeError('Application boundary/context check failed; details are not copied to backup logs.')
    context = json.loads(context_result.stdout)
    database = context['database']
    if context['driver'] != 'pgsql' or not re.fullmatch(r'[A-Za-z0-9_-]{1,100}', database):
        raise ValueError('Expected an explicit PostgreSQL application database.')
    storage = Path(context['private_storage']).resolve(strict=True)
    if inside(destination, storage):
        raise ValueError('Destination cannot be inside private storage.')
    if args.preflight:
        print(json.dumps({'preflight': True, 'database': database, 'maintenance': context['maintenance'], 'encryption': 'age', 'destination_outside_application': True}))
        return
    if not args.quiesced or not context['maintenance']:
        raise ValueError('Enable maintenance, stop only HRIS services, drain writes, then supply --quiesced.')
    destination.mkdir(parents=True, exist_ok=True, mode=0o700)
    name = 'hris-' + datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%S%fZ')
    work = Path(tempfile.mkdtemp(prefix='.partial-', dir=destination))
    try:
        encrypt_dump([pg_dump, '--no-password', '--format=custom', '--dbname', f'service={args.pg_service} dbname={database}'], age, args.recipient, work / 'database.dump.age')
        encrypt_storage(storage, age, args.recipient, work / 'private.tar.age')
        hashes = {}
        for artifact in work.iterdir():
            with artifact.open('rb') as stream:
                hashes[artifact.name] = hashlib.file_digest(stream, 'sha256').hexdigest()
        revision = subprocess.run(['git', 'rev-parse', 'HEAD'], cwd=project, capture_output=True, text=True, timeout=10)
        manifest = {'created_at': datetime.now(timezone.utc).isoformat(), 'database': database, 'installation': context['installation'], 'revision': revision.stdout.strip() if revision.returncode == 0 else None, 'sha256': hashes, 'app_key': 'Backed up separately by operator; never embedded here', 'restore_verified': False}
        (work / 'manifest.json').write_text(json.dumps(manifest, indent=2), encoding='utf8')
        completed = destination / name
        work.rename(completed)
        print(json.dumps({'backup': str(completed), 'encrypted': True, 'restore_verified': False}))
    except BaseException:
        # work is generated directly under a checked destination, never supplied by a caller.
        if work.parent == destination and work.name.startswith('.partial-'):
            shutil.rmtree(work)
        raise


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print(f'Backup stopped: {error}', file=sys.stderr)
        sys.exit(1)

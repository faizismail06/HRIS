"""Verify encrypted artifact hashes; optionally test decryption without restoring a live DB."""
import argparse
import hashlib
import json
from pathlib import Path, PurePosixPath, PureWindowsPath
import subprocess
import sys
import tarfile
import tempfile
from backup import binary


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('backup', type=Path)
    parser.add_argument('--identity', type=Path, help='Off-host age recovery identity file; never its contents')
    parser.add_argument('--age', default='age')
    parser.add_argument('--pg-restore', default='pg_restore')
    args = parser.parse_args()
    root = args.backup.resolve(strict=True)
    manifest = json.loads((root / 'manifest.json').read_text(encoding='utf8'))
    expected = {'database.dump.age', 'private.tar.age'}
    if set(manifest.get('sha256', {})) != expected:
        raise ValueError('Manifest must contain exactly the two expected encrypted artifacts.')
    for name in expected:
        path = root / name
        if path.is_symlink(): raise ValueError('Artifact symlinks are not supported.')
        with path.open('rb') as stream:
            if hashlib.file_digest(stream, 'sha256').hexdigest() != manifest['sha256'][name]:
                raise ValueError('Encrypted artifact checksum mismatch.')
    if args.identity is None:
        print(json.dumps({'hashes_verified': True, 'decryption_verified': False, 'restore_verified': False}))
        return
    identity = args.identity.resolve(strict=True)
    age, pg_restore = binary(args.age), binary(args.pg_restore)
    with tempfile.TemporaryFile() as errors:
        decrypt = subprocess.Popen([age, '-d', '-i', str(identity), str(root / 'database.dump.age')], stdout=subprocess.PIPE, stderr=errors)
        reader = None
        try:
            reader = subprocess.Popen([pg_restore, '--list'], stdin=decrypt.stdout, stdout=subprocess.DEVNULL, stderr=errors)
            decrypt.stdout.close()
            if reader.wait(timeout=3600) or decrypt.wait(timeout=3600):
                raise RuntimeError('Dump decryption/readability verification failed.')
        finally:
            for process in [reader, decrypt]:
                if process is not None and process.poll() is None:
                    process.kill(); process.wait()
        decrypt = subprocess.Popen([age, '-d', '-i', str(identity), str(root / 'private.tar.age')], stdout=subprocess.PIPE, stderr=errors)
        try:
            with tarfile.open(fileobj=decrypt.stdout, mode='r|') as archive:
                for member in archive:
                    posix, windows = PurePosixPath(member.name), PureWindowsPath(member.name)
                    if posix.is_absolute() or windows.drive or '..' in posix.parts or '..' in windows.parts or not (member.isfile() or member.isdir()):
                        raise ValueError('Unsafe private archive member.')
                    if member.isfile():
                        source = archive.extractfile(member)
                        while source.read(1024 * 1024): pass
            while decrypt.stdout.read(1024 * 1024): pass
            if decrypt.wait(timeout=3600):
                raise RuntimeError('Private storage decryption verification failed.')
        finally:
            if decrypt.poll() is None: decrypt.kill(); decrypt.wait()
    print(json.dumps({'hashes_verified': True, 'decryption_verified': True, 'restore_verified': False, 'note': 'Full recovery still requires a guarded isolated DB/storage restore and application checks.'}))


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print(f'Verification stopped: {error}', file=sys.stderr)
        sys.exit(1)

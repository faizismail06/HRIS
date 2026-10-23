import importlib.util
from pathlib import Path
import subprocess
import sys
import tarfile
import tempfile
import unittest
from unittest.mock import patch
from io import BytesIO

spec = importlib.util.spec_from_file_location('hris_backup', Path(__file__).resolve().parents[2] / 'scripts/operations/backup.py')
backup = importlib.util.module_from_spec(spec)
spec.loader.exec_module(backup)


class BackupPipelineTest(unittest.TestCase):
    """Tests transport/guards with a clearly fake cipher, not cryptographic strength."""
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.cipher = self.root / 'fake_cipher.py'
        self.cipher.write_text("import sys,pathlib\npathlib.Path(sys.argv[-1]).write_bytes(b'TEST-ONLY\\n'+sys.stdin.buffer.read())\n")
        self.real_popen = subprocess.Popen

    def tearDown(self):
        self.temp.cleanup()

    def popen(self, args, **kwargs):
        if args[0] == 'FAKE-AGE':
            args = [sys.executable, str(self.cipher), *args[1:]]
        return self.real_popen(args, **kwargs)

    def test_database_pipe_preserves_binary_bytes_and_detects_failure(self):
        output = self.root / 'db.age'
        with patch.object(backup.subprocess, 'Popen', self.popen):
            backup.encrypt_dump([sys.executable, '-c', 'import sys;sys.stdout.buffer.write(bytes(range(256))*100)'], 'FAKE-AGE', 'PUBLIC', output)
            self.assertEqual(b'TEST-ONLY\n' + bytes(range(256))*100, output.read_bytes())
            with self.assertRaises(RuntimeError):
                backup.encrypt_dump([sys.executable, '-c', 'raise SystemExit(4)'], 'FAKE-AGE', 'PUBLIC', self.root / 'failed.age')

    def test_storage_tar_is_streamed_without_plaintext_archive(self):
        storage = self.root / 'private'
        storage.mkdir()
        (storage / 'proof.bin').write_bytes(bytes(range(256)))
        with patch.object(backup.subprocess, 'Popen', self.popen):
            backup.encrypt_storage(storage, 'FAKE-AGE', 'PUBLIC', self.root / 'private.age')
        with tarfile.open(fileobj=BytesIO((self.root / 'private.age').read_bytes()[10:]), mode='r:') as archive:
            self.assertEqual(bytes(range(256)), archive.extractfile('private/proof.bin').read())
        self.assertFalse(list(self.root.glob('*.tar')))
        self.assertFalse(list(self.root.glob('*.dump')))

    def test_destination_must_stay_outside_application_tree(self):
        self.assertTrue(backup.inside(self.root / 'public/backups', self.root))
        self.assertFalse(backup.inside(self.root.parent / 'other', self.root))


if __name__ == '__main__':
    unittest.main()

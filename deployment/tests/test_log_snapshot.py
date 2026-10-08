import importlib.util
import json
import pathlib
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('snapshot', pathlib.Path(__file__).parents[1] / 'scripts/exadcam-log-snapshot.py')
snapshot = importlib.util.module_from_spec(spec)
spec.loader.exec_module(snapshot)


class SnapshotTests(unittest.TestCase):
    def record(self, message):
        return json.dumps({'MESSAGE': message, '__REALTIME_TIMESTAMP': '1790593200000000'})

    def test_formats_real_events_and_skips_malformed_messages(self):
        content, truncated = snapshot.render(self.record('device_authenticated') + '\ninvalid\n' + self.record([1, 2]))
        self.assertIn('+01:00 device_authenticated', content)
        self.assertEqual(len(content.splitlines()), 1)
        self.assertFalse(truncated)

    def test_bounds_bytes_and_lines_preserving_recent_events(self):
        raw = '\n'.join(self.record('x' * 8193 + str(i)) for i in range(1100))
        content, truncated = snapshot.render(raw)
        self.assertLessEqual(len(content.encode()), snapshot.MAX_BYTES)
        self.assertLessEqual(len(content.splitlines()), 1000)
        self.assertTrue(truncated)

    def test_rejects_arbitrary_units(self):
        with self.assertRaises(ValueError):
            snapshot.collect('ssh.service')

    def test_reports_failures_without_exposing_process_errors(self):
        with patch.object(snapshot.subprocess, 'run', side_effect=OSError('sensitive details')):
            result = snapshot.collect('exadcam-gps.service')
        self.assertFalse(result['available'])
        self.assertEqual(result['content'], '')
        self.assertNotIn('sensitive details', json.dumps(result))


if __name__ == '__main__':
    unittest.main()

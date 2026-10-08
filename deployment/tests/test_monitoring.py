import importlib.util
import json
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('monitoring', Path(__file__).parents[1] / 'scripts/exadcam-monitoring.py')
monitoring = importlib.util.module_from_spec(spec)
spec.loader.exec_module(monitoring)


class MonitoringTests(unittest.TestCase):
    def test_cpu_counts_guest_once_and_computes_delta(self):
        before = monitoring.cpu_stat('cpu 100 10 30 500 20 5 5 0 40 2\ncpu0 1\ncpu1 1\n')
        after = monitoring.cpu_stat('cpu 120 10 40 560 30 5 5 0 50 2\ncpu0 1\ncpu1 1\n')
        self.assertEqual(before['total'], 670)
        self.assertEqual(before['cores'], 2)
        self.assertEqual(monitoring.cpu_usage(after, before), 30)
        self.assertIsNone(monitoring.cpu_usage(before, after))
        self.assertIsNone(monitoring.cpu_usage(before, before))
        self.assertIsNone(monitoring.cpu_usage(after, None))
        self.assertIsNone(monitoring.cpu_stat('cpu invalid'))

    def test_memory_uses_available_and_absence_is_not_zero(self):
        actual = monitoring.memory_info('MemTotal: 10000 kB\nMemFree: 100 kB\nMemAvailable: 6000 kB\nSwapTotal: 0 kB\nSwapFree: 0 kB\n')
        self.assertEqual(actual['used'], 4000 * 1024)
        self.assertEqual(actual['percent'], 40)
        self.assertEqual(actual['swap_total'], 0)
        self.assertIsNone(actual['swap_percent'])
        self.assertIsNone(monitoring.memory_info('')['percent'])
        self.assertIsNone(monitoring.memory_info('MemTotal: 100 kB')['used'])

    def test_network_elapsed_reset_and_new_interface(self):
        text = 'eth0: 2000 0 0 0 0 0 0 0 4000 0 0 0 0 0 0 0\nlo: 100 0 0 0 0 0 0 0 100 0 0 0 0 0 0 0'
        current = monitoring.network_stat(text)
        self.assertNotIn('lo', current)
        before = {'eth0': {'rx': 1000, 'tx': 2000}}
        actual = monitoring.network_rates(current, before, 5)
        self.assertEqual(actual['rx_rate'], 200)
        self.assertEqual(actual['tx_rate'], 400)
        self.assertIsNone(monitoring.network_rates(before, current, 5)['rx_rate'])
        self.assertIsNone(monitoring.network_rates(current, {}, 5)['rx_rate'])
        self.assertIsNone(monitoring.network_rates(current, before, 0)['rx_rate'])
        self.assertIsNone(monitoring.network_rates(current, before, 60)['rx_rate'])
        self.assertIsNone(monitoring.network_rates({}, before, 5)['rx_rate'])

    def test_disk_distinguishes_reserved_space_from_used(self):
        class Stat:
            f_blocks, f_bfree, f_bavail, f_frsize = 100, 20, 15, 1024
        with patch.object(monitoring.os, 'statvfs', return_value=Stat()):
            disk = monitoring.disk_info('/')
        self.assertEqual(disk['used'], 80 * 1024)
        self.assertEqual(disk['available'], 15 * 1024)
        self.assertEqual(disk['percent'], 80)

    def test_history_is_bounded_and_gaps_rebaseline_counters(self):
        with tempfile.TemporaryDirectory() as directory:
            proc = Path(directory)
            (proc / 'stat').write_text('cpu 100 0 0 100 0 0 0 0\ncpu0 1\n')
            (proc / 'meminfo').write_text('MemTotal: 1000 kB\nMemAvailable: 500 kB\n')
            (proc / 'loadavg').write_text('0.09 0.15 0.2 1/20 30')
            (proc / 'uptime').write_text('120.5 5')
            sampler = monitoring.Sampler(proc, proc)
            with patch.object(monitoring, 'disk_info', return_value={}):
                first = sampler.sample(1000, 10)
                self.assertIsNone(first['cpu']['usage'])
                self.assertEqual(first['load']['one'], .09)
                self.assertEqual(first['system']['uptime'], 120.5)
                for i in range(1, 201):
                    latest = sampler.sample(1000 + i * 5, 10 + i * 5)
                self.assertEqual(len(latest['history']), 180)
                self.assertEqual(latest['history'][-1]['time'], 2000000)
                gap = sampler.sample(2100, 1110)
                self.assertEqual(len(gap['history']), 1)
                self.assertIsNone(gap['cpu']['usage'])
                clock_reset = sampler.sample(100, 1115)
                self.assertEqual(len(clock_reset['history']), 1)

    def test_publish_is_private_atomic_json(self):
        with tempfile.TemporaryDirectory() as directory:
            output = Path(directory) / 'metrics.json'
            monitoring.publish({'schema': 1, 'history': []}, output)
            monitoring.publish({'schema': 1, 'history': [1]}, output)
            self.assertEqual(json.loads(output.read_text())['history'], [1])
            self.assertFalse(output.with_suffix('.tmp').exists())
            self.assertEqual(output.stat().st_mode & 0o777, 0o640)


if __name__ == '__main__':
    unittest.main()

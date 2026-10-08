#!/usr/bin/env python3
"""Read-only Linux host metrics. No web input, subprocesses or camera commands."""
import json
import math
import os
import platform
import time
from collections import deque
from pathlib import Path

INTERVAL = 5
HISTORY = 180
OUTPUT = Path('/var/lib/exadcam-monitoring/metrics.json')


def read(path):
    try:
        return path.read_text(encoding='ascii')
    except (OSError, UnicodeError):
        return ''


def cpu_stat(text):
    lines = text.splitlines()
    if not lines or not lines[0].startswith('cpu '):
        return None
    try:
        # guest/guest_nice are already included in user/nice. Do not count twice.
        values = [int(v) for v in lines[0].split()[1:9]]
        if len(values) < 4 or min(values) < 0:
            return None
        return {'total': sum(values), 'idle': values[3] + (values[4] if len(values) > 4 else 0),
                'cores': sum(line.split()[0][3:].isdigit() for line in lines if line.startswith('cpu')) or None}
    except ValueError:
        return None


def cpu_usage(current, previous):
    if not current or not previous:
        return None
    total, idle = current['total'] - previous['total'], current['idle'] - previous['idle']
    if total <= 0 or idle < 0 or idle > total:
        return None
    return round(100 * (total - idle) / total, 2)


def memory_info(text):
    info = {}
    for line in text.splitlines():
        fields = line.split()
        if len(fields) >= 2 and fields[1].isdigit():
            info[fields[0].rstrip(':')] = int(fields[1]) * 1024
    total, available = info.get('MemTotal'), info.get('MemAvailable')
    used = max(0, total - available) if total and available is not None else None
    swap_total, swap_free = info.get('SwapTotal'), info.get('SwapFree')
    swap_used = max(0, swap_total - swap_free) if swap_total is not None and swap_free is not None else None
    return {'total': total, 'available': available, 'used': used,
            'percent': round(100 * used / total, 2) if total and used is not None else None,
            'swap_total': swap_total, 'swap_used': swap_used,
            'swap_percent': round(100 * swap_used / swap_total, 2) if swap_total and swap_used is not None else None}


def network_stat(text):
    result = {}
    for line in text.splitlines():
        if ':' not in line:
            continue
        name, values = line.split(':', 1)
        name, values = name.strip(), values.split()
        if name == 'lo' or len(values) < 16:
            continue
        try:
            rx, tx = int(values[0]), int(values[8])
            if rx >= 0 and tx >= 0:
                result[name] = {'rx': rx, 'tx': tx}
        except ValueError:
            continue
    return result


def network_rates(current, previous, elapsed):
    interfaces = []
    for name, values in sorted(current.items()):
        rx_rate = tx_rate = None
        old = previous.get(name)
        # A disappeared/new/reset interface needs a fresh baseline, not a fake zero.
        if old and 0 < elapsed <= INTERVAL * 3 and values['rx'] >= old['rx'] and values['tx'] >= old['tx']:
            rx_rate = round((values['rx'] - old['rx']) / elapsed, 2)
            tx_rate = round((values['tx'] - old['tx']) / elapsed, 2)
        interfaces.append({'name': name, **values, 'rx_rate': rx_rate, 'tx_rate': tx_rate})
    complete = bool(interfaces) and all(i['rx_rate'] is not None for i in interfaces)
    return {'interfaces': interfaces,
            'rx_rate': round(sum(i['rx_rate'] for i in interfaces), 2) if complete else None,
            'tx_rate': round(sum(i['tx_rate'] for i in interfaces), 2) if complete else None}


def disk_info(path):
    try:
        stat = os.statvfs(path)
        total = stat.f_blocks * stat.f_frsize
        free = stat.f_bfree * stat.f_frsize
        available = stat.f_bavail * stat.f_frsize
        used = max(0, total - free)
        return {'total': total, 'used': used, 'free': free, 'available': available,
                'percent': round(100 * used / total, 2) if total else None}
    except OSError:
        return dict.fromkeys(['total', 'used', 'free', 'available', 'percent'])


class Sampler:
    def __init__(self, proc=Path('/proc'), disk=Path('/var/www/exadcam')):
        self.proc, self.disk = proc, disk
        self.previous_cpu = None
        self.previous_network = {}
        self.previous_time = None
        self.history = deque(maxlen=HISTORY)

    def sample(self, wall=None, monotonic=None):
        wall = time.time() if wall is None else wall
        monotonic = time.monotonic() if monotonic is None else monotonic
        elapsed = monotonic - self.previous_time if self.previous_time is not None else 0
        cpu = cpu_stat(read(self.proc / 'stat'))
        network = network_stat(read(self.proc / 'net/dev'))
        memory = memory_info(read(self.proc / 'meminfo'))
        usage = cpu_usage(cpu, self.previous_cpu) if 0 < elapsed <= INTERVAL * 3 else None
        rates = network_rates(network, self.previous_network, elapsed)
        try:
            load = [float(v) for v in read(self.proc / 'loadavg').split()[:3]]
            if len(load) != 3 or not all(math.isfinite(v) and v >= 0 for v in load):
                raise ValueError()
        except ValueError:
            load = [None, None, None]
        try:
            uptime = float(read(self.proc / 'uptime').split()[0])
            if not math.isfinite(uptime) or uptime < 0:
                raise ValueError()
        except (ValueError, IndexError):
            uptime = None
        timestamp = round(wall * 1000)
        if self.history and (timestamp <= self.history[-1]['time'] or elapsed > INTERVAL * 3):
            # A restart, suspend or wall-clock step must not join unrelated chart points.
            self.history.clear()
        self.history.append({'time': timestamp, 'cpu': usage, 'memory': memory['percent'],
                             'rx': rates['rx_rate'], 'tx': rates['tx_rate'], 'load': load[0]})
        while self.history and self.history[0]['time'] < timestamp - 15 * 60 * 1000:
            self.history.popleft()
        self.previous_cpu, self.previous_network, self.previous_time = cpu, network, monotonic
        return {'schema': 1, 'captured_at': timestamp, 'interval': INTERVAL,
                'cpu': {'usage': usage, 'cores': cpu['cores'] if cpu else None},
                'memory': memory, 'disk': disk_info(self.disk),
                'load': {'one': load[0], 'five': load[1], 'fifteen': load[2]}, 'network': rates,
                'system': {'hostname': platform.node(), 'os': platform.system() + ' ' + platform.release(), 'uptime': uptime},
                'history': list(self.history)}


def publish(payload, output=OUTPUT):
    temporary = output.with_suffix('.tmp')
    with temporary.open('w', encoding='utf-8') as handle:
        json.dump(payload, handle, ensure_ascii=True, allow_nan=False, separators=(',', ':'))
    os.chmod(temporary, 0o640)
    os.replace(temporary, output)


def main():
    sampler = Sampler()
    while True:
        started = time.monotonic()
        publish(sampler.sample())
        time.sleep(max(0.1, INTERVAL - (time.monotonic() - started)))


if __name__ == '__main__':
    main()

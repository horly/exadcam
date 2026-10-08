#!/usr/bin/python3
"""Read-only journal snapshots for the EXADCAM log viewer; no user input."""
import collections
import datetime
import json
import os
import pathlib
import subprocess
import tempfile
from zoneinfo import ZoneInfo

UNITS = {"gps": "exadcam-gps.service", "video": "exadcam-video.service",
         "audio": "exadcam-audio.service", "recordings": "exadcam-recordings.service"}
DIRECTORY = pathlib.Path('/var/lib/exadcam-logs')
MAX_BYTES = 262144
MAX_READ = 2097152


def render(raw):
    rows = collections.deque()
    size = 0
    truncated = False
    for line in raw.splitlines():
        try:
            entry = json.loads(line)
            message = entry.get('MESSAGE', '')
            if not isinstance(message, str):
                continue
            timestamp = datetime.datetime.fromtimestamp(int(entry['__REALTIME_TIMESTAMP']) / 1000000,
                                                        datetime.timezone.utc).astimezone(ZoneInfo('Africa/Kinshasa'))
            if len(message) > 8192:
                message = message[:8192] + ' [truncated]'
                truncated = True
            text = timestamp.isoformat(timespec='seconds') + ' ' + message
            for part in text.splitlines():
                encoded = part.encode('utf-8', errors='replace')
                rows.append(encoded)
                size += len(encoded) + 1
                while rows and (size > MAX_BYTES or len(rows) > 1000):
                    size -= len(rows.popleft()) + 1
                    truncated = True
        except (ValueError, KeyError, TypeError, OverflowError, OSError):
            continue
    return b'\n'.join(rows).decode('utf-8', errors='replace'), truncated


def collect(unit):
    if unit not in UNITS.values():
        raise ValueError('Unknown unit')
    captured = datetime.datetime.now(datetime.timezone.utc).isoformat()
    try:
        with tempfile.TemporaryFile() as spool:
            result = subprocess.run(['/usr/bin/journalctl', '--quiet', '--no-pager', '--unit', unit,
                                     '--lines=1000', '--output=json', '--output-fields=MESSAGE,__REALTIME_TIMESTAMP'],
                                    stdin=subprocess.DEVNULL, stdout=spool, stderr=subprocess.DEVNULL,
                                    timeout=3, check=False)
            if result.returncode:
                raise RuntimeError('Journal unavailable')
            length = spool.tell()
            offset = max(0, length - MAX_READ)
            spool.seek(offset)
            raw = spool.read(MAX_READ)
            if offset:
                raw = raw.partition(b'\n')[2]
            content, truncated = render(raw.decode('utf-8', errors='replace'))
            return {'available': True, 'captured_at': captured, 'content': content,
                    'truncated': truncated or offset > 0}
    except (OSError, RuntimeError, subprocess.TimeoutExpired):
        return {'available': False, 'captured_at': captured, 'content': '', 'truncated': False}


def main():
    os.umask(0o027)
    for source, unit in UNITS.items():
        target = DIRECTORY / (source + '.json')
        temporary = target.with_suffix('.tmp')
        temporary.write_text(json.dumps(collect(unit), ensure_ascii=False), encoding='utf-8')
        os.chmod(temporary, 0o640)
        temporary.replace(target)


if __name__ == '__main__':
    main()

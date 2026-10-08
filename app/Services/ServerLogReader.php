<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Throwable;

class ServerLogReader
{
    public const SOURCES = ['gps', 'video', 'audio', 'recordings', 'laravel'];

    private const MAX_BYTES = 524288;

    public function read(string $source, int $lines, string $level, string $search): array
    {
        if (! in_array($source, self::SOURCES, true)) {
            throw new \InvalidArgumentException('Unknown log source');
        }
        $result = $source === 'laravel' ? $this->laravel() : $this->snapshot($source);
        $content = $this->redact($result['content']);
        $rows = $content === '' ? [] : preg_split('/\r\n|\n|\r/', rtrim($content));
        $needle = mb_strtolower(trim($search));
        $rows = array_values(array_filter($rows, fn ($line) => ($level !== 'errors' || preg_match('/error|exception|failed|failure|rejected|unavailable|critical|alert|emergency|timeout/i', $line))
            && ($needle === '' || str_contains(mb_strtolower($line), $needle))));
        $visible = array_slice($rows, -max(1, min($lines, 1000)));

        return [...$result, 'content' => implode("\n", $visible), 'lines' => count($visible),
            'source' => $source, 'checked_at' => now()->toIso8601String()];
    }

    private function snapshot(string $source): array
    {
        $directory = config('server_logs.directory', '/var/lib/exadcam-logs');
        $raw = $this->boundedFile($directory.'/'.$source.'.json', $directory, 1048576);
        if ($raw === null) {
            return $this->unavailable();
        }
        try {
            $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($data) || ! is_bool($data['available'] ?? null) || ! is_string($data['content'] ?? null)
                || ! is_string($data['captured_at'] ?? null) || strlen($data['content']) > self::MAX_BYTES) {
                return $this->unavailable();
            }
            $captured = Carbon::parse($data['captured_at']);

            return ['available' => $data['available'], 'content' => $data['content'],
                'updated_at' => $captured->toIso8601String(), 'stale' => $captured->lt(now()->subSeconds(45)),
                'truncated' => (bool) ($data['truncated'] ?? false)];
        } catch (Throwable) {
            return $this->unavailable();
        }
    }

    private function laravel(): array
    {
        $directory = config('server_logs.laravel_directory', storage_path('logs'));
        $paths = array_filter(glob($directory.'/laravel*.log') ?: [], fn ($p) => preg_match('/^laravel(?:-\d{4}-\d{2}-\d{2})?\.log$/', basename($p))
            && ! is_link($p) && is_file($p) && is_readable($p));
        usort($paths, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        if (! $paths) {
            return ['available' => true, 'content' => '', 'updated_at' => null, 'stale' => false, 'truncated' => false];
        }
        $path = $paths[0];
        $content = $this->boundedFile($path, $directory, self::MAX_BYTES, true);
        if ($content === null) {
            return $this->unavailable();
        }
        $content = preg_replace_callback('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/m',
            fn ($match) => '['.Carbon::parse($match[1], config('app.timezone', 'UTC'))->timezone('Africa/Kinshasa')->toIso8601String().']', $content);
        $all = preg_split('/\r\n|\n|\r/', rtrim($content));

        return ['available' => true, 'content' => implode("\n", array_slice($all, -1000)),
            'updated_at' => Carbon::createFromTimestamp(filemtime($path))->toIso8601String(), 'stale' => false,
            'truncated' => filesize($path) > self::MAX_BYTES || count($all) > 1000];
    }

    private function boundedFile(string $path, string $directory, int $limit, bool $tail = false): ?string
    {
        $base = realpath($directory);
        $real = realpath($path);
        if (! $base || ! $real || is_link($path) || ! str_starts_with($real, $base.DIRECTORY_SEPARATOR) || ! is_file($real) || ! is_readable($real)) {
            return null;
        }
        $handle = @fopen($real, 'rb');
        if ($handle === false) {
            return null;
        }
        try {
            $size = fstat($handle)['size'];
            if (! $tail && $size > $limit) {
                return null;
            }
            $offset = $tail ? max(0, $size - $limit) : 0;
            fseek($handle, $offset);
            $content = stream_get_contents($handle, $limit) ?: '';
            if ($offset > 0) {
                $newline = strpos($content, "\n");
                $content = $newline === false ? '' : substr($content, $newline + 1);
            }

            return mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        } finally {
            fclose($handle);
        }
    }

    private function unavailable(): array
    {
        return ['available' => false, 'content' => '', 'updated_at' => null, 'stale' => true, 'truncated' => false];
    }

    public function redact(string $content): string
    {
        $content = preg_replace('/\x1b\[[0-?]*[ -\/]*[@-~]|[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '', $content);
        $content = preg_replace('/-----BEGIN [^-]*PRIVATE KEY-----.*?-----END [^-]*PRIVATE KEY-----/s', '[REDACTED PRIVATE KEY]', $content);
        $content = preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=:-]+/i', '$1 [REDACTED]', $content);
        $content = preg_replace('~(://)[^\s/:@]+:[^\s/@]+@~', '$1[REDACTED]@', $content);
        $content = preg_replace('/(["\']?[\w.-]*(?:password|passwd|secret|token|api[_-]?key|authorization|cookie)["\']?\s*[:=]\s*)("(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|[^\s,}\]]+)/i', '$1[REDACTED]', $content);

        return $content;
    }
}

<?php

namespace App\Services;

class ServerMetricsReader
{
    public function read(): array
    {
        $unavailable = ['available' => false, 'stale' => true, 'captured_at' => null, 'history' => []];
        $file = config('server_monitoring.snapshot');
        if (! is_string($file) || is_link($file) || ! is_file($file)) {
            return $unavailable;
        }

        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return $unavailable;
        }
        try {
            $content = stream_get_contents($handle, 524289);
        } finally {
            fclose($handle);
        }
        if (! is_string($content) || strlen($content) > 524288) {
            return $unavailable;
        }
        $data = json_decode($content, true, 32);
        if (! is_array($data) || ($data['schema'] ?? null) !== 1 || ! is_numeric($data['captured_at'] ?? null)) {
            return $unavailable;
        }
        foreach (['cpu', 'memory', 'disk', 'load', 'network', 'system', 'history'] as $key) {
            if (! is_array($data[$key] ?? null)) {
                return $unavailable;
            }
        }
        // Only the metric contract is returned; never expose extra collector fields.
        $result = ['available' => true, 'captured_at' => (int) $data['captured_at'],
            'stale' => abs(microtime(true) * 1000 - $data['captured_at']) > 25000,
            'interval' => 5];
        $fields = [
            'cpu' => ['usage', 'cores'],
            'memory' => ['total', 'used', 'available', 'percent', 'swap_total', 'swap_used', 'swap_percent'],
            'disk' => ['total', 'used', 'free', 'available', 'percent'],
            'load' => ['one', 'five', 'fifteen'],
            'system' => ['hostname', 'os', 'uptime'],
        ];
        foreach ($fields as $key => $names) {
            $result[$key] = array_intersect_key($data[$key], array_flip($names));
        }
        $result['system'] += ['php' => PHP_VERSION, 'laravel' => app()->version(), 'environment' => app()->environment()];
        $result['network'] = array_intersect_key($data['network'], array_flip(['rx_rate', 'tx_rate']));
        $result['network']['interfaces'] = array_values(array_map(
            fn (array $row) => array_intersect_key($row, array_flip(['name', 'rx', 'tx', 'rx_rate', 'tx_rate'])),
            array_slice(array_filter((array) ($data['network']['interfaces'] ?? []), 'is_array'), 0, 64)
        ));
        $result['history'] = array_values(array_map(
            fn (array $row) => array_intersect_key($row, array_flip(['time', 'cpu', 'memory', 'rx', 'tx', 'load'])),
            array_slice(array_filter($data['history'], 'is_array'), -180)
        ));

        return $result;
    }
}

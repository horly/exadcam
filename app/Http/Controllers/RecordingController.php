<?php

namespace App\Http\Controllers;

use App\Models\Dashcam;
use App\Models\User;
use App\Support\FleetAccess;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RecordingController extends Controller
{
    private function authorizeCamera(Request $request, Dashcam $dashcam): void
    {
        abort_unless(FleetAccess::allows($request->user(), User::PERMISSION_VIDEO_VIEW), 403);
        abort_unless(FleetAccess::camera($request->user(), $dashcam, User::PERMISSION_VIDEO_VIEW), 404);
        abort_unless($dashcam->enabled, 403);
        $dashcam->loadMissing('vehicle.fleet');
    }

    private function scope(Dashcam $camera): string
    {
        return hash('sha256', json_encode([$camera->vehicle_id, $camera->vehicle?->fleet_id,
            $camera->vehicle_assigned_at?->toIso8601String(), $camera->vehicle?->fleet_assigned_at?->toIso8601String()]));
    }

    private function key(Request $request, Dashcam $camera, string $kind, string $id): string
    {
        return 'recordings:'.$request->user()->id.':'.$camera->id.':'.$kind.':'.$id;
    }

    private function node(string $service, string $route, array $data): array
    {
        $token = (string) config('listener.token');
        abort_if(strlen($token) < 32, 503, __('recordings.unavailable'));
        try {
            $response = Http::withToken($token)->acceptJson()->connectTimeout(3)->timeout(40)
                ->post(config('listener.'.$service.'_url').$route, $data);
        } catch (ConnectionException) {
            abort(503, __('recordings.unavailable'));
        }
        abort_if($response->status() === 409, 409, __('recordings.offline_busy'));
        abort_if($response->status() === 422, 422, __('recordings.unsupported'));
        abort_if($response->status() === 404, 404, __('recordings.expired'));
        abort_unless($response->successful(), 503, __('recordings.unavailable'));

        return $response->json();
    }

    public function search(Request $request, Dashcam $dashcam): JsonResponse
    {
        $this->authorizeCamera($request, $dashcam);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'channel' => ['required', 'integer', 'between:0,'.$dashcam->channels], ...$this->tableRules()]);
        $start = Carbon::createFromFormat('!Y-m-d', $data['date'], 'Africa/Kinshasa')->utc();
        $end = $start->copy()->addDay()->subSecond()->min(now());
        $assignedAt = null;
        if (! $request->user()->isSuperadmin()) {
            abort_unless($dashcam->vehicle_assigned_at && $dashcam->vehicle?->fleet_assigned_at, 403);
            $assignedAt = $dashcam->vehicle_assigned_at->copy()->max($dashcam->vehicle->fleet_assigned_at);
            $start = $start->max($assignedAt);
        }
        $records = [];
        if ($end->gt($start)) {
            $result = $this->node('gps', '/recordings/query', ['device_id' => $dashcam->id, 'channel' => (int) $data['channel'],
                'start' => $start->toIso8601String(), 'end' => $end->toIso8601String()]);
            foreach ($result['records'] ?? [] as $record) {
                if (! in_array($record['media_type'], [0, 2], true) || $record['channel'] > $dashcam->channels
                    || ($data['channel'] && $record['channel'] !== (int) $data['channel'])) {
                    continue;
                }
                // Some firmware seeks to the preceding keyframe or file boundary.
                // Exclude whole files that straddle an assignment to avoid exposing
                // images from the previous vehicle/fleet, even during that seek.
                if ($assignedAt && Carbon::parse($record['start'])->lt($assignedAt)) {
                    continue;
                }
                $from = Carbon::parse($record['start'])->max($start);
                $to = Carbon::parse($record['end'])->min($end);
                if ($from->gte($to)) {
                    continue;
                }
                $records[] = [...$record, 'start' => $from->toIso8601String(), 'end' => $to->toIso8601String()];
            }
        }
        usort($records, fn ($a, $b) => strcmp($b['start'], $a['start']) ?: $a['channel'] <=> $b['channel']);
        $id = (string) Str::uuid();
        Cache::put($this->key($request, $dashcam, 'query', $id), ['records' => $records, 'scope' => $this->scope($dashcam)], now()->addMinutes(30));

        return $this->results($request, $dashcam, $id);
    }

    private function tableRules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['sometimes', 'integer', 'in:5,10,25,50'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['sometimes', 'in:channel,start,end,duration,size'],
            'direction' => ['sometimes', 'in:asc,desc'],
        ];
    }

    public function results(Request $request, Dashcam $dashcam, string $query): JsonResponse
    {
        $this->authorizeCamera($request, $dashcam);
        $data = $request->validate($this->tableRules());
        $cached = Cache::get($this->key($request, $dashcam, 'query', $query));
        abort_unless($cached && hash_equals($cached['scope'], $this->scope($dashcam)), 404, __('recordings.expired'));
        $needle = mb_strtolower(trim($data['search'] ?? ''));
        $records = [];
        foreach ($cached['records'] as $index => $record) {
            $start = Carbon::parse($record['start'])->timezone('Africa/Kinshasa');
            $end = Carbon::parse($record['end'])->timezone('Africa/Kinshasa');
            $duration = (int) $start->diffInSeconds($end);
            if ($needle !== '') {
                $searchable = mb_strtolower(implode(' ', [__('recordings.channel').' '.$record['channel'],
                    $start->format('d/m/Y H:i:s'), $end->format('d/m/Y H:i:s'), $start->format('Y-m-d'), $end->format('Y-m-d'),
                    intdiv($duration, 60).' min '.($duration % 60).' s', number_format($record['size'] / 1048576, 1, '.', '').' Mo MB']));
                if (! str_contains($searchable, $needle)) {
                    continue;
                }
            }
            // Keep the original cache index: a sorted/filtered row must still open
            // the same recording in prepare(), never its new display position.
            $records[] = ['index' => $index, 'channel' => $record['channel'], 'start' => $record['start'],
                'end' => $record['end'], 'size' => $record['size'], 'duration' => $duration];
        }
        $sort = $data['sort'] ?? 'start';
        $direction = $data['direction'] ?? 'desc';
        usort($records, function ($a, $b) use ($sort, $direction) {
            $order = $a[$sort] <=> $b[$sort];

            return ($direction === 'asc' ? $order : -$order) ?: $a['index'] <=> $b['index'];
        });
        $total = count($records);
        $perPage = (int) ($data['per_page'] ?? 5);
        $last = max(1, (int) ceil($total / $perPage));
        $page = min((int) ($data['page'] ?? 1), $last);
        $rows = array_slice($records, ($page - 1) * $perPage, $perPage);

        return response()->json(['query_id' => $query, 'total' => $total, 'page' => $page, 'last_page' => $last,
            'per_page' => $perPage, 'from' => $total ? ($page - 1) * $perPage + 1 : 0,
            'to' => min($page * $perPage, $total), 'unfiltered_total' => count($cached['records']),
            'sort' => $sort, 'direction' => $direction, 'records' => $rows]);
    }

    public function prepare(Request $request, Dashcam $dashcam): JsonResponse
    {
        $this->authorizeCamera($request, $dashcam);
        $data = $request->validate(['query_id' => ['required', 'uuid'], 'index' => ['required', 'integer', 'min:0'],
            'start' => ['nullable', 'date'], 'end' => ['nullable', 'date']]);
        $query = Cache::get($this->key($request, $dashcam, 'query', $data['query_id']));
        abort_unless($query && hash_equals($query['scope'], $this->scope($dashcam)), 404, __('recordings.expired'));
        $record = $query['records'][$data['index']] ?? null;
        abort_unless($record, 404);
        $start = Carbon::parse($data['start'] ?? $record['start']);
        $end = Carbon::parse($data['end'] ?? $record['end']);
        abort_unless($start->gte(Carbon::parse($record['start'])) && $end->lte(Carbon::parse($record['end']))
            && $end->gt($start) && $start->diffInSeconds($end) <= 1800, 422, __('recordings.duration_limit'));
        $record['start'] = $start->toIso8601String();
        $record['end'] = $end->toIso8601String();
        $result = $this->node('recording', '/jobs', ['device_id' => $dashcam->id, 'record' => $record]);
        abort_unless(Str::isUuid($result['job_id'] ?? ''), 503);
        $expires = now()->addHours(6);
        Cache::put($this->key($request, $dashcam, 'job', $result['job_id']), ['scope' => $query['scope'], 'record' => $record,
            'expires' => $expires->toIso8601String(), 'ready' => false], $expires);

        return response()->json($result, 201);
    }

    private function job(Request $request, Dashcam $camera, string $job): array
    {
        $this->authorizeCamera($request, $camera);
        $data = Cache::get($this->key($request, $camera, 'job', $job));
        abort_unless($data && hash_equals($data['scope'], $this->scope($camera)), 404, __('recordings.expired'));

        return $data;
    }

    public function status(Request $request, Dashcam $dashcam, string $job): JsonResponse
    {
        $cached = $this->job($request, $dashcam, $job);
        $result = $this->node('recording', '/jobs/'.$job, ['device_id' => $dashcam->id]);
        if (($result['status'] ?? '') === 'ready') {
            $cached['ready'] = true;
            Cache::put($this->key($request, $dashcam, 'job', $job), $cached, Carbon::parse($cached['expires']));
        }

        return response()->json($result);
    }

    public function cancel(Request $request, Dashcam $dashcam, string $job): JsonResponse
    {
        $this->job($request, $dashcam, $job);

        return response()->json($this->node('recording', '/jobs/'.$job.'/cancel', ['device_id' => $dashcam->id]));
    }

    public function media(Request $request, Dashcam $dashcam, string $job): BinaryFileResponse
    {
        $cached = $this->job($request, $dashcam, $job);
        abort_unless($cached['ready'], 409, __('recordings.preparing'));
        $file = rtrim(config('listener.recording_storage'), '/\\').DIRECTORY_SEPARATOR.$job.DIRECTORY_SEPARATOR.'recording.mp4';
        abort_unless(is_file($file) && ! is_link($file), 404, __('recordings.expired'));
        $name = 'EXADCAM-CH'.$cached['record']['channel'].'-'.Carbon::parse($cached['record']['start'])->timezone('Africa/Kinshasa')->format('Ymd-His').'.mp4';
        $response = $request->boolean('download') ? response()->download($file, $name) : response()->file($file);

        $response->headers->add(['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);

        return $response;
    }
}

<?php

namespace App\Support;

use App\Models\Dashcam;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DashcamProfile
{
    public const MODELS = ['ES500-603', 'JK114'];

    public static function validate(array $input, ?Dashcam $dashcam = null): array
    {
        $data = validator($input, [
            'model' => ['required', Rule::in(self::MODELS)],
            'name' => ['required', 'string', 'max:100'],
            'imei' => ['required', 'string', 'regex:/^[0-9]{15}$/', Rule::unique('dashcams')->ignore($dashcam)],
            'transport' => ['required', Rule::in(['TCP'])],
            'protocol_version' => ['required', Rule::in(($input['model'] ?? '') === 'ES500-603' ? ['2013'] : ['2013', '2019'])],
            'communication_id' => [Rule::requiredIf(($input['model'] ?? '') === 'ES500-603'), 'nullable', 'string', 'regex:/^[0-9]{12}$/'],
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')],
            'channels' => ['required', 'integer', 'between:1,8'],
            'frame_rate' => ['required', 'integer', 'between:1,30'],
        ], [], trans('dashcams.attributes'))->validate();

        if (! $dashcam) {
            // Persist the installation default so new cameras do not inherit Node's UTC+8 fallback.
            // Existing per-device calibrations remain unchanged during edits.
            $data['gps_timezone_minutes'] = config('listener.default_gps_timezone_minutes');
        }

        $identityChanged = ! $dashcam || $dashcam->model !== $data['model'] || $dashcam->imei !== $data['imei']
            || $dashcam->protocol_version !== $data['protocol_version'];
        if ($data['model'] === 'ES500-603') {
            $data['terminal_id_2013'] = $data['communication_id'];
            $data['video_terminal_id'] = $data['communication_id'];
            $data['normalize_video_timestamps'] = true;
        } elseif ($identityChanged) {
            $data['terminal_id_2013'] = substr($data['imei'], -12);
            $data['video_terminal_id'] = $data['protocol_version'] === '2019'
                ? str_pad($data['imei'], 20, '0', STR_PAD_LEFT) : substr($data['imei'], -12);
            $data['normalize_video_timestamps'] = false;
        }
        if ($identityChanged) {
            $data['terminal_id_2019'] = str_pad($data['imei'], 20, '0', STR_PAD_LEFT);
        }
        foreach (['terminal_id_2013', 'terminal_id_2019', 'video_terminal_id'] as $field) {
            if (isset($data[$field]) && Dashcam::where($field, $data[$field])->when($dashcam, fn ($query) => $query->where('id', '!=', $dashcam->id))->exists()) {
                throw ValidationException::withMessages([
                    $data['model'] === 'ES500-603' && $field !== 'terminal_id_2019' ? 'communication_id' : 'imei' => __('dashcams.alias_conflict'),
                ]);
            }
        }
        unset($data['communication_id']);

        return $data;
    }
}

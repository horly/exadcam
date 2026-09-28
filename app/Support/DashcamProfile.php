<?php

namespace App\Support;

use App\Models\Dashcam;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DashcamProfile
{
    public const SMARTVISION = '4G SmartVision JT808/1078';
    public const ESTON = 'ESTON ES500-603 JK114';
    public const MODELS = [self::SMARTVISION, self::ESTON];

    public static function canonicalModel(?string $model): ?string
    {
        return match ($model) {
            'ES500-603' => self::SMARTVISION,
            'JK114' => self::ESTON,
            default => $model,
        };
    }

    // Internal listener profile keys are stable: existing sessions must keep
    // their GPS timeout, video framing and microphone gain after a label edit.
    public static function listenerModel(?string $model): ?string
    {
        return match (self::canonicalModel($model)) {
            self::SMARTVISION => 'ES500-603',
            self::ESTON => 'JK114',
            default => $model,
        };
    }

    public static function validate(array $input, ?Dashcam $dashcam = null): array
    {
        // Accept already-open forms using the previous model labels.
        if (isset($input['model']) && is_string($input['model'])) {
            $input['model'] = self::canonicalModel($input['model']);
        }
        $data = validator($input, [
            'model' => ['required', Rule::in(self::MODELS)],
            'name' => ['required', 'string', 'max:100'],
            'imei' => ['required', 'string', 'regex:/^[0-9]{15}$/', Rule::unique('dashcams')->ignore($dashcam)],
            'transport' => ['required', Rule::in(['TCP'])],
            'protocol_version' => ['required', Rule::in(($input['model'] ?? '') === self::SMARTVISION ? ['2013'] : ['2013', '2019'])],
            'communication_id' => [Rule::requiredIf(($input['model'] ?? '') === self::SMARTVISION), 'nullable', 'string', 'regex:/^[0-9]{12}$/'],
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')],
            'channels' => ['required', 'integer', 'between:1,8'],
            'frame_rate' => ['required', 'integer', 'between:1,30'],
        ], [], trans('dashcams.attributes'))->validate();

        if (self::canonicalModel($data['name']) === $data['model']) {
            $data['name'] = $data['model'];
        }

        if (! $dashcam) {
            // Persist the installation default so new cameras do not inherit Node's UTC+8 fallback.
            // Existing per-device calibrations remain unchanged during edits.
            $data['gps_timezone_minutes'] = config('listener.default_gps_timezone_minutes');
        }

        $identityChanged = ! $dashcam || $dashcam->model !== $data['model'] || $dashcam->imei !== $data['imei']
            || $dashcam->protocol_version !== $data['protocol_version'];
        if ($data['model'] === self::SMARTVISION) {
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
                    $data['model'] === self::SMARTVISION && $field !== 'terminal_id_2019' ? 'communication_id' : 'imei' => __('dashcams.alias_conflict'),
                ]);
            }
        }
        unset($data['communication_id']);

        return $data;
    }
}

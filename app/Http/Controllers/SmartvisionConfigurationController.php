<?php

namespace App\Http\Controllers;

use App\Models\Dashcam;
use App\Models\SmartvisionConfigurationDraft;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SmartvisionConfigurationController extends Controller
{
    public const SERVERS = ['backup' => ['ipbak', 'portbak'], 'secondary' => ['ip2', 'port2'], 'secondary_backup' => ['ipbak2', 'portbak2']];

    public const FIELDS = ['ip', 'port', 'backup_action', 'ipbak', 'portbak', 'secondary_action', 'ip2', 'port2', 'secondary_backup_action', 'ipbak2', 'portbak2', 'telnum', 'tid', 'manuf', 'module', 'provinceid', 'cityid', 'carid', 'platecolor'];

    public function show(Request $request, Dashcam $dashcam): JsonResponse
    {
        $this->authorizeCamera($request->user(), $dashcam);

        return $this->response($dashcam);
    }

    public function update(Request $request, Dashcam $dashcam): JsonResponse
    {
        $this->authorizeCamera($request->user(), $dashcam);
        if (array_diff(array_keys($request->except('_token')), ['revision', 'context', 'settings'])) {
            throw ValidationException::withMessages(['settings' => __('smartvision.invalid_fields')]);
        }
        $envelope = $request->validate([
            'revision' => ['required', 'integer', 'min:0', 'max:2147483646'],
            'context' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'settings' => ['required', 'array:'.implode(',', self::FIELDS)],
        ]);
        $settings = $this->validateSettings($envelope['settings']);

        return DB::transaction(function () use ($request, $dashcam, $envelope, $settings) {
            $actor = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            // Lock the parent as well: first saves have no draft row to lock yet.
            $camera = Dashcam::query()->lockForUpdate()->findOrFail($dashcam->id);
            $this->authorizeCamera($actor, $camera);
            $context = $this->context($camera);
            $draft = SmartvisionConfigurationDraft::where('dashcam_id', $camera->id)->lockForUpdate()->first();
            if (! hash_equals($context, $envelope['context']) || ($draft?->revision ?? 0) !== (int) $envelope['revision']) {
                return response()->json(['message' => __('smartvision.conflict')], 409);
            }
            $draft ??= new SmartvisionConfigurationDraft(['dashcam_id' => $camera->id]);
            $draft->fill(['settings' => $settings, 'context_hash' => $context, 'updated_by' => $actor->id, 'revision' => ($draft->revision ?? 0) + 1])->save();

            // Deliberately no listener call, job, or "sent/applied" status.
            return $this->response($camera, __('smartvision.saved'));
        });
    }

    private function authorizeCamera(User $actor, Dashcam $camera): void
    {
        abort_unless($actor->isActive() && $actor->isSuperadmin(), 403);
        abort_unless($camera->isSmartVision(), 404);
    }

    private function context(Dashcam $camera): string
    {
        // GPS presence updates must not invalidate an editor; identity/reassignment must.
        return hash('sha256', json_encode([$camera->id, $camera->model, $camera->imei,
            $camera->terminal_id_2013, $camera->terminal_id_2019, $camera->vehicle_id,
            $camera->vehicle_assigned_at?->toIso8601String()]));
    }

    private function response(Dashcam $camera, ?string $message = null): JsonResponse
    {
        $draft = SmartvisionConfigurationDraft::where('dashcam_id', $camera->id)->first();
        $camera->loadMissing('vehicle');

        return response()->json([
            'camera' => ['id' => $camera->id, 'name' => $camera->name, 'model' => $camera->model, 'imei' => $camera->imei,
                'vehicle' => $camera->vehicle?->name, 'registration' => $camera->vehicle?->registration_number],
            'revision' => $draft?->revision ?? 0, 'context' => $this->context($camera),
            'state' => $draft ? 'pending' : 'empty', 'can_send' => false,
            'settings' => $draft?->settings,
            'stale' => $draft && ! hash_equals($draft->context_hash, $this->context($camera)),
            'updated_at' => $draft?->updated_at?->toIso8601String(),
            'updated_by' => $draft ? User::find($draft->updated_by)?->name : null,
            'defaults' => ['ip' => config('smartvision.server_host'), 'port' => config('smartvision.server_port')],
            'message' => $message,
        ]);
    }

    private function validateSettings(array $settings): array
    {
        $host = function ($attribute, $value, $fail) {
            if (! is_string($value) || strlen($value) > 253) {
                $fail(__('smartvision.invalid_host'));

                return;
            }
            $ipv4 = filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
            $domain = filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) && preg_match('/[a-z]/i', $value);
            if (! $ipv4 && ! $domain) {
                $fail(__('smartvision.invalid_host'));
            }
        };
        $rules = [
            'ip' => ['required', 'string', $host], 'port' => ['required', 'integer', 'between:1,65535'],
            'telnum' => ['nullable', 'string', 'regex:/^[0-9]{12}$/D'],
            'tid' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9]{1,7}$/D'],
            'manuf' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9]{1,5}$/D'],
            'module' => ['nullable', 'string', 'max:20', 'regex:/^[\x20-\x7E]+$/D'],
            'provinceid' => ['nullable', 'integer', 'between:0,65535'],
            'cityid' => ['nullable', 'integer', 'between:0,65535'],
            'carid' => ['nullable', 'string', 'max:32', 'regex:/^[^\x00-\x1F\x7F]+$/uD'],
            'platecolor' => ['nullable', 'integer', 'between:0,255'],
        ];
        foreach (self::SERVERS as $key => [$ip, $port]) {
            $rules[$key.'_action'] = ['required', Rule::in(['keep', 'set', 'remove'])];
            $set = ($settings[$key.'_action'] ?? null) === 'set';
            $rules[$ip] = $set ? ['required', 'string', $host] : ['prohibited'];
            $rules[$port] = $set ? ['required', 'integer', 'between:1,65535'] : ['prohibited'];
        }
        $attributes = array_combine(self::FIELDS, array_map(fn ($key) => __('smartvision.fields.'.$key), self::FIELDS));
        $validator = Validator::make($settings, $rules, [], $attributes);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($value, $key) => ['settings.'.$key => $value])->all());
        }
        $result = array_replace(array_fill_keys(self::FIELDS, null), $validator->validated());
        foreach (self::SERVERS as $key => [$ip, $port]) {
            if ($result[$key.'_action'] !== 'set') {
                $result[$ip] = $result[$port] = null;
            }
        }
        foreach (['port', 'portbak', 'port2', 'portbak2', 'provinceid', 'cityid', 'platecolor'] as $key) {
            $result[$key] = isset($result[$key]) ? (int) $result[$key] : null;
        }

        return $result;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ApplicationSetting;
use App\Models\Fleet;
use App\Models\User;
use App\Services\BrandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomizationController extends Controller
{
    public function updateGlobal(Request $request, BrandingService $branding): JsonResponse
    {
        abort_unless($request->user()?->isActive() && $request->user()->isSuperadmin(), 403);
        $rules = [
            'app_name' => ['required', 'string', 'max:80'], 'short_name' => ['required', 'string', 'max:24'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'support_email' => ['nullable', 'email:rfc', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:40', 'regex:/^[+0-9().\s-]+$/D'],
            'map_type' => ['required', Rule::in(['roadmap', 'hybrid', 'satellite', 'terrain'])],
        ];
        foreach (BrandingService::COLORS as $key => $default) {
            $rules[$key] = ['required', 'string', 'regex:/^#[0-9a-f]{6}$/iD'];
        }
        foreach (['logo', 'internal_logo', 'favicon'] as $key) {
            $rules[$key] = $this->imageRules($key === 'favicon');
            $rules['remove_'.$key] = ['sometimes', 'boolean'];
        }
        $data = $request->validate($rules);
        $newPaths = [];
        try {
            foreach (['logo', 'internal_logo', 'favicon'] as $key) {
                if ($request->hasFile($key)) {
                    $newPaths[$key.'_path'] = $this->storeImage($request->file($key), 'branding/global');
                }
            }
            $oldPaths = DB::transaction(function () use ($request, $data, $newPaths) {
                $actor = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                abort_unless($actor->isActive() && $actor->isSuperadmin(), 403);
                $record = ApplicationSetting::query()->lockForUpdate()->findOrFail(1);
                $values = array_replace(BrandingService::DEFAULTS, $record->values ?? []);
                $oldPaths = [];
                foreach (array_keys(BrandingService::DEFAULTS) as $key) {
                    if (! str_ends_with($key, '_path') && array_key_exists($key, $data)) {
                        $values[$key] = is_string($data[$key]) ? trim($data[$key]) : $data[$key];
                    }
                }
                foreach (['logo', 'internal_logo', 'favicon'] as $key) {
                    if (isset($newPaths[$key.'_path']) || $request->boolean('remove_'.$key)) {
                        $oldPaths[] = $values[$key.'_path'];
                        $values[$key.'_path'] = $newPaths[$key.'_path'] ?? null;
                    }
                }
                $record->values = $values;
                $record->save();

                return $oldPaths;
            });
        } catch (\Throwable $error) {
            $this->deleteImages($newPaths, 'branding/global', $branding);
            throw $error;
        }
        $this->deleteImages($oldPaths, 'branding/global', $branding);

        return $this->saved($request);
    }

    public function updateFleet(Request $request, BrandingService $branding): JsonResponse
    {
        abort_unless($request->user()?->isAdmin() && $branding->canManage($request->user()), 403);
        // Clients never choose a fleet id or any global setting in this endpoint.
        if (array_diff(array_keys($request->except('_token')), ['fleet_name', 'logo', 'remove_logo'])) {
            throw ValidationException::withMessages(['logo' => __('customization.fleet_only')]);
        }
        $data = $request->validate(['fleet_name' => ['sometimes', 'required', 'string', 'max:255'], 'logo' => $this->imageRules(), 'remove_logo' => ['sometimes', 'boolean']]);
        if (! array_key_exists('fleet_name', $data) && ! $request->hasFile('logo') && ! $request->boolean('remove_logo')) {
            throw ValidationException::withMessages(['logo' => __('customization.choose_logo')]);
        }
        $fleetId = $request->user()->fleet_id;
        $directory = 'branding/fleets/'.$fleetId;
        $changeLogo = $request->hasFile('logo') || $request->boolean('remove_logo');
        $newPath = $request->hasFile('logo') ? $this->storeImage($request->file('logo'), $directory) : null;
        try {
            $oldPath = DB::transaction(function () use ($request, $fleetId, $newPath, $changeLogo, $data) {
                $actor = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                abort_unless($actor->isActive() && $actor->isAdmin() && $actor->fleet_id === $fleetId, 403);
                $fleet = Fleet::query()->lockForUpdate()->findOrFail($fleetId);
                abort_unless($fleet->status === 'active', 403);
                $old = $changeLogo ? $fleet->logo_path : null;
                if ($changeLogo) {
                    $fleet->logo_path = $newPath;
                }
                if (array_key_exists('fleet_name', $data)) {
                    $fleet->name = $data['fleet_name'];
                }
                $fleet->save();

                return $old;
            });
        } catch (\Throwable $error) {
            $this->deleteImages([$newPath], $directory, $branding);
            throw $error;
        }
        $this->deleteImages([$oldPath], $directory, $branding);

        return $this->saved($request);
    }

    public function globalImage(string $kind, string $version, BrandingService $branding): BinaryFileResponse
    {
        abort_unless(in_array($kind, ['logo', 'internal_logo', 'favicon'], true), 404);

        return $this->image($branding->settings()[$kind.'_path'], 'branding/global', $version, $branding);
    }

    public function fleetImage(Request $request, string $version, BrandingService $branding): BinaryFileResponse
    {
        $user = $request->user();
        $fleet = Fleet::find($user?->fleet_id);
        abort_unless($user?->isActive() && ! $user->isSuperadmin() && $fleet?->status === 'active', 403);

        return $this->image($fleet->logo_path, 'branding/fleets/'.$user->fleet_id, $version, $branding);
    }

    private function image(?string $path, string $directory, string $version, BrandingService $branding): BinaryFileResponse
    {
        abort_unless($branding->validPath($path, $directory) && hash_equals($branding->version($path), $version), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private', 'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    private function imageRules(bool $favicon = false): array
    {
        return ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:'.($favicon ? '1024' : '2048'),
            $favicon ? 'dimensions:min_width=16,min_height=16,max_width=512,max_height=512' : 'dimensions:min_width=30,min_height=20,max_width=2400,max_height=1200'];
    }

    private function storeImage(UploadedFile $file, string $directory): string
    {
        $image = @imagecreatefromstring($file->get());
        if ($image === false) {
            throw ValidationException::withMessages(['logo' => __('customization.invalid_image')]);
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        try {
            if (! imagepng($image)) {
                throw new \RuntimeException('Cannot encode branding image');
            }
            $content = ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($image);
        }
        $path = $directory.'/'.Str::uuid().'.png';
        if (! Storage::disk('local')->put($path, $content)) {
            throw new \RuntimeException('Cannot store branding image');
        }

        return $path;
    }

    private function deleteImages(array $paths, string $directory, BrandingService $branding): void
    {
        foreach ($paths as $path) {
            if ($branding->validPath($path, $directory)) {
                Storage::disk('local')->delete($path);
            }
        }
    }

    private function saved(Request $request): JsonResponse
    {
        $request->session()->flash('customization_status', __('customization.saved'));

        return response()->json(['message' => __('customization.saved')]);
    }
}

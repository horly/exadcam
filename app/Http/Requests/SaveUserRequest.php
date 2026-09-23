<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $this->route('user')
            ? Gate::authorize('update', $this->route('user'))
            : Gate::authorize('create', User::class);

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
        if ($this->user()?->isAdmin()) {
            $this->mergeIfMissing(['role' => 'user', 'fleet_id' => $this->user()->fleet_id]);
        }
    }

    public function rules(): array
    {
        $isPlatform = $this->user()->isSuperadmin();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'password' => [$this->route('user') ? 'nullable' : 'required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols(),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && strlen($value) > 72) {
                        $fail(__('users.password_max'));
                    }
                },
            ],
            'password_confirmation' => ['nullable', 'string', 'required_with:password'],
            'role' => ['required', Rule::in($isPlatform ? ['user', 'admin'] : ['user'])],
            'fleet_id' => ['required', 'integer', Rule::exists('fleets', 'id')->where('status', 'active'),
                Rule::when(! $isPlatform, [Rule::in([$this->user()->fleet_id])]),
            ],
            'permissions' => ['nullable', 'array', 'max:'.count(User::CLIENT_PERMISSIONS)],
            'permissions.*' => ['string', 'distinct', Rule::in(User::CLIENT_PERMISSIONS)],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return collect(['name', 'email', 'password', 'password_confirmation', 'role', 'fleet_id', 'permissions', 'phone', 'address'])
            ->mapWithKeys(fn (string $key): array => [$key => __('users.'.($key === 'fleet_id' ? 'fleet' : $key))])->all();
    }
}

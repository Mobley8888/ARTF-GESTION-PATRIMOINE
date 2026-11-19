<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user instanceof User ? $user->getKey() : $user),
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $actor = $this->user();
                $target = $this->route('user');

                if ($actor?->role !== UserRole::Administrator) {
                    return;
                }

                if (
                    $target instanceof User
                    && $actor->is($target)
                    && $this->input('role') !== $target->role->value
                ) {
                    $validator->errors()->add('role', 'Vous ne pouvez pas modifier vos propres habilitations.');
                }

                if (($target instanceof User && $target->isSuperAdmin()) || $this->input('role') === UserRole::SuperAdmin->value) {
                    $validator->errors()->add('role', 'Seul un super administrateur peut gérer ce compte ou attribuer ce rôle.');
                }
            },
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssetAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-assignments') ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'agent_id' => [
                'nullable',
                'integer',
                Rule::exists('agents', 'id')->where('is_active', true),
            ],
            'service_id' => [
                'nullable',
                'integer',
                Rule::exists('services', 'id')->where('is_active', true),
            ],
            'assigned_at' => ['required', 'date', 'before_or_equal:now'],
            'reason' => ['required', 'string', 'max:120'],
            'observations' => ['nullable', 'string', 'max:5000'],
        ];

        if ($this->routeIs('assignments.store')) {
            $rules['asset_id'] = ['required', 'integer', 'exists:assets,id'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('agent_id') === $this->filled('service_id')) {
                    $validator->errors()->add('agent_id', 'Sélectionnez un seul agent ou un seul service.');
                }
            },
        ];
    }
}

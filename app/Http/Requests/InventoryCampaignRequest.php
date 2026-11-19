<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InventoryCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventories') ?? false;
    }

    public function rules(): array
    {
        $scopesLocked = $this->route('campaign')?->tasks()->exists() ?? false;
        $directionExists = Rule::exists('directions', 'id');
        $serviceExists = Rule::exists('services', 'id');
        $locationExists = Rule::exists('locations', 'id');
        if (! $scopesLocked) {
            $directionExists->where('is_active', true);
            $serviceExists->where('is_active', true);
            $locationExists->where('is_active', true);
        }

        return [
            'reference' => ['required', 'string', 'max:60', Rule::unique('inventory_campaigns', 'reference')->ignore($this->route('campaign')?->getKey())],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'global_scope' => ['nullable', 'boolean'],
            'direction_ids' => ['nullable', 'array', 'max:100'],
            'direction_ids.*' => ['integer', 'distinct', $directionExists],
            'service_ids' => ['nullable', 'array', 'max:200'],
            'service_ids.*' => ['integer', 'distinct', $serviceExists],
            'location_ids' => ['nullable', 'array', 'max:300'],
            'location_ids.*' => ['integer', 'distinct', $locationExists],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $hasScopes = collect(['direction_ids', 'service_ids', 'location_ids'])->contains(fn (string $key) => collect($this->input($key, []))->isNotEmpty());

            if ($this->boolean('global_scope') === $hasScopes) {
                $validator->errors()->add('global_scope', 'Choisissez soit le patrimoine global, soit un ou plusieurs périmètres ciblés.');
            }
        }];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventories') ?? false;
    }

    public function rules(): array
    {
        return [
            'scope_id' => ['required', 'integer', Rule::exists('inventory_campaign_scopes', 'id')->where('campaign_id', $this->route('campaign')->getKey())],
            'agent_id' => ['required', 'integer', Rule::exists('agents', 'id')->where('is_active', true)],
            'due_on' => ['nullable', 'date'],
        ];
    }
}

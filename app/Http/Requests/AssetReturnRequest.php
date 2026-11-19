<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssetReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-assignments') ?? false;
    }

    public function rules(): array
    {
        return [
            'returned_at' => ['required', 'date', 'before_or_equal:now'],
            'reason' => ['required', 'string', 'max:120'],
            'received_by_name' => ['nullable', 'string', 'max:160'],
            'return_condition' => ['nullable', 'in:new,good,fair,poor,unserviceable'],
            'return_observations' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

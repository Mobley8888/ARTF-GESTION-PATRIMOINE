<?php

namespace App\Http\Requests;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssetLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-assignments') ?? false;
    }

    public function rules(): array
    {
        return [
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'moved_at' => ['required', 'date', 'before_or_equal:now'],
            'reason' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('location_id') || ! $this->filled('location_id')) {
                return;
            }

            $location = Location::query()->with('parent.parent')->find($this->input('location_id'));
            $valid = match (true) {
                $location?->type?->isSite() => $location->parent_id === null,
                $location?->type?->isBuilding() => $location->parent?->is_active && $location->parent?->type?->isSite() && $location->parent->parent_id === null,
                $location?->type?->isLocal() => $location->parent?->type?->isSite()
                    ? $location->parent->is_active && $location->parent->parent_id === null
                    : $location->parent?->is_active && $location->parent?->type?->isBuilding() && $location->parent->parent?->is_active && $location->parent->parent?->type?->isSite(),
                default => false,
            };

            if (! $location?->is_active || ! $valid) {
                $validator->errors()->add('location_id', 'Choisissez un emplacement actif respectant la hiérarchie site, bâtiment et local.');
            }
        }];
    }
}

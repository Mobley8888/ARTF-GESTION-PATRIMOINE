<?php

namespace App\Http\Requests;

use App\Enums\LocationType;
use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-assets') ?? false;
    }

    public function rules(): array
    {
        $location = $this->route('location');
        $locationId = $location instanceof Location ? $location->getKey() : $location;
        $type = LocationType::tryFrom((string) $this->input('type'));
        $parentTypes = array_map(
            fn (LocationType $parentType): string => $parentType->value,
            $type?->parentTypes() ?? [],
        );
        $parentRules = $parentTypes === []
            ? ['nullable', 'prohibited']
            : [
                'required',
                'integer',
                Rule::exists('locations', 'id')
                    ->whereIn('type', $parentTypes)
                    ->where('is_active', true),
            ];

        return [
            'type' => ['required', Rule::enum(LocationType::class)],
            'parent_id' => $parentRules,
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('locations', 'code')->ignore($locationId),
            ],
            'name' => ['required', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'surface_m2' => ['nullable', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Le niveau du lieu est obligatoire.',
            'type.enum' => 'Le niveau doit être Site, Bâtiment ou Local.',
            'parent_id.required' => 'Sélectionnez le lieu parent correspondant au niveau choisi.',
            'parent_id.exists' => 'Le lieu parent doit être actif et compatible avec le niveau choisi.',
            'parent_id.prohibited' => 'Un site ne peut pas avoir de lieu parent.',
            'code.required' => 'Le code est obligatoire.',
            'code.unique' => 'Ce code de lieu est déjà utilisé.',
            'name.required' => 'La désignation est obligatoire.',
            'surface_m2.regex' => 'La superficie doit être positive et comporter au plus deux décimales.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\LocationType;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use stdClass;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $categoryParentId = $this->input('category_parent_id');

        if (filled($categoryParentId)) {
            $this->merge([
                'category_id' => filled($this->input('subcategory_id'))
                    ? $this->input('subcategory_id')
                    : $categoryParentId,
            ]);
        } elseif (filled($this->input('subcategory_id'))) {
            $this->merge(['category_id' => $this->input('subcategory_id')]);
        }

        $characteristics = $this->input('characteristics');

        if (is_string($characteristics)) {
            if (trim($characteristics) === '') {
                $this->merge(['characteristics' => null]);
            } else {
                try {
                    $decoded = json_decode($characteristics, false, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $decoded = null;
                }

                if ($decoded instanceof stdClass) {
                    $this->merge(['characteristics' => (array) $decoded]);
                }
            }
        }

        $hasCascadeSelection = collect(['site_id', 'building_id', 'room_id'])
            ->contains(fn (string $field) => filled($this->input($field)));

        if ($hasCascadeSelection || $this->input('location_id') === null) {
            $this->merge([
                'location_id' => $this->input('room_id') ?: $this->input('building_id') ?: $this->input('site_id'),
            ]);
        }
    }

    public function rules(): array
    {
        $asset = $this->route('asset');
        $assetId = $asset instanceof Asset ? $asset->getKey() : $asset;
        $categoryParentId = $this->input('category_parent_id');
        $siteId = $this->input('site_id');
        $buildingId = $this->input('building_id');
        $roomParentId = filled($buildingId) ? $buildingId : $siteId;

        return [
            'asset_code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('assets', 'asset_code')->ignore($assetId),
            ],
            'designation' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'category_parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')
                    ->whereNull('parent_id')
                    ->where('is_active', true),
            ],
            'subcategory_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('parent_id', $categoryParentId)
                    ->where('is_active', true),
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where('is_active', true),
            ],
            'site_id' => [
                'nullable',
                'integer',
                'required_with:building_id,room_id',
                Rule::exists('locations', 'id')
                    ->where('type', 'site')
                    ->whereNull('parent_id')
                    ->where('is_active', true),
            ],
            'building_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')
                    ->whereIn('type', LocationType::buildingValues())
                    ->where('is_active', true)
                    ->where('parent_id', $siteId),
            ],
            'room_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')
                    ->whereIn('type', LocationType::localValues())
                    ->where('is_active', true)
                    ->where('parent_id', $roomParentId),
            ],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'manufacturer' => ['nullable', 'string', 'max:160'],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'manufacturer_reference' => ['nullable', 'string', 'max:120'],
            'characteristics' => ['nullable', 'array', 'max:50'],
            'acquired_on' => ['nullable', 'date'],
            'commissioned_on' => ['nullable', 'date', 'after_or_equal:acquired_on'],
            'acquisition_mode' => ['nullable', 'string', 'max:80'],
            'supplier' => ['nullable', 'string', 'max:180'],
            'invoice_reference' => ['nullable', 'string', 'max:120'],
            'delivery_reference' => ['nullable', 'string', 'max:120'],
            'acquisition_notes' => ['nullable', 'string', 'max:5000'],
            'acquisition_value' => ['nullable', 'regex:/^\d{1,16}(?:\.\d{1,2})?$/D'],
            'condition' => ['nullable', Rule::enum(AssetCondition::class)],
            'usage_status' => ['nullable', 'in:in_service,broken,under_repair,retired,other'],
            'status' => ['required', Rule::enum(AssetStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'category_parent_id.exists' => 'Sélectionnez une catégorie principale active.',
            'subcategory_id.exists' => 'La sous-catégorie doit appartenir à la catégorie principale sélectionnée.',
            'site_id.exists' => 'Sélectionnez un site actif.',
            'site_id.required_with' => 'Sélectionnez le site avant le bâtiment ou le local.',
            'building_id.exists' => 'Le bâtiment doit appartenir au site sélectionné et être actif.',
            'room_id.exists' => 'Le local doit appartenir au site sélectionné ou au bâtiment sélectionné et être actif.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $validator->errors()->has('category_id') && filled($this->input('category_id'))) {
                    $category = Category::query()->with('parent')->find($this->input('category_id'));

                    if (
                        $category
                        && $category->parent_id !== null
                        && (! $category->parent || ! $category->parent->is_active || $category->parent->parent_id !== null)
                    ) {
                        $validator->errors()->add('category_id', 'La catégorie parente doit être active et être une catégorie principale.');
                    }
                }

                $hasCascadeSelection = collect(['site_id', 'building_id', 'room_id'])
                    ->contains(fn (string $field) => filled($this->input($field)));

                if (! $hasCascadeSelection && ! $validator->errors()->has('location_id') && filled($this->input('location_id'))) {
                    $location = Location::query()
                        ->with('parent.parent')
                        ->find($this->input('location_id'));

                    $validHierarchy = match (true) {
                        $location?->type?->isSite() => $location->parent_id === null,
                        $location?->type?->isBuilding() => $location->parent?->type?->isSite()
                            && $location->parent->parent_id === null
                            && $location->parent->is_active,
                        $location?->type?->isLocal() => match (true) {
                            $location->parent?->type?->isSite() => $location->parent->parent_id === null
                                && $location->parent->is_active,
                            $location->parent?->type?->isBuilding() => $location->parent->is_active
                                && $location->parent->parent?->type?->isSite()
                                && $location->parent->parent->parent_id === null
                                && $location->parent->parent->is_active,
                            default => false,
                        },
                        default => false,
                    };

                    if ($location && (! $location->is_active || ! $validHierarchy)) {
                        $validator->errors()->add('location_id', 'La localisation doit être active et respecter la hiérarchie des sites, bâtiments et locaux.');
                    }
                }
            },
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Enums\InventoryItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'observed_asset_code' => ['nullable', 'string', 'max:60'],
            'observed_designation' => ['nullable', 'string', 'max:180'],
            'observed_serial_number' => ['nullable', 'string', 'max:120'],
            'observed_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'observed_agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'observed_service_id' => ['nullable', 'integer', 'exists:services,id'],
            'physical_condition' => ['nullable', Rule::enum(AssetCondition::class)],
            'status' => ['required', Rule::in([
                InventoryItemStatus::PresentConform->value,
                InventoryItemStatus::PresentAnomaly->value,
                InventoryItemStatus::Absent->value,
                InventoryItemStatus::Moved->value,
                InventoryItemStatus::Damaged->value,
                InventoryItemStatus::Unidentified->value,
            ])],
            'physical_anomaly' => ['nullable', 'string', 'max:5000'],
            'administrative_anomaly' => ['nullable', 'string', 'max:5000'],
            'observations' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

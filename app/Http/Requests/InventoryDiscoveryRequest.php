<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryDiscoveryRequest extends FormRequest
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
            'physical_condition' => ['nullable', 'in:new,good,fair,poor,unserviceable'],
            'status' => ['required', 'in:discovered,unidentified'],
            'physical_anomaly' => ['nullable', 'string', 'max:5000'],
            'administrative_anomaly' => ['nullable', 'string', 'max:5000'],
            'observations' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

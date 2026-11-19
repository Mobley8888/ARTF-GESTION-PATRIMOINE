<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-assets') ?? false;
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')
                    ->whereNull('parent_id')
                    ->where('is_active', true),
            ],
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('categories', 'code')->ignore($category instanceof Category ? $category->getKey() : $category),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.exists' => 'La catégorie parente doit être une catégorie principale active.',
            'code.required' => 'Le code est obligatoire.',
            'code.unique' => 'Ce code de catégorie est déjà utilisé.',
            'name.required' => 'La désignation est obligatoire.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Destination;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDestinationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'category' => ['nullable', 'string', Rule::in(Destination::CATEGORIES)],
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_popular' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Destination name is required.',
            'name.max' => 'Destination name cannot exceed 255 characters.',
            'city.required' => 'City is required.',
            'city.max' => 'City name cannot exceed 255 characters.',
            'country.required' => 'Country is required.',
            'country.max' => 'Country name cannot exceed 255 characters.',
            'category.in' => 'Category must be one of: '.implode(', ', Destination::CATEGORIES).'.',
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'The image must be a file of type: jpeg, png, jpg, gif, webp.',
            'image.max' => 'The image size cannot exceed 2MB.',
        ];
    }
}

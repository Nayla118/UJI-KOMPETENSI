<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTourPackageRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'destination_id' => 'required|exists:destinations,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'max_people' => 'required|integer|min:1',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'rating' => 'nullable|numeric|min:0|max:5',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'destination_id.required' => 'Destination is required.',
            'destination_id.exists' => 'The selected destination does not exist.',
            'title.required' => 'Package title is required.',
            'title.max' => 'Package title cannot exceed 255 characters.',
            'price.required' => 'Price is required.',
            'price.numeric' => 'Price must be a number.',
            'price.min' => 'Price cannot be less than 0.',
            'duration_days.required' => 'Duration is required.',
            'duration_days.integer' => 'Duration must be a whole number.',
            'duration_days.min' => 'Duration must be at least 1 day.',
            'max_people.required' => 'Maximum people is required.',
            'max_people.integer' => 'Maximum people must be a whole number.',
            'max_people.min' => 'Maximum people must be at least 1.',
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'The image must be a file of type: jpeg, png, jpg, gif, webp.',
            'image.max' => 'The image size cannot exceed 2MB.',
            'rating.numeric' => 'Rating must be a number.',
            'rating.min' => 'Rating cannot be less than 0.',
            'rating.max' => 'Rating cannot be greater than 5.',
        ];
    }
}

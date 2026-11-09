<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTourPackageRequest extends FormRequest
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
            'destination_id' => 'required|exists:destinations,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'max_people' => 'required|integer|min:1',
            'image' => 'nullable|image|mimetypes:image/jpeg,image/png,image/webp,image/gif|max:4096',
            'inclusions' => 'nullable',
            'exclusions' => 'nullable',
            'meeting_point' => 'nullable|string|max:255',
            'meeting_latitude' => 'nullable|numeric|between:-90,90|required_with:meeting_longitude',
            'meeting_longitude' => 'nullable|numeric|between:-180,180|required_with:meeting_latitude',
            'terms' => 'nullable|string',
            'itinerary' => 'nullable|array',
            'itinerary.*.day_number' => 'nullable|integer|min:1',
            'itinerary.*.title' => 'nullable|string|max:255',
            'itinerary.*.description' => 'nullable|string',
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|mimetypes:image/jpeg,image/png,image/webp,image/gif|max:4096',
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
            'image.mimetypes' => 'The image must be a JPEG, PNG, WEBP, or GIF file.',
            'image.max' => 'The image size cannot exceed 4MB.',
            'images.*.image' => 'Every gallery file must be an image.',
            'images.*.mimetypes' => 'Gallery files must be JPEG, PNG, WEBP, or GIF.',
            'images.*.max' => 'Each gallery image cannot exceed 4MB.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Destination;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the optional catalog filters/sort/page parameters. Invalid values
 * return Laravel's standard 422 JSON response instead of silently producing
 * an unfiltered list.
 */
class FilterDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Keep the legacy `search` alias working while `q` becomes the documented
     * parameter. The raw value is also trimmed so an unencoded `4+` duration
     * (arriving as "4 ") is still accepted.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('q') && $this->filled('search')) {
            $this->merge(['q' => $this->input('search')]);
        }

        if ($this->has('duration')) {
            $duration = trim((string) $this->input('duration'));

            if ($duration === '4') {
                $duration = '4+';
            }

            $this->merge(['duration' => $duration]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:255',
            'search' => 'nullable|string|max:255',
            'category' => ['nullable', 'string', Rule::in(Destination::CATEGORIES)],
            'location' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'min_rating' => 'nullable|numeric|between:0,5',
            'duration' => ['nullable', 'string', Rule::in(['1', '2-3', '4+'])],
            'available_date' => 'nullable|date_format:Y-m-d',
            'sort' => ['nullable', 'string', Rule::in([
                'newest',
                'price_asc',
                'price_desc',
                'rating_desc',
                'popular',
            ])],
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.in' => 'Category must be one of: '.implode(', ', Destination::CATEGORIES).'.',
            'duration.in' => 'Duration must be one of: 1, 2-3, 4+.',
            'available_date.date_format' => 'Available date must use the YYYY-MM-DD format.',
            'sort.in' => 'Sort must be one of: newest, price_asc, price_desc, rating_desc, popular.',
            'per_page.max' => 'The page size may not be greater than 50.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('min_price')
                && $this->filled('max_price')
                && (float) $this->input('max_price') < (float) $this->input('min_price')) {
                $validator->errors()->add(
                    'max_price',
                    'Max price must be greater than or equal to min price.'
                );
            }
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Destination;
use App\Models\TourPackage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportService
{
    /**
     * Required columns for destinations CSV
     */
    protected array $destinationRequiredColumns = [
        'name',
        'city',
        'country',
    ];

    /**
     * All columns for destinations CSV
     */
    protected array $destinationAllColumns = [
        'name',
        'city',
        'country',
        'description',
        'image',
        'rating',
        'is_popular',
    ];

    /**
     * Required columns for tour_packages CSV
     */
    protected array $tourPackageRequiredColumns = [
        'destination_id',
        'title',
        'price',
        'duration_days',
        'max_people',
    ];

    /**
     * All columns for tour_packages CSV
     */
    protected array $tourPackageAllColumns = [
        'destination_id',
        'title',
        'description',
        'price',
        'duration_days',
        'max_people',
        'image',
        'rating',
    ];

    /**
     * Import destinations from CSV
     */
    public function importDestinations(UploadedFile $file, bool $preview = false): array
    {
        $results = [
            'success' => true,
            'total_rows' => 0,
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'preview_data' => [],
        ];

        try {
            $data = $this->parseCsv($file);
            $results['total_rows'] = count($data);

            if (empty($data)) {
                $results['success'] = false;
                $results['errors'][] = 'CSV file is empty or invalid format.';
                return $results;
            }

            // Validate headers
            $headers = array_keys($data[0]);
            $validation = $this->validateDestinationHeaders($headers);
            if (!$validation['valid']) {
                $results['success'] = false;
                $results['errors'] = $validation['errors'];
                return $results;
            }

            // If preview mode, return first 10 rows
            if ($preview) {
                $results['preview_data'] = array_slice($data, 0, 10);
                return $results;
            }

            // Process each row
            DB::beginTransaction();
            try {
                foreach ($data as $index => $row) {
                    $rowNumber = $index + 2; // +2 because of 0-index and header row

                    // Validate row
                    $validation = $this->validateDestinationRow($row, $rowNumber);
                    if (!$validation['valid']) {
                        $results['errors'] = array_merge($results['errors'], $validation['errors']);
                        $results['skipped']++;
                        continue;
                    }

                    // Check for duplicate by name + city + country
                    $existingDestination = Destination::where('name', $row['name'])
                        ->where('city', $row['city'])
                        ->where('country', $row['country'])
                        ->first();

                    if ($existingDestination) {
                        // Update existing
                        $existingDestination->update([
                            'description' => $row['description'] ?? $existingDestination->description,
                            'rating' => $row['rating'] ?? $existingDestination->rating,
                            'is_popular' => isset($row['is_popular']) ? filter_var($row['is_popular'], FILTER_VALIDATE_BOOLEAN) : $existingDestination->is_popular,
                        ]);
                        $results['updated']++;
                    } else {
                        // Create new
                        Destination::create([
                            'name' => $row['name'],
                            'city' => $row['city'],
                            'country' => $row['country'],
                            'description' => $row['description'] ?? null,
                            'image' => $row['image'] ?? null,
                            'rating' => $row['rating'] ?? 0,
                            'is_popular' => isset($row['is_popular']) ? filter_var($row['is_popular'], FILTER_VALIDATE_BOOLEAN) : false,
                        ]);
                        $results['imported']++;
                    }
                }

                DB::commit();

                // Log the import
                Log::info('Destinations import completed', [
                    'total_rows' => $results['total_rows'],
                    'imported' => $results['imported'],
                    'updated' => $results['updated'],
                    'skipped' => $results['skipped'],
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                $results['success'] = false;
                $results['errors'][] = 'Database error: ' . $e->getMessage();
            }
        } catch (\Exception $e) {
            $results['success'] = false;
            $results['errors'][] = 'Error processing CSV: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Import tour packages from CSV
     */
    public function importTourPackages(UploadedFile $file, bool $preview = false): array
    {
        $results = [
            'success' => true,
            'total_rows' => 0,
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'preview_data' => [],
        ];

        try {
            $data = $this->parseCsv($file);
            $results['total_rows'] = count($data);

            if (empty($data)) {
                $results['success'] = false;
                $results['errors'][] = 'CSV file is empty or invalid format.';
                return $results;
            }

            // Validate headers
            $headers = array_keys($data[0]);
            $validation = $this->validateTourPackageHeaders($headers);
            if (!$validation['valid']) {
                $results['success'] = false;
                $results['errors'] = $validation['errors'];
                return $results;
            }

            // If preview mode, return first 10 rows
            if ($preview) {
                $results['preview_data'] = array_slice($data, 0, 10);
                return $results;
            }

            // Process each row
            DB::beginTransaction();
            try {
                foreach ($data as $index => $row) {
                    $rowNumber = $index + 2; // +2 because of 0-index and header row

                    // Validate row
                    $validation = $this->validateTourPackageRow($row, $rowNumber);
                    if (!$validation['valid']) {
                        $results['errors'] = array_merge($results['errors'], $validation['errors']);
                        $results['skipped']++;
                        continue;
                    }

                    // Check if destination exists
                    $destination = Destination::find($row['destination_id']);
                    if (!$destination) {
                        $results['errors'][] = "Row {$rowNumber}: Destination with ID {$row['destination_id']} not found. Skipping.";
                        $results['skipped']++;
                        continue;
                    }

                    // Check for duplicate by title + destination_id
                    $existingPackage = TourPackage::where('title', $row['title'])
                        ->where('destination_id', $row['destination_id'])
                        ->first();

                    if ($existingPackage) {
                        // Update existing
                        $existingPackage->update([
                            'description' => $row['description'] ?? $existingPackage->description,
                            'price' => $row['price'] ?? $existingPackage->price,
                            'duration_days' => $row['duration_days'] ?? $existingPackage->duration_days,
                            'max_people' => $row['max_people'] ?? $existingPackage->max_people,
                            'rating' => $row['rating'] ?? $existingPackage->rating,
                        ]);
                        $results['updated']++;
                    } else {
                        // Create new
                        TourPackage::create([
                            'destination_id' => $row['destination_id'],
                            'title' => $row['title'],
                            'description' => $row['description'] ?? null,
                            'price' => $row['price'],
                            'duration_days' => $row['duration_days'],
                            'max_people' => $row['max_people'],
                            'image' => $row['image'] ?? null,
                            'rating' => $row['rating'] ?? 4.5,
                        ]);
                        $results['imported']++;
                    }
                }

                DB::commit();

                // Log the import
                Log::info('Tour packages import completed', [
                    'total_rows' => $results['total_rows'],
                    'imported' => $results['imported'],
                    'updated' => $results['updated'],
                    'skipped' => $results['skipped'],
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                $results['success'] = false;
                $results['errors'][] = 'Database error: ' . $e->getMessage();
            }
        } catch (\Exception $e) {
            $results['success'] = false;
            $results['errors'][] = 'Error processing CSV: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Parse CSV file to array
     */
    protected function parseCsv(UploadedFile $file): array
    {
        $data = [];
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new \Exception('Unable to open CSV file');
        }

        // Get headers
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return [];
        }

        // Clean headers
        $headers = array_map('trim', $headers);

        // Get data
        while (($row = fgetcsv($handle)) !== false) {
            // Combine headers with row data
            $rowData = [];
            foreach ($headers as $index => $header) {
                $rowData[$header] = $row[$index] ?? null;
            }
            $data[] = $rowData;
        }

        fclose($handle);
        return $data;
    }

    /**
     * Validate destination CSV headers
     */
    protected function validateDestinationHeaders(array $headers): array
    {
        $errors = [];
        $headers = array_map('strtolower', $headers);

        foreach ($this->destinationRequiredColumns as $required) {
            if (!in_array($required, $headers)) {
                $errors[] = "Missing required column: {$required}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate tour package CSV headers
     */
    protected function validateTourPackageHeaders(array $headers): array
    {
        $errors = [];
        $headers = array_map('strtolower', $headers);

        foreach ($this->tourPackageRequiredColumns as $required) {
            if (!in_array($required, $headers)) {
                $errors[] = "Missing required column: {$required}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate destination row data
     */
    protected function validateDestinationRow(array $row, int $rowNumber): array
    {
        $errors = [];

        // Check required fields
        if (empty($row['name'])) {
            $errors[] = "Row {$rowNumber}: Name is required.";
        }
        if (empty($row['city'])) {
            $errors[] = "Row {$rowNumber}: City is required.";
        }
        if (empty($row['country'])) {
            $errors[] = "Row {$rowNumber}: Country is required.";
        }

        // Validate rating
        if (isset($row['rating']) && $row['rating'] !== '') {
            if (!is_numeric($row['rating']) || $row['rating'] < 0 || $row['rating'] > 5) {
                $errors[] = "Row {$rowNumber}: Rating must be a number between 0 and 5.";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate tour package row data
     */
    protected function validateTourPackageRow(array $row, int $rowNumber): array
    {
        $errors = [];

        // Check required fields
        if (empty($row['destination_id'])) {
            $errors[] = "Row {$rowNumber}: Destination ID is required.";
        } elseif (!is_numeric($row['destination_id'])) {
            $errors[] = "Row {$rowNumber}: Destination ID must be a number.";
        }

        if (empty($row['title'])) {
            $errors[] = "Row {$rowNumber}: Title is required.";
        }

        if (empty($row['price'])) {
            $errors[] = "Row {$rowNumber}: Price is required.";
        } elseif (!is_numeric($row['price']) || $row['price'] < 0) {
            $errors[] = "Row {$rowNumber}: Price must be a positive number.";
        }

        if (empty($row['duration_days'])) {
            $errors[] = "Row {$rowNumber}: Duration is required.";
        } elseif (!is_int((int)$row['duration_days']) || $row['duration_days'] < 1) {
            $errors[] = "Row {$rowNumber}: Duration must be a positive integer.";
        }

        if (empty($row['max_people'])) {
            $errors[] = "Row {$rowNumber}: Max people is required.";
        } elseif (!is_int((int)$row['max_people']) || $row['max_people'] < 1) {
            $errors[] = "Row {$rowNumber}: Max people must be a positive integer.";
        }

        // Validate rating
        if (isset($row['rating']) && $row['rating'] !== '') {
            if (!is_numeric($row['rating']) || $row['rating'] < 0 || $row['rating'] > 5) {
                $errors[] = "Row {$rowNumber}: Rating must be a number between 0 and 5.";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get CSV template for destinations
     */
    public function getDestinationTemplate(): array
    {
        return [
            'headers' => $this->destinationAllColumns,
            'sample_data' => [
                [
                    'name' => 'Bali',
                    'city' => 'Denpasar',
                    'country' => 'Indonesia',
                    'description' => 'Beautiful island with stunning beaches and temples',
                    'image' => '',
                    'rating' => '4.5',
                    'is_popular' => 'true',
                ],
                [
                    'name' => 'Yogyakarta',
                    'city' => 'Yogyakarta',
                    'country' => 'Indonesia',
                    'description' => 'Cultural heart of Java with ancient temples and royal palaces',
                    'image' => '',
                    'rating' => '4.7',
                    'is_popular' => 'true',
                ],
                [
                    'name' => 'Raja Ampat',
                    'city' => 'Sorong',
                    'country' => 'Indonesia',
                    'description' => 'World-class diving destination with pristine coral reefs',
                    'image' => '',
                    'rating' => '4.9',
                    'is_popular' => 'true',
                ],
                [
                    'name' => 'Komodo Island',
                    'city' => 'Labuan Bajo',
                    'country' => 'Indonesia',
                    'description' => 'Home of the famous Komodo dragons and stunning pink beaches',
                    'image' => '',
                    'rating' => '4.6',
                    'is_popular' => 'true',
                ],
                [
                    'name' => 'Lombok',
                    'city' => 'Mataram',
                    'country' => 'Indonesia',
                    'description' => 'Beautiful beaches and the majestic Mount Rinjani',
                    'image' => '',
                    'rating' => '4.4',
                    'is_popular' => 'false',
                ],
                [
                    'name' => 'Jakarta',
                    'city' => 'Jakarta',
                    'country' => 'Indonesia',
                    'description' => 'Vibrant capital city with modern attractions and rich history',
                    'image' => '',
                    'rating' => '4.2',
                    'is_popular' => 'false',
                ],
                [
                    'name' => 'Bandung',
                    'city' => 'Bandung',
                    'country' => 'Indonesia',
                    'description' => 'Cool highland city known for tea plantations and art deco architecture',
                    'image' => '',
                    'rating' => '4.3',
                    'is_popular' => 'false',
                ],
                [
                    'name' => 'Surabaya',
                    'city' => 'Surabaya',
                    'country' => 'Indonesia',
                    'description' => 'Historic port city with colonial architecture and vibrant markets',
                    'image' => '',
                    'rating' => '4.1',
                    'is_popular' => 'false',
                ],
                [
                    'name' => 'Malang',
                    'city' => 'Malang',
                    'country' => 'Indonesia',
                    'description' => 'Charming city with colonial heritage and access to Mount Bromo',
                    'image' => '',
                    'rating' => '4.4',
                    'is_popular' => 'false',
                ],
                [
                    'name' => 'Semarang',
                    'city' => 'Semarang',
                    'country' => 'Indonesia',
                    'description' => 'Historic port city with Dutch colonial architecture',
                    'image' => '',
                    'rating' => '4.0',
                    'is_popular' => 'false',
                ],
                [
                    'name' => 'Makassar',
                    'city' => 'Makassar',
                    'country' => 'Indonesia',
                    'description' => 'Gateway to Eastern Indonesia with rich Bugis culture',
                    'image' => '',
                    'rating' => '4.2',
                    'is_popular' => 'false',
                ],
                [
                    'name' => 'Medan',
                    'city' => 'Medan',
                    'country' => 'Indonesia',
                    'description' => 'Gateway to Lake Toba and Batak culture',
                    'image' => '',
                    'rating' => '4.1',
                    'is_popular' => 'false',
                ],
                [
                    'name' => 'Padang',
                    'city' => 'Padang',
                    'country' => 'Indonesia',
                    'description' => 'Famous for Minangkabau culture and delicious cuisine',
                    'image' => '',
                    'rating' => '4.3',
                    'is_popular' => 'false',
                ],
                [
                    'name' => 'Palembang',
                    'city' => 'Palembang',
                    'country' => 'Indonesia',
                    'description' => 'Historic city with the iconic Ampera Bridge',
                    'image' => '',
                    'rating' => '4.0',
                    'is_popular' => 'false',
                ],
                [
                    'name' => 'Pontianak',
                    'city' => 'Pontianak',
                    'country' => 'Indonesia',
                    'description' => 'Equatorial city on the Kapuas River',
                    'image' => '',
                    'rating' => '3.9',
                    'is_popular' => 'false',
                ],
            ],
        ];
    }

    /**
     * Get CSV template for tour packages
     */
    public function getTourPackageTemplate(): array
    {
        $destinations = Destination::select('id', 'name', 'city', 'country')->get()->toArray();
        $firstDestinationId = (string) (Arr::first($destinations)['id'] ?? '1');

        return [
            'headers' => $this->tourPackageAllColumns,
            'sample_data' => [
                [
                    'destination_id' => $firstDestinationId,
                    'title' => 'Bali Adventure Tour',
                    'description' => 'Explore the best of Bali in 5 days',
                    'price' => '500000',
                    'duration_days' => '5',
                    'max_people' => '10',
                    'image' => '',
                    'rating' => '4.5',
                ],
                [
                    'destination_id' => $firstDestinationId,
                    'title' => 'Yogyakarta Cultural Tour',
                    'description' => 'Discover the rich culture and history of Yogyakarta',
                    'price' => '750000',
                    'duration_days' => '4',
                    'max_people' => '15',
                    'image' => '',
                    'rating' => '4.7',
                ],
                [
                    'destination_id' => $firstDestinationId,
                    'title' => 'Raja Ampat Diving Package',
                    'description' => 'World-class diving experience in pristine waters',
                    'price' => '1500000',
                    'duration_days' => '7',
                    'max_people' => '8',
                    'image' => '',
                    'rating' => '4.9',
                ],
                [
                    'destination_id' => $firstDestinationId,
                    'title' => 'Komodo Island Explorer',
                    'description' => 'See the famous Komodo dragons and explore pink beaches',
                    'price' => '1200000',
                    'duration_days' => '6',
                    'max_people' => '12',
                    'image' => '',
                    'rating' => '4.6',
                ],
                [
                    'destination_id' => $firstDestinationId,
                    'title' => 'Lombok Beach Getaway',
                    'description' => 'Relax on beautiful beaches and explore local culture',
                    'price' => '600000',
                    'duration_days' => '4',
                    'max_people' => '20',
                    'image' => '',
                    'rating' => '4.4',
                ],
                [
                    'destination_id' => $firstDestinationId,
                    'title' => 'Jakarta City Tour',
                    'description' => 'Explore the vibrant capital city of Indonesia',
                    'price' => '400000',
                    'duration_days' => '3',
                    'max_people' => '25',
                    'image' => '',
                    'rating' => '4.2',
                ],
                [
                    'destination_id' => $firstDestinationId,
                    'title' => 'Bandung Highland Retreat',
                    'description' => 'Escape to cool highlands with tea plantations',
                    'price' => '550000',
                    'duration_days' => '3',
                    'max_people' => '18',
                    'image' => '',
                    'rating' => '4.3',
                ],
                [
                    'destination_id' => $firstDestinationId,
                    'title' => 'Surabaya Heritage Walk',
                    'description' => 'Discover the historic port city of Surabaya',
                    'price' => '350000',
                    'duration_days' => '2',
                    'max_people' => '30',
                    'image' => '',
                    'rating' => '4.1',
                ],
                [
                    'destination_id' => $firstDestinationId,
                    'title' => 'Malang Bromo Sunrise',
                    'description' => 'Witness the stunning sunrise at Mount Bromo',
                    'price' => '800000',
                    'duration_days' => '3',
                    'max_people' => '15',
                    'image' => '',
                    'rating' => '4.5',
                ],
                [
                    'destination_id' => $firstDestinationId,
                    'title' => 'Semarang Colonial Tour',
                    'description' => 'Explore Dutch colonial architecture in Semarang',
                    'price' => '450000',
                    'duration_days' => '2',
                    'max_people' => '20',
                    'image' => '',
                    'rating' => '4.0',
                ],
            ],
            'available_destinations' => $destinations,
        ];
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

class ExportService
{
    /**
     * Export bookings to array format
     */
    public function bookingsToArray(Collection $bookings): array
    {
        $data = [];

        foreach ($bookings as $booking) {
            $data[] = [
                'ID' => $booking->id,
                'Customer' => $booking->user->name,
                'Email' => $booking->user->email,
                'Package' => $booking->tourPackage->title,
                'Destination' => $booking->tourPackage->destination->name,
                'Booking Date' => $booking->booking_date->format('Y-m-d'),
                'People' => $booking->people_count,
                'Total Price' => $booking->total_price,
                'Payment Status' => ucfirst($booking->payment_status),
                'Booking Status' => ucfirst($booking->booking_status),
                'Created At' => $booking->created_at->format('Y-m-d H:i:s'),
            ];
        }

        return $data;
    }

    /**
     * Export users to array format
     */
    public function usersToArray(Collection $users): array
    {
        $data = [];

        foreach ($users as $user) {
            $data[] = [
                'ID' => $user->id,
                'Name' => $user->name,
                'Email' => $user->email,
                'Firebase UID' => $user->firebase_uid,
                'Total Bookings' => $user->bookings->count(),
                'Total Spent' => $user->bookings->sum('total_price'),
                'Joined At' => $user->created_at->format('Y-m-d H:i:s'),
            ];
        }

        return $data;
    }

    /**
     * Export destinations to array format
     */
    public function destinationsToArray(Collection $destinations): array
    {
        $data = [];

        foreach ($destinations as $destination) {
            $data[] = [
                'ID' => $destination->id,
                'Name' => $destination->name,
                'City' => $destination->city,
                'Country' => $destination->country,
                'Rating' => $destination->rating,
                'Tour Packages' => $destination->tourPackages->count(),
                'Created At' => $destination->created_at->format('Y-m-d H:i:s'),
            ];
        }

        return $data;
    }

    /**
     * Export tour packages to array format
     */
    public function tourPackagesToArray(Collection $tourPackages): array
    {
        $data = [];

        foreach ($tourPackages as $package) {
            $data[] = [
                'ID' => $package->id,
                'Title' => $package->title,
                'Destination' => $package->destination->name,
                'Price' => $package->price,
                'Duration (Days)' => $package->duration_days,
                'Max People' => $package->max_people,
                'Total Bookings' => $package->bookings->count(),
                'Created At' => $package->created_at->format('Y-m-d H:i:s'),
            ];
        }

        return $data;
    }

    /**
     * Generate CSV content
     */
    public function toCsv(array $data, string $filename): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Header
        fputcsv($output, array_keys($data[0]));

        // Data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    /**
     * Generate HTML for PDF
     */
    public function toHtml(array $data, string $title): string
    {
        $headers = !empty($data) ? array_keys($data[0]) : [];

        return View::make('admin.exports.template', [
            'title' => $title,
            'headers' => $headers,
            'data' => $data,
        ])->render();
    }
}

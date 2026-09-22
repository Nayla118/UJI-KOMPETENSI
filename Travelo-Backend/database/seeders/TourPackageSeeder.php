<?php

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\TourPackage;
use Illuminate\Database\Seeder;

class TourPackageSeeder extends Seeder
{
    public function run(): void
    {
        $bali = Destination::where('name', 'Bali')->first();
        $yogyakarta = Destination::where('name', 'Yogyakarta')->first();
        $lombok = Destination::where('name', 'Lombok')->first();
        $rajaAmpat = Destination::where('name', 'Raja Ampat')->first();
        $labuanBajo = Destination::where('name', 'Labuan Bajo')->first();
        $bandung = Destination::where('name', 'Bandung')->first();
        $komodo = Destination::where('name', 'Komodo')->first();
        $bromo = Destination::where('name', 'Bromo')->first();

        $tourPackages = [
            // Bali packages
            [
                'destination_id' => $bali->id,
                'title' => 'Bali Beach & Culture Tour',
                'description' => 'Explore the beautiful beaches and rich culture of Bali. Visit ancient temples, rice terraces, and enjoy pristine beaches.',
                'price' => 1500000,
                'duration_days' => 5,
                'max_people' => 10,
                'image' => 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?w=800',
                'rating' => 4.8,
            ],
            [
                'destination_id' => $bali->id,
                'title' => 'Bali Adventure Package',
                'description' => 'Experience thrilling adventures in Bali including white water rafting, ATV riding, and volcano trekking.',
                'price' => 2500000,
                'duration_days' => 4,
                'max_people' => 8,
                'image' => 'https://images.unsplash.com/photo-1555400038-63f5ba517a47?w=800',
                'rating' => 4.7,
            ],
            [
                'destination_id' => $bali->id,
                'title' => 'Bali Luxury Retreat',
                'description' => 'Indulge in a luxurious escape with private villa, spa treatments, and exclusive dining experiences.',
                'price' => 8500000,
                'duration_days' => 6,
                'max_people' => 4,
                'image' => 'https://images.unsplash.com/photo-1573790387438-4da905039392?w=800',
                'rating' => 4.9,
            ],
            // Yogyakarta packages
            [
                'destination_id' => $yogyakarta->id,
                'title' => 'Javanese Heritage Tour',
                'description' => 'Discover the ancient wonders of Java including Borobudur, Prambanan, and the royal palaces.',
                'price' => 1200000,
                'duration_days' => 3,
                'max_people' => 12,
                'image' => 'https://images.unsplash.com/photo-1569060716907-60ed066d8c81?w=800',
                'rating' => 4.6,
            ],
            [
                'destination_id' => $yogyakarta->id,
                'title' => 'Yogyakarta Culinary Adventure',
                'description' => 'Explore the best of Javanese cuisine with cooking classes, street food tours, and traditional dining.',
                'price' => 1800000,
                'duration_days' => 4,
                'max_people' => 8,
                'image' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=800',
                'rating' => 4.5,
            ],
            // Lombok packages
            [
                'destination_id' => $lombok->id,
                'title' => 'Lombok Island Hopping',
                'description' => 'Visit the stunning Gili Islands with snorkeling, diving, and beach relaxation.',
                'price' => 2000000,
                'duration_days' => 4,
                'max_people' => 10,
                'image' => 'https://images.unsplash.com/photo-1518548419970-58e3b4079ab2?w=800',
                'rating' => 4.7,
            ],
            [
                'destination_id' => $lombok->id,
                'title' => 'Mount Rinjani Trekking',
                'description' => 'Challenge yourself with a trek to the summit of Mount Rinjani, one of Indonesia\'s most beautiful volcanoes.',
                'price' => 3500000,
                'duration_days' => 3,
                'max_people' => 6,
                'image' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=800',
                'rating' => 4.8,
            ],
            // Raja Ampat packages
            [
                'destination_id' => $rajaAmpat->id,
                'title' => 'Raja Ampat Diving Paradise',
                'description' => 'Explore the world\'s most biodiverse marine area with world-class diving and snorkeling experiences.',
                'price' => 5500000,
                'duration_days' => 6,
                'max_people' => 8,
                'image' => 'https://images.unsplash.com/photo-1516690561799-46d8f74f9abf?w=800',
                'rating' => 4.9,
            ],
            [
                'destination_id' => $rajaAmpat->id,
                'title' => 'Raja Ampat Expedition',
                'description' => 'A comprehensive expedition covering the best of Raja Ampat\'s islands, beaches, and marine life.',
                'price' => 7000000,
                'duration_days' => 8,
                'max_people' => 6,
                'image' => 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=800',
                'rating' => 4.9,
            ],
            // Labuan Bajo packages
            [
                'destination_id' => $labuanBajo->id,
                'title' => 'Komodo Island Adventure',
                'description' => 'Visit the legendary Komodo National Park, see Komodo dragons, and explore stunning islands.',
                'price' => 2200000,
                'duration_days' => 3,
                'max_people' => 12,
                'image' => 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=800',
                'rating' => 4.7,
            ],
            [
                'destination_id' => $labuanBajo->id,
                'title' => 'Flores Island Overland',
                'description' => 'Journey across Flores island visiting traditional villages, colorful lakes, and ancient forests.',
                'price' => 4500000,
                'duration_days' => 7,
                'max_people' => 8,
                'image' => 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?w=800',
                'rating' => 4.6,
            ],
            // Bandung packages
            [
                'destination_id' => $bandung->id,
                'title' => 'Bandung Family Getaway',
                'description' => 'Perfect for families with visits to amusement parks, tea plantations, and delicious local cuisine.',
                'price' => 900000,
                'duration_days' => 2,
                'max_people' => 6,
                'image' => 'https://images.unsplash.com/photo-1565967511849-76a60a516170?w=800',
                'rating' => 4.5,
            ],
            // Komodo packages
            [
                'destination_id' => $komodo->id,
                'title' => 'Komodo Dragon Safari',
                'description' => 'Get up close with the legendary Komodo dragons on a guided safari. Trek through rugged terrain and visit ranger stations.',
                'price' => 3000000,
                'duration_days' => 4,
                'max_people' => 8,
                'image' => 'https://images.unsplash.com/photo-1570789210967-2cac24f169ab?w=800',
                'rating' => 4.8,
            ],
            [
                'destination_id' => $komodo->id,
                'title' => 'Pink Beach & Snorkeling',
                'description' => 'Visit the famous Pink Beach with its unique rose-colored sand. Snorkel in crystal-clear waters teeming with marine life.',
                'price' => 2500000,
                'duration_days' => 3,
                'max_people' => 10,
                'image' => 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=800',
                'rating' => 4.7,
            ],
            // Bromo packages
            [
                'destination_id' => $bromo->id,
                'title' => 'Bromo Sunrise Tour',
                'description' => 'Witness the breathtaking sunrise from Mount Penanjakan overlooking Mount Bromo caldera. Trek across the sea of sand.',
                'price' => 800000,
                'duration_days' => 2,
                'max_people' => 10,
                'image' => 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?w=800',
                'rating' => 4.7,
            ],
            [
                'destination_id' => $bromo->id,
                'title' => 'Bromo & Ijen Expedition',
                'description' => 'A complete East Java adventure combining Mount Bromo sunrise with the ethereal blue fire of Kawah Ijen crater.',
                'price' => 1800000,
                'duration_days' => 4,
                'max_people' => 8,
                'image' => 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?w=800',
                'rating' => 4.8,
            ],
        ];

        foreach ($tourPackages as $package) {
            TourPackage::create($package);
        }
    }
}

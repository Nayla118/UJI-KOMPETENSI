<?php

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\TourPackage;
use Illuminate\Database\Seeder;

/**
 * Tour packages untuk 20 destinasi awal Travelo.
 *
 * Safe to rerun: existing rows are matched by `title` and never overwritten.
 * Harga, durasi, dan kapasitas adalah contoh — admin disarankan menyesuaikan
 * dengan harga dan kapasitas aktual mereka.
 */
class TourPackageSeeder extends Seeder
{
    public function run(): void
    {
        $destinations = Destination::all()->keyBy('name');

        $tourPackages = [
            // ---------- Jawa Timur ----------
            [
                'destination' => 'Gunung Bromo',
                'title' => 'Bromo Sunrise Tour 1 Hari',
                'description' => 'Witness the breathtaking sunrise from Mount Penanjakan overlooking Mount Bromo caldera. Trek across the sea of sand.',
                'price' => 800000,
                'duration_days' => 1,
                'max_people' => 10,
                'image' => 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?w=800',
            ],
            [
                'destination' => 'Gunung Bromo',
                'title' => 'Bromo & Ijen Expedition 3 Hari 2 Malam',
                'description' => 'A complete East Java adventure combining Mount Bromo sunrise with the ethereal blue fire of Kawah Ijen crater.',
                'price' => 1800000,
                'duration_days' => 3,
                'max_people' => 8,
                'image' => 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?w=800',
            ],
            [
                'destination' => 'Kawah Ijen',
                'title' => 'Kawah Ijen Blue Fire Trek 2 Hari 1 Malam',
                'description' => 'A guided night trek to see the rare blue flames and the turquoise crater lake at sunrise.',
                'price' => 650000,
                'duration_days' => 2,
                'max_people' => 8,
                'image' => 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?w=800',
            ],
            [
                'destination' => 'Kota Batu',
                'title' => 'Batu Family Trip 2 Hari 1 Malam',
                'description' => 'Cool mountain city with recreation parks, museums, apple orchards, and scenic dining. Perfect for family holidays.',
                'price' => 950000,
                'duration_days' => 2,
                'max_people' => 6,
                'image' => 'https://images.unsplash.com/photo-1565967511849-76a60a516170?w=800',
            ],
            [
                'destination' => 'Pantai Pulau Merah',
                'title' => 'Banyuwangi Explore (Ijen + Pulau Merah) 3 Hari 2 Malam',
                'description' => 'Combine Kawah Ijen blue fire with surfing lessons and sunset at Pantai Pulau Merah.',
                'price' => 1600000,
                'duration_days' => 3,
                'max_people' => 8,
                'image' => 'https://images.unsplash.com/photo-1507525429053-4e973242c517?w=800',
            ],
            // ---------- Jawa Tengah & DIY ----------
            [
                'destination' => 'Candi Borobudur',
                'title' => 'Borobudur Heritage 1 Hari',
                'description' => '9th-century Buddhist monument and UNESCO World Heritage site. Enjoy the morning atmosphere around the temple.',
                'price' => 450000,
                'duration_days' => 1,
                'max_people' => 12,
                'image' => 'https://images.unsplash.com/photo-1569060716907-60ed066d8c81?w=800',
            ],
            [
                'destination' => 'Candi Prambanan',
                'title' => 'Prambanan + Keraton Sehari Penuh',
                'description' => 'Largest Hindu temple complex in Indonesia with Ramayana ballet performance at sunset.',
                'price' => 550000,
                'duration_days' => 1,
                'max_people' => 12,
                'image' => 'https://images.unsplash.com/photo-1572398878314-6d8346b388b8?w=800',
            ],
            [
                'destination' => 'Malioboro & Keraton Yogyakarta',
                'title' => 'Jogja City Tour 1 Hari',
                'description' => 'Iconic shopping street, street food, and the cultural heart of Java at the Keraton.',
                'price' => 350000,
                'duration_days' => 1,
                'max_people' => 12,
                'image' => 'https://images.unsplash.com/photo-1533185024229-1e8bc1a03e7e?w=800',
            ],
            [
                'destination' => 'Malioboro & Keraton Yogyakarta',
                'title' => 'Jogja Budaya 2 Hari 1 Malam',
                'description' => 'Two days of Yogyakarta culture: Malioboro, Keraton, Taman Sari, gudeg, and batik.',
                'price' => 900000,
                'duration_days' => 2,
                'max_people' => 8,
                'image' => 'https://images.unsplash.com/photo-1533185024229-1e8bc1a03e7e?w=800',
            ],
            // ---------- Jawa Barat & Jakarta ----------
            [
                'destination' => 'Kawah Putih',
                'title' => 'Ciwidey Day Trip dari Bandung',
                'description' => 'Volcanic crater lake with white-green water and cool highland air. Popular for photography.',
                'price' => 400000,
                'duration_days' => 1,
                'max_people' => 10,
                'image' => 'https://images.unsplash.com/photo-1533800212231-3e58a05e23bf?w=800',
            ],
            [
                'destination' => 'Jalan Braga & Kuliner Bandung',
                'title' => 'Bandung Food & City Tour 1 Hari',
                'description' => 'Colonial-era architecture, cafes, shops, and Bandung culinary specialties like batagor and surabi.',
                'price' => 500000,
                'duration_days' => 1,
                'max_people' => 10,
                'image' => 'https://images.unsplash.com/photo-1565967511849-76a60a516170?w=800',
            ],
            [
                'destination' => 'Kota Tua Jakarta',
                'title' => 'Jakarta Heritage Walk Setengah Hari',
                'description' => 'Colonial historical area with museums, old buildings, and ontel bicycle rentals.',
                'price' => 300000,
                'duration_days' => 1,
                'max_people' => 12,
                'image' => 'https://images.unsplash.com/photo-1515023115689-589c33041697?w=800',
            ],
            // ---------- Bali & Nusa Tenggara ----------
            [
                'destination' => 'Tanah Lot',
                'title' => 'Bali Sunset Tour Setengah Hari',
                'description' => 'Temple on a rock by the sea famous for its sunset views, waves crashing against the rock, and traditional dance performances.',
                'price' => 350000,
                'duration_days' => 1,
                'max_people' => 12,
                'image' => 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?w=800',
            ],
            [
                'destination' => 'Ubud & Tegallalang',
                'title' => 'Ubud Culture & Nature 1 Hari',
                'description' => 'Rice terraces, Monkey Forest, galleries, and traditional Balinese dance in the artistic heart of Bali.',
                'price' => 600000,
                'duration_days' => 1,
                'max_people' => 8,
                'image' => 'https://images.unsplash.com/photo-1544644181-1484b3fdfc62?w=800',
            ],
            [
                'destination' => 'Nusa Penida',
                'title' => 'Nusa Penida Day Trip',
                'description' => 'Dramatic limestone cliffs, hidden beaches, and snorkeling to see manta rays. Access by speedboat from Bali.',
                'price' => 700000,
                'duration_days' => 1,
                'max_people' => 8,
                'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800',
            ],
            [
                'destination' => 'Nusa Penida',
                'title' => 'Nusa Penida Escape 2 Hari 1 Malam',
                'description' => 'Two days exploring Kelingking Beach, Angel\'s Billabong, Broken Beach, and manta snorkeling.',
                'price' => 1400000,
                'duration_days' => 2,
                'max_people' => 6,
                'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800',
            ],
            [
                'destination' => 'Gili Trawangan',
                'title' => 'Gili Escape 3 Hari 2 Malam',
                'description' => 'Small island without motor vehicles surrounded by white beaches and clear water. Snorkeling with turtles and sunset on the west side.',
                'price' => 1600000,
                'duration_days' => 3,
                'max_people' => 6,
                'image' => 'https://images.unsplash.com/photo-1533431601175-a3a0118a78bc?w=800',
            ],
            [
                'destination' => 'Gunung Rinjani',
                'title' => 'Rinjani Trekking 3 Hari 2 Malam',
                'description' => 'Second highest volcano in Indonesia with Segara Anak crater lake. Requires physical preparation and licensed guide/porter.',
                'price' => 2500000,
                'duration_days' => 3,
                'max_people' => 6,
                'image' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=800',
            ],
            [
                'destination' => 'Labuan Bajo & Pulau Komodo',
                'title' => 'Open Trip Komodo 3 Hari 2 Malam',
                'description' => 'Gateway to Komodo National Park. Island hopping, light trekking on Padar Island, snorkeling, and seeing Komodo dragons with rangers.',
                'price' => 1800000,
                'duration_days' => 3,
                'max_people' => 10,
                'image' => 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=800',
            ],
            [
                'destination' => 'Labuan Bajo & Pulau Komodo',
                'title' => 'Komodo Day Trip',
                'description' => 'One day trip to see Komodo dragons and snorkel in crystal clear waters.',
                'price' => 800000,
                'duration_days' => 1,
                'max_people' => 12,
                'image' => 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=800',
            ],
            // ---------- Sumatera, Sulawesi, Papua ----------
            [
                'destination' => 'Raja Ampat',
                'title' => 'Raja Ampat Island Hopping 4 Hari 3 Malam',
                'description' => 'Archipelago of limestone islands with lagoons and coral reefs among the richest marine biodiversity in the world. Premium segment.',
                'price' => 7500000,
                'duration_days' => 4,
                'max_people' => 8,
                'image' => 'https://images.unsplash.com/photo-1516690561799-46d8f74f9abf?w=800',
            ],
            [
                'destination' => 'Danau Toba',
                'title' => 'Toba Cultural Trip 3 Hari 2 Malam',
                'description' => 'Largest volcanic lake in Southeast Asia with Samosir Island in the middle. Batak cultural villages and lakeside accommodation.',
                'price' => 1200000,
                'duration_days' => 3,
                'max_people' => 8,
                'image' => 'https://images.unsplash.com/photo-1544716278-ca5e3f4abdac?w=800',
            ],
            [
                'destination' => 'Taman Laut Bunaken',
                'title' => 'Bunaken Snorkeling Day Trip',
                'description' => 'National marine park with steep coral walls rich in marine life, popular for diving and snorkeling.',
                'price' => 500000,
                'duration_days' => 1,
                'max_people' => 10,
                'image' => 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=800',
            ],
            [
                'destination' => 'Taman Laut Bunaken',
                'title' => 'Manado Diving 3 Hari',
                'description' => 'Three days of diving and snorkeling in Bunaken National Marine Park with Manado cuisine.',
                'price' => 1500000,
                'duration_days' => 3,
                'max_people' => 8,
                'image' => 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=800',
            ],
            [
                'destination' => 'Tana Toraja',
                'title' => 'Toraja Heritage 3 Hari 2 Malam',
                'description' => 'Highlands with tongkonan traditional houses, unique ceremonies, and green hill scenery. Respect local customs during ceremonies.',
                'price' => 1600000,
                'duration_days' => 3,
                'max_people' => 6,
                'image' => 'https://images.unsplash.com/photo-1536715336237-68a47079a21e?w=800',
            ],
        ];

        foreach ($tourPackages as $attributes) {
            $destination = $destinations->get($attributes['destination']);

            if ($destination === null) {
                continue;
            }

            $data = $attributes;
            unset($data['destination']);
            $data['destination_id'] = $destination->id;

            TourPackage::firstOrCreate(['title' => $data['title']], $data);
        }
    }
}

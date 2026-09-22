<?php

namespace Database\Seeders;

use App\Models\Destination;
use Illuminate\Database\Seeder;

class DestinationSeeder extends Seeder
{
    public function run(): void
    {
        $destinations = [
            [
                'name' => 'Bali',
                'city' => 'Denpasar',
                'country' => 'Indonesia',
                'description' => 'Bali is a living postcard, an Indonesian paradise that feels like a fantasy. Soak up the sun on a stretch of fine white sand, or catch the perfect wave on the endless ocean.',
                'rating' => 4.8,
                'image' => 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?w=800',
                'is_popular' => true,
            ],
            [
                'name' => 'Yogyakarta',
                'city' => 'Yogyakarta',
                'country' => 'Indonesia',
                'description' => 'Yogyakarta is a cultural capital of Java and the cradle of classical Javanese arts. Home to the magnificent temples of Borobudur and Prambanan.',
                'rating' => 4.7,
                'image' => 'https://images.unsplash.com/photo-1569060716907-60ed066d8c81?w=800',
                'is_popular' => true,
            ],
            [
                'name' => 'Lombok',
                'city' => 'Mataram',
                'country' => 'Indonesia',
                'description' => 'Lombok is an island in the West Nusa Tenggara province. It is famous for its beaches, surfing, and the towering Mount Rinjani volcano.',
                'rating' => 4.6,
                'image' => 'https://images.unsplash.com/photo-1518548419970-58e3b4079ab2?w=800',
                'is_popular' => true,
            ],
            [
                'name' => 'Raja Ampat',
                'city' => 'Sorong',
                'country' => 'Indonesia',
                'description' => 'Raja Ampat is a remote archipelago off the northwest tip of Bird\'s Head Peninsula. It is known for its marine biodiversity and pristine reefs.',
                'rating' => 4.9,
                'image' => 'https://images.unsplash.com/photo-1516690561799-46d8f74f9abf?w=800',
                'is_popular' => true,
            ],
            [
                'name' => 'Labuan Bajo',
                'city' => 'Labuan Bajo',
                'country' => 'Indonesia',
                'description' => 'Labuan Bajo is the gateway to the Komodo National Park. It offers stunning islands, crystal clear waters, and the famous Komodo dragons.',
                'rating' => 4.7,
                'image' => 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=800',
                'is_popular' => true,
            ],
            [
                'name' => 'Bandung',
                'city' => 'Bandung',
                'country' => 'Indonesia',
                'description' => 'Bandung is the capital of West Java province. Known for its colonial-era architecture, natural attractions, and shopping outlets.',
                'rating' => 4.5,
                'image' => 'https://images.unsplash.com/photo-1565967511849-76a60a516170?w=800',
                'is_popular' => false,
            ],
            [
                'name' => 'Surabaya',
                'city' => 'Surabaya',
                'country' => 'Indonesia',
                'description' => 'Surabaya is the capital of East Java. It is the second-largest city in Indonesia and a major business hub.',
                'rating' => 4.4,
                'image' => 'https://images.unsplash.com/photo-1569263979104-865ab7cd8d13?w=800',
                'is_popular' => false,
            ],
            [
                'name' => 'Jakarta',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'description' => 'Jakarta is the capital and largest city of Indonesia. It is a bustling metropolis with diverse attractions.',
                'rating' => 4.3,
                'image' => 'https://images.unsplash.com/photo-1515023115689-589c33041697?w=800',
                'is_popular' => false,
            ],
            [
                'name' => 'Komodo',
                'city' => 'Labuan Bajo',
                'country' => 'Indonesia',
                'description' => 'Komodo Island is home to the legendary Komodo dragons, the largest living lizards on earth. The island also boasts stunning pink sand beaches and world-class diving spots.',
                'rating' => 4.8,
                'image' => 'https://images.unsplash.com/photo-1570789210967-2cac24f169ab?w=800',
                'is_popular' => true,
            ],
            [
                'name' => 'Bromo',
                'city' => 'Malang',
                'country' => 'Indonesia',
                'description' => 'Mount Bromo is an active volcano in East Java. Famous for its stunning sunrise views over the caldera and the sea of sand. A must-visit for nature lovers and photographers.',
                'rating' => 4.7,
                'image' => 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?w=800',
                'is_popular' => true,
            ],
        ];

        foreach ($destinations as $destination) {
            Destination::create($destination);
        }
    }
}

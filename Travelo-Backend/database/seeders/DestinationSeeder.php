<?php

namespace Database\Seeders;

use App\Models\Destination;
use Illuminate\Database\Seeder;

/**
 * 20 destinasi awal Travelo dari data awal pemilik project.
 *
 * Safe to rerun: existing destinations are matched by `name` and never
 * overwritten. A missing category is the only field ever filled in, so
 * categories added by this feature work for older demo rows too.
 */
class DestinationSeeder extends Seeder
{
    public function run(): void
    {
        $destinations = [
            // Jawa Timur
            [
                'name' => 'Gunung Bromo',
                'city' => 'Probolinggo',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_GUNUNG,
                'description' => 'Kawasan gunung berapi aktif di Taman Nasional Bromo Tengger Semeru dengan lautan pasir dan kawah yang masih mengepulkan asap. Pengunjung biasanya berangkat dini hari untuk menikmati matahari terbit dari Penanjakan, lalu turun ke lautan pasir dan mendaki tangga menuju bibir kawah.',
                'rating' => 4.8,
                'image' => 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?w=800',
                'is_popular' => true,
            ],
            [
                'name' => 'Kawah Ijen',
                'city' => 'Banyuwangi',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_GUNUNG,
                'description' => 'Kawah dengan danau asam berwarna toska yang terkenal dengan fenomena api biru (blue fire) di malam hari. Pendakian dimulai tengah malam dan memakan waktu sekitar 2 sampai 3 jam, sehingga cocok bagi yang suka petualangan.',
                'rating' => 4.6,
                'image' => 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?w=800',
                'is_popular' => false,
            ],
            [
                'name' => 'Kota Batu',
                'city' => 'Batu',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_KOTA,
                'description' => 'Kota wisata berhawa sejuk di lereng pegunungan yang dipenuhi taman rekreasi, museum, kebun apel, dan tempat makan dengan pemandangan. Cocok untuk liburan keluarga karena banyak pilihan aktivitas dalam jarak berdekatan.',
                'rating' => 4.5,
                'image' => 'https://images.unsplash.com/photo-1565967511849-76a60a516170?w=800',
                'is_popular' => false,
            ],
            [
                'name' => 'Pantai Pulau Merah',
                'city' => 'Banyuwangi',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_PANTAI,
                'description' => 'Pantai berpasir lembut dengan bukit kecil di tepi laut yang bisa dijangkau dengan berjalan kaki saat air surut. Ombaknya cocok untuk belajar selancar dan sore harinya terkenal dengan pemandangan matahari terbenam.',
                'rating' => 4.4,
                'image' => 'https://images.unsplash.com/photo-1507525429053-4e973242c517?w=800',
                'is_popular' => false,
            ],
            // Jawa Tengah & DIY
            [
                'name' => 'Candi Borobudur',
                'city' => 'Magelang',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_BUDAYA,
                'description' => 'Candi Buddha abad ke-9 yang menjadi salah satu monumen terbesar di dunia dan situs Warisan Dunia UNESCO. Relief pada dindingnya menceritakan ajaran dan kisah kehidupan, dan pengunjung dapat menikmati suasana pagi di sekitar candi.',
                'rating' => 4.9,
                'image' => 'https://images.unsplash.com/photo-1569060716907-60ed066d8c81?w=800',
                'is_popular' => true,
            ],
            [
                'name' => 'Candi Prambanan',
                'city' => 'Sleman',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_BUDAYA,
                'description' => 'Kompleks candi Hindu terbesar di Indonesia dengan menara-menara tinggi yang megah, dikaitkan dengan legenda Roro Jonggrang. Pada waktu tertentu ada pertunjukan sendratari Ramayana dengan latar candi.',
                'rating' => 4.7,
                'image' => 'https://images.unsplash.com/photo-1572398878314-6d8346b388b8?w=800',
                'is_popular' => true,
            ],
            [
                'name' => 'Malioboro & Keraton Yogyakarta',
                'city' => 'Yogyakarta',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_KOTA,
                'description' => 'Jantung wisata Kota Jogja: jalan belanja ikonik, kuliner kaki lima, dan pusat budaya Jawa di Keraton. Cocok dijelajahi dengan berjalan kaki, becak, atau andong.',
                'rating' => 4.6,
                'image' => 'https://images.unsplash.com/photo-1533185024229-1e8bc1a03e7e?w=800',
                'is_popular' => false,
            ],
            // Jawa Barat & Jakarta
            [
                'name' => 'Kawah Putih',
                'city' => 'Ciwidey',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_ALAM,
                'description' => 'Danau kawah vulkanik di dataran tinggi dengan air berwarna putih kehijauan dan udara sejuk. Pemandangannya berubah mengikuti cuaca dan kandungan belerang, sehingga populer untuk fotografi.',
                'rating' => 4.6,
                'image' => 'https://images.unsplash.com/photo-1533800212231-3e58a05e23bf?w=800',
                'is_popular' => false,
            ],
            [
                'name' => 'Jalan Braga & Kuliner Bandung',
                'city' => 'Bandung',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_KULINER,
                'description' => 'Kawasan kota lama dengan bangunan bergaya kolonial, kafe, dan toko yang berpadu dengan wisata kuliner khas Bandung. Cocok untuk wisata santai satu hari sambil berburu oleh-oleh.',
                'rating' => 4.5,
                'image' => 'https://images.unsplash.com/photo-1565967511849-76a60a516170?w=800',
                'is_popular' => false,
            ],
            [
                'name' => 'Kota Tua Jakarta',
                'city' => 'Jakarta Barat',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_BUDAYA,
                'description' => 'Kawasan bersejarah peninggalan masa kolonial dengan museum, bangunan tua, dan lapangan yang ramai pada akhir pekan. Pengunjung bisa menyewa sepeda ontel dan mengunjungi beberapa museum dalam satu area.',
                'rating' => 4.3,
                'image' => 'https://images.unsplash.com/photo-1515023115689-589c33041697?w=800',
                'is_popular' => false,
            ],
            // Bali & Nusa Tenggara
            [
                'name' => 'Tanah Lot',
                'city' => 'Tabanan',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_BUDAYA,
                'description' => 'Pura di atas batu karang di tepi laut yang terkenal dengan pemandangan matahari terbenamnya. Ombak yang menghantam karang dan siluet pura saat senja membuatnya menjadi salah satu ikon Bali.',
                'rating' => 4.7,
                'image' => 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?w=800',
                'is_popular' => true,
            ],
            [
                'name' => 'Ubud & Tegallalang',
                'city' => 'Gianyar',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_ALAM,
                'description' => 'Pusat seni dan budaya Bali dengan sawah terasering, hutan monyet, galeri, serta suasana desa yang tenang. Banyak wisatawan menggabungkan jalan-jalan sawah, ayunan, kelas kerajinan, dan wisata kuliner.',
                'rating' => 4.8,
                'image' => 'https://images.unsplash.com/photo-1544644181-1484b3fdfc62?w=800',
                'is_popular' => true,
            ],
            [
                'name' => 'Nusa Penida',
                'city' => 'Klungkung',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_PANTAI,
                'description' => 'Pulau dengan tebing karang dramatis, pantai tersembunyi, dan titik snorkeling untuk melihat pari manta. Akses menggunakan kapal cepat dari Bali, dan medan di beberapa titik cukup menantang.',
                'rating' => 4.7,
                'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800',
                'is_popular' => false,
            ],
            [
                'name' => 'Gili Trawangan',
                'city' => 'Lombok Utara',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_PANTAI,
                'description' => 'Pulau kecil tanpa kendaraan bermotor yang dikelilingi pantai putih dan laut jernih. Bisa dijelajahi dengan sepeda atau cidomo, dengan aktivitas snorkeling, menyelam, dan melihat penyu.',
                'rating' => 4.8,
                'image' => 'https://images.unsplash.com/photo-1533431601175-a3a0118a78bc?w=800',
                'is_popular' => false,
            ],
            [
                'name' => 'Gunung Rinjani',
                'city' => 'Lombok',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_GUNUNG,
                'description' => 'Gunung berapi tertinggi kedua di Indonesia dengan danau kawah Segara Anak. Pendakian memerlukan persiapan fisik dan pemandu atau porter berlisensi, dan jalur ditutup pada musim hujan.',
                'rating' => 4.7,
                'image' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=800',
                'is_popular' => false,
            ],
            [
                'name' => 'Labuan Bajo & Pulau Komodo',
                'city' => 'Labuan Bajo',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_ALAM,
                'description' => 'Gerbang menuju Taman Nasional Komodo, habitat asli komodo. Wisata utamanya adalah berlayar antar pulau, trekking ringan di Pulau Padar, snorkeling, dan melihat komodo bersama ranger.',
                'rating' => 4.8,
                'image' => 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=800',
                'is_popular' => true,
            ],
            // Sumatera, Sulawesi, Papua
            [
                'name' => 'Raja Ampat',
                'city' => 'Sorong',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_PANTAI,
                'description' => 'Gugusan pulau karang dengan laguna dan terumbu karang yang termasuk paling kaya keanekaragaman hayati lautnya. Cocok untuk penyelam, pencinta snorkeling, dan pencari pemandangan pulau dari ketinggian.',
                'rating' => 4.9,
                'image' => 'https://images.unsplash.com/photo-1516690561799-46d8f74f9abf?w=800',
                'is_popular' => false,
            ],
            [
                'name' => 'Danau Toba',
                'city' => 'Sumatera Utara',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_ALAM,
                'description' => 'Danau vulkanik terbesar di Asia Tenggara dengan Pulau Samosir di tengahnya. Wisatawan bisa menikmati pemandangan danau, desa budaya Batak, dan penginapan di tepi air.',
                'rating' => 4.6,
                'image' => 'https://images.unsplash.com/photo-1544716278-ca5e3f4abdac?w=800',
                'is_popular' => false,
            ],
            [
                'name' => 'Taman Laut Bunaken',
                'city' => 'Manado',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_PANTAI,
                'description' => 'Taman nasional laut dengan dinding karang curam yang kaya biota, populer untuk menyelam dan snorkeling. Ditempuh dengan perahu dari dermaga Manado.',
                'rating' => 4.7,
                'image' => 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=800',
                'is_popular' => false,
            ],
            [
                'name' => 'Tana Toraja',
                'city' => 'Makassar',
                'country' => 'Indonesia',
                'category' => Destination::CATEGORY_BUDAYA,
                'description' => 'Dataran tinggi dengan rumah adat tongkonan beratap melengkung, upacara adat yang unik, dan pemandangan perbukitan hijau. Cocok untuk wisata budaya, dan pengunjung perlu menghormati tata krama saat menyaksikan upacara.',
                'rating' => 4.7,
                'image' => 'https://images.unsplash.com/photo-1536715336237-68a47079a21e?w=800',
                'is_popular' => false,
            ],
        ];

        foreach ($destinations as $attributes) {
            $destination = Destination::firstOrCreate(
                ['name' => $attributes['name']],
                $attributes
            );

            // Additive backfill only; never replace data an admin edited.
            if (blank($destination->category) && filled($attributes['category'])) {
                $destination->forceFill(['category' => $attributes['category']])->save();
            }
        }
    }
}

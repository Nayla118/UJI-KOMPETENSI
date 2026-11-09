<?php

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\TourPackage;
use Illuminate\Database\Seeder;

/**
 * Demo travel-agent detail content: a real itinerary and a photo gallery for
 * at least three packages, plus coordinates for their destinations.
 *
 * Safe to rerun: itinerary days are matched by (package, day_number) and
 * gallery rows by (package, path).
 */
class TourPackageContentSeeder extends Seeder
{
    public function run(): void
    {
        $content = [
            'Bromo Sunrise Tour 1 Hari' => [
                'meeting_point' => 'Bandara Juanda Terminal 1, Sidoarjo',
                'meeting_latitude' => -7.3795,
                'meeting_longitude' => 112.7870,
                'inclusions' => [
                    'Penjemputan hotel di Malang/Surabaya',
                    'Transportasi 4x4 ke Penanjakan',
                    'Tiket masuk Taman Nasional Bromo',
                    'Guide lokal berlisensi',
                    'Sarapan pagi',
                ],
                'exclusions' => [
                    'Pengeluaran pribadi',
                    'Asuransi perjalanan',
                    'Sewa kuda di lautan pasir',
                    'Makan siang dan malam',
                ],
                'terms' => "Harap tiba 15 menit sebelum jadwal penjemputan.\nSuhu di Bromo bisa turun mendekati 0°C; bawa jaket tebal.\nPembatalan H-3 dikenakan biaya 50%, H-1 tidak dapat dikembalikan.",
                'itinerary' => [
                    [1, 'Penjemputan & Perjalanan Malam', 'Penjemputan di hotel sekitar pukul 00.00, perjalanan menuju Penanjakan menggunakan jeep 4x4.'],
                    [2, 'Sunrise Penanjakan', 'Menyaksikan matahari terbit di Penanjakan, dilanjutkan turun ke lautan pasir dan kawah Bromo.'],
                    [3, 'Kembali ke Kota', 'Sarapan pagi, mengunjungi Bukit Teletubbies dan Pasir Berbisik, lalu kembali ke hotel.'],
                ],
                'images' => [
                    'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?w=1200',
                    'https://images.unsplash.com/photo-1501785888041-af3ef285b470?w=1200',
                    'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=1200',
                ],
            ],
            'Ubud Culture & Nature 1 Hari' => [
                'meeting_point' => 'Bandara I Gusti Ngurah Rai, Denpasar',
                'meeting_latitude' => -8.7482,
                'meeting_longitude' => 115.1672,
                'inclusions' => [
                    'Transportasi AC dengan driver',
                    'Tiket masuk Monkey Forest',
                    'Tiket Tegallalang Rice Terrace',
                    'Makan siang setempat',
                    'Air mineral',
                ],
                'exclusions' => [
                    'Tiket pertunjukan tari opsional',
                    'Belanja suvenir',
                    'Pengeluaran pribadi',
                ],
                'terms' => "Jadwal dapat berubah menyesuaikan cuaca dan kondisi lalu lintas.\nBawa pakaian sopan untuk area pura.\nAnak di bawah 3 tahun gratis tanpa kursi terpisah.",
                'itinerary' => [
                    [1, 'Tegallalang Rice Terrace', 'Berjalan menyusuri terasering sawah dan berfoto di ayunan ikonik Ubud.'],
                    [2, 'Monkey Forest & Pusat Ubud', 'Berinteraksi dengan monyet ekor panjang di Monkey Forest, lalu menjelajah pasar seni Ubud.'],
                    [3, 'Pertunjukan Tari & Kembali', 'Menikmati pertunjukan tari Bali sebelum diantar kembali ke hotel.'],
                ],
                'images' => [
                    'https://images.unsplash.com/photo-1544644181-1484b3fdfc62?w=1200',
                    'https://images.unsplash.com/photo-1537996194471-e657df975ab4?w=1200',
                ],
            ],
            'Open Trip Komodo 3 Hari 2 Malam' => [
                'meeting_point' => 'Pelabuhan Labuan Bajo, Flores',
                'meeting_latitude' => -8.4964,
                'meeting_longitude' => 119.8877,
                'inclusions' => [
                    'Kapal liveaboard 3 hari 2 malam',
                    'Semua makan selama trip',
                    'Guide dan ranger Komodo',
                    'Peralatan snorkeling',
                    'Tiket masuk Taman Nasional Komodo',
                ],
                'exclusions' => [
                    'Penerbangan ke Labuan Bajo',
                    'Asuransi perjalanan',
                    'Sewa kamera underwater',
                    'Tip guide dan kru kapal',
                ],
                'terms' => "Peserta wajib mengikuti arahan ranger saat mendekati komodo.\nDilarang membawa plastik sekali pakai ke area taman nasional.\nJadwal pelayaran bergantung pada kondisi cuaca dan izin otoritas pelabuhan.",
                'itinerary' => [
                    [1, 'Labuan Bajo – Kelor – Rinca', 'Penjemputan di pelabuhan, menuju Pulau Kelor untuk snorkeling, lalu trekking di Pulau Rinca.'],
                    [2, 'Padar – Pink Beach – Komodo', 'Sunrise di Pulau Padar, snorkeling di Pink Beach, dan melihat komodo di Pulau Komodo.'],
                    [3, 'Manta Point – Kembali', 'Snorkeling bersama manta ray di Manta Point sebelum kembali ke Labuan Bajo.'],
                ],
                'images' => [
                    'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=1200',
                    'https://images.unsplash.com/photo-1516690561799-46d8f74f9abf?w=1200',
                    'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=1200',
                ],
            ],
        ];

        foreach ($content as $title => $data) {
            $tourPackage = TourPackage::where('title', $title)->first();

            if ($tourPackage === null) {
                continue;
            }

            $tourPackage->update([
                'inclusions' => $data['inclusions'],
                'exclusions' => $data['exclusions'],
                'meeting_point' => $data['meeting_point'],
                'meeting_latitude' => $data['meeting_latitude'],
                'meeting_longitude' => $data['meeting_longitude'],
                'terms' => $data['terms'],
            ]);

            foreach ($data['itinerary'] as $index => [$dayNumber, $dayTitle, $description]) {
                $tourPackage->itineraryDays()->updateOrCreate(
                    ['day_number' => $dayNumber],
                    [
                        'title' => $dayTitle,
                        'description' => $description,
                        'sort_order' => $index,
                    ]
                );
            }

            foreach ($data['images'] as $index => $path) {
                $tourPackage->images()->firstOrCreate(
                    ['path' => $path],
                    ['sort_order' => $index]
                );
            }

            $destination = $tourPackage->destination;

            if ($destination instanceof Destination) {
                $destination->update([
                    'latitude' => $data['meeting_latitude'],
                    'longitude' => $data['meeting_longitude'],
                ]);

                $destination->images()->firstOrCreate(
                    ['path' => $data['images'][0]],
                    ['sort_order' => 0, 'caption' => $destination->name]
                );
            }
        }
    }
}

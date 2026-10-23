<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Destination;
use App\Models\Payment;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\ReviewService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Local presentation data only.
 *
 * Every demo review still goes through ReviewService, so it is attached to a
 * paid, confirmed booking whose ticket was marked used. This keeps the public
 * review invariant intact while giving a fresh installation enough examples
 * to demonstrate the review UI on every destination.
 *
 * This seeder is intentionally NOT called by DatabaseSeeder. Run it only on a
 * local/demo database:
 *
 *     php artisan db:seed --class=DemoReviewSeeder
 */
class DemoReviewSeeder extends Seeder
{
    /** @var array<int, array{name: string, email: string, firebase_uid: string}> */
    private array $reviewers = [
        [
            'name' => 'Dewi Anggraini',
            'email' => 'demo.dewi@travelo.test',
            'firebase_uid' => 'demo-reviewer-dewi',
        ],
        [
            'name' => 'Rizky Pratama',
            'email' => 'demo.rizky@travelo.test',
            'firebase_uid' => 'demo-reviewer-rizky',
        ],
        [
            'name' => 'Siti Maharani',
            'email' => 'demo.siti@travelo.test',
            'firebase_uid' => 'demo-reviewer-siti',
        ],
    ];

    /**
     * Three comments per destination, one for each demo reviewer.
     *
     * @var array<string, array<int, string>>
     */
    private array $comments = [
        'Bali' => [
            'Pantai dan sunset-nya luar biasa. Guide ramah dan itinerary berjalan tepat waktu.',
            'Hotelnya nyaman, tapi waktu snorkeling agak singkat. Secara keseluruhan seru.',
            'Liburan keluarga yang menyenangkan. Anak-anak suka sekali pantai dan makanannya.',
        ],
        'Yogyakarta' => [
            'Borobudur saat pagi benar-benar indah. Penjelasan guide tentang sejarah juga menarik.',
            'Makanan khasnya enak semua, terutama gudeg. Jadwal lumayan padat tapi seru.',
            'Kota yang ramah dan penuh budaya. Prambanan jadi favorit saya.',
        ],
        'Lombok' => [
            'Pantai-pantai di Lombok masih bersih dan tidak terlalu ramai. Pemandangannya juara.',
            'Trip island hopping rapi. Snorkeling-nya menyenangkan, walau perjalanan perahu cukup panjang.',
            'Gunung Rinjani terlihat megah dari jauh. Penginapan nyaman dan stafnya helpful.',
        ],
        'Raja Ampat' => [
            'Bawah lautnya benar-benar luar biasa. Banyak ikan dan terumbu karang yang masih sehat.',
            'Perjalanan ke Raja Ampat memang jauh, tapi semua terbayar saat sampai. Dive guide berpengalaman.',
            'Tempat paling indah yang pernah saya kunjungi. Airnya jernih banget.',
        ],
        'Labuan Bajo' => [
            'Sailing trip-nya seru dan sunset di Padar tidak terlupakan. Kru ramah.',
            'Melihat Komodo dari dekat benar-benar pengalaman unik. Kapalnya bersih dan nyaman.',
            'Pulau-pulaunya cantik, tapi cuaca sempat panas. Tetap worth it.',
        ],
        'Bandung' => [
            'Udara sejuk dan tempat-tempatnya instagramable. Cocok untuk liburan singkat.',
            'Makanan dan kafe di Bandung enak-enak. Trip keluarga kami menyenangkan.',
            'Pemandangan kebun tehnya indah. Guide ramah dan mobilnya nyaman.',
        ],
        'Komodo' => [
            'Pink Beach cantik sekali dan snorkeling-nya jelas. Komodo terlihat saat trekking.',
            'Trip laut yang rapi dan aman. Pemandu menjelaskan aturan keselamatan dengan jelas.',
            'Pengalaman seru melihat komodo langsung. Bawa air minum karena cuaca panas.',
        ],
        'Bromo' => [
            'Sunrise di Bromo luar biasa. Bangun pagi memang berat tapi tidak menyesal.',
            'Jeep-nya datang tepat waktu dan guide ramah. Udara dingin, jadi bawa jaket.',
            'Pemandangan kawah dan lautan pasirnya keren. Perjalanan dari Malang juga nyaman.',
        ],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn(
                'DemoReviewSeeder is local-only. Set APP_ENV=local on a demo database first.'
            );

            return;
        }

        $reviewService = app(ReviewService::class);
        $adminId = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->orderBy('id')
            ->value('id');

        $destinations = Destination::query()->orderBy('id')->get();
        $created = 0;

        foreach ($destinations as $destinationIndex => $destination) {
            $packages = TourPackage::query()
                ->where('destination_id', $destination->id)
                ->orderBy('id')
                ->get();

            if ($packages->isEmpty()) {
                $this->command?->warn("Skipping {$destination->name}: no tour package.");
                continue;
            }

            $texts = $this->comments[$destination->name] ?? $this->fallbackComments($destination->name);

            foreach ($this->reviewers as $reviewerIndex => $reviewerData) {
                $reviewer = User::query()->firstOrCreate(
                    ['email' => $reviewerData['email']],
                    [
                        'name' => $reviewerData['name'],
                        'password' => Hash::make(Str::random(40)),
                        'role' => User::ROLE_TRAVELLER,
                        'firebase_uid' => $reviewerData['firebase_uid'],
                    ]
                );

                if ($reviewer->email_verified_at === null) {
                    $reviewer->forceFill(['email_verified_at' => now()])->save();
                }

                /** @var TourPackage $package */
                $package = $packages[$reviewerIndex % $packages->count()];

                // Fixed dates make the seeder idempotent across reruns.
                $visit = Carbon::parse('2026-09-01 09:00:00')
                    ->addDays(($destinationIndex * 3) + $reviewerIndex)
                    ->setTime(9 + $reviewerIndex, 0);

                $booking = Booking::query()->updateOrCreate(
                    [
                        // A demo account intentionally has one booking per
                        // package. Do not use booking_date as a lookup key:
                        // SQLite stores date columns with a time component.
                        'user_id' => $reviewer->id,
                        'tour_package_id' => $package->id,
                    ],
                    [
                        'booking_date' => $visit->toDateString(),
                        'people_count' => 1,
                        'total_price' => $package->price,
                        'payment_status' => 'paid',
                        'booking_status' => 'confirmed',
                        'ticket_status' => Booking::TICKET_USED,
                        'ticket_used_at' => $visit,
                        'ticket_used_by' => $adminId,
                        'ticket_version' => 1,
                        'privacy_consent_at' => $visit,
                        'privacy_consent_version' => '1.0',
                    ]
                );

                Payment::query()->updateOrCreate(
                    ['booking_id' => $booking->id],
                    [
                        'midtrans_transaction_id' => 'demo-transaction-'.$booking->id,
                        'midtrans_order_id' => 'DEMO-'.$booking->id,
                        'payment_status' => Payment::STATUS_PAID,
                        'payment_method' => 'qris',
                        'payment_channel' => 'qris',
                        'transaction_status' => 'settlement',
                        'fraud_status' => 'accept',
                        'amount' => $booking->total_price,
                        'paid_at' => $visit->copy()->subDay()->setTime(10, 0),
                    ]
                );

                if ($booking->review()->exists()) {
                    continue;
                }

                $rating = [5, 4, 5][($reviewerIndex + $destinationIndex) % 3];
                $result = $reviewService->submit(
                    $reviewer,
                    $booking->fresh(),
                    $rating,
                    $texts[$reviewerIndex] ?? $texts[0]
                );

                if (! $result['ok']) {
                    $this->command?->warn(
                        "Review for {$destination->name} / {$reviewer->name} skipped: {$result['reason']}"
                    );

                    continue;
                }

                // Make the review date follow the visit instead of the seed run.
                $result['review']->forceFill([
                    'created_at' => $visit->copy()->addHours(3),
                ])->save();

                $created++;
            }
        }

        $this->command?->info("Demo reviews ready: {$created} new review(s).");
    }

    /**
     * @return array<int, string>
     */
    private function fallbackComments(string $destinationName): array
    {
        return [
            "Pengalaman berkunjung ke {$destinationName} sangat menyenangkan dan sesuai ekspektasi.",
            "Pemandangan di {$destinationName} bagus dan pelayanannya ramah.",
            "Trip ke {$destinationName} berjalan lancar. Akan kembali lagi suatu hari.",
        ];
    }
}

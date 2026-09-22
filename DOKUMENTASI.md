# Travelo — Dokumentasi Gabungan

Satu file pengganti semua `.md` yang sebelumnya tersebar di project.
File-file lama yang redundan / histori fix sudah dihapus.

Terakhir diringkas: 2026-09-22

---

## Daftar Isi
1. [Jalankan Backend + ngrok](#1-jalankan-backend--ngrok)
2. [Google Sign-In (Android + Firebase)](#2-google-sign-in-android--firebase)
3. [Midtrans Payment (Sandbox)](#3-midtrans-payment-sandbox)
4. [Endpoint Payment Penting](#4-endpoint-payment-penting)
5. [Troubleshooting](#5-troubleshooting)
6. [Logo & Brand](#6-logo--brand)

---

## 1. Jalankan Backend + ngrok

Setup authtoken cukup 1x per laptop:

```cmd
ngrok config add-authtoken PASTE_AUTHTOKEN_DISINI
ngrok config check
```

Ambil authtoken dari: https://dashboard.ngrok.com/get-started/your-authtoken

Jalankan tiap kali ngoding (2 terminal):

**Terminal 1 — Laravel:**
```cmd
cd "C:\Users\Administrator\Documents\UJI KOMPETENSI\Travelo-Backend"
php artisan serve
```

**Terminal 2 — ngrok:**
```cmd
ngrok http 8000
```

Copy URL https dari ngrok (contoh: `https://xxxx.ngrok-free.dev`) lalu pakai sebagai base URL di Android (`NetworkModule` / `Constants`) dan di `.env` backend:

```env
APP_URL=https://xxxx.ngrok-free.dev
FRONTEND_URL=https://xxxx.ngrok-free.dev
TRUSTED_PROXIES="*"
```

Setelah ganti URL: `php artisan config:clear && php artisan cache:clear`

Catatan:
- URL ngrok free berubah setiap restart. Update di 3 tempat: `.env` backend, base URL Android, webhook Midtrans dashboard.
- Cek traffic webhook di: http://127.0.0.1:4040 (ngrok inspector).
- Kalau port 8000 kepakai: `php artisan serve --port=8001` + `ngrok http 8001`.

---

## 2. Google Sign-In (Android + Firebase)

Config yang benar:
- Firebase project: `travelo-818bc`
- Android package: `com.naylaaisyahh.traveloapp`
- SHA-1 debug: `3C:FF:24:8C:FA:E5:E7:34:82:04:52:42:80:66:B0:07:82:AF:71:92`
- Backend endpoint: `POST /api/auth/login` dengan body `{"id_token": "<firebase_id_token>"}`

Checklist kalau login gagal:
1. Google Cloud Console → APIs & Services → Credentials → pastikan ada Android OAuth Client dengan package + SHA-1 di atas. Kalau belum ada, buat baru.
2. Firebase Console → Authentication → Sign-in method → Google harus ENABLED.
3. Firebase Console → Project Settings → Android app → SHA fingerprint harus terdaftar. Kalau habis ganti laptop, tambahkan lagi lalu download ulang `google-services.json` ke `Travelo-Android/app/`.
4. Pastikan ngrok masih jalan dan HP bisa akses URL-nya (test dengan curl).
5. Tambahkan header `ngrok-skip-browser-warning: true` di OkHttp (sudah ada di `NetworkModule.kt`).
6. Error `com.transsion.*` abaikan saja — itu bawaan HP Transsion, bukan error login.
7. Rebuild: `cd Travelo-Android && ./gradlew clean assembleDebug`

Test cepat backend:
```bash
curl -X POST https://xxxx.ngrok-free.dev/api/auth/login \
  -H "Content-Type: application/json" \
  -H "ngrok-skip-browser-warning: true" \
  -d '{"id_token":"test-token"}'
```
Hasil wajar untuk token palsu: `{"success":false,"message":"Invalid token format"}` — artinya koneksi OK.

---

## 3. Midtrans Payment (Sandbox)

`.env` backend:
```env
MIDTRANS_SERVER_KEY=isi-dari-dashboard-sandbox
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_MERCHANT_ID=isi-dari-dashboard
```

Jangan copy-paste server key dari dokumentasi lama — ambil dari https://dashboard.midtrans.com → Settings → Access Keys (mode Sandbox).

Alur:
```
Android POST /api/bookings → backend bikin booking + snap_token
Android buka Snap (WebView) dengan snap_token → user bayar
Midtrans kirim webhook → POST /api/midtrans/callback → DB jadi paid/confirmed
Android WAJIB panggil verify-and-sync setelah Snap ditutup (jangan hanya andalkan webhook)
```

Webhook di dashboard Midtrans → Settings → Configuration:
```
Payment Notification URL: https://xxxx.ngrok-free.dev/api/midtrans/callback
```

Test kartu sandbox: `4811 1111 1111 1114`, exp `12/2030`, CVV `123`. Atau pakai menu Simulation di dashboard Midtrans.

Aturan penting di Android: **setelah Snap ditutup, langsung panggil `verify-and-sync`**. Kalau masih pending, polling `payment-status` tiap 5 detik (±2 menit), fallback ke `retry-sync`.

---

## 4. Endpoint Payment Penting

Semua butuh header `Authorization: Bearer <token>`.

| Endpoint | Fungsi | Kapan dipanggil |
|---|---|---|
| `GET /api/bookings/{id}/payment-status` | Cek status di DB | Buka detail booking / polling |
| `POST /api/bookings/{id}/payment/verify-and-sync` | Tanya Midtrans langsung + update DB | **Wajib setelah bayar** |
| `POST /api/bookings/{id}/payment/retry-sync` | Coba sync 3x | Kalau verify macet |
| `POST /api/bookings/{id}/payment/mark-as-paid` | Override manual | Darurat saja |
| `POST /api/midtrans/callback` | Webhook Midtrans | Otomatis, jangan dipanggil manual |
| `POST /api/payments/create-snap-token` | Bikin ulang snap token | Kalau token expired |

Contoh:
```bash
curl -X GET "http://localhost:8000/api/bookings/1/payment-status" \
  -H "Authorization: Bearer TOKEN"

curl -X POST "http://localhost:8000/api/bookings/1/payment/verify-and-sync" \
  -H "Authorization: Bearer TOKEN"
```

Status: `pending` → nunggu bayar, `paid` → booking `confirmed`, `failed`/`expired` → booking `cancelled`.

DB: tabel `payments` punya kolom `midtrans_order_id` + `paid_at`. Migrasi sudah di-apply (`php artisan migrate`).

---

## 5. Troubleshooting

| Gejala | Penyebab umum | Solusi |
|---|---|---|
| Payment stuck `pending` padahal sudah bayar | App tidak panggil verify, webhook tidak masuk ngrok | Panggil `verify-and-sync`, cek ngrok inspector, pastikan webhook URL di Midtrans = URL ngrok aktif |
| `500` di `/api/destinations/popular` | Dulu kolom `is_popular` tidak ada di DB | Sudah difix: pakai `latest()->take(6)`. Kalau mau fitur popular, bikin migrasi `is_popular` |
| API balas HTML bukan JSON | Error Laravel / route cache | Cek `storage/logs/laravel.log`, `php artisan optimize:clear` |
| `401 Unauthorized` | Token salah format | Harus `Bearer <token>` |
| `404 create-snap-token` | Route cache basi | `php artisan route:clear` |
| ngrok `authentication failed` | Authtoken belum diset | `ngrok config add-authtoken ...` |
| ngrok `not recognized` | Belum install / PATH | Pakai full path `C:\ngrok\ngrok.exe http 8000` atau `npx ngrok http 8000` |
| Build Android KAPT error | Cache Gradle rusak | Android Studio → Invalidate Caches, atau hapus `.gradle` lalu rebuild |

Debug cepat:
```bash
cd Travelo-Backend
type storage\logs\laravel.log
php artisan tinker
>>> Booking::with('payment')->latest()->take(5)->get()
```

---

## 6. Logo & Brand

File ada di `Travelo-Backend/public/images/logos/`:
- `travelo-logo-primary.svg` — logo utama (Navigator/kompas, direkomendasikan)
- `travelo-logo-journey.svg`, `travelo-logo-discover.svg` — alternatif
- `travelo-logo-mono.svg` — untuk background gelap
- `travelo-favicon.svg` — favicon

Warna: biru utama `#2563eb`, biru tua `#1d4ed8`, cyan `#06b6d4`, aksen orange `#f59e0b`. Font: Inter. Detail lengkap sebelumnya ada di `BRAND_GUIDELINES.md` (sudah dihapus, pakai ringkasan ini saja).

```html
<img src="/images/logos/travelo-logo-primary.svg" alt="Travelo" width="150" height="40">
<link rel="icon" href="/images/logos/travelo-favicon.svg">
```

---

## File yang dihapus

Dihapus karena isinya sudah diringkas di atas / hanya histori sementara:
`GOOGLE_SIGNIN_FIX.md`, `GOOGLE_SIGNIN_QUICK_REFERENCE.md`, `IMPLEMENTATION_STATUS.md`, `NGROK_AUTHTOKEN_CEK.md`, `NGROK_CMD_QUICK.md`, `NGROK_SETUP_GUIDE.md`, `PAYMENT_PENDING_FIX.md`, `.aider.chat.history.md`, `Travelo-Backend/MIDTRANS_SETUP.md`, `FIXES_APPLIED.md`, `FINAL_FIX.md`, `BOOKING_PAYMENT_ANALYSIS.md`, `ANDROID_PAYMENT_INTEGRATION.md`, `README_PAYMENT_FIX.md`, `PAYMENT_STATUS_FIX.md`, `PAYMENT_FIX_COMPLETE.md`, `BRAND_GUIDELINES.md`, `LOGO_QUICK_REFERENCE.md`, `Travelo-Backend/README.md` (boilerplate Laravel).

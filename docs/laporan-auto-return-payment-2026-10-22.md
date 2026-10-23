# Laporan auto-return pembayaran Midtrans — 22 Oktober 2026

## Tujuan

Setelah pembayaran selesai di halaman Midtrans Snap, aplikasi tidak lagi
menunggu user menekan tombol **Close**. Begitu backend memastikan pembayaran
sudah `paid/confirmed`, halaman Midtrans menutup sendiri dan aplikasi masuk ke
layar **Confirming your payment** sebelum menampilkan hasil akhir.

## Perilaku baru

1. User menekan **Pay** dan halaman Snap terbuka di `PaymentWebViewActivity`.
2. Selama halaman terbuka, aplikasi memanggil
   `GET /api/bookings/{id}/payment-status` setiap 5 detik.
3. Jika backend mengembalikan status pembayaran sukses/confirmed, WebView:
   - mengeset hasil `RESULT_PAYMENT_SUCCESS`,
   - menutup dirinya sendiri.
4. `PaymentScreen` langsung menampilkan overlay **Confirming your payment**,
   lalu menjalankan `verify-and-sync` dan bounded status polling seperti
   sebelumnya.
5. Hasil akhir tetap ditentukan backend: sukses, menunggu, gagal, atau expired.

Auto-return **tidak** dipicu oleh URL Snap, teks halaman, atau status lokal.
Hanya respons backend terautentikasi yang bisa memicu penutupan otomatis. Ini
mencegah halaman pembayaran tertutup sebelum pembayaran benar-benar selesai.

## File yang diubah

| File | Perubahan |
|---|---|
| `ui/screens/payment/PaymentWebViewActivity.kt` | Polling status backend tiap 5 detik, auto-close hanya saat `PaymentOutcome.SUCCESS`, Hilt injection, pembatalan polling saat activity hancur |
| `ui/screens/payment/PaymentScreen.kt` | Mengirim `booking_id` ke WebView agar polling status memiliki konteks booking |
| `app/src/test/.../PaymentOutcomeTest.kt` | 5 unit test untuk mapping status backend yang memicu auto-return |

## Verifikasi

| Pemeriksaan | Hasil |
|---|---|
| `:app:testDebugUnitTest` | **45 tests, 0 failures** |
| `:app:compileDebugKotlin` | **BUILD SUCCESSFUL** |
| `:app:lintDebug` | **BUILD SUCCESSFUL** (0 error) |
| `:app:assembleDebug` | **BUILD SUCCESSFUL** |
| Backend full suite | **97 passed, 546 assertions** (tidak berubah oleh pekerjaan Android ini) |

APK terbaru:
`Travelo-Android/app/build/outputs/apk/debug/app-debug.apk`

## Keterbatasan

- Auto-return bergantung pada backend yang sudah menerima webhook atau
  mempunyai status `paid`. Jika backend masih `pending`, halaman Snap tetap
  terbuka dan user masih bisa menutupnya manual; setelah ditutup, app tetap
  melakukan rekonsiliasi.
- Pembayaran Midtrans nyata dan tampilan WebView belum diuji ulang di
  emulator/perangkat pada pass ini karena membutuhkan akun serta transaksi
  Midtrans. Verifikasi terbatas pada unit test, kompilasi, lint, dan build APK.
- Belum ada commit atau push; perubahan tetap di working tree.

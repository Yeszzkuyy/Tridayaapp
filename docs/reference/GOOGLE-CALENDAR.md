# Google Calendar — Menu Teknisi

Fitur **Kalender Teknisi** terintegrasi dengan Google Calendar via Google Calendar API + OAuth 2.0.

Alur sinkronisasi saat ini: **Web App → Google Calendar** (event yang dibuat/diubah/dihapus di aplikasi ikut berubah di Google). Struktur kode (`GoogleCalendarService`) sudah disiapkan agar mudah diperluas menjadi dua arah.

## 1. Setup Google Cloud

1. Buka [Google Cloud Console](https://console.cloud.google.com/) → **New Project** (contoh: `dbteknisi`).
2. **Enable API**: menu *APIs & Services → Library* → cari **Google Calendar API** → **Enable**.
3. **OAuth consent screen**: *APIs & Services → OAuth consent screen* → pilih **External**:
   - App name: `dbteknisi`
   - User support email: email Anda
   - *Scopes*: tambahkan `https://www.googleapis.com/auth/calendar.events` (membaca & mengelola event kalender)
   - *Test users*: tambahkan email akun Google yang akan dipakai login (selama status app masih *Testing*)
4. **OAuth Client**: *APIs & Services → Credentials → Create Credentials → OAuth client ID*:
   - Application type: **Web application**
   - **Authorized redirect URIs**: `https://<domain-aplikasi>/teknisi/kalender/callback`
     - Lokal: `http://localhost:8000/teknisi/kalender/callback`
   - Simpan **Client ID** dan **Client Secret**.

> **Keamanan:** Client Secret hanya ada di server (`.env`). Tidak pernah dikirim ke frontend / masuk source code.

## 2. Isi .env

```env
GOOGLE_CLIENT_ID=xxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxx
GOOGLE_REDIRECT_URI=http://localhost:8000/teknisi/kalender/callback
```

Lalu `php artisan config:clear`.

## 3. Menjalankan aplikasi

```bash
composer install
php artisan migrate
npm install
npm run build          # atau npm run dev
php artisan serve
```

## 4. Menghubungkan Google Calendar

1. Login sebagai user dengan permission `manage-teknisi`.
2. Buka menu **Teknisi → Kalender**.
3. Klik **Hubungkan Google Calendar** → login akun Google → izinkan *calendar.events* → otomatis kembali ke aplikasi.
4. Status berubah menjadi **"Google Calendar Terhubung ✓"**.

Token disimpan di tabel `google_credentials` (access token & refresh token **ter-enkripsi** dengan APP_KEY, bukan plaintext). Refresh token otomatis dilakukan oleh library saat akses token kedaluwarsa.

## 5. Mengetes Create / Update / Delete event

Pastikan akun Google yang terhubung — buka [Google Calendar](https://calendar.google.com) di browser yang sama.

| Aksi | Di aplikasi | Di Google Calendar |
|---|---|---|
| **Create** | Teknisi → Kalender → `+ Tambah Jadwal` → isi form → Simpan | Muncul event baru `[TEKNISI] <judul>` (warna sesuai status, ada reminder email 24 jam + popup) |
| **Update** | Klik event di kalender (atau baris di **Jadwal**) → Edit → ubah jam → Simpan | Event yang sama berubah (tidak dibuat duplikat) |
| **Delete** | Edit jadwal → Hapus → konfirmasi | Event di Google ikut terhapus |

Kolom `google_event_id` di tabel `technician_schedules` menjadi penghubung event aplikasi ↔ Google. Jika event Google sudah dihapus manual di Google, aplikasi otomatis membuatnya ulang saat jadwal diedit (404 → recreate).

### Status sinkronisasi

| Status | Arti |
|---|---|
| ✓ Synced | Event tersinkron dengan Google |
| ⟳ Syncing | Sedang diproses (buat/update) |
| ⚠ Sync Error | Gagal sinkron — jadwal tetap aman di database, pesan error ditampilkan |
| — Not Connected | Akun Google belum dihubungkan |

## 6. Error handling

- Google belum terhubung → jadwal tetap disimpan lokal, status `Not Connected`.
- Token expired → auto-refresh (refresh token di database).
- Refresh gagal / izin dicabut → status `Sync Error`, pesan "hubungkan kembali", tombol **Putuskan Koneksi** tersedia.
- Event Google tidak ditemukan (404) → dibuat ulang otomatis.
- Gagal buat/update/hapus di Google → **tidak pernah menghapus data lokal**, error disimpan di `google_sync_error`.

## 7. Menuju sinkronisasi dua arah (roadmap)

- `GoogleCalendarService` sudah punya `clientFor()` + mekanisme token yang bisa dipakai untuk endpoint `events.list`.
- Tinggal menambah method `pullEvents()` yang mengambil event Google (query `syncToken`/`updatedMin`) lalu mencocokkan dengan `google_event_id` di database, dan job queue untuk polling berkala.

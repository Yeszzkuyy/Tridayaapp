# PLAN-SISTEM-NOTIFIKASI-REALTIME: Notifikasi Harus Langsung Hilang Setelah Dibaca / Dihapus

**Status Pengerjaan:** Siap Dikerjakan
**Menu:** Sistem / Notification Center
**Branch:** `feature/sys-notif-popup`
**Worktree:** `/home/raafi/3dyapp-notifpop` (branch ini sudah 5 commit di depan `main`, basis paling baru untuk pekerjaan notifikasi)
**Terakhir diperbarui:** 2026-11-09
**Baseline kode:** `main` @ `d30b32d2`

> Catatan: `feature/sys-notif-center` sudah 0 commit di depan `main` (kerja sebelumnya sudah masuk).
> Cache view/cache file wajib dirapikan setelah menjalankan test (AGENTS.md §5).

---

## 1. Tujuan & hasil akhir

Setelah selesai:

- Notifikasi yang sudah dibaca langsung berubah status di semua tab/perangkat tanpa perlu restart atau reload.
- Notifikasi yang dihapus (satu atau semua) langsung hilang dan **tidak muncul lagi** saat panel dibuka ulang.
- Badge angka notifikasi selalu sama dengan isi database.

## 2. Ruang lingkup

Termasuk:

- Sinkronisasi status baca/hapus antar tab.
- Perbaikan mode `merge` pada daftar notifikasi.
- Rollback UI saat request gagal.
- Perbaikan antrean notifikasi (`jobs`) yang saat ini error masif.

Tidak termasuk:

- Desain ulang tampilan bell/panel (sudah selesai di branch popup).
- Notifikasi jadwal teknisi ke marketing (dibahas di `PLAN-TEKNISI-NOTIF-JADWAL.md`).

## 3. Temuan kondisi sekarang (akar masalah)

1. **Read/delete tidak pernah dikirim ke tab lain.**
   `app/Events/` hanya berisi `NotificationCreated`. Tidak ada event `NotificationRead` / `NotificationDeleted`.
   Akibatnya tab/perangkat lain tidak tahu ada perubahan.

2. **Polling dimatikan saat socket nyambung.**
   `resources/js/components/NotificationCenter.jsx:519` -> `if (... || connected) return;`.
   Selama Reverb tersambung, tidak ada penyegaran cadangan. Kalau satu event gagal, bell beku sampai reload.

3. **Mode `merge` tidak membuang item yang sudah hilang di server.**
   `resources/js/components/NotificationCenter.jsx:387-403`: item lama yang tidak ada di respons server tetap dipertahankan.
   Panel dibuka kembali memakai `merge: false` (baris 540-546), jadi kalau delete gagal, item lama muncul lagi.

4. **Aksi optimistis tanpa rollback.**
   `fetchJson` mengembalikan `null` untuk semua respons non-OK (`NotificationCenter.jsx:351-377`), termasuk 419/403/500.
   `markRead`, `dismiss`, `markAllRead`, `clearAll` sudah mengubah tampilan lebih dulu dan tidak mengembalikan state saat gagal (baris 602-666).
   `NotificationController@destroyAll` (baris 67-72) selalu membalas `unread: 0` tanpa memeriksa jumlah baris yang benar-benar terhapus.

5. **Notifikasi antre bisa masuk lagi setelah dibersihkan.**
   `QUEUE_CONNECTION=database`. Beberapa notifikasi `implements ShouldQueue`.
   Log produksi 2026-11-09 mencatat **21.232 error** `SQLSTATE[42501] permission denied for table jobs`.
   Artinya antrean tidak sehat: notifikasi bisa tertunda lalu muncul belakangan, dan dispatch bisa melempar 500.

6. **Sisa file ganda di working tree `main`.**
   Ada `resources/js/NotificationCenter.jsx`, `NotificationToast.jsx`, `ToastViewport.jsx` di root `resources/js/` yang tidak dipakai (salinan), sementara versi aktif ada di `resources/js/components/`.
   Ini rawan: build dari source yang salah membuat perilaku bell berbeda.

## 4. Langkah pengerjaan

### 4.1 Backend

1. Tambah event `App\Events\NotificationRead` dan `App\Events\NotificationDeleted`
   (`ShouldBroadcastNow`, `ShouldRescue`), broadcast ke `private-notifications.{userId}`.
   Payload: `{ ids: string[], unread: int, scope: 'single'|'all' }`.
2. Panggil event dari `NotificationController@read`, `@readAll`, `@destroy`, `@destroyAll`.
3. `destroyAll` mengembalikan `{ unread: 0, deleted: <jumlah> }` (hasil `delete()`), bukan angka tetap.
4. Pastikan response error tetap JSON (401/419) agar frontend bisa membedakan gagal vs sukses.

### 4.2 Frontend

5. `NotificationCenter.jsx`:
   - Dengarkan `.notification.read` dan `.notification.deleted` di channel yang sama dengan `.notification.created`.
   - `applyStatus({ merge })`: saat `merge` true, buang item yang tidak ada di respons server (server = sumber kebenaran), tetapi pertahankan posisi scroll.
   - Setelah `markRead` / `dismiss` / `markAllRead` / `clearAll` sukses, panggil `refreshStatus({ merge: false })`.
   - Saat request gagal: kembalikan state sebelumnya + tampilkan toast error (jangan diam-diam).
6. Polling: tetap jalankan interval 60 detik sebagai jaring pengaman meski `connected === true`
   (atau minimal saat `document.visibilitychange` kembali ke tab). Cukup satu request ringan ke `/notifications/status`.

### 4.3 Operasional (wajib, bukan opsional)

7. Perbaiki hak akses tabel `jobs`, `failed_jobs`, `job_batches` untuk user database aplikasi
   (`GRANT SELECT, INSERT, UPDATE, DELETE`). Ini penyebab 21.232 error hari ini.
8. Jalankan `php artisan queue:restart` setelah grant, lalu pastikan worker hidup.
9. Bersihkan file JSX duplikat di root `resources/js/` (koordinasi dulu karena working tree `main` sedang kotor).

### 4.4 Test

10. `tests/Feature/NotificationCenterTest.php` tambah:
    - broadcast `read` / `delete` terkirim,
    - `destroyAll` mengembalikan jumlah terhapus,
    - status setelah delete-all kosong.
11. Test JS (bila ada) untuk perilaku `merge` dan rollback.

## 5. Kriteria selesai

- Buka dua tab akun yang sama:
  - baca 1 notifikasi di tab A -> tab B ikut berubah tanpa reload,
  - hapus semua di tab A -> tab B kosong,
  - tutup dan buka panel -> item tetap kosong.
- DevTools: `POST /notifications/{id}/read` dan `DELETE /notifications` merespons 200.
- Database: `notifications` untuk user tersebut benar-benar kosong.
- `php artisan test --filter=Notification` lulus.

## 6. Dependensi

- Tidak ada dependensi ke divisi lain.
- Butuh akses DBA/root hanya untuk grant tabel `jobs`.

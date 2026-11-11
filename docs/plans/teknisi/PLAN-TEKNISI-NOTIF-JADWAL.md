# PLAN-TEKNISI-NOTIF-JADWAL: Marketing Tidak Menerima Notifikasi Perubahan Jadwal Teknisi

**Status Pengerjaan:** Siap Dikerjakan
**Menu:** Teknisi / Jadwal
**Branch:** `feature/div-teknisi`
**Worktree:** `/var/www/3dyapp-tek`
**Terakhir diperbarui:** 2026-11-09
**Baseline kode:** `main` @ `d30b32d2`

> Worktree `feature/div-teknisi` saat ini 419 commit di belakang `main` dan 5 commit di depan.
> Sebelum mulai: `git pull origin feature/div-teknisi`, `git merge main`, lalu `php artisan migrate`.

---

## 1. Tujuan & hasil akhir

Setelah selesai:

- Anggie (role `marketing`) dan Syifa (role `lead-marketing`) **tidak lagi** menerima notifikasi
  saat teknisi membuat, mengubah, atau menghapus jadwal.
- Teknisi yang benar-benar ditugaskan tetap menerima notifikasi seperti sekarang.
- Tidak ada user non-teknisi yang bisa "nyasar" menjadi penerima jadwal.

## 2. Ruang lingkup

Termasuk:

- Filter penerima notifikasi jadwal teknisi.
- Pembersihan data penugasan yang salah.
- Validasi saat memilih teknisi di form jadwal.
- Penghormatan preferensi notifikasi user.

Tidak termasuk:

- Perubahan tampilan kalender/jadwal.
- Notifikasi jadwal Sales (`SalesScheduleController`) - sudah benar hanya ke assignee.

## 3. Temuan kondisi sekarang

Penerima notifikasi jadwal teknisi ditentukan di
`app/Services/GoogleCalendarService.php`:

- `createSchedule()` (721-745): semua user di pivot `technician_schedule_user`.
- `updateSchedule()` (751-803): teknisi baru, teknisi yang dilepas, semua teknisi saat ini, dan **owner jadwal** (`user_id`).
- `deleteSchedule()` (805-817): semua teknisi + owner.
- Guard pusat `notifyTechnician()` (831-848) hanya melewati pelaku aksi dan duplikat.
  **Tidak ada filter role sama sekali.**

Artinya Anggie/Syifa hanya bisa menerima notifikasi kalau:

- mereka dimasukkan ke pivot `technician_schedule_user`, atau
- mereka menjadi `user_id` (owner) jadwal, atau
- mereka tersimpan di kolom lama `technician_id`.

Ketiganya adalah **data penugasan yang salah**, bukan pengiriman berbasis role.
Role marketing memang tidak pernah dikirimi notifikasi jadwal oleh kode.

## 4. Langkah pengerjaan

### 4.1 Diagnosa data (read-only, sebelum ubah kode)

1. Cek jadwal yang melibatkan akun Anggie dan Syifa:
   - `technician_schedule_user` (pivot),
   - `technician_schedules.user_id` (owner),
   - `technician_schedules.technician_id` (kolom lama).
2. Catat siapa yang menambahkan mereka dan pada jadwal mana.
3. Simpan hasilnya sebagai lampiran laporan (jangan ubah data dulu).

### 4.2 Aturan penerima

4. Tambah satu method terpusat di `GoogleCalendarService`, misalnya
   `canReceiveScheduleNotification(User $user): bool`, dengan aturan:
   - user punya role `technician`, `lead-technician`, atau `prakerin-technician`, **atau**
   - user adalah owner jadwal **dan** punya salah satu role teknisi di atas.
5. Panggil method itu di `notifyTechnician()` sebelum mengirim.
   Dengan begitu create/update/delete otomatis ikut aturan yang sama.
6. Hormati preferensi `notify_system`:
   `TechnicianScheduleChangedNotification::via()` saat ini selalu memaksa channel `database`.
   Ubah agar mengikuti preferensi user (pola sama seperti `LeadTaskNotification`).
   `Assigned` dan `Changed` tetap mengirim database secara default.

### 4.3 Cegah data salah lagi

7. Di form assign teknisi (`TechnicianScheduleController` + view), validasi bahwa user yang dipilih
   hanya yang punya role teknisi.
8. Kalau owner jadwal bukan teknisi (mis. marketing membuat jadwal), owner tidak lagi menerima notifikasi
   perubahan. Cukup teknisi pelaksana.
9. Bersihkan data pivot yang salah berdasarkan hasil diagnosa 4.1 (dengan persetujuan User).

## 5. Kriteria selesai

- Ubah jadwal teknisi -> bell Anggie dan Syifa tidak bertambah.
- Teknisi yang ditugaskan tetap menerima notifikasi "jadwal diubah".
- Teknisi yang dilepas dari jadwal tetap menerima notifikasi "dilepas".
- Mencoba memilih user marketing di form jadwal ditolak validasi.
- `php artisan test --filter="TechnicianSchedule"` lulus, plus test baru:
  "user marketing di pivot tidak menerima notifikasi".

## 6. Dependensi

- Tidak ada. Branch ini hanya menyentuh modul teknisi + service jadwal.
- Kalau `feature/notif-teknisi` (7 commit, belum di-merge) dikerjakan lebih dulu,
  lakukan `git merge main` setelah merge agar tidak konflik di `GoogleCalendarService`.

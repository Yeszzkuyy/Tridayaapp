Rencana Pembaruan Modul Sales (Integrasi DAR & Timeline Follow Up)

## 1. Ringkasan & Tujuan Pembaruan
Pembaruan ini bertujuan untuk menyederhanakan *User Experience* (UX) tim Sales dengan meniru kebiasaan pencatatan manual mereka pada *Daily Activity Report* (DAR).
Fokus utama:
- **Menghapus Menu Terpisah:** Menghapus menu "Follow Up" dan "Meeting" yang berdiri sendiri.
- **Activity Timeline:** Semua pencatatan interaksi digabung menjadi satu di halaman Detail *Lead* sebagai "Log Follow Up" (mengadaptasi fitur Activity Timeline yang sudah ada di modul Proyek).
- **Auto-Generate DAR:** Menambahkan fitur Export DAR (PDF/Excel) yang secara otomatis menyusun tabel laporan berdasarkan log aktivitas harian.

---

## 2. Pembaruan Skema Database (Migrations)

### A. Modifikasi Tabel `leads`
Untuk menyesuaikan dengan format DAR, pastikan tabel `leads` (atau `customers`) memiliki/ditambahkan kolom berikut:
- `category` (string, nullable) -> Contoh: 'Reseller', 'End User'.
- `needs` / `kebutuhan` (text, nullable) -> Contoh: 'SK RTT blue e WALL MOUNT COOLER 500W'.
- `address` (text, nullable) -> Alamat klien.

### B. Pembuatan Tabel `lead_activities` (Log Follow Up)
Buat *migration* baru untuk tabel riwayat aktivitas (pengganti tabel `follow_ups` atau `meetings` lama):
- `id` (PK)
- `lead_id` (FK ke tabel leads, cascade on delete)
- `user_id` (FK ke users - Sales yang melakukan follow up)
- `activity_type` (enum/string) -> Opsi: 'WhatsApp/Chat', 'Telepon', 'Email', 'Kunjungan/Meeting', 'Kirim Penawaran'.
- `activity_date` (datetime) -> Kapan follow up / meeting dilakukan.
- `description` / `detail` (text) -> Catatan riwayat (Contoh: "Revisi penawaran minta diskon dan minta dokumen...").
- `timestamps` & `softDeletes`

---

## 3. Pembaruan Model Eloquent (`app/Models/`)

### A. Model `Lead`
- Tambahkan `category`, `needs`, dan `address` ke dalam array `$fillable`.
- Tambahkan relasi `hasMany`:
  ```php
  public function activities() {
      return $this->hasMany(LeadActivity::class)->orderBy('activity_date', 'desc');
  }
B. Model LeadActivityBuat model baru dengan array $fillable berisi lead_id, user_id, activity_type, activity_date, description.Relasi belongsTo ke Lead dan User.4. Pembaruan Antarmuka (View / UI)A. Halaman Detail Lead (resources/views/leads/show.blade.php)Hapus referensi ke halaman Follow Up eksternal.Form Tambah Log: Buat form ringkas di halaman ini untuk menambah LeadActivity.Input: Dropdown activity_type, Input Date activity_date, Textarea description.Logic Khusus: Jika activity_type yang dipilih adalah "Kunjungan/Meeting", munculkan form tambahan untuk Waktu/Lokasi (opsional).Tabel/List Activity Timeline: Tampilkan riwayat activities milik Lead tersebut secara menurun (kronologis), mirip dengan project_activities yang sudah ada.B. Pembersihan Menu (Navigation)Hapus menu "Follow Up" dan "Meeting" dari resources/views/layouts/navigation.blade.php (sidebar/navbar)[cite: 2]. Cukup sisakan "My Leads" atau "Pipeline".5. Fitur Baru: Auto-Generate DAR (Daily Activity Report)A. Controller ReportController atau LeadControllerBuat method baru exportDAR(Request $request) yang bertugas menarik data untuk hari ini (atau rentang tanggal yang di-filter) dari lead_activities dan me-load relasi Lead.B. Format Kolom Export (Mendekati DAR Manual)Saat diexport (PDF/Excel), sistem harus menyusun kolom secara berurutan sesuai pedoman DAR Sales:   TANGGAL (lead_activities.activity_date)   CUSTOMER (leads.company_name)   CATEGORY (leads.category)   ADDRESS (leads.address)   PIC (leads.pic_name)[cite: 1]PHONE (leads.phone)[cite: 1]EMAIL (leads.email)[cite: 1]STATUS (leads.status -> misal: Prospect, Won, Lost)[cite: 1]KEBUTUHAN (leads.needs)[cite: 1]DETAIL (lead_activities.description)[cite: 1]6. Urutan Eksekusi untuk AI AssistantGenerate file migration untuk lead_activities dan penambahan kolom pada leads.Update Model Lead.php dan buat Model LeadActivity.php.Buat/Update controller method untuk menyimpan form "Log Follow Up".Edit Blade view leads/show.blade.php untuk menampilkan UI Timeline dan Form Input.Buat fitur Export DAR (menggunakan library Excel/PDF seperti Maatwebsite/Laravel-Excel atau Barryvdh/Laravel-DomPDF).
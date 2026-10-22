# Product Requirements Document (PRD)
## Project Workflow & Document Segregation (Sales & Technician Integration)

---

## 1. Executive Summary & Problem Statement

### 1.1 Latar Belakang
Saat ini, terdapat ambiguitas operasional mengenai siapa yang bertanggung jawab membuat data **Project** di dalam sistem (Sales atau Teknisi) serta bagaimana menangani order pembelian (PO) dari Customer yang **tidak membutuhkan instalasi/dukungan teknisi** (contoh: transaksi murni jual putus / retail pengadaan barang). Selain itu, terdapat ketidakjelasan mengenai penempatan berkas teknis (hasil survey, sizing produk, dokumentasi instalasi) vs berkas administratif customer.

### 1.2 Masalah Utama
1. **Redundansi & Kebingungan Input:** Sales memiliki akses form pembuatan project manual yang berisiko menciptakan duplikasi atau data tidak lengkap tanpa koordinasi teknis.
2. **Ketiadaan Filter Kebutuhan Proyek:** Tidak semua PO customer butuh pengerjaan lapangan; memaksakan semua transaksi menjadi project membebani dashboard teknisi dengan order jual-putus.
3. **Penyimpanan Dokumen Tidak Terstruktur:** Berkas teknis bercampur aduk dengan berkas legal/administratif di modul Customer, menyulitkan audit riwayat instalasi proyek.

---

## 2. Tujuan & Sasaran (Objectives)

1. **Memisahkan Peran & Tanggung Jawab (Clear Ownership):**
   * **Sales:** Penanggung jawab transaksi komersial & inisiasi handover ke proyek jika dibutuhkan.
   * **Teknisi:** Penanggung jawab eksekusi teknis, penjadwalan, progress pengerjaan lapangan, dan pelaporan BAST.
2. **Handover Terstruktur (Convert to Project):**
   * Pembuatan project dari sisi Sales dilakukan eksklusif melalui alur **Convert to Project** dari proposal/PO yang berstatus *Won/Deal*.
   * Menu pembuatan project manual (`/projects/create`) dibatasi khusus untuk tim Teknisi dan Manajemen.
3. **Sentralisasi Dokumen yang Tepat Konteks:**
   * Memisahkan dokumen entitas bisnis (Customer-level) dengan dokumen pelaksanaan teknis (Project-level).

---

## 3. Matriks Peran & Hak Akses (Role & Permission Matrix)

| Aksi / Modul | Sales | Teknisi / Lead Tek | Management / Super Admin |
|---|---|---|---|
| **Buat Project Manual (Form Kosong)** | ❌ Dibatasi | ✅ Ya | ✅ Ya |
| **Convert Proposal ke Project** | ✅ Ya (khusus proposalnya/timnya) | ❌ Tidak | ✅ Ya |
| **Pilih PIC Teknisi & Jadwal Lapangan** | ❌ Read-only | ✅ Ya | ✅ Ya |
| **Update Progress & Milestone Project** | ❌ Read-only | ✅ Ya | ✅ Ya |
| **Upload Dokumen Teknis (Survey/Sizing/BAST)** | ⚠️ Read-only (kecuali survey awal) | ✅ Ya | ✅ Ya |
| **Upload Dokumen Legalitas Customer** | ✅ Ya | ⚠️ Read-only | ✅ Ya |

---

## 4. Alur Kerja & Logika Sistem (Workflow & Logic)

```mermaid
flowchart TD
    A[Sales: Buat Penawaran / Proposal] --> B{Status Proposal?}
    B -->|Loss / Cancel| C[Selesai / Diarsipkan]
    B -->|Won / PO Diterima| D{Apakah Butuh Dukungan Teknisi?}
    
    D -->|Tidak (Hanya Beli Barang)| E[Proses Invoice & Delivery Order Saja]
    E --> F[Order Selesai Tanpa Membuat Project]
    
    D -->|Ya (Butuh Instalasi/Support)| G[Sales Klik: Convert to Project]
    G --> H[Sistem Generate Project Baru:
    - Auto-link Customer ID
    - Auto-link Proposal ID & Sales ID
    - Status Default: New / Waiting Assignment]
    H --> I[Notifikasi Masuk ke Tim Teknisi]
    I --> J[Lead Teknisi Menunjuk PIC Teknisi & Menentukan Jadwal]
    J --> K[Pelaksanaan Lapangan & Upload BAST]
    K --> L[Project Completed]
```

### 4.1 Logika Bisnis & Validasi
1. **Trigger Konversi:**
   * Tombol `Convert to Project` hanya aktif jika `status_proposal == 'won'` dan relasi `project_id` masih kosong/null.
   * Saat dikonversi, sistem otomatis mengisi kolom:
     * `customer_id` = proposal->customer_id
     * `sales_user_id` = auth()->id() (atau proposal->user_id)
     * `name` = default dari judul proposal / nama kebutuhan instalasi
     * `status` = status awal proyek (misal: *Pending / Assignment*)
2. **Keterlibatan Sales Pasca-Konversi:**
   * Sales dapat memantau status pengerjaan proyek dari tab *"My Sales Projects"* tanpa mengubah milestone teknis.

---

## 5. Tata Kelola Berkas & Dokumen (Document Architecture)

```
├── CUSTOMER REPOSITORY (Customer Detail)
│   ├── Legalitas Perusahaan (NPWP, NIB, Akta, Form Registrasi)
│   ├── Kontrak Payung / Master Service Agreement (MSA)
│   └── Ringkasan Riwayat Proyek (Aggregated Project Links)
│
└── PROJECT REPOSITORY (Project Detail)
    ├── Dokumen Pra-Pengerjaan (Hasil Survey Lapangan, Estimasi Sizing Produk)
    ├── Dokumen Desain & Skema (Topologi Jaringan, Diagram Single-Line)
    ├── Dokumentasi Lapangan (Foto Instalasi, Log Aktivitas Harian)
    └── Dokumen Serah Terima (BAST, Formulir UAT, Garansi Pengerjaan)
```

* **Aturan Upload:**
  * Dokumen yang berhubungan dengan *event* pengerjaan fisik wajib diunggah ke **Project Detail** agar memiliki riwayat audit per tanggal pengerjaan dan PIC pelaksana.
  * Dokumen legalitas/kontrak yang berlaku multi-transaksi wajib berada di **Customer Detail**.

---

## 6. Persyaratan Fungsional (Functional Requirements)

### 6.1 Modul Sales (Proposal & Deals)
* **FR-SALES-01:** Menambahkan opsi atau tombol aksi terarah `Convert to Project` pada detail proposal yang berstatus *Won*.
* **FR-SALES-02:** Modal konfirmasi konversi menampilkan ringkasan data (Customer, Scope Singkat, Estimasi Target Selesai, Field Catatan untuk Teknisi).
* **FR-SALES-03:** Validasi idempotensi: proposal yang sudah memiliki project terikat tidak boleh di-convert dua kali.

### 6.2 Modul Project (Teknisi & General)
* **FR-PROJ-01:** Pembatasan route & UI form `/projects/create` hanya untuk role ber-permission `manage-technician` atau `super-admin`.
* **FR-PROJ-02:** Penambahan relasi `sales_user_id` pada model `Project` untuk melacak sales penanggung jawab akun.
* **FR-PROJ-03:** Halaman daftar project (`/projects`) menyediakan filter status dan tab filter cepat:
  * *All Projects* (Teknisi / Management)
  * *My Assigned Projects* (Teknisi PIC)
  * *My Sales Projects* (Khusus akun Sales untuk memantau customer mereka)

### 6.3 Modul Dokumen
* **FR-DOC-01:** Menyediakan kategori dokumen saat upload di halaman detail Project (`Survey`, `Sizing`, `Installation`, `BAST`).
* **FR-DOC-02:** Di halaman detail Customer, sediakan sub-tab *"Project Documents"* yang secara dinamis menampilkan daftar dokumen dari seluruh project milik customer tersebut secara *read-only*.

---

## 7. Rencana Rilis & Milestone Teknis

| Tahap | Aktivitas | Target Luaran |
|---|---|---|
| **Fase 1** | Refactoring Route & Policy Permission | Route `/projects/create` diisolasi ke teknisi; Proposal `convertToProject` dipastikan aman. |
| **Fase 2** | Model & Database Update | Penambahan kolom relasi `sales_user_id` pada tabel `projects` dan relasi di Eloquent model. |
| **Fase 3** | UI/UX Modal Convert & Filter Tab | Integrasi modal handover di detail proposal dan penambahan filter tab di daftar project. |
| **Fase 4** | Standardisasi Upload Dokumen | Form upload dokumen project dengan kategori teknis (Survey, Sizing, BAST). |
| **Fase 5** | Verifikasi & Pengujian Alur | Testing peran Sales vs Teknisi, verifikasi kepatuhan terhadap aturan `AGENTS.md`. |

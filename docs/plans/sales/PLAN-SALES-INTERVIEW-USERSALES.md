# PLAN-SALES-INTERVIEW-USERSALES: Penyesuaian Menu Sales dengan Wawancara Adi Santosa

**Status Pengerjaan:** Selesai
**Work:** INTERVIEW-USERSALES
**PIC:** Raafi
**Menu:** Sales (Lead, Pipeline, Quotation, Dashboard, Customer Hub)
**Sumber:** Wawancara Sales User Adi Santosa
**Terakhir diperbarui:** 2026-11-12

---

## 1. Tujuan & hasil akhir

Sales mendapatkan alur kerja yang sesuai kebiasaan lapangan:

- Data prospek lengkap sejak awal, bukan hanya teks kebutuhan.
- DAR menjadi syarat sebelum prospek naik tahap.
- Nomor quotation baku per PT dan tidak ada quotation ganda untuk satu lead.
- Sales mengirim parameter, Inside Sales membuat draf, Sales memeriksa.
- KPI menampilkan omzet **dan** margin, bukan omzet saja.
- Customer Hub, PI, dan status pengiriman bisa dilihat Sales.

---

## 2. Ide pokok wawancara yang dilayani

Mengacu ke `docs/archive/WAWANCARA-USER-SALES.md`:

- **Bagian 1A:** format nomor `[PT]/[Nomor Urut]/[Inisial Sales]/[Tahun]`,
  contoh `MGK/015/AS/2026`, untuk 4 entitas PT (MBK/NTI, MGK, TPS, WANI).
- **Bagian 1B:** Sales lapangan menentukan strategi harga/margin; Inside Sales
  membuat draf; Sales memeriksa ulang sebelum dikirim ke klien.
- **Bagian 4A:** DAR wajib untuk prospek baru; atribut prospek wajib lengkap;
  riwayat percakapan per tanggal.
- **Bagian 4B:** tahap Cool/Warm/Hot/Won/Loss dan alasan Loss.
- **Bagian 5A:** setelah PO, Sales menyerahkan PO, DAR, dan quotation ke Admin
  Invoice dan Admin Purchasing; Sales memverifikasi PI.
- **Bagian 5C:** alamat tagihan dan alamat pengiriman wajib dipisah.
- **Bagian 6:** KPI omzet dan margin; contoh target PO Rp 500 juta dan target
  margin Rp 50 juta.
- **Bagian 7:** Customer Hub terpusat, kalender, notifikasi mobile, dan
  pelacakan pengiriman.

---

## 3. Temuan audit sistem sekarang

### Sudah sesuai

- Profil customer sudah menyimpan PT, alamat, PIC, WhatsApp, dan email.
- Segmentasi lead sudah ada: End User, Vendor, System Integrator, Kontraktor,
  Distributor, Gov, Principle, dan lainnya.
- Pipeline Cool/Warm/Hot/Won/Lost sudah ada; alasan Loss mencakup harga,
  kompetitor, budget, kebutuhan berubah, no response, dan lainnya.
- Riwayat follow-up dan aktivitas lead sudah tercatat per tanggal.
- Alur Sales mengirim task ke Inside Sales sudah ada.
- Quotation per PT dan PDF per PT sudah ada.
- Dashboard Sales, target omzet, target jumlah PO, dan drill-down Management
  sudah ada.
- Customer Hub sudah menampilkan customer, tahap, update terakhir, jumlah
  dokumen, DAR, dan upload dokumen.
- Notifikasi lead baru/assignment sudah ada (database, email, web push).

### Belum sesuai / masih kurang

1. **Nomor quotation belum baku.**
   - Sales memakai nomor otomatis `Q/2026/11/0001`.
   - Inside Sales memakai nomor manual bebas.
   - Belum ada urutan per PT, inisial Sales, dan tahun berjalan.

2. **Satu lead belum dikunci satu quotation.**
   - Satu lead masih bisa punya beberapa proposal.
   - Satu task Inside Sales memang hanya satu proposal, tetapi task baru untuk
     lead yang sama bisa membuat nomor quotation baru.

3. **Riwayat revisi belum jelas.**
   - Status `revision` ada.
   - Belum ada daftar versi revisi bernomor yang bisa dilihat Sales/Inside
     Sales.

4. **Sales masih bisa membuat quotation langsung dari nol.**
   - Jalur lama `Sales → Quotation → Create` masih terbuka.
   - Wawancara menempatkan Sales sebagai pemberi parameter dan pemeriksa,
     bukan pembuat draf.

5. **Data prospek belum lengkap secara struktur.**
   - Belum ada field khusus **Nama Proyek**.
   - Belum ada pilihan **Jenis Kebutuhan** (CCTV, PABX, Video Conference,
     jaringan, lainnya).
   - Belum ada status **Baru vs Replacement**.

6. **DAR belum menjadi syarat semua tahap.**
   - DAR baru dicek saat membuat quotation dan upload PO.
   - Status pipeline masih bisa naik ke Warm/Hot/Won tanpa DAR.

7. **KPI margin belum ada.**
   - Target hanya omzet dan jumlah PO.
   - Belum ada target margin dan akumulasi laba kotor di dashboard Sales.
   - Margin per quotation tersimpan, tetapi tidak diringkas menjadi KPI Sales.

8. **Customer Hub masih terbatas.**
   - Hanya menampilkan 15 customer terbaru.
   - Belum ada tombol “Lihat semua customer saya”.

9. **Handoff dan logistik belum terlihat Sales.**
   - Upload PO dari Customer Hub masih dokumen umum, bukan PO quotation.
   - Sales belum bisa melihat PI/invoice untuk verifikasi.
   - Status pengiriman barang masih di menu Admin.

---

## 4. Ruang lingkup

### Termasuk

- Field prospek baru dan validasi DAR.
- Aturan nomor quotation, satu lead satu quotation, dan revisi.
- Pembatasan Sales membuat quotation langsung.
- Target margin dan ringkasan margin di dashboard.
- Customer Hub: lihat semua, upload PO resmi, PI read-only, status kirim,
  alamat tagihan/pengiriman terpisah, dan kalender agenda untuk pimpinan.

### Tidak termasuk

- Perubahan sumber harga modal (Plan Estimator).
- Riwayat harga di form item (Plan Inside Sales).
- Layout plan dan biaya jasa teknisi (Plan Teknisi).
- Purchasing saving dan invoice internal (Plan Admin/Purchasing).

---

## 5. Langkah pengerjaan

### A. Kelengkapan data prospek

1. Tambah field lead:
   - `project_name` - Nama Proyek.
   - `need_type` - pilihan: CCTV, PABX, Video Conference, Jaringan, Lainnya.
   - `procurement_status` - pilihan: Baru, Replacement.
2. Tampilkan field tersebut di form create/edit lead dan detail lead.
3. Nama proyek dan jenis kebutuhan wajib untuk lead baru; replacement boleh
   kosong bila belum diketahui.
4. Validasi kelengkapan identitas klien sebelum lead masuk pipeline:
   - nama PT,
   - alamat lengkap,
   - nama PIC,
   - nomor WhatsApp,
   - email,
   - kategori klien: End User, Reseller, Kontraktor, atau lainnya.
5. Lead baru dari Marketing wajib masuk antrean follow-up dan diingatkan untuk
   dicatat ke DAR pada hari yang sama.
6. Migrasi data lama: kosongkan dengan aman, jangan mengarang nilai.

### B. DAR dan pipeline

1. Wajibkan minimal satu DAR sebelum status berubah:
   - Cool → Warm.
   - Warm → Hot.
   - Hot → Won.
2. Untuk Lost:
   - Wajib pilih alasan Loss.
   - Alasan mencakup slow response/tidak membalas setelah 1-3 follow-up,
     harga kalah, kompetitor, budget, kebutuhan berubah, proyek dibatalkan,
     dan lainnya.
   - Wajib isi catatan singkat.
3. Tampilkan pesan yang jelas di pipeline/task bila DAR belum ada:
   “Catat minimal satu follow-up sebelum menaikkan tahap.”
4. Pertimbangkan peringatan (bukan blokir) bila Hot ditandai tanpa survei,
   demo, atau quotation.

### C. Quotation

1. **Format nomor baku**
   - Struktur: `PT/NomorUrut/InisialSales/Tahun`.
   - Contoh: `MGK/015/AS/2026`.
   - Nomor urut berjalan per PT; apakah di-reset tiap tahun atau terus
     berjalan menunggu keputusan bagian 8.
   - Sumber inisial Sales (field profil atau turunan nama) menunggu keputusan
     bagian 8; sediakan mekanisme koreksi Admin.
2. **Satu lead satu quotation**
   - Blokir pembuatan proposal kedua untuk lead yang sama.
   - Revisi memakai nomor yang sama, bukan nomor baru.
3. **Riwayat revisi**
   - Simpan snapshot versi setiap revisi (nomor versi, tanggal, pembuat,
     ringkasan perubahan, total sebelum/sesudah).
   - Tampilkan daftar versi di halaman quotation.
4. **Peran**
   - Sales hanya mengirim parameter/task ke Inside Sales.
   - Tombol “Create Quotation” langsung untuk Sales ditutup atau dibatasi
     untuk kondisi darurat yang disetujui Management.
   - Sales tetap bisa melihat, memeriksa, memberi revisi, dan mengirim.

### D. KPI omzet dan margin

1. Tambah target margin di pengaturan target Sales:
   - Target margin nominal (Rp) dan/atau persentase.
   - Contoh wawancara: target PO Rp 500 juta, target margin Rp 50 juta.
2. Dashboard Sales menampilkan:
   - Omzet closed.
   - Margin kotor closed.
   - Persentase pencapaian omzet dan margin.
3. Jika harga modal item belum lengkap, tampilkan “margin belum lengkap”,
   jangan menghitung 0.
4. Management KPI dan drill-down ikut menampilkan margin.

### E. Customer Hub, kalender, dan handoff

1. Tambah tombol **“Lihat semua customer saya”** menuju menu Customer.
2. Satu profil customer menampung:
   - riwayat kontak PIC,
   - catatan DAR,
   - quotation,
   - lampiran PO,
   - riwayat pengiriman barang.
3. Upload PO dari Customer Hub:
   - Pilih quotation Accepted.
   - Isi nomor PO, tanggal PO, dan file.
   - Simpan sebagai PO quotation resmi, bukan dokumen umum.
4. Sales bisa melihat PI/invoice miliknya secara read-only untuk verifikasi.
5. Tampilkan status pengiriman barang di halaman customer/project yang bisa
   dilihat Sales.
6. Sediakan tombol salin link PO/DAR/quotation untuk kebutuhan grup Admin
   Invoice, bila proses WhatsApp masih dipakai.
7. Sebelum PO diunggah, pastikan alamat tagihan kantor dan alamat pengiriman
   proyek terisi terpisah. Blokir handoff PO bila alamat pengiriman kosong.
8. Kalender agenda Sales (demo, survei, rapat, kunjungan) bisa dipantau
   pimpinan (Pak Victor/Bu Yanita) dari dashboard Management tanpa bertanya
   manual di grup chat.
9. Pastikan notifikasi lead baru dan penugasan tetap sampai lewat email/web
   push dan nyaman dibuka dari ponsel.

---

## 6. Dependensi

- **Estimator:** harga modal harus terpisah dari harga jual.
- **Inside Sales:** pembuat draf dan riwayat harga item.
- **Teknisi:** biaya jasa dan layout plan.
- **Admin/Purchasing:** PI, invoice, status pengiriman, purchasing saving.

---

## 7. Kriteria selesai & cara verifikasi

### Kriteria selesai

- Nomor quotation mengikuti format PT/urutan/inisial/tahun.
- Satu lead tidak bisa punya dua nomor quotation.
- Revisi punya daftar versi yang bisa dibaca.
- Sales tidak bisa membuat quotation langsung tanpa jalur darurat.
- DAR wajib sebelum Warm/Hot/Won.
- Dashboard menampilkan omzet dan margin beserta targetnya.
- Customer Hub punya tombol lihat semua dan PO resmi.
- Profil customer menampung PIC, DAR, quotation, PO, dan pengiriman.
- Alamat tagihan dan alamat pengiriman terpisah; handoff PO diblokir bila
  alamat pengiriman kosong.
- Kalender agenda Sales bisa dipantau pimpinan.
- Sales bisa melihat PI dan status pengiriman miliknya.

### Cara testing

1. Login Sales Adi → buat lead baru:
   - Isi nama proyek, jenis kebutuhan, status baru/replacement.
   - **Hasil:** data tersimpan dan tampil di detail lead.
2. Coba ubah Cool → Warm tanpa DAR:
   - **Hasil:** ditolak dengan pesan untuk mengisi DAR.
3. Isi DAR, lalu ubah ke Warm:
   - **Hasil:** status berubah.
4. Login Sales → kirim task quotation ke Inside Sales, lalu login Inside
   Sales → generate quotation:
   - **Hasil:** nomor mengikuti format PT/urutan/inisial/tahun.
5. Coba buat quotation kedua untuk lead yang sama:
   - **Hasil:** ditolak; revisi harus memakai nomor yang sama.
6. Login Sales → buka menu Quotation:
   - **Hasil:** tombol Create Quotation langsung tidak tersedia.
7. Buka quotation → revisi:
   - **Hasil:** ada daftar versi revisi.
8. Login Sales → Dashboard:
   - **Hasil:** omzet dan margin closed tampil terhadap target.
9. Login Management Bu Yanita → Sales KPI:
   - **Hasil:** margin per Sales terlihat.
10. Buka Customer Hub → Lihat semua customer:
    - **Hasil:** daftar lengkap dengan pencarian dan halaman berikutnya.
11. Buka satu customer:
    - **Hasil:** riwayat PIC, DAR, quotation, PO, dan pengiriman tampil di
      satu profil.
12. Upload PO dari Customer Hub:
    - **Hasil:** PO masuk ke quotation resmi dan handoff Admin.
13. Coba upload PO tanpa alamat pengiriman:
    - **Hasil:** ditolak dengan pesan untuk melengkapi alamat pengiriman.
14. Login Management Bu Yanita → buka kalender agenda Sales:
    - **Hasil:** agenda demo, survei, dan rapat Sales terlihat.

---

## 8. Keputusan yang perlu dijawab

1. Inisial Sales diambil dari field baru di profil user, atau otomatis dari
   nama?
2. Nomor urut per PT di-reset setiap tahun, atau berjalan terus?
3. Apakah Sales sama sekali tidak boleh membuat quotation, atau tetap boleh
   untuk kondisi darurat?
4. Target margin dihitung nominal Rupiah, persentase, atau keduanya?

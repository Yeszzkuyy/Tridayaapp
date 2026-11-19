# PLAN-TEKNISI-INTERVIEW-USERSALES: Request Jasa, Layout Plan & Biaya Jasa

**Status Pengerjaan:** Belum Dikerjakan
**Work:** INTERVIEW-USERSALES
**PIC:** Raafi
**Menu:** Teknisi (Request Jasa, Survey, Instalasi)
**Sumber:** Wawancara Sales User Adi Santosa
**Terakhir diperbarui:** 2026-11-11

---

## 1. Tujuan & hasil akhir

Permintaan jasa teknisi jelas sejak awal dan biayanya masuk ke quotation:

- Sales bisa meminta jasa konfigurasi atau instalasi.
- Pekerjaan kabel/fisik wajib punya layout plan dan estimasi panjang kabel.
- Jika layout belum ada, sistem mengarahkan ke survey dulu.
- Kepala teknisi bisa memberi estimasi biaya jasa.
- Biaya jasa masuk ke quotation induk yang sama, tidak membuat quotation
  terpisah.

---

## 2. Ide pokok wawancara yang dilayani

Mengacu ke `docs/archive/WAWANCARA-USER-SALES.md` bagian 3:

- Sales berkoordinasi dengan Kepala Teknisi (Mas Amir) sebelum penawaran final.
- Dua jenis pekerjaan:
  1. Konfigurasi/setting perangkat lunak - estimasi biaya relatif sederhana.
  2. Penarikan kabel/pekerjaan sipil - wajib layout plan dan estimasi panjang
     kabel; jika belum ada, teknisi harus survey lokasi.
- Biaya jasa tidak dibuat quotation terpisah; Inside Sales menggabungkannya ke
  nomor quotation induk.
- Penambahan item sebelum dikirim bukan versi revisi baru.

---

## 3. Temuan audit sistem sekarang

### Sudah sesuai

- Modul Request Teknisi sudah ada.
- Jenis request: survey, installation, troubleshooting, consultation, demo,
  POC, other.
- Sales bisa membuat request dari lead.
- Kepala teknisi bisa review, assign, progress, result, complete.
- Ada lampiran file pada request.
- Ada modul survey dan instalasi di sisi teknisi.
- Hasil teknisi bisa diunggah sebagai dokumen.

### Belum sesuai

1. **Layout plan belum wajib.**
   - Lampiran request teknisi opsional.
   - Pekerjaan penarikan kabel/fisik bisa diajukan tanpa layout plan.

2. **Belum ada field panjang kabel.**
   - Estimasi panjang kabel tidak tersimpan sebagai data.

3. **Belum ada aturan “survey dulu”.**
   - Jika layout belum ada, sistem tidak otomatis mengarahkan ke survey.

4. **Biaya jasa belum terstruktur.**
   - Hasil teknisi berupa teks/attachment.
   - Belum ada nominal biaya jasa yang bisa dibaca Inside Sales.

5. **Belum terhubung ke quotation.**
   - Technical Request tidak punya tautan ke Proposal/quotation.
   - Inside Sales harus menambah biaya jasa manual.

6. **Belum ada pencegahan double entry.**
   - Satu request jasa bisa saja dimasukkan dua kali ke quotation.

---

## 4. Ruang lingkup

### Termasuk

- Validasi layout plan dan panjang kabel.
- Alur survey otomatis bila layout belum ada.
- Estimasi biaya jasa terstruktur.
- Tautan request jasa ke quotation/item.
- Pencegahan double entry.
- Single quotation rule dari Plan Sales.

### Tidak termasuk

- Nomor quotation dan KPI Sales.
- Harga modal barang Estimator.
- Invoice/PI dan pengiriman Admin.
- Margin quotation (Inside Sales).

---

## 5. Langkah pengerjaan

### A. Validasi request instalasi

1. Untuk request bertipe **installation** atau pekerjaan fisik:
   - Wajib pilih cakupan: konfigurasi saja atau kabel/fisik.
   - Jika kabel/fisik:
     - wajib unggah layout plan, ATAU
     - pilih “Belum ada layout, ajukan survey dulu”.
2. Tambah field:
   - `work_scope` - configuration / cabling / civil / other.
   - `cable_length_estimate` - estimasi panjang kabel (meter).
   - `layout_plan_path` - file layout.
   - `survey_required` - ya/tidak.
3. Jika layout belum ada dan survey diminta:
   - Request instalasi tidak langsung diproses.
   - Sistem membuat/menghubungkan request survey.
   - Setelah survey selesai, layout/hasil survey menjadi lampiran instalasi.

### B. Biaya jasa

1. Tambah form hasil teknisi:
   - biaya jasa (nominal),
   - rincian pekerjaan,
   - qty/unit bila perlu,
   - catatan,
   - lampiran.
2. Biaya jasa disimpan sebagai data terstruktur, bukan hanya teks.
3. Kepala teknisi bisa memberi estimasi sebelum pekerjaan selesai.
4. Status biaya:
   - belum diestimasi,
   - estimasi tersedia,
   - final.

### C. Hubungkan ke quotation

1. Tambah relasi request teknisi ke quotation/proposal.
2. Di Inside Sales, tampilkan request jasa yang siap ditarik:
   - jenis jasa,
   - biaya,
   - lampiran layout/survey.
3. Saat ditarik:
   - buat item jasa di quotation,
   - simpan tautan request agar tidak dobel.
4. Satu request hanya bisa ditarik ke satu quotation aktif.
5. Semua item jasa masuk ke nomor quotation induk.
6. Penambahan item jasa sebelum quotation dikirim ke klien tidak membuat
   versi revisi baru; nomor quotation tetap sama.

### D. Koordinasi Kepala Teknisi

1. Notifikasi request baru ke Kepala Teknisi (Mas Amir) dan teknisi terkait.
2. Tampilkan antrean request di dashboard teknisi.
3. Sediakan status survey, instalasi, dan hasil.
4. Hasil survey/layout otomatis tersedia untuk Sales dan Inside Sales.

---

## 6. Dependensi

- **Sales:** pengaju request dan data lead/customer.
- **Inside Sales:** penarikan biaya jasa ke quotation.
- **Admin/Purchasing:** pengiriman barang dan penerimaan.
- **Estimator:** harga barang, bila jasa terkait produk.

---

## 7. Kriteria selesai & cara verifikasi

### Kriteria selesai

- Request instalasi kabel/fisik tanpa layout ditolak atau diarahkan survey.
- Layout plan dan panjang kabel tersimpan.
- Biaya jasa punya nominal terstruktur.
- Biaya jasa bisa ditarik ke quotation tanpa dobel.
- Satu request jasa hanya masuk satu quotation.
- Hasil survey/layout bisa dilihat Sales/Inside Sales.

### Cara testing

1. Login Sales → buat Request Teknisi tipe Installation, cakupan kabel/fisik,
   tanpa layout.
   - **Hasil:** diminta unggah layout atau pilih survey dulu.
2. Pilih “Ajukan survey”.
   - **Hasil:** request survey dibuat/terhubung.
3. Selesaikan survey dan unggah layout.
   - **Hasil:** layout tersimpan; instalasi bisa diproses.
4. Login Kepala Teknisi → isi biaya jasa.
   - **Hasil:** nominal dan rincian tersimpan.
5. Login Inside Sales → tarik biaya jasa ke quotation.
   - **Hasil:** item jasa muncul di quotation induk.
6. Coba tarik request yang sama lagi.
   - **Hasil:** ditolak karena sudah dipakai.
7. Generate PDF quotation.
   - **Hasil:** item jasa tampil bersama item barang.

---

## 8. Keputusan yang perlu dijawab

1. Request tipe apa saja yang wajib layout plan: hanya installation, atau
   termasuk survey/demo/POC?
2. Panjang kabel wajib diisi angka, atau boleh estimasi teks?
3. Biaya jasa berbentuk satu nominal lump sum, atau rincian item?
4. Apakah survey otomatis membuat request baru, atau hanya status di request
   yang sama?
5. Siapa yang menentukan harga jual jasa: Kepala Teknisi, Inside Sales, atau
   Sales?

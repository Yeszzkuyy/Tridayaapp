# PLAN-ADMIN-PURCHASING-INTERVIEW-USERSALES: Handoff PO, PI, Saving & Logistik

**Status Pengerjaan:** Belum Dikerjakan
**Work:** INTERVIEW-USERSALES
**PIC:** Yeski
**Menu:** Admin / Purchasing / Invoice
**Sumber:** Wawancara Sales User Adi Santosa
**Terakhir diperbarui:** 2026-11-11

---

## 1. Tujuan & hasil akhir

Serah terima setelah PO berjalan jelas dan terukur:

- Sales bisa melihat PI/invoice miliknya untuk verifikasi.
- Purchasing punya catatan penghematan (purchasing saving) dari negosiasi.
- Status pengiriman bisa dipantau Sales, Admin, dan Teknisi.
- Alamat tagihan dan alamat pengiriman tidak tertukar.
- Bukti terima barang tersimpan di customer/project.

---

## 2. Ide pokok wawancara yang dilayani

Mengacu ke `docs/archive/WAWANCARA-USER-SALES.md` bagian 5:

- Setelah PO diterima, Sales menyerahkan PO, DAR, dan quotation ke Admin
  Invoice dan Admin Purchasing.
- Admin Invoice (Mbak April) menerbitkan Proforma Invoice/DP.
- Sales memverifikasi nominal dan rincian PI.
- Admin Purchasing (Mbak Nabila) mengunci alokasi stok dan memesan unit.
- Purchasing menegosiasi ulang ke distributor untuk diskon tambahan 1-3%
  sebagai KPI purchasing saving.
- Alamat tagihan kantor dan alamat pengiriman proyek wajib dipisah.
- Pengadaan 3-4 hari kerja; tanda terima barang diunggah sebagai bukti.

---

## 3. Temuan audit sistem sekarang

### Sudah sesuai

- PO customer bisa diunggah ke quotation dan menjadi syarat convert project.
- Admin/Purchasing mendapat notifikasi handoff setelah PO.
- Admin bisa membuat invoice DP, termin, pelunasan, atau full.
- Purchasing bisa membuat supplier PO.
- Barang masuk bisa dicatat lewat goods receipt.
- Customer punya alamat tagihan dan alamat pengiriman terpisah.
- Project punya alamat pengiriman (`delivery_address`) dan deadline.
- Goods delivery punya status prepared/shipped/delivered.

### Belum sesuai

1. **Sales belum bisa melihat PI/invoice.**
   - Invoice hanya bisa dibuka role Admin/Purchasing.
   - Sales tidak bisa memverifikasi nominal dan rincian PI di sistem.

2. **Purchasing saving belum tercatat.**
   - Supplier PO punya diskon, tetapi tidak ada perbandingan harga estimasi
     vs harga beli aktual.
   - Tidak ada laporan penghematan 1-3% untuk KPI Purchasing.

3. **Status pengiriman belum terlihat Sales/Customer.**
   - Goods delivery masih di menu Admin.
   - Customer Hub dan halaman project Sales belum menampilkan status kirim.

4. **Handoff masih mengandalkan grup WhatsApp.**
   - Notifikasi sistem sudah ada.
   - Namun penyalinan link PO/DAR/quotation dan tag Mbak April/Nabila masih
     manual di luar aplikasi.

5. **Bukti terima barang belum menyatu ke customer.**
   - Goods receipt ada di Admin.
   - Belum tampil sebagai riwayat dokumen customer/Sales.

6. **SLA 3-4 hari belum terukur.**
   - Ada deadline pengiriman, tetapi belum ada laporan durasi aktual dari
     pembayaran ke barang tiba.

---

## 4. Ruang lingkup

### Termasuk

- PI/invoice read-only untuk Sales pemilik.
- Purchasing saving: estimasi vs aktual.
- Status pengiriman yang bisa dilihat Sales/Customer.
- Bukti terima barang di riwayat customer/project.
- Checklist handoff PO.
- Tombol salin link dokumen untuk grup WhatsApp, bila masih dipakai.

### Tidak termasuk

- Akuntansi/Finance lanjutan.
- Perubahan nomor quotation.
- Harga modal Estimator.
- Biaya jasa teknisi.

---

## 5. Langkah pengerjaan

### A. PI/invoice read-only untuk Sales

1. Sales bisa membuka invoice/PI yang terkait:
   - proposal miliknya,
   - project dengan `sales_user_id` dirinya,
   - customer/lead miliknya.
2. Hanya baca; tidak bisa mengubah nominal, status, atau pembayaran.
3. Tampilkan:
   - nomor PI/invoice,
   - tipe (DP/termin/pelunasan/full),
   - nominal,
   - rincian item,
   - status bayar.
4. Tambah tombol “Verifikasi PI” atau catatan verifikasi Sales bila diperlukan.

### B. Purchasing saving

1. Setelah pembayaran DP/lunas terkonfirmasi, Sales/Estimator menyerahkan
   rincian harga beli distributor ke Purchasing.
2. Simpan harga estimasi/harga modal saat supplier PO dibuat.
3. Simpan harga beli aktual setelah negosiasi.
4. Hitung:
   - saving nominal = baseline − aktual,
   - saving persen = saving nominal / baseline × 100.
   - Baseline (harga modal Estimator, harga penawaran Sales, atau budget
     Purchasing) menunggu keputusan bagian 8.
5. Tampilkan di supplier PO dan laporan Purchasing.
6. Target saving 1-3% bisa menjadi indikator, bukan hard block.
7. Jangan mengubah harga modal produk otomatis dari saving; itu urusan
   Estimator.

### C. Logistik terbuka

1. Sebelum PI/pengiriman, validasi:
   - alamat tagihan kantor terisi,
   - alamat pengiriman proyek terisi dan berbeda bila lokasinya berbeda.
2. Tampilkan status goods delivery di:
   - halaman project (Sales/Teknisi),
   - Customer Hub / detail customer.
3. Status: disiapkan, dikirim, diterima.
4. Tampilkan tanggal kirim, estimasi tiba, dan bukti terima.
5. Sales bisa melihat tanpa bisa mengubah.
6. Bukti terima masuk ke riwayat dokumen customer/project.

### D. Handoff PO

1. Sediakan panel handoff setelah PO:
   - link PO customer,
   - link DAR,
   - link quotation,
   - status PI,
   - status supplier PO,
   - status pengiriman.
2. Notifikasi ke Admin Invoice dan Purchasing sudah ada; pastikan penerima
   sesuai role.
3. Sediakan tombol salin ringkasan/link untuk grup WhatsApp, tanpa mengirim
   otomatis.
4. Tampilkan status alokasi stok dan PO resmi ke distributor.
5. Checklist:
   - PO diterima,
   - PI diterbitkan,
   - stok dialokasikan,
   - supplier PO dibuat,
   - barang dikirim,
   - barang diterima.

### E. SLA pengadaan

1. Catat tanggal pembayaran masuk.
2. Catat tanggal barang tiba.
3. Hitung durasi aktual.
4. Tampilkan laporan sederhana: target 3-4 hari vs aktual.
5. Jangan jadikan blokir; cukup indikator keterlambatan.

---

## 6. Dependensi

- **Sales:** akses read-only dan data PO/DAR/quotation.
- **Purchasing:** supplier PO, harga aktual, diskon.
- **Admin Invoice:** PI/invoice dan pembayaran.
- **Teknisi:** penerimaan barang di lokasi.
- **Customer:** alamat tagihan vs pengiriman.

---

## 7. Kriteria selesai & cara verifikasi

### Kriteria selesai

- Sales bisa melihat PI/invoice miliknya tanpa bisa mengubah.
- Purchasing saving tercatat nominal dan persen.
- Status pengiriman terlihat di project/customer.
- Alamat tagihan dan pengiriman tervalidasi sebelum PI/pengiriman.
- Bukti terima barang tersimpan.
- Handoff PO punya checklist yang jelas.
- Laporan durasi pengadaan tersedia.

### Cara testing

1. Login Admin → buat invoice DP untuk project Sales.
2. Login Sales pemilik → buka invoice/PI.
   - **Hasil:** bisa melihat nominal dan rincian; tombol edit tidak ada.
3. Login Purchasing → buat supplier PO dari project.
   - **Hasil:** harga estimasi dan harga aktual tersimpan.
4. Isi harga aktual lebih rendah.
   - **Hasil:** saving nominal/persen muncul.
5. Admin ubah status goods delivery menjadi delivered.
   - **Hasil:** Sales melihat status “Diterima” di project/customer.
6. Upload bukti terima.
   - **Hasil:** bukti muncul di riwayat dokumen customer/project.
7. Coba buat PI/pengiriman tanpa alamat pengiriman:
   - **Hasil:** ditolak dengan pesan untuk melengkapi alamat pengiriman.
8. Buka panel handoff PO.
   - **Hasil:** checklist PI, alokasi stok, supplier PO, pengiriman, dan
     penerimaan tampil.

---

## 8. Keputusan yang perlu dijawab

1. Apakah Sales boleh melihat seluruh invoice customer atau hanya invoice
   project/quotation miliknya?
2. Purchasing saving dihitung dari harga modal Estimator, harga penawaran
   Sales, atau budget Purchasing?
3. Apakah target saving 1-3% berlaku untuk semua pembelian atau hanya barang
   tertentu?
4. Apakah status pengiriman boleh dilihat Customer langsung, atau hanya
   Sales/Admin/Teknisi?
5. Apakah integrasi WhatsApp grup tetap manual, atau perlu pengiriman pesan
   otomatis?

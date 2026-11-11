# PLAN-INSIDE-SALES-INTERVIEW-USERSALES: Item Quotation, Margin & Jasa Teknisi

**Status Pengerjaan:** Belum Dikerjakan
**Work:** INTERVIEW-USERSALES
**PIC:** Yeski
**Menu:** Inside Sales (Dashboard, Task, Quotation, PDF)
**Sumber:** Wawancara Sales User Adi Santosa
**Terakhir diperbarui:** 2026-11-11

---

## 1. Tujuan & hasil akhir

Inside Sales bisa menyusun quotation yang lengkap dan akurat:

- Harga modal dari Estimator dan harga jual terpisah.
- Riwayat harga bisa dipilih dari yang terbaru sampai terlama.
- Margin per item dan total margin terlihat.
- Biaya jasa teknisi ikut masuk ke nomor quotation yang sama.
- Revisi margin bisa dilakukan cepat sesuai instruksi Sales.
- PDF per PT tetap rapi.

---

## 2. Ide pokok wawancara yang dilayani

Mengacu ke `docs/archive/WAWANCARA-USER-SALES.md`:

- **Bagian 1B:** Inside Sales adalah operator pembuat draf quotation formal;
  Sales memeriksa hasilnya sebelum dikirim ke klien.
- **Bagian 2B:** margin bersifat dinamis; Sales bisa meminta revisi margin
  menjadi persentase tertentu.
- **Bagian 3B:** biaya jasa teknisi tidak dibuat quotation terpisah; Inside
  Sales menggabungkannya ke quotation induk; penambahan item sebelum dokumen
  keluar ke klien bukan versi revisi baru.
- **Bagian 7:** alur quotation terpadu dengan riwayat revisi dan margin
  dinamis.

---

## 3. Temuan audit sistem sekarang

### Sudah sesuai

- Dashboard Inside Sales, antrean task, dan detail task sudah ada.
- Form item sudah memilih produk, scope of supply, lead time, qty, unit,
  harga jual, dan harga modal.
- Harga modal terbaru dari Estimator sudah bisa mengisi item.
- Margin per item dan total margin sudah dihitung.
- Generate quotation membuat Proposal + item + PDF per PT.
- Sales menerima notifikasi dan bisa memeriksa/mengirim quotation.
- Satu task hanya boleh punya satu quotation.

### Belum sesuai

1. **Riwayat harga belum bisa dipilih.**
   - Form item hanya menampilkan harga Estimator terbaru.
   - Wawancara meminta pilihan harga dari update terbaru sampai lama.

2. **Harga modal dan harga jual masih bercampur.**
   - Harga jual default produk bisa ikut tertimpa harga modal dari Estimator.
   - Akibatnya margin bisa 0 sebelum Inside Sales mengubah harga jual.

3. **Revisi margin belum praktis.**
   - Sales harus mengubah harga item satu per satu.
   - Belum ada input “revisi margin menjadi X%” yang menghitung harga jual
     otomatis.

4. **Jasa teknisi belum otomatis masuk quotation.**
   - Request Teknisi berdiri sendiri.
   - Belum ada tarik biaya jasa dari request ke item quotation.
   - Biaya jasa masih harus diketik manual, rawan terlewat.

5. **Satu lead satu quotation belum dijamin.**
   - Satu task satu quotation sudah.
   - Namun lead yang sama bisa punya beberapa task/quotation baru.

6. **Riwayat revisi quotation belum punya versi.**
   - Status revisi ada, tetapi tidak ada daftar versi dan ringkasan perubahan.

---

## 4. Ruang lingkup

### Termasuk

- Pilihan riwayat harga di form item.
- Pemisahan harga modal dan harga jual.
- Margin per item/total dan revisi margin persen.
- Integrasi biaya jasa dari Request Teknisi.
- Aturan nomor quotation dan revisi dari Plan Sales.
- Verifikasi PDF per PT.

### Tidak termasuk

- Sumber harga modal dan report Estimator (Plan Estimator).
- Nomor quotation dan KPI Sales (Plan Sales).
- Layout plan dan survey (Plan Teknisi).
- Invoice/PI dan pengiriman (Plan Admin/Purchasing).

---

## 5. Langkah pengerjaan

### A. Form item harga

1. Saat produk dipilih:
   - Harga jual default terisi.
   - Harga modal terbaru terisi.
   - Muncul info supplier dan tanggal update.
2. Tambah dropdown **Riwayat Harga**:
   - Urutan terbaru ke terlama.
   - Isi: tanggal, harga modal, supplier.
   - Memilih riwayat mengubah harga modal item, bukan harga jual.
3. Harga jual tetap bisa diedit Inside Sales.
4. Tampilkan margin per item:
   - Margin nominal = (qty × harga jual − diskon) − (qty × harga modal).
   - Margin persen mengikuti keputusan bisnis (lihat bagian 8).
5. Total margin di bawah tabel hanya muncul jika semua item punya harga modal;
   jika belum lengkap, tampilkan “margin belum lengkap”.

### B. Revisi margin

1. Tambah input **Target Margin %** pada level quotation atau item.
2. Margin boleh berbeda per item:
   - barang komoditas/pasar kompetitif: margin tipis,
   - penjualan langsung ke end-user: markup lebih longgar.
3. Sales mengirim instruksi margin; Inside Sales mengeksekusi dan mencatat
   alasannya.
4. Saat diisi, sistem menghitung harga jual berdasarkan harga modal.
5. Tampilkan perbandingan sebelum dan sesudah revisi.
6. Simpan alasan revisi (mis. “permintaan Sales menurunkan margin”).
7. Jangan mengubah harga modal saat margin direvisi.

### C. Integrasi jasa teknisi

1. Tampilkan daftar Request Teknisi terkait lead yang sudah selesai/diestimasi.
2. Tarik biaya jasa sebagai item quotation:
   - deskripsi jasa,
   - qty,
   - harga jual jasa,
   - harga modal jasa (jika ada),
   - lampiran layout/survey.
3. Cegah duplikasi: satu request jasa hanya bisa ditarik sekali ke quotation.
4. Jika biaya jasa belum ada, tampilkan status “menunggu estimasi teknisi”.
5. Semua jasa masuk ke nomor quotation induk, bukan quotation terpisah.

### D. Quotation & revisi

1. Ikuti aturan Plan Sales:
   - nomor baku per PT/urutan/inisial/tahun,
   - satu lead satu quotation,
   - revisi memakai nomor yang sama.
2. Simpan versi revisi:
   - versi, tanggal, pembuat, ringkasan perubahan, total sebelum/sesudah.
3. Tampilkan riwayat versi di halaman quotation.
4. Jika quotation sudah dikirim, perubahan harus lewat status revisi.
5. Penambahan atau perubahan item sebelum quotation dikirim tidak membuat
   versi revisi baru; nomor quotation tetap sama.

### E. PDF

1. Pastikan PDF memakai:
   - kop PT yang benar,
   - data customer/PIC,
   - item barang dan jasa,
   - total, PPN, terms, rekening, tanda tangan.
2. Setelah perubahan harga/margin, PDF harus digenerate ulang.
3. Sales bisa mengunduh PDF yang sama.

---

## 6. Dependensi

- **Estimator:** harga modal terbaru dan riwayat.
- **Sales:** aturan nomor quotation, satu lead satu quotation, target margin.
- **Teknisi:** estimasi biaya jasa, layout plan, hasil survey.
- **Admin/Purchasing:** status PO dan pengiriman.

---

## 7. Kriteria selesai & cara verifikasi

### Kriteria selesai

- Form item menampilkan pilihan riwayat harga terbaru sampai lama.
- Harga modal dan harga jual tidak saling menimpa.
- Margin per item dan total margin benar.
- Revisi margin persen menghitung harga jual otomatis.
- Biaya jasa dari Request Teknisi bisa ditarik ke quotation.
- Satu lead hanya punya satu nomor quotation.
- Riwayat revisi bisa dilihat.
- Penambahan item sebelum quotation dikirim tidak menambah versi revisi.
- PDF per PT benar setelah perubahan.

### Cara testing

1. Login Inside Sales Audi → buka task dengan item.
2. Pilih produk yang punya beberapa riwayat harga.
   - **Hasil:** dropdown riwayat muncul dari terbaru ke terlama.
3. Pilih harga lama.
   - **Hasil:** harga modal berubah; harga jual tidak ikut berubah.
4. Isi harga jual dan lihat margin.
   - **Hasil:** margin nominal/persen muncul.
5. Isi target margin 20%.
   - **Hasil:** harga jual dihitung ulang, harga modal tetap.
6. Buka Request Teknisi terkait → tarik biaya jasa.
   - **Hasil:** item jasa masuk quotation, tidak dobel.
7. Generate quotation → PDF.
   - **Hasil:** barang dan jasa tampil di satu PDF.
8. Login Sales → cek quotation.
   - **Hasil:** nomor sama, revisi punya versi, PDF bisa diunduh.
9. Tambah item sebelum quotation dikirim:
   - **Hasil:** item masuk tanpa membuat versi revisi baru.
10. Kirim quotation, lalu ubah item:
    - **Hasil:** perubahan tercatat sebagai versi revisi baru.

---

## 8. Keputusan yang perlu dijawab

1. Margin persen dihitung dari **harga jual** (gross margin) atau dari
   **harga modal** (markup)?
2. Revisi margin dilakukan per quotation atau per item?
3. Apakah Inside Sales boleh mengubah harga jual setelah quotation dikirim,
   atau harus lewat status revisi dan persetujuan Sales?
4. Biaya jasa teknisi berbentuk lump sum per request atau rincian item?
5. Siapa yang menentukan harga jual jasa: Sales, Inside Sales, atau Teknisi?

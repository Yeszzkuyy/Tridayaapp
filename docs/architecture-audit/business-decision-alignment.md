# BUSINESS DECISION ALIGNMENT
## 3DY App — Phase 2.1

**Status:** Draft hasil diskusi bisnis
**Tujuan:** Menetapkan keputusan bisnis sebagai dasar evaluasi arsitektur database dan pengembangan CRM.

---

## 1. Keputusan Bisnis

### A. Sales, Customer, dan Quotation

**DEC-001 — Invoice dan DP sebelum BAST**
- Invoice DP boleh diterbitkan sebelum BAST.
- Invoice tidak harus menunggu project selesai.
- Status invoice dan status project harus dipisahkan.
- Project tetap mengikuti persyaratan Closing.

**DEC-002 — Persentase DP fleksibel**
- Persentase DP mengikuti kesepakatan dengan customer.
- Tidak ada persentase DP tetap yang diwajibkan.
- Nilai DP dihitung berdasarkan nilai quotation atau kontrak.

**DEC-003 — Hak akses harga modal**
- Sales boleh melihat harga modal.
- Sales tidak boleh mengubah harga modal.
- Estimator bertanggung jawab menginput dan memperbarui harga modal.
- Perubahan harga harus memiliki riwayat.

**DEC-004 — Progress project otomatis**
- Progress project dihitung berdasarkan workflow.
- Pengguna tidak boleh mengubah persentase progress secara manual.
- Project tidak otomatis Closing hanya karena progress mencapai 100%.

**DEC-005 — Penghapusan customer**
- Customer yang memiliki riwayat transaksi tidak boleh dihapus permanen.
- Customer dapat diarsipkan.
- Riwayat quotation, PO, invoice, dan pembayaran harus tetap tersimpan.

**DEC-006 — Work Type**
- Work Type yang sudah digunakan tidak boleh dihapus permanen.
- Work Type yang tidak digunakan dapat dinonaktifkan.
- Project lama harus tetap memiliki relasi Work Type.

**DEC-007 — Status inti Project**
- Status inti workflow dikelola Super Admin/developer.
- Admin biasa tidak boleh mengubah atau menghapus status inti.
- Perubahan status inti harus melalui evaluasi dan pengujian.

**DEC-008 — Master Supplier**
- Menggunakan satu sumber data utama supplier.
- Semua modul menggunakan master supplier yang sama.
- Data lama dari `partners` dan `suppliers` harus dipetakan sebelum migrasi.
- Tabel lama tidak boleh langsung dihapus.

**DEC-009 — Master Produk**
- Menggunakan satu Master Produk untuk seluruh modul.
- Sales, Estimator, Purchasing, dan Admin menggunakan data produk yang sama.
- Produk memiliki identitas yang konsisten.
- Produk yang sudah digunakan dalam transaksi tidak boleh dihapus sembarangan.

**DEC-010 — Riwayat harga modal**
Setiap perubahan harga modal harus menyimpan:
- Produk.
- Supplier.
- Harga sebelumnya.
- Harga terbaru.
- Waktu perubahan.
- Pengguna yang melakukan perubahan.

Riwayat harga harus dapat ditelusuri.

**DEC-011 — Nomor dokumen**
- Nomor dokumen harus unik.
- Nomor yang sudah diterbitkan tidak boleh digunakan ulang.
- Nomor dokumen yang dibatalkan tetap tercatat.
- Pembuatan nomor harus aman terhadap proses bersamaan.

**DEC-012 — Audit Log**
CRM harus mencatat aktivitas penting:
- Pengguna yang melakukan aktivitas.
- Waktu aktivitas.
- Modul terkait.
- Jenis tindakan.
- Data sebelum perubahan.
- Data setelah perubahan.

Audit Log tidak boleh dapat diubah atau dihapus oleh pengguna biasa.

**DEC-013 — Pembatasan akses data Sales**
- Sales hanya dapat mengakses customer miliknya.
- Sales tidak boleh melihat customer atau performa Sales lain.
- Akses data harus diperiksa di backend.
- Lead baru dari Marketing tidak dapat diproses Sales sebelum ditugaskan.
- Akses Management mengikuti kewenangan yang ditetapkan perusahaan.

**DEC-014 — Jumlah follow-up**
- Jumlah follow-up sebelum lead dinyatakan Loss bersifat fleksibel.
- Sales menentukan jumlah follow-up berdasarkan kondisi customer.
- Alasan perubahan menjadi Loss harus dicatat.
- Sales Leader dapat memantau aktivitas dan alasan tersebut sesuai kewenangannya.

**DEC-015 — Pengingat follow-up otomatis**
Status: **DITUNDA**

Kebutuhan notifikasi berjenjang melalui web push, WhatsApp, dan email belum menjadi prioritas implementasi saat ini.

**DEC-016 — Data end-user untuk Reseller**
- Nama end-user tidak wajib diisi.
- Informasi end-user dapat ditambahkan jika tersedia.
- Data dapat diperbarui kemudian.

**DEC-017 — Riwayat komunikasi customer**
- Seluruh komunikasi Sales dengan customer tidak wajib dicatat.
- Pencatatan komunikasi dapat dilakukan jika diperlukan.

**DEC-018 — Kalender Sales**
Kalender hanya digunakan untuk:
- Jadwal meeting dengan customer.
- Tanggal dan waktu meeting.
- Lokasi atau media meeting.
- Customer yang akan ditemui.

Tidak mencakup jadwal follow-up, survey, atau deadline quotation.

**DEC-019 — Penentuan KPI Sales**
- Management menjadi pihak yang menentukan KPI dan target penjualan.
- Target dapat ditetapkan untuk setiap Sales.
- CRM menampilkan target dan realisasi.
- Tidak perlu membuat role Sales Leader khusus untuk kebutuhan penentuan KPI.

**DEC-020 — Perhitungan omzet Sales**
- Omzet penjualan dihitung ketika PO resmi customer diterima.
- Nilai PO menjadi dasar pencapaian omzet Sales.
- DP dan pelunasan dipantau secara terpisah.
- Status Closing project tetap mengikuti persyaratan Closing.

**DEC-021 — Target margin**
Status: **PENDING**

Belum ada keputusan mengenai target margin minimum.

Berdasarkan wawancara Sales:
- Margin end-user dapat lebih tinggi.
- Margin reseller atau tender kompetitif dapat lebih rendah.
- Margin bergantung pada kondisi negosiasi.

Angka minimum margin harus menunggu keputusan Management.

---

### B. Purchasing, Admin, dan Finance

**DEC-022 — Penerimaan barang tanpa invoice supplier**
- Barang boleh diterima meskipun invoice supplier belum diterima.
- Goods Receipt tetap dapat dibuat.
- Sistem harus mencatat invoice supplier yang belum diterima.
- Invoice dapat dihubungkan dengan transaksi setelah diterima.

**DEC-023 — Penerimaan barang bertahap**
CRM wajib mendukung Partial Goods Receipt.

Sistem mencatat:
- Jumlah barang dipesan.
- Jumlah barang diterima.
- Jumlah barang tersisa.
- Tanggal penerimaan.
- Bukti penerimaan.

Status penerimaan:
- Belum diterima.
- Diterima sebagian.
- Diterima seluruhnya.

**DEC-024 — Deadline pengiriman**
- Deadline dicatat berdasarkan PO atau SPK.
- Sistem memberikan peringatan sebelum deadline.
- Sistem memberikan peringatan jika deadline terlewati.
- Admin dan Purchasing dapat memantau keterlambatan.

**DEC-025 — Status pengiriman dan penerimaan customer**
Status harus dibedakan:
- Belum dikirim.
- Dikirim.
- Diterima sebagian.
- Diterima seluruhnya.

Bukti penerimaan harus dapat dilampirkan dan ditelusuri.

**DEC-026 — BAST**
- BAST wajib tercatat di CRM sebelum project Closing.
- Nomor dan tanggal BAST disimpan.
- Dokumen BAST dapat dilihat oleh pihak berwenang.
- Riwayat perubahan BAST dapat ditelusuri.

**DEC-027 — Variasi skema pembayaran customer**
CRM harus mendukung berbagai variasi pembayaran:
- DP.
- Termin pembayaran.
- Pelunasan.
- Pembayaran bertahap.

Aturannya:
- Jumlah termin dapat berbeda setiap project.
- Persentase atau nominal termin dapat disesuaikan.
- Jadwal pembayaran dapat diatur berdasarkan kesepakatan.
- Sistem menghitung total tagihan dan sisa pembayaran.
- Pembayaran memiliki riwayat lengkap.

---

## 2. Persyaratan Project Closing

Project hanya dapat dinyatakan Closing apabila seluruh persyaratan berikut terpenuhi:

1. Instalasi selesai.
2. Semua barang sudah dikirim dan diterima sesuai kebutuhan project.
3. BAST sudah selesai dan tercatat.
4. Pembayaran customer sudah lunas.

Progress workflow, status pembayaran, dan status Closing harus menjadi informasi yang berbeda tetapi saling terhubung.

---

## 3. Daftar Keputusan Pending dan Ditunda

| ID | Topik | Status |
|---|---|---|
| DEC-015 | Pengingat follow-up otomatis | Ditunda |
| DEC-021 | Target margin minimum | Pending |

Keputusan yang berstatus pending atau ditunda tidak boleh dianggap sebagai kebijakan final.

---

## 4. Prinsip Implementasi

1. Jangan mengubah kode atau database hanya berdasarkan asumsi.
2. Periksa seluruh relasi dan penggunaan tabel sebelum migrasi.
3. Pertahankan riwayat transaksi dan dokumen historis.
4. Pastikan hak akses diperiksa di backend.
5. Pisahkan status project, invoice, pembayaran, dan pengiriman.
6. Hindari penghapusan data yang berpotensi menghilangkan riwayat transaksi.
7. Setiap perubahan penting harus dapat diaudit.
8. Keputusan yang belum disetujui Management harus tetap ditandai pending.

---

## 5. Tujuan Phase 2.1

Dokumen ini menjadi dasar untuk:

- Mencocokkan hasil audit database Phase 2 dengan keputusan bisnis.
- Mengidentifikasi perubahan database yang diperlukan.
- Mengidentifikasi konflik antara database dan proses bisnis.
- Menyusun rekomendasi perbaikan.
- Menentukan prioritas implementasi berikutnya.

**Batasan:** Dokumen ini belum memberikan persetujuan untuk mengubah source code, schema database, migration, atau data production.

Semua perubahan harus menunggu hasil analisis dan persetujuan implementasi berikutnya.

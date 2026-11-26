# INITIAL BUSINESS DISCOVERY

## 3DY App — Business Process Discovery

**Status:** Initial Discovery
**Versi:** 1.0
**Basis:** Tiga dokumen hasil wawancara Sales, Admin, dan Manajemen
**Tujuan:** Menjadi acuan pemetaan proses bisnis, kebutuhan sistem, validasi aturan bisnis, dan pengembangan CRM 3DY App.

---

# 1. Executive Summary

3DY App dikembangkan untuk menyatukan proses operasional perusahaan yang saat ini masih tersebar di WhatsApp, Google Spreadsheet, Google Drive, dan pencatatan manual.

Berdasarkan tiga wawancara, kebutuhan utama perusahaan adalah membangun sistem CRM yang menghubungkan proses:

**Marketing → Sales → Estimator → Inside Sales → Teknisi → Admin/Purchasing → Finance → Management**

Sistem diharapkan mampu mengelola perjalanan prospek hingga proyek selesai, termasuk:

- Distribusi dan penugasan lead.
- Pengelolaan customer dan DAR.
- Pembuatan quotation.
- Permintaan dan histori harga dari supplier.
- Pengelolaan PO customer dan supplier.
- Penerimaan serta pengiriman barang.
- Pelaksanaan instalasi.
- BAST.
- Invoice dan pembayaran.
- Monitoring performa Sales.
- Dashboard manajemen.

Ada tiga kebutuhan lintas departemen yang sangat penting:

1. **Single Source of Truth:** Seluruh data customer dan proyek harus terpusat.
2. **Workflow Integration:** Perubahan status pada satu departemen harus dapat menjadi informasi bagi departemen berikutnya.
3. **Data Access Control:** Setiap divisi hanya dapat mengakses data sesuai kewenangannya.

Dokumen ini merupakan hasil discovery awal. Seluruh kebutuhan belum otomatis menjadi keputusan desain atau aturan final sistem.

---

# 2. Sumber Discovery

| ID | Sumber | Fokus |
|---|---|---|
| SRC-01 | Wawancara Sales.md | Aktivitas Sales dari lead hingga pengiriman |
| SRC-02 | summary_hasil_wawancara_user_admin.md | Purchasing, logistik, invoicing, dan finance |
| SRC-03 | summary_hasil_rekaman_meet_ruangan_bapak.md | Arahan manajemen, privasi, monitoring, dan AI |

## 2.1 Batasan sumber

Discovery ini baru menggunakan tiga sumber tersebut.

Belum tersedia wawancara langsung yang terpisah dan mendalam dengan seluruh pelaksana operasional, khususnya:

- Marketing.
- Estimator.
- Inside Sales.
- Teknisi.
- Admin Invoice.
- Admin Purchasing.
- Finance.

Beberapa kebutuhan berasal dari perspektif Sales atau arahan Manajemen, sehingga perlu dikonfirmasi kepada pelaksana proses terkait.

---

# 3. Struktur Organisasi dan Peran Operasional

## 3.1 Entitas perusahaan

Operasional penjualan mencakup empat entitas:

- MBK (MGK)
- NTI
- TPS
- WANI

Setiap proses yang melibatkan customer dan quotation perlu memperhatikan identitas PT yang digunakan.

## 3.2 Pemetaan peran

| Departemen/Peran | Tanggung Jawab Berdasarkan Wawancara |
|---|---|
| Marketing | Menghasilkan dan memasukkan prospek opt-in |
| Sales Leader | Membagikan lead dan memonitor performa Sales |
| Sales / AM | Mengelola customer, DAR, komunikasi, dan negosiasi |
| Estimator | Mencari dan memasukkan harga modal supplier |
| Inside Sales | Membantu penyusunan quotation |
| Teknisi | Memberikan estimasi pekerjaan dan melaksanakan instalasi |
| Admin Purchasing | PO supplier, penerimaan barang, pengiriman, invoice supplier |
| Admin Invoice | Menyiapkan PI dan invoice customer |
| Finance | Memantau pembayaran dan pelunasan |
| Management | Monitoring performa, target, dan perkembangan bisnis |

Catatan: Pembagian di atas merupakan pemetaan tanggung jawab dari wawancara, bukan struktur organisasi final.

---

# 4. Business Process Map

## 4.1 Gambaran proses utama

```text
MARKETING
   |
   v
Lead / Opt-in
   |
   v
SALES LEADER
   |
   v
Assign Lead ke Sales
   |
   v
SALES
   |
   +--> Pemeriksaan Customer
   |
   +--> DAR dan Follow-up
   |
   +--> Koordinasi Teknisi
   |
   +--> Permintaan Harga
           |
           v
       ESTIMATOR
           |
           v
       Harga Modal
           |
           v
       INSIDE SALES
           |
           v
       Quotation
           |
           v
       Sales Cross-check
           |
           v
       Penawaran ke Customer
           |
           v
       Negosiasi / PO Customer
           |
           v
       ADMIN
           |
           +--> PI / Invoice
           |
           +--> Purchasing
           |
           +--> PO Supplier
           |
           +--> Penerimaan Barang
           |
           +--> Pengiriman Barang
           |
           v
       TEKNISI
           |
           v
       Instalasi dan BAST
           |
           v
       FINANCE
           |
           v
       Pelunasan Customer
           |
           v
       CLOSING
           |
           v
       MANAGEMENT DASHBOARD
```

Diagram tersebut adalah gambaran konseptual berdasarkan wawancara. Urutan detail, dependensi, dan kemungkinan proses paralel masih perlu divalidasi.

---

# 5. Business Discovery — Departemen Sales

**Sumber utama:** SRC-01 dan SRC-03.

## 5.1 Kondisi operasional saat ini

Aktivitas Sales masih menggunakan beberapa media berbeda:

- WhatsApp untuk komunikasi dan koordinasi.
- Google Spreadsheet untuk DAR, kalkulasi proyek, dan daftar PO.
- Google Drive untuk penyimpanan dokumen.
- Buku catatan manual untuk aktivitas harian.

Dampak yang disebutkan dalam wawancara:

1. Riwayat komunikasi sulit ditemukan.
2. Harga lama sulit dilacak.
3. Dokumen proyek tersebar.
4. Sales harus melakukan pencarian manual.
5. Proyek yang berlangsung berbulan-bulan lebih sulit dipantau.

**Kebutuhan:** Seluruh aktivitas customer dan proyek harus tersedia dalam satu profil terpusat.

## 5.2 Distribusi lead

Alur yang disampaikan:

1. Marketing memasukkan lead opt-in.
2. Lead masuk ke grup Sales berdasarkan kode PT.
3. Sales Leader membagikan lead kepada Sales.
4. Sales yang ditugaskan menangani lead tersebut.

Manajemen menegaskan bahwa lead baru harus terkunci sampai di-assign.

Setelah assignment, hanya Sales yang ditugaskan yang boleh membuka dan memproses lead tersebut.

### Kebutuhan sistem

- Lead assignment.
- Assignment owner.
- Assignment timestamp.
- Status acknowledgement.
- Notifikasi assignment.
- Riwayat perpindahan assignment.
- Pembatasan akses berdasarkan pemilik lead.

## 5.3 Pipeline Sales

| Status | Definisi Berdasarkan Wawancara |
|---|---|
| Cool | Lead baru dari Marketing yang baru di-assign |
| Warm | Komunikasi aktif dan follow-up berkala |
| Hot | Peluang di atas 50%, sudah ada survei, layout, atau indikasi kuat membeli |
| Won / PO | Pesanan resmi sudah diterima |
| Loss | Batal atau tidak merespons setelah 1–3 follow-up |

Catatan:

- Won/PO belum berarti seluruh proyek selesai.
- Setelah PO diterima, Sales tetap mengawal DP, pengiriman, dan pelunasan.
- Loss perlu ditandai agar tidak terus dipertanyakan oleh Leader.

### Hal yang perlu dikonfirmasi

- Apakah status Warm dan Hot dapat berubah kembali?
- Siapa yang berwenang mengubah status menjadi Won?
- Apakah Loss membutuhkan alasan wajib?
- Bagaimana menangani customer yang kembali aktif setelah Loss?
- Apakah batas 1–3 follow-up berlaku untuk seluruh kategori customer?

## 5.4 Daily Activity Report (DAR)

Informasi yang disebutkan sebagai data wajib:

- Tanggal.
- Kategori customer: End-User atau Reseller.
- Nama PT.
- Alamat.
- Nama PIC.
- Nomor WhatsApp.
- Email.
- Persentase peluang proyek.
- Nama proyek.
- Nama end-user akhir jika customer adalah reseller.
- Kebutuhan spesifik customer.

Setiap follow-up pada tanggal berbeda harus menghasilkan catatan baru agar riwayat komunikasi bersifat kronologis.

Manajemen meminta DAR diperbarui sebelum Sales dapat melanjutkan tahapan proses.

### Kebutuhan sistem

- DAR memiliki tanggal dan waktu.
- DAR terhubung dengan customer dan proyek.
- Riwayat DAR tidak tertimpa oleh update berikutnya.
- Ada kolom rencana follow-up.
- Perubahan tahap dapat divalidasi berdasarkan kelengkapan DAR.
- Aktivitas DAR dapat digunakan dalam laporan kinerja.

## 5.5 Pembuatan quotation

Format nomor quotation berdasarkan wawancara:

`[Kode PT] / [Kode & Nomor Urut] / [Inisial Sales] / [Tahun]`

Contoh struktur:

`MBK / [Kode-Nomor] / AS / [Tahun]`

Alur:

1. Sales menerima lead atau BOQ.
2. Sales memeriksa kebutuhan customer.
3. Sales meminta bantuan teknisi jika spesifikasi belum jelas.
4. Sales meminta Estimator mencari harga beli supplier.
5. Sales memberikan data kepada Inside Sales.
6. Inside Sales membuat draft quotation.
7. Sales memeriksa kuantitas, tipe barang, dan nominal.
8. Sales mengirim quotation kepada customer.

Data yang dibutuhkan untuk quotation meliputi:

- PT.
- Alamat customer.
- PIC.
- Harga beli distributor.
- Stok.
- Garansi.
- Margin.
- Item dan kuantitas.

## 5.6 Aturan revisi quotation

Terdapat dua kondisi berbeda:

**Kondisi A — Sebelum dikirim ke customer**

Jika ada tambahan komponen atau biaya instalasi, item digabungkan ke quotation yang sama tanpa membuat revisi.

**Kondisi B — Setelah dikirim ke customer**

Jika terjadi negosiasi penurunan harga atau margin, dibuat dokumen revisi dengan nomor induk yang tetap.

### Kebutuhan sistem

- Nomor quotation induk.
- Nomor revisi.
- Riwayat perubahan item.
- Riwayat perubahan harga.
- Status draft, sent, revised, dan status lain yang akan divalidasi.
- Audit trail perubahan.

Status di atas merupakan usulan kebutuhan pencatatan, bukan daftar status final yang telah disepakati.

## 5.7 Penentuan margin

Margin tidak memiliki satu persentase tetap.

Contoh praktik yang dijelaskan:

- End-user: margin awal lebih tinggi untuk menyediakan ruang negosiasi.
- Produk komoditas dengan persaingan ketat: margin lebih tipis.

Karena itu, sistem perlu mendukung pengaturan harga jual yang fleksibel.

Estimator bertugas memasukkan harga modal. Sales dan Inside Sales menentukan harga jual quotation.

Master harga jual default hanya boleh diubah Admin, Purchasing, atau Super Admin berdasarkan wawancara Sales.

Kebutuhan ini perlu divalidasi bersama Manajemen karena berhubungan dengan pembatasan akses harga.

## 5.8 Koordinasi teknisi untuk estimasi instalasi

Dua jenis pekerjaan dijelaskan:

| Jenis Pekerjaan | Kebutuhan Estimasi |
|---|---|
| Konfigurasi software/jaringan | Teknisi dapat memperkirakan langsung |
| Pekerjaan fisik/penarikan kabel | Memerlukan layout, estimasi panjang kabel, atau survei |

Kebutuhan sistem:

- Permintaan estimasi biaya teknisi.
- Informasi jenis pekerjaan.
- Lampiran layout.
- Hasil estimasi.
- Status permintaan.
- Riwayat komunikasi dan perubahan estimasi.

## 5.9 Kebutuhan notifikasi lead

Terdapat usulan eskalasi tiga lapis:

| Waktu | Notifikasi | Tujuan |
|---|---|---|
| Menit ke-0 | Web Push | Memberi tahu Sales bahwa lead telah di-assign |
| Menit ke-15 | WhatsApp | Mengingatkan jika belum acknowledge atau follow-up |
| Menit ke-30 | Email dan dashboard Leader | Eskalasi kepada Sales dan Leader |

Nomor WhatsApp yang disebutkan dalam wawancara merupakan nomor testing internal.

Detail implementasi, kondisi pemicu, dan pengecualian perlu dikonfirmasi sebelum menjadi SLA final.

## 5.10 Kebutuhan tambahan Sales

- Kalender kunjungan dan kegiatan.
- Monitoring target omzet bulanan.
- Monitoring margin keuntungan kotor.
- Riwayat harga customer.
- Dokumen PO customer.
- Bukti pengiriman.
- Update progres teknisi.
- Profil customer terpusat.

---

# 6. Business Discovery — Estimator dan Inside Sales

**Sumber utama:** SRC-01 dan SRC-03.

## 6.1 Tanggung jawab Estimator

Estimator bertanggung jawab mencari harga beli dari supplier dan memasukkan harga modal ke sistem.

Informasi yang diperlukan:

- Item barang.
- Supplier.
- Harga modal.
- Tanggal update otomatis.
- Customer atau proyek terkait.
- Riwayat harga sebelumnya.

Supplier diwajibkan pada setiap input harga modal baru.

Lampiran pada permintaan harga bersifat opsional.

## 6.2 Price Request

Manajemen menginginkan halaman khusus untuk permintaan harga.

Kebutuhan:

- Permintaan harga dari Sales/AM.
- Identitas Sales pemohon.
- Customer.
- PT.
- Nama proyek.
- Item yang diminta.
- Supplier.
- Harga modal.
- Timestamp otomatis.
- Riwayat perubahan harga.

## 6.3 Histori harga

Ketika Inside Sales memilih item pada quotation, sistem diharapkan menampilkan:

- Harga terbaru.
- Tanggal update.
- Harga sebelumnya.
- Histori perubahan.

Tujuannya agar Inside Sales dapat membandingkan harga terbaru dengan harga lama.

## 6.4 Tanggung jawab Inside Sales

Inside Sales membantu Sales menyusun dokumen quotation.

Kebutuhan:

- Mengakses price list.
- Memilih item.
- Mengakses histori harga.
- Menyusun draft quotation.
- Mengelola nomor quotation.
- Menyimpan informasi stok dan garansi.
- Menyediakan informasi margin sesuai hak akses.

## 6.5 Laporan Estimator

Manajemen meminta laporan mingguan yang dapat diekspor ke Excel.

Informasi minimal:

- Nama Sales pemohon.
- Tanggal permintaan.
- Item.
- PT.
- Customer/proyek.
- Harga yang diberikan Estimator.

---

# 7. Business Discovery — Admin, Purchasing, dan Finance

**Sumber utama:** SRC-02 dan SRC-03.

## 7.1 Masalah operasional

Admin saat ini harus mengetik ulang data quotation yang sudah deal ke spreadsheet.

Data quotation masuk dan data PO supplier juga berada di spreadsheet berbeda.

Akibatnya, data yang sama harus dimasukkan berulang kali.

**Kebutuhan utama:** Admin dapat mengambil data quotation dari CRM setelah transaksi mencapai kondisi yang ditentukan.

Kondisi pemicu penarikan data perlu dikonfirmasi: apakah saat PO customer diterima, status Won, atau setelah verifikasi dokumen.

## 7.2 Pembagian fungsi Admin

Dua bagian utama:

**Purchasing**
- Pengadaan barang.
- PO supplier.
- Penerimaan barang.
- Pengiriman barang ke customer.
- Invoice supplier.

**Invoicing / Finance**
- PI.
- Invoice customer.
- Penagihan.
- Pembayaran.
- Laporan keuangan.

Prioritas awal yang dinyatakan Admin adalah Purchasing sebelum akuntansi atau laporan keuangan mendalam.

## 7.3 Master data barang

Master barang harus berdiri sendiri, tidak hanya berupa input bebas di form PO.

Kolom yang diminta:

- Kode barang internal.
- PN Number supplier.
- Serial Number.
- Kategori barang atau jasa.

PO supplier harus dapat mengambil item dari master tersebut.

## 7.4 Pemecahan item quotation menjadi item PO

Satu item paket dalam quotation customer dapat membutuhkan beberapa item berbeda ketika dibuat menjadi PO supplier.

Contoh yang disebutkan: paket perangkat Cisco dapat dipecah menjadi hardware dan license tambahan.

Kebutuhan sistem:

- Relasi quotation item dengan PO item.
- Dukungan pemecahan satu item menjadi beberapa komponen.
- Kuantitas komponen.
- Kode item supplier.
- Harga pembelian per komponen.
- Riwayat pemetaan item.

Detail struktur paket dan aturan konversinya masih perlu divalidasi.

## 7.5 Penerimaan barang parsial

Barang dari supplier dapat dikirim bertahap.

Sistem harus bisa membedakan:

- Barang sudah dipesan.
- Barang belum dikirim supplier.
- Barang sudah diterima sebagian.
- Sisa barang belum diterima.
- Barang sudah diterima lengkap.

Kebutuhan tambahan:

- Tanggal penerimaan.
- Kuantitas diterima.
- Referensi PO supplier.
- Bukti penerimaan.
- Riwayat penerimaan bertahap.

## 7.6 Invoice supplier

Barang dapat diterima sebelum invoice supplier datang.

Karena itu, penerimaan barang tidak boleh diblokir hanya karena invoice supplier belum tersedia.

Sistem harus menyediakan laporan:

**Barang sudah diterima tetapi invoice supplier belum masuk.**

## 7.7 Handoff Teknisi dan BAST

Setelah barang dikirim dan teknisi menyelesaikan instalasi, teknisi mendapatkan BAST.

Nomor dan data BAST dimasukkan ke sistem Admin.

BAST menjadi salah satu prasyarat proses invoicing customer.

Kebutuhan:

- Nomor BAST.
- Tanggal BAST.
- Dokumen BAST.
- Relasi dengan customer dan proyek.
- Status pekerjaan teknisi.
- Informasi penyelesaian instalasi.

## 7.8 Deadline SPK

SPK customer dapat memiliki batas waktu pengiriman barang.

Keterlambatan dapat menyebabkan denda.

Sistem perlu mencatat:

- Tanggal quotation masuk.
- Tanggal deadline pengiriman.
- Status pengiriman.
- Peringatan mendekati deadline.
- Peringatan keterlambatan.

Nilai denda, cara perhitungan, dan pihak yang bertanggung jawab belum dijelaskan dalam sumber.

## 7.9 Kriteria Closing

Berdasarkan wawancara Admin, proyek baru dianggap Closing dan masuk pencapaian/performa Sales apabila seluruh kondisi berikut terpenuhi:

1. Instalasi selesai.
2. Seluruh barang sudah dikirim.
3. BAST selesai.
4. Customer sudah melunasi pembayaran.

Kriteria ini merupakan aturan bisnis penting yang perlu diselaraskan dengan definisi status Won/PO pada pipeline Sales.

Won/PO dan Closing tidak boleh dianggap sebagai istilah yang sama tanpa keputusan bisnis lebih lanjut.

## 7.10 Validasi nomor dokumen

Sistem harus menolak nomor dokumen yang sudah terdaftar.

Cakupan yang disebutkan:

- Nomor quotation.
- Nomor voucher.
- Nomor dokumen terkait.

Perlu dikonfirmasi apakah validasi unik berlaku global, per PT, per jenis dokumen, atau kombinasi beberapa atribut.

## 7.11 Privasi data Admin

Sales tidak boleh mengakses menu Admin yang menampilkan harga modal supplier.

Setiap divisi memiliki dashboard dan tracker sesuai kewenangannya.

---

# 8. Business Discovery — Management

**Sumber utama:** SRC-03.

## 8.1 Isolasi data Sales

Sales hanya boleh melihat:

- Profil sendiri.
- Target sendiri.
- Customer sendiri.
- Pekerjaan sendiri.

Sales tidak boleh melihat performa dan customer milik Sales lain.

## 8.2 Akses Sales Leader

Sales Leader memiliki akses untuk:

- Melihat performa seluruh tim.
- Melihat persentase pencapaian target.
- Memilih Sales tertentu.
- Melihat detail pekerjaan Sales tersebut.
- Melihat customer yang sedang ditangani.

Hak akses ini berbeda dari Sales biasa.

## 8.3 Dashboard Sales

Dashboard Sales diharapkan menampilkan:

- Ringkasan lead aktif.
- Ringkasan close bulan ini.
- Daftar customer terbaru.
- Progres customer.
- Search bar.

DAR dapat diinput langsung dari daftar customer terkait.

## 8.4 Target Sales

Sales Leader bertugas mengatur target penjualan masing-masing Sales.

Ketika Finance memperbarui status invoice menjadi lunas, dashboard Sales terkait harus memperbarui pencapaian target secara otomatis.

Detail rumus target dan perhitungan pencapaian omzet versus margin masih perlu dipastikan.

## 8.5 Visi AI internal

Manajemen menyampaikan rencana integrasi AI privat yang di-hosting di VPS perusahaan.

Rencana penggunaan:

- Merangkum progres mingguan Sales.
- Memberikan rekomendasi tindak lanjut.
- Membantu peluang upscaling.
- Membantu Sales menyusun pertanyaan teknis untuk customer.
- Menyimpan percakapan sebagai referensi database.

Estimasi pengguna yang disebutkan adalah sekitar 15–20 orang.

AI merupakan rencana jangka panjang, bukan kebutuhan operasional yang sudah diputuskan untuk implementasi tahap awal.

## 8.6 Kepemilikan sistem

Manajemen menginginkan tim developer internal tetap menjaga source code dan data perusahaan.

Visi jangka panjang:

- CRM menjadi produk komersial.
- Pengembangan divisi programming internal.
- Pengembangan aplikasi bisnis lain.

Hal ini menjadi konteks strategis untuk mempertimbangkan maintainability, modularitas, keamanan, dan kepemilikan data.

---

# 9. Business Rules Register

Daftar berikut merangkum aturan yang muncul dalam wawancara.

| ID | Aturan Bisnis | Sumber | Status |
|---|---|---|---|
| BR-001 | Lead Marketing terkunci sebelum di-assign | SRC-03 | Perlu validasi detail |
| BR-002 | Lead yang sudah di-assign hanya diproses Sales terkait | SRC-03 | Kebutuhan eksplisit |
| BR-003 | Sales mencatat DAR untuk follow-up | SRC-01 | Kebutuhan eksplisit |
| BR-004 | Follow-up pada tanggal berbeda dicatat sebagai baris baru | SRC-01 | Kebutuhan eksplisit |
| BR-005 | DAR wajib diperbarui sebelum tahap berikutnya | SRC-03 | Perlu definisi tahap |
| BR-006 | Hot Prospect memiliki peluang di atas 50% | SRC-01 | Kebutuhan eksplisit |
| BR-007 | Quotation menggunakan identitas PT, kode/nomor, inisial Sales, dan tahun | SRC-01 | Perlu format final |
| BR-008 | Tambahan item sebelum quotation dikirim tidak membuat revisi | SRC-01 | Kebutuhan eksplisit |
| BR-009 | Negosiasi setelah quotation dikirim menghasilkan revisi | SRC-01 | Kebutuhan eksplisit |
| BR-010 | Estimator memasukkan harga modal | SRC-01, SRC-03 | Kebutuhan eksplisit |
| BR-011 | Supplier wajib pada input harga modal baru | SRC-01 | Kebutuhan eksplisit |
| BR-012 | Harga jual diatur Sales/Inside Sales | SRC-01 | Perlu validasi akses |
| BR-013 | Master harga jual default dibatasi untuk Admin/Purchasing/Super Admin | SRC-01 | Perlu validasi |
| BR-014 | Penerimaan barang tidak harus menunggu invoice supplier | SRC-02 | Kebutuhan eksplisit |
| BR-015 | Satu PO supplier dapat diterima bertahap | SRC-02 | Kebutuhan eksplisit |
| BR-016 | Barang diterima tanpa invoice supplier harus dapat dipantau | SRC-02 | Kebutuhan eksplisit |
| BR-017 | Nomor dokumen duplikat ditolak | SRC-02 | Perlu cakupan unik |
| BR-018 | Sales tidak boleh mengakses menu Admin yang berisi harga modal | SRC-02 | Kebutuhan eksplisit |
| BR-019 | BAST menjadi prasyarat proses invoicing | SRC-02 | Perlu detail pengecualian |
| BR-020 | Closing membutuhkan instalasi, pengiriman, BAST, dan pelunasan | SRC-02 | Perlu validasi lintas divisi |
| BR-021 | Sales hanya melihat customer dan performanya sendiri | SRC-03 | Kebutuhan eksplisit |
| BR-022 | Sales Leader dapat melihat performa dan detail tim | SRC-03 | Kebutuhan eksplisit |
| BR-023 | Pembayaran lunas memperbarui pencapaian target Sales | SRC-03 | Perlu rumus final |

---

# 10. Data dan Dokumen yang Perlu Dikelola

| Data | Pemilik Proses | Konsumen Data |
|---|---|---|
| Lead | Marketing | Sales Leader, Sales |
| Assignment | Sales Leader | Sales, Management |
| Customer | Sales | Inside Sales, Admin, Teknisi |
| DAR | Sales | Sales Leader, Management |
| Price Request | Sales/AM | Estimator |
| Harga Modal | Estimator | Inside Sales sesuai izin |
| Price History | Estimator | Inside Sales |
| Quotation | Inside Sales/Sales | Customer, Admin |
| PO Customer | Sales | Admin |
| PI | Admin Invoice | Sales, Customer |
| PO Supplier | Purchasing | Supplier, Admin |
| Goods Receipt | Purchasing | Admin, Teknisi |
| Delivery Proof | Purchasing/Logistik | Sales, Admin |
| Progres Instalasi | Teknisi | Sales, Admin |
| BAST | Teknisi/Admin | Admin Invoice |
| Invoice Customer | Admin Invoice | Finance, Sales |
| Pembayaran | Finance | Management, Sales |
| Target Sales | Sales Leader | Sales, Management |

Daftar ini adalah pemetaan awal berdasarkan sumber, bukan definisi final kepemilikan data secara teknis.

---

# 11. Cross-Department Dependencies

| No. | Proses Awal | Pihak Berikutnya | Ketergantungan |
|---|---|---|---|
| 1 | Marketing membuat lead | Sales Leader | Lead perlu di-assign |
| 2 | Sales menerima lead | Sales | Assignment menentukan pemilik |
| 3 | Sales membutuhkan harga | Estimator | Harga modal dibutuhkan |
| 4 | Estimator memberikan harga | Inside Sales | Harga digunakan dalam quotation |
| 5 | Sales membutuhkan estimasi instalasi | Teknisi | Biaya pekerjaan perlu dihitung |
| 6 | Inside Sales membuat quotation | Sales | Sales melakukan cross-check |
| 7 | Customer menerbitkan PO | Admin Invoice | Data digunakan untuk PI |
| 8 | Customer membayar | Purchasing | Pengadaan supplier dilakukan |
| 9 | Purchasing menerima barang | Teknisi | Barang tersedia untuk instalasi |
| 10 | Teknisi menyelesaikan instalasi | Admin | BAST menjadi prasyarat invoicing |
| 11 | Admin menerbitkan invoice | Finance | Pembayaran dipantau |
| 12 | Finance menerima pelunasan | Management/Sales | Pencapaian target diperbarui |

---

# 12. Gap dan Risiko Operasional yang Teridentifikasi

Temuan berikut berasal dari masalah yang dijelaskan narasumber. Ini bukan hasil audit teknis implementasi kode.

| ID | Masalah | Dampak |
|---|---|---|
| GAP-001 | Data tersebar di berbagai media | Informasi sulit ditemukan |
| GAP-002 | Input data berulang oleh Admin | Risiko salah input dan pekerjaan ganda |
| GAP-003 | Histori harga tidak terpusat | Sulit membandingkan harga lama |
| GAP-004 | Lead berpotensi terlewat | Follow-up tidak konsisten |
| GAP-005 | Riwayat DAR tersebar | Monitoring aktivitas sulit |
| GAP-006 | Dokumen proyek tidak terpusat | Dokumen sulit dilacak |
| GAP-007 | Quotation dapat memiliki nomor ganda | Risiko duplikasi administrasi |
| GAP-008 | Penerimaan barang parsial sulit dipantau | Risiko ketidaksesuaian stok dan pengiriman |
| GAP-009 | Invoice supplier datang terlambat | Kewajiban tagihan sulit dipantau |
| GAP-010 | Deadline SPK berisiko terlewat | Potensi denda |
| GAP-011 | Progres instalasi tidak terhubung | Sales sulit memberikan informasi akurat |
| GAP-012 | Dashboard lintas divisi berisiko membuka data sensitif | Risiko kebocoran harga modal |
| GAP-013 | Definisi Won dan Closing belum selaras | Laporan kinerja dapat berbeda |
| GAP-014 | Perhitungan target belum terdefinisi lengkap | Potensi perbedaan angka pencapaian |

---

# 13. Open Business Decisions

Bagian ini berisi hal-hal yang belum cukup jelas untuk ditetapkan sebagai aturan final.

| ID | Pertanyaan | Pihak yang Perlu Mengonfirmasi |
|---|---|---|
| DEC-001 | Pada tahap Warm atau Hot, kapan customer resmi dianggap menjadi customer aktif? | Management, Sales Leader |
| DEC-002 | Apakah peluang di atas 50% merupakan satu-satunya syarat Hot? | Sales Leader |
| DEC-003 | Apakah Sales boleh melihat harga modal supplier? | Management, Finance |
| DEC-004 | Siapa yang boleh mengubah margin dan harga jual default? | Management, Purchasing |
| DEC-005 | Apakah manual progress proyek boleh mengesampingkan progres workflow otomatis? | Management, Teknisi, Admin |
| DEC-006 | Apakah Admin atau Teknisi boleh membuat proyek langsung tanpa lead Sales? | Management |
| DEC-007 | Apakah Won/PO dan Closing merupakan dua status yang berbeda? | Management, Sales, Finance |
| DEC-008 | Kapan data quotation boleh ditarik Admin? | Admin, Sales |
| DEC-009 | Bagaimana aturan pembatalan PO supplier? | Purchasing |
| DEC-010 | Bagaimana menangani retur barang? | Purchasing |
| DEC-011 | Bagaimana menangani pembayaran sebagian? | Finance |
| DEC-012 | Apakah Closing memerlukan seluruh invoice lunas atau ada pengecualian? | Finance, Management |
| DEC-013 | Bagaimana definisi nomor dokumen unik? | Admin |
| DEC-014 | Bagaimana menangani customer reseller dengan banyak end-user? | Sales |
| DEC-015 | Siapa yang menyetujui revisi quotation? | Management, Sales Leader |
| DEC-016 | Bagaimana SLA dan pengecualian eskalasi notifikasi lead? | Sales Leader |
| DEC-017 | Bagaimana target omzet dan margin dihitung? | Management, Finance |
| DEC-018 | Apakah jadwal teknisi harus melalui approval? | Teknisi, Management |
| DEC-019 | Siapa yang bertanggung jawab jika BAST belum tersedia? | Teknisi, Admin |
| DEC-020 | Bagaimana aturan denda keterlambatan SPK? | Management, Admin |

Tidak ada keputusan pada tabel ini yang boleh diasumsikan sudah disepakati.

---

# 14. Interview Coverage dan Discovery Berikutnya

Tiga wawancara memberikan gambaran awal, tetapi belum cukup untuk membentuk SOP final.

Prioritas wawancara lanjutan:

## 14.1 Marketing

Pertanyaan utama:
- Dari mana lead diperoleh?
- Apa kriteria lead valid?
- Bagaimana opt-in dilakukan?
- Data apa yang wajib diisi?
- Bagaimana lead dikirim ke Sales?
- Bagaimana menangani lead duplikat?

## 14.2 Estimator

Pertanyaan utama:
- Bagaimana mencari supplier?
- Bagaimana menentukan harga modal?
- Bagaimana menangani harga yang kedaluwarsa?
- Apakah satu item memiliki beberapa supplier?
- Bagaimana menyimpan histori harga?
- Apakah ada approval harga tertentu?

## 14.3 Inside Sales

Pertanyaan utama:
- Bagaimana membuat quotation?
- Siapa yang memberikan nomor quotation?
- Siapa yang menyetujui quotation?
- Bagaimana mengelola revisi?
- Bagaimana menangani perubahan item?
- Bagaimana menangani quotation paket?

## 14.4 Teknisi

Pertanyaan utama:
- Bagaimana menerima pekerjaan?
- Bagaimana menentukan jadwal instalasi?
- Bagaimana mencatat progres?
- Bagaimana menangani pekerjaan tertunda?
- Bagaimana membuat BAST?
- Bagaimana mengelola instalasi parsial?

## 14.5 Admin Invoice

Pertanyaan utama:
- Bagaimana membuat PI?
- Bagaimana membuat invoice?
- Dokumen apa yang wajib tersedia?
- Bagaimana menangani DP?
- Bagaimana menangani invoice revisi?
- Bagaimana menangani pembayaran sebagian?

## 14.6 Purchasing

Pertanyaan utama:
- Bagaimana memilih supplier?
- Bagaimana membuat PO supplier?
- Bagaimana melakukan negosiasi harga?
- Bagaimana menerima barang parsial?
- Bagaimana menangani barang rusak atau kurang?
- Bagaimana menangani retur?

## 14.7 Finance

Pertanyaan utama:
- Bagaimana mencatat pembayaran?
- Bagaimana melakukan rekonsiliasi?
- Bagaimana memantau piutang?
- Bagaimana menghitung pelunasan?
- Bagaimana menangani pembayaran sebagian?
- Bagaimana status pembayaran memengaruhi Closing?

---

# 15. Prioritas Pengembangan Berdasarkan Discovery

Prioritas berikut merupakan rekomendasi awal, bukan keputusan final manajemen.

## Prioritas 1 — Fondasi Data dan Workflow

- Customer master.
- Lead ownership.
- Lead assignment.
- DAR.
- Role dan permission.
- Audit trail.
- Status pipeline.

## Prioritas 2 — Sales dan Quotation

- Price Request.
- Estimator.
- Price History.
- Inside Sales.
- Quotation.
- Revision management.
- Estimasi biaya teknisi.

## Prioritas 3 — Purchasing dan Logistik

- Product master.
- Supplier master.
- PO supplier.
- Paket dan komponen.
- Goods receipt.
- Partial delivery.
- Delivery tracking.
- Supplier invoice tracking.

## Prioritas 4 — Instalasi dan Finance

- Work order.
- Jadwal teknisi.
- Progress instalasi.
- BAST.
- PI.
- Customer invoice.
- Pembayaran.
- Pelunasan.

## Prioritas 5 — Management Monitoring

- Dashboard Sales.
- Dashboard Leader.
- Target omzet.
- Target margin.
- Closing performance.
- Laporan mingguan.

## Prioritas 6 — AI Internal

- Ringkasan progres Sales.
- Rekomendasi follow-up.
- Bantuan pertanyaan teknis.
- Analisis histori customer.

AI sebaiknya dikembangkan setelah struktur data, permission, dan workflow inti stabil.

---

# 16. Prinsip Desain Sistem yang Perlu Dipertahankan

Berdasarkan kebutuhan yang disampaikan:

1. Data customer dan proyek harus terpusat.
2. Histori aktivitas harus dapat ditelusuri.
3. Setiap departemen memiliki batas akses yang jelas.
4. Harga modal harus dilindungi.
5. Perubahan status harus memiliki sumber dan riwayat.
6. Penerimaan barang parsial harus didukung.
7. Dokumen operasional harus terhubung ke customer/proyek.
8. Workflow harus mendukung kondisi pengecualian.
9. Perhitungan performa harus menggunakan definisi bisnis yang disepakati.
10. Source code dan data perusahaan harus tetap berada dalam kendali perusahaan.

---

# 17. Kesimpulan

Hasil discovery awal menunjukkan bahwa kebutuhan 3DY App bukan sekadar CRM untuk menyimpan data customer dan lead.

Sistem perlu menjadi penghubung operasional lintas departemen, mulai dari Marketing sampai Finance.

Tiga area paling penting untuk divalidasi sebelum implementasi lebih lanjut:

1. **Workflow Closing:** Definisi Won, PO, instalasi selesai, BAST, pembayaran, dan Closing harus konsisten.
2. **Data Ownership dan Authorization:** Kepemilikan customer, akses Sales, hak Sales Leader, dan perlindungan harga modal harus jelas.
3. **Handoff Antar-Departemen:** Perpindahan data dari Sales ke Admin, Purchasing, Teknisi, dan Finance harus memiliki aturan dan tanggung jawab yang jelas.

Dokumen ini menjadi baseline awal Business Discovery. Dokumen harus diperbarui setelah wawancara operasional tambahan dan validasi bersama pihak yang berwenang.

---

# 18. Source Traceability

| ID | Dokumen Sumber |
|---|---|
| SRC-01 | `Wawancara Sales.md` |
| SRC-02 | `summary_hasil_wawancara_user_admin.md` |
| SRC-03 | `summary_hasil_rekaman_meet_ruangan_bapak.md` |

Setiap pembaruan terhadap dokumen ini harus mempertahankan keterlacakan antara temuan, sumber wawancara, dan keputusan bisnis.

**End of Initial Business Discovery v1.0**

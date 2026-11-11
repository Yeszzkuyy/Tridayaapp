# Rangkuman & Penjelasan Ide Pokok Hasil Wawancara Sales User Adi Santosa

Dokumen ini memuat rangkuman terstruktur dan komprehensif dari hasil wawancara operasional bersama tim *Sales* lapangan. Poin-poin ini menjadi acuan utama perancangan proses bisnis serta pemetaan kebutuhan fitur pada sistem web terintegrasi (**3DY App**).

---

## 1. Tata Kelola, Penomoran, dan Kolaborasi Dokumen Penawaran (*Quotation*)

### A. Anatomi & Standar Format Penomoran
* **Format Kode Unik:** Dokumen penawaran memiliki format baku terstruktur:
  $$\text{[Kode Entitas PT]} \,/\, \text{[Nomor Urut]} \,/\, \text{[Inisial Sales]} \,/\, \text{[Tahun]}$$
  * *Contoh:* `MGK/015/AS/2026` (`MGK` = PT Mega Gada Kencana / entitas grup; `015` = nomor urut; `AS` = inisial sales, misal Adi Santosa; `2026` = tahun berjalan).
* **Entitas Multi-Company:** Penomoran penawaran menyesuaikan 4 entitas perseroan grup (*MBK/NTI, MGK, TPS, WANI*).

### B. Pembagian Peran (*Role Separation*)
* **Sales Lapangan (*Account Executive / AM*):** Bertanggung jawab atas pencarian prospek, verifikasi teknis/kebutuhan awal, penentuan strategi harga (*margin*), dan komunikasi langsung dengan calon klien.
* **Inside Sales (*Dini / Audi*):** Bertindak sebagai operator pembuat draf surat penawaran (*quotation* formal). Sales lapangan tidak membuat draf dokumen cetak sendiri dari nol, melainkan mengirimkan parameter data ke *Inside Sales*.
* **Kontrol Kualitas Dokumen (*Cross-Check*):** Setelah *Inside Sales* menerbitkan draf *quotation*, berkas dikembalikan ke Sales lapangan untuk diperiksa ulang (*kuantiti, tipe part number, total harga, spesifikasi*) sebelum resmi dikirim ke klien.

---

## 2. Alur Pengadaan Harga Modal (*Costing*) & Strategi Penentuan Margin

### A. Alur Pencarian Harga Beli (*Supplier/Distributor Cost*)
1. Sales menerima data kebutuhan barang/BOQ (*Bill of Quantities*) dari prospek.
2. Sales melakukan kurasi awal kelayakan barang.
3. Kebutuhan barang diajukan ke bagian *Purchasing/Estimator* (*Vanesha*) melalui grup kerja.
4. Tim Estimator mengonfirmasi ketersediaan dan harga beli terkini ke pihak distributor resmi.
5. Harga modal dikembalikan ke Sales untuk diformulasikan ke draf penawaran.

### B. Kebijakan & Strategi Penetapan *Margin* Keuntungan
* **Tidak Ada Standar Persentase Tunggal:** Persentase keuntungan tidak bisa dipukul rata (misal dipatok kaku 20%), melainkan bersifat dinamis:
  * **Barang Komoditas / Pasar Kompetitif:** Jika produk memiliki banyak distributor/kompetitor dan mudah dibandingkan di pasar, margin ditetapkan tipis agar penawaran kompetitif dan memenangkan tender.
  * **Penjualan Langsung ke Pengguna Akhir (*End-User*):** Diterapkan strategi *markup* harga awal yang lebih longgar/tinggi untuk memberikan ruang negosiasi di kemudian hari.
* **Mekanisme Negosiasi & Revisi Penawaran:**
  * Jika klien meminta penurunan harga, Sales lapangan menginstruksikan *Inside Sales* untuk merevisi persentase margin keuntungan: *"Tolong revisi margin dikurangi menjadi sekian persen."*

---

## 3. Integrasi Pekerjaan Teknis & Jasa Instalasi

### A. Koordinasi Penambahan Jasa Teknisi
* Jika proyek melibatkan pekerjaan fisik (instalasi, penarikan kabel, konfigurasi sistem), Sales berkoordinasi dengan Kepala Teknisi (*Mas Amir*) sebelum penawaran final dikirim ke klien.
* Penentuan lingkup kerja teknis terbagi dua:
  1. **Konfigurasi / Setting Perangkat Lunak Saja:** Perhitungan biaya jasa relatif sederhana dan dapat langsung diestimasikan oleh tim teknisi.
  2. **Penarikan Kabel & Pekerjaan Sipil/Fisik:** Wajib melampirkan gambar tata letak (*layout plan*) dan estimasi panjang kabel; jika belum tersedia, teknisi wajib melakukan survei lokasi terlebih dahulu.

### B. Konsolidasi Dokumen (*Single Quotation Rule*)
* Biaya jasa teknisi tidak dibuatkan surat penawaran terpisah.
* Biaya instalasi/konfigurasi diserahkan ke *Inside Sales* untuk digabungkan ke dalam nomor *quotation* induk yang sama agar klien menerima satu dokumen komprehensif.
* Penambahan item sebelum berkas keluar ke klien tidak dihitung sebagai versi revisi baru guna mencegah kerancuan nomor dokumen.

---

## 4. Pengelolaan Riwayat Prospek (DAR) & Tahapan Penjualan (*Sales Pipeline*)

### A. Pengisian *Daily Activity Report* (DAR)
* **Kewajiban Pengisian Prospek Baru:** Setiap *opt-in* / prospek yang dibagikan oleh tim *Marketing* (misal melalui Mbak Ayu / Mbak Yunita) wajib langsung di-*follow up* dan dicatat ke lembar DAR.
* **Riset Profil Klien:** Sebelum menghubungi klien, Sales melakukan verifikasi latar belakang profil perusahaan (PT) agar komunikasi berjalan terarah dan profesional.
* **Atribut Data Wajib Prospek:**
  * Identitas Klien: Nama PT, alamat lengkap, nama PIC, nomor WhatsApp, email.
  * Kategori Klien: Pengguna langsung (*End-User*) vs Mitra Dagang (*Reseller/Kontraktor*).
  * Detail Proyek: Nama proyek, jenis kebutuhan (CCTV, PABX, Video Conference), status pengadaan (pemasangan baru vs *replacement*/penggantian unit lama).
  * Riwayat Percakapan: Kronologi interaksi per tanggal tindak lanjut (*history log*).

### B. Tahapan Prospek (*Pipeline Stage*)
* **Cool:** Prospek baru masuk dari Marketing yang belum disentuh / baru mulai dijajaki.
* **Warm (Kuning):** Prospek dalam tahap komunikasi aktif, penggalian kebutuhan, atau tindak lanjut berkala.
* **Hot Prospect:** Peluang matang dengan probabilitas penutupan di atas 50% (sudah ada survei lapangan, pengujian alat, atau draf penawaran disepakati). Tahap ini wajib dikawal intensif.
* **Won / PO (Hijau):** Klien resmi menerbitkan pesanan beli (*Purchase Order*).
* **Loss (Merah):** Prospek dinyatakan gugur jika klien lambat merespons (*slow response*) atau tidak membalas pesan setelah di-*follow up* 1–3 kali berturut-turut, atau klien membatalkan proyek. Penandaan merah penting agar prospek pasif tidak membebani laporan pipa penjualan.

---

## 5. Prosedur Serah Terima Pesanan (*Handoff*) Pasca-PO

### A. Koordinasi ke Bagian Administrasi & Tagihan (*Admin Invoice*)
* Begitu PO resmi diterima dari klien, Sales menyalin tautan dokumen (PO Klien, DAR, dan Quotation) ke **Grup Admin Invoice**.
* Sales menugaskan dua pihak sekaligus melalui *mention/tag*:
  1. **Admin Invoice (*Mbak April*):** Untuk segera menerbitkan *Proforma Invoice* (PI) atau Faktur Uang Muka (DP) sebagai dasar transfer dana dari klien.
  2. **Admin Purchasing (*Mbak Nabila*):** Untuk mengunci alokasi stok dan bersiap melakukan pemesanan unit ke distributor.
* Sales melakukan verifikasi berkas draf PI yang dikirimkan Admin Invoice via pesan pribadi untuk memastikan nominal dan rincian barang telah sesuai dengan PO klien.

### B. Eksekusi Pembelian Barang & Negosiasi Lanjutan
* Setelah pembayaran (DP atau lunas) terkonfirmasi, Sales menyerahkan rincian harga beli distributor kepada tim *Purchasing*.
* Tim *Purchasing* melakukan negosiasi ulang ke pihak distributor untuk mendapatkan diskon tambahan (1–3%) guna memenuhi target KPI efisiensi pengadaan (*purchasing saving*).
* *Purchasing* menerbitkan PO resmi kepada distributor.

### C. Kejelasan Alamat & Logistik Pengiriman
* Sales wajib memisahkan secara tegas alamat tagihan kantor (*head office*) dengan alamat pengiriman fisik barang (*project site / delivery address*).
* Contoh kasus: Klien berkantor di Tangerang/Jakarta, namun unit harus dikirim langsung ke lokasi proyek lapangan (misal proyek waduk/pabrik).
* Durasi pengadaan barang berkisar 3–4 hari kerja pasca-pembayaran. Setelah pengiriman dilakukan, tanda terima barang diunggah ke grup kerja sebagai bukti fisik penyelesaian logistik.

---

## 6. Evaluasi Kinerja Penjualan (*Sales Performance & Target*)

### A. Pengukuran Ganda: Omzet vs Realisasi Margin
* Kinerja sales tidak semata-mata diukur dari nominal omzet PO yang masuk, melainkan dari **pencapaian nilai margin kotor (*profit margin*)**.
* *Contoh Parameter Target:*
  * Target PO: Rp 500.000.000,- / bulan.
  * Target Margin Minimal: Rp 50.000.000,- (10% dari nilai pencapaian).
* Jika seorang sales membukukan omzet di bawah target tetapi berhasil memperoleh laba margin yang tinggi, performa sales tersebut tetap dinilai positif.

### B. Masalah pada Sistem Manual Saat Ini
* Pencatatan realisasi PO dan kalkulasi margin saat ini masih diinput secara manual per transaksi ke dalam *spreadsheet*.
* Belum ada rekapitulasi otomatis yang memperlihatkan kontribusi laba per transaksi secara langsung di layar dasbor individu.

---

## 7. Rangkuman Kebutuhan Pokok Aplikasi Web Baru (*Web App Requirements*)

Berdasarkan seluruh kendala operasional yang diidentifikasi, berikut fitur kunci yang wajib disediakan pada sistem baru:

| No | Modul / Kebutuhan | Deskripsi Fungsional yang Diharapkan |
| :--- | :--- | :--- |
| **1** | **Sentralisasi Data Pelanggan (*Customer Hub*)** | Satu profil perusahaan menampung riwayat kontak PIC, catatan DAR, dokumen penawaran (*quotation*), lampiran PO, hingga riwayat pengiriman barang tanpa tercecer di Google Drive. |
| **2** | **Alur Terpadu Permintaan Quotation** | Form digital dari Sales lapangan ke *Inside Sales* untuk pengajuan draf penawaran, dilengkapi riwayat revisi dan penetapan margin dinamis. |
| **3** | **Modul Permintaan Jasa Teknisi** | Menu terintegrasi untuk mengajukan kebutuhan jasa instalasi/konfigurasi ke kepala teknisi dengan lampiran gambar *layout* dan penggabungan biaya otomatis ke nomor *quotation*. |
| **4** | **Kalender Agenda & Kunjungan Lapangan** | Kalender kegiatan sales (agenda demo, survei, rapat) yang dapat dipantau langsung oleh pimpinan (*Pak Victor / Bu Yanita*) tanpa harus bertanya manual di grup chat. |
| **5** | **Kalkulasi Otomatis KPI Sales & Margin** | Dasbor performa individu yang secara *real-time* menampilkan persentase pencapaian omzet PO sekaligus akumulasi nominal margin kotor yang berhasil diperoleh. |
| **6** | **Notifikasi & Respons Cepat (*Mobile Friendly*)** | Pengiriman notifikasi penugasan *lead* baru dari Marketing ke ponsel Sales (via email/aplikasi) agar Sales dapat merespons klien secara instan meski sedang berada di perjalanan. |
| **7** | **Pelacakan Pengiriman Terbuka** | Sistem pelacakan status logistik barang dari distributor ke kantor hingga tiba di lokasi proyek klien yang dapat dipantau bersama oleh Sales, Admin, dan Teknisi. |
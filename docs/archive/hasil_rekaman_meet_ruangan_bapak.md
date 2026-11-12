# Hasil Rekaman: `Meet Ruangan Bapak.mp3` (Diskusi Alur Dashboard Sales, Estimator, Admin & AI)

## 1. Tampilan Dashboard Sales & Privasi Data
* **Pembatasan Akses Antar-Sales:** Saat login menggunakan akun Sales, Sales tersebut tidak perlu melihat data milik Sales lain (seperti milik Irfan, Audi, atau lainnya) karena bersifat privasi. Sales hanya membuka *record* dan *account* miliknya sendiri.
* **Tampilan Performa Individu:** Dashboard Sales berisi tampilan *performance* milik Sales itu sendiri, mengikuti *setting* target yang diatur oleh Bu Yanita.
* **Update Persentase Target dari Closing:** *Performance* Sales mengikuti proyek yang *closing* berdasarkan input dari Admin/Finance (ketika PO selesai atau pembayaran masuk), lalu otomatis meng-update persentase pencapaian target di dashboard Sales tersebut.
* **Daftar Customer & Input Report (DAR):** Bagian bawah dashboard Sales bukan hanya menampilkan data *lead* aktif dan *close* bulan ini, melainkan menampilkan *list* customer milik Sales tersebut (customer paling terakhir beserta progresnya).
* **Pengisian Update & Upload Dokumen di Customer:** Sales dapat mengisi keterangan update harian (seperti DAR / *report* harian) langsung pada *list* customer tersebut, serta meng-upload dokumen (seperti PO yang didapat) sehingga seluruh data terkumpul di Customer A tersebut.
* **Fitur Pencarian (*Search Bar*):** Disediakan *search bar* agar pengguna dapat langsung mencari nama PT tanpa perlu melakukan *scrolling*.

## 2. Tampilan Manajemen / Lead of Sales (Bu Yanita)
* **Daftar Seluruh Sales:** Tampilan yang memunculkan semua Sales (A, B, C, D, E, F, G) beserta persentase pencapaian target masing-masing hanya muncul di Manajemen / Bu Yanita sebagai *Lead of Sales*.
* **Fitur Klik Detail Sales (*Drill-Down*):** Jika Bu Yanita ingin melihat detail pekerjaan salah satu Sales, ia dapat mengklik nama Sales tersebut, lalu akan muncul tabel/dashboard milik Sales itu (sedangkan sesama Sales tidak bisa saling mengklik).

## 3. Alur Admin Keuangan & Penagihan
* **Otomatisasi Status Close ke Target:** Saat Admin Keuangan / bagian penagihan meng-update tentang *invoice* dan proyek yang *close* (pembayaran sudah masuk dan status *fix* bayar/*close*), status tersebut otomatis meng-update ke bar pencapaian target Sales terkait.
* **Pengaturan Level User (*Permission*):** Mengatur *permission* dan level *user* mengenai siapa yang boleh membuka dan siapa yang diblokir ketika login.

## 4. Komunikasi Personal ke Staf & Alur Inside Sales — Estimator — AM
* **Penggalian Alur Langsung ke Personal:** Penggalian kebutuhan alur kerja harus dilakukan langsung secara personal ke masing-masing Sales dan Admin (seperti bagian *Purchasing* Nabila, atau penagihan April), tidak bisa hanya mengambil informasi lewat satu orang seperti Hanifah saja.
* **Keterkaitan Inside Sales & Estimator:**
  * *Inside Sales* memiliki dashboard untuk membuat penawaran dan membutuhkan *Pricelist* agar saat memanggil nama item barang, harganya langsung muncul.
  * Pihak yang menginput *Pricelist* adalah **Estimator**.
  * Ketika ada permintaan harga item (A, B, C, D, E, F, G) dari Sales/AM, Estimator mencarikan harga dan menginputnya ke sistem (contoh: kamera Logitech A harga Rp 1 juta, *bracket* harga Rp 500 ribu).
  * Sistem secara otomatis (*real-time*) membuat *log* tanggal kapan harga tersebut diinput/di-update tanpa Estimator perlu memasukkan tanggal secara manual.
  * Saat *Inside Sales* menyusun penawaran dan memilih barang (contoh: kamera Logitech A), langsung muncul pilihan harga dari update yang paling terakhir (paling baru) hingga yang lebih lama.
  * Jika ada permintaan dari AM mengenai barang (contoh: Ruijie tipe A, B, C, D), Estimator dapat mengecek tanggal update terakhir dan harganya; bila setelah ditanyakan ke *supplier* harganya berubah, Estimator menginput harga baru dan sistem otomatis meng-update.
* **Report Mingguan Estimator:** Estimator harus memiliki *report* (*log* database) mengenai siapa saja Sales/AM yang meminta harga seharian/seminggu itu (misal dari Sales Hendri) dan untuk *project*/PT apa saja, yang tinggal dijadikan Excel sebagai *report* Estimator.

## 5. Penugasan Lead dari Marketing & Kedisiplinan Update Data
* **Assign Lead oleh Bu Yanita:** Data dari Marketing yang belum di-*assign* kemudian di-*assign* oleh Bu Yanita ke salah satu AM/Sales (misal: Irfan), memunculkan notifikasi, dan hanya Irfan yang bisa membuka data tersebut (Sales lain tidak bisa).
* **Kewajiban Update Progres:** Sales wajib meng-update keterangan tindak lanjutnya di sistem; jika tidak di-update, proses tidak bisa lanjut ke tahap berikutnya dan tidak memiliki *report*.

## 6. Rencana Integrasi AI, Konsultan Eksternal, & Komersialisasi
* **Fungsi AI untuk Monitoring & Asistensi:**
  * Ke depannya sistem akan diintegrasikan ke AI (di VPS sendiri agar data tidak keluar).
  * Bu Yanita atau Bapak dapat meminta AI membuatkan *summary* pekerjaan atau *project* seorang Sales (misal Irfan) dalam seminggu beserta progres survei dan penawarannya, serta rekomendasi langkah selanjutnya atau *upscaling*.
  * Sales juga dapat bertanya ke AI mengenai apa saja yang perlu ditanyakan ke pelanggan untuk suatu kebutuhan solusi, dan percakapan tersebut terekam di database sebagai referensi.
* **Kolaborasi dengan Konsultan Eksternal:**
  * Bapak memperkenalkan seorang konsultan berpengalaman di bidang AI dan pengembangan aplikasi (pernah membangun portal berita dan aplikasi *e-commerce* seperti FamilyMart/Circle K) untuk berdiskusi, memberi *insight*, dan menyiapkan sisi AI-nya (estimasi menggunakan Claude untuk sekitar 15–20 orang pengguna).
  * Tim utama pengembang tetap tim internal (Yeski dan Raafi) yang menjaga seluruh data dan *source code* sebagai aset perusahaan.
* **Target Jangka Panjang:** Sistem CRM ini ditargetkan benar-benar bagus karena ke depannya ingin dikomersialkan (dijual) dan dikembangkan menjadi divisi *programming/developer* untuk membuat aplikasi lain (seperti aplikasi apartemen).
* **Fokus Saat Ini (Sebelum Masuk ke Akuntansi Finance yang Rumit):** Fokus menyelesaikan *detailing* alur kecil dari **Marketing sampai ke Admin** (termasuk menyimpan PO ke database Customer, bukan lagi lewat WA atau grup *Invoice*) terlebih dahulu sebelum masuk ke laporan keuangan/akuntansi *Finance* yang rumit seperti aplikasi Accurate.
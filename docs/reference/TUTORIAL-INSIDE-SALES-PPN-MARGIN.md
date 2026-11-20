# Tutorial Uji Alur PPN, Riwayat Harga, dan Margin

**Status:** Panduan review
**Menu:** Estimator, Inside Sales, Sales Quotation, Admin Panel
**Data uji:** Customer `PT Uji Coba 3 (TEST)`
**Login per peran:** Admin: Yeski, Estimator: Vanesha, Inside Sales: Audi, Sales: Irfan

---

## 1. Tujuan

Panduan ini dipakai untuk mencoba alur baru dari Estimator sampai Quotation:

- Harga supplier bisa masuk sebagai harga termasuk PPN atau belum termasuk PPN.
- Harga modal yang dipakai menghitung margin selalu harga sebelum PPN.
- Inside Sales bisa memilih riwayat harga dan mengisi target margin.
- Quotation menyimpan tarif PPN dan margin per item.
- Admin bisa mengatur tarif PPN dan margin default.
- PDF quotation ikut diperbarui setelah edit.

Semua data uji di bawah ini memakai nama **TEST** supaya mudah dikenali dan
bisa dihapus setelah review.

---

## 2. Data Simulasi yang Sudah Disiapkan

| Data | Nama | Dipegang oleh |
|---|---|---|
| Customer | `PT Uji Coba 3 (TEST)` | - |
| Lead | `Uji Coba Quotation 3 (TEST)` | Irfan (Sales NTI) |
| Task Inside Sales | `Uji Coba Quotation 3 (TEST)` | Audi (Inside Sales) |
| Request Harga | `Request #17` | Irfan (peminta), Vanesha (Estimator) |
| Produk | 4 item (PABX, switch, kabel, jasa) | - |
| Riwayat harga produk | Beberapa riwayat harga modal per produk | - |

Lead dipegang Sales, task dipegang Inside Sales, dan request harga diminta
oleh Sales lalu dijawab Estimator. Jadi setiap peran bisa mengecek menu dan
tampilannya masing-masing.

**Penting:** buka **link preview**, bukan website utama. Website utama belum
memakai kode baru.

Link langsung ke data uji (tempel di belakang URL preview):

- Task Inside Sales: `/lead-tasks/17`
- Request Harga: `/estimator/requests/17`
- Lead: `/leads/53`
- Daftar Quotation Sales: `/sales/proposals`
- Pengaturan Admin: `/admin-panel/pricing`

---

## 3. Persiapan

1. Buka link preview yang diberikan di chat.
2. Siapkan akun berikut:
   - **Yeski** untuk Admin Panel.
   - **Vanesha** (`vanesha@tridayaapp.com`) untuk Estimator.
   - **Audi** (`audi@tridayaapp.com`) untuk Inside Sales.
   - **Irfan** (`irfan@tridayaapp.com`) untuk Sales pemilik lead.
3. Kalau lupa password salah satu akun, reset dulu lewat
   **Admin Panel → Users → Edit**.
4. Login sebagai **Yeski**, lalu buka **Admin Panel → Pajak & Margin**.
5. Pastikan nilai awal:
   - Tarif PPN: `12`
   - Margin default: `20`
6. Jangan ubah dulu. Perubahan admin diuji di langkah terakhir.

---

## 4. Skenario 1 - Estimator Mengisi Harga Termasuk PPN

**Login:** Vanesha (Estimator)

> Request #17 sudah berstatus dijawab. Untuk mencoba input harga baru, buat
> request harga baru dari lead yang sama, lalu ikuti langkah di bawah.

### Langkah

1. Buka menu **Estimator → Request Harga**.
2. Cari request harga untuk customer `PT Uji Coba 3 (TEST)`.
3. Buka request tersebut.
4. Isi baris produk `IP PABX 32 Extension`:
   - Supplier: pilih supplier yang biasa dipakai.
   - Harga: `1.120.000`.
   - Centang **`Price includes VAT`**.
   - Stock: `Ready`.
   - Warranty: `1 year distributor`.
   - Valid until: pilih tanggal beberapa minggu ke depan.
5. Simpan / kirim harga.

### Hasil yang diharapkan

- Harga yang diketik tetap terlihat: `Rp 1.120.000` sebagai harga termasuk PPN.
- Harga bersih yang dipakai sistem: `Rp 1.000.000`.
- Harga modal produk `IP PABX 32 Extension` berubah menjadi `Rp 1.000.000`.
- Di riwayat harga muncul keterangan harga termasuk PPN dan harga bersih.

### Yang perlu dinilai dari UX

- Apakah checkbox **Price includes VAT** cukup jelas?
- Apakah harga bersih langsung terlihat tanpa harus membuka halaman lain?
- Apakah angka `1.120.000` dan `1.000.000` bisa dibedakan dengan mudah?

---

## 5. Skenario 2 - Inside Sales Mengisi Item Quotation dan Riwayat Harga

**Login:** Audi (Inside Sales)

Task ini sudah berisi 4 item hasil uji. Kalau ingin mencoba alur dari kosong,
hapus semua kartu item lalu klik **Save Items** terlebih dahulu.

### Langkah

1. Buka menu **Inside Sales → Dashboard**.
2. Cari task **`Uji Coba Quotation 3 (TEST)`** atau buka langsung
   `/lead-tasks/17`.
3. Buka task tersebut.
4. Di panel **Estimator Items**, lihat 4 item hasil jawaban Estimator:
   - `IP PABX 32 Extension` - 1 unit.
   - `Switch Managed 24 Port Gigabit` - 2 unit.
   - `Kabel UTP Cat6 + Instalasi` - 100 meter.
   - `Jasa Survey & Desain Sistem` - 1 lot.
5. Klik **Use All** untuk memasukkan semuanya, atau klik **Use** pada satu
   baris saja.
6. Setiap item langsung menjadi kartu di bagian **Quotation Items**:
   - Baris atas: Product, Description, Scope of Supply, Lead Time, Section.
   - Baris bawah: Qty, Cost Price, Target Margin, Selling Price, Discount.
   - Footer kartu: Subtotal dan Margin.
   - Baris Estimator yang sudah dipakai menampilkan badge **Added**.
7. Untuk mengganti harga modal, klik ikon jam di samping **Cost Price**.
8. Modal **Price History** menampilkan:
   - Tanggal.
   - Harga modal sebelum PPN.
   - Harga supplier termasuk PPN.
   - Supplier.
   - Masa berlaku.
   - Tombol **Select**.
9. Pilih salah satu riwayat. Harga modal dan harga jual otomatis
   menyesuaikan target margin.
10. Ubah **Target Margin (%)** menjadi `20`.
    - Harga jual dihitung dari harga modal sebelum PPN.
    - Margin ditampilkan dalam Rupiah dan persen.
    - Harga jual di bawah modal akan ditolak saat menyimpan.
11. Coba **Add Item** untuk menambah baris kosong, lalu hapus dengan tombol
    tempat sampah. Perubahan belum tersimpan sampai **Save Items** diklik.
12. Klik **Save Items**. Semua kartu disimpan sekaligus.
13. Klik **Generate Quotation**. Halaman quotation terbuka di tab baru.
14. Isi data quotation:
    - Tanggal: hari ini.
    - Customer: otomatis dari lead.
    - PT / Kop: `NTI`.
    - Payment: `TT in Advance`.
    - Delivery: `Ready stock`.
    - Warranty: `1 year official distributor`.
15. Klik **Generate Quotation** di halaman quotation.

### Hasil yang diharapkan

- Subtotal 4 item: `Rp 57.500.000`.
- Margin total: `Rp 11.500.000 (20,00%)`.
- PPN `12%`: `Rp 6.900.000`.
- Grand total: `Rp 64.400.000`.
- Quotation draft terbentuk dengan nomor quotation baru.
- PDF quotation bisa diunduh dari halaman task.
- Audi bisa melihat nomor quotation dan mengunduh PDF. Untuk membuka detail
  dan mengedit, login sebagai **Irfan** (Sales pemilik lead).

### Yang perlu dinilai dari UX

- Apakah tombol **Use All** dan badge **Added** cukup jelas?
- Apakah ikon jam untuk riwayat harga mudah ditemukan?
- Apakah isi modal riwayat harga (tanggal, harga sebelum/sesudah PPN,
  supplier, masa berlaku) cukup jelas?
- Apakah pengisian target margin terasa membantu atau justru mengubah harga
  jual secara mengejutkan?
- Apakah tombol **Save Items** dan indikator **Unsaved changes** cukup jelas?

---

## 6. Skenario 3 - Sales Melihat dan Mengedit Quotation

**Login:** Irfan (Sales NTI)

### Langkah

1. Buka menu **Sales → Quotation**.
2. Buka quotation untuk customer `PT Uji Coba 3 (TEST)`.
3. Cek ringkasan 4 item:
   - Total unit: `Rp 57.500.000`.
   - Total modal: `Rp 46.000.000`.
   - Margin: `Rp 11.500.000 (20,00%)`.
   - PPN: `12%` atau `Rp 6.900.000`.
   - Grand total: `Rp 64.400.000`.
4. Klik **Edit**.
5. Ubah **Margin %** salah satu item, misalnya `IP PABX 32 Extension`
   dari `20` menjadi `25`.
   - Harga jual item itu otomatis dihitung ulang.
   - Subtotal, PPN, dan grand total ikut menyesuaikan.
6. Simpan.
7. Buka kembali detail quotation.
8. Unduh PDF.
9. Cek isi PDF:
   - Kop PT NTI.
   - 4 item dengan scope of supply dan lead time.
   - Harga jual dan total sesuai perubahan.
   - PPN memakai tarif quotation, bukan tarif default yang berubah-ubah.
10. Buka riwayat revisi. Harus ada versi baru setelah simpan.

### Hasil yang diharapkan

- Perubahan margin langsung terlihat sebelum disimpan.
- PDF diperbarui setelah simpan.
- Data scope, lead time, dan section tidak hilang.
- Riwayat versi bertambah.

### Yang perlu dinilai dari UX

- Apakah field margin per item mudah ditemukan di form edit?
- Apakah harga modal read-only terlihat jelas sebagai informasi internal?
- Apakah perubahan harga jual setelah margin diubah terasa wajar?
- Apakah PDF sudah rapi dan informasinya lengkap?

---

## 7. Skenario 4 - Admin Mengubah Tarif PPN dan Margin Default

**Login:** Yeski (Super Admin)

### Langkah

1. Buka **Admin Panel → Pajak & Margin**.
2. Ubah:
   - Tarif PPN dari `12` menjadi `11`.
   - Margin default dari `20` menjadi `25`.
3. Simpan.
4. Buka kembali quotation yang tadi.
5. Cek PPN quotation.
6. Buat quotation baru kalau ingin melihat tarif `11%` dipakai.

### Hasil yang diharapkan

- Quotation lama tetap memakai PPN `12%` karena tarif disimpan saat quotation
  dibuat.
- Quotation baru akan memakai tarif `11%`.
- Item baru akan mewarisi margin default `25%`, tetapi item lama tidak berubah
  otomatis.

### Yang perlu dinilai dari UX

- Apakah halaman pengaturan mudah ditemukan dari Admin Panel?
- Apakah penjelasan bahwa tarif hanya berlaku untuk quotation baru cukup jelas?
- Apakah angka persen dan rupiah tidak tertukar?

---

## 8. Skenario 5 - Pengecekan Akses Harga Modal

Ini opsional kalau akun Marketing atau Teknisi tersedia.

1. Login sebagai Marketing atau Teknisi.
2. Coba buka halaman produk atau riwayat harga modal.
3. Harga modal dan margin tidak boleh terlihat.
4. Kalau membuka URL riwayat harga secara langsung, akses harus ditolak.

---

## 9. Checklist UX

Centang atau catat bagian yang terasa membingungkan:

- [ ] Checkbox harga termasuk PPN mudah dipahami.
- [ ] Harga bersih dan harga termasuk PPN bisa dibedakan.
- [ ] Riwayat harga mudah dipilih dan informasinya lengkap.
- [ ] Target margin langsung menghitung harga jual dengan jelas.
- [ ] Margin tampil dalam Rupiah dan persen.
- [ ] PPN tidak lagi terasa hardcode.
- [ ] Form edit quotation tidak terlalu ramai.
- [ ] PDF rapi dan datanya lengkap.
- [ ] Alur dari Estimator ke Inside Sales terasa nyambung.
- [ ] Tidak ada langkah yang terasa berulang atau tidak perlu.

---

## 10. Batasan yang Masih Ada

- Jasa teknisi belum masuk ke quotation.
- Perpanjangan quotation dan perbandingan versi belum dibuat.
- Profil PT NTI, MGK, dan TPS belum punya rekening, syarat, dan penandatangan
  lengkap.
- Edit item langsung di task belum ada; sementara edit lewat quotation.
- Setiap simpan quotation masih membuat versi baru, termasuk saat masih draft.
- Jalur lama "terima file quotation" memakai PPN `0%` karena file dianggap
  sumber utama.

---

## 11. Setelah Review

Kalau alur sudah sesuai, data uji `TEST` bisa dihapus. Kalau ada bagian yang
belum pas, catat:

- Menu dan langkah yang membingungkan.
- Angka yang salah.
- Label yang kurang jelas.
- Bagian yang terasa terlalu ribet.

Perbaikan dilakukan dulu sebelum merge ke website utama.

---

## 12. Skenario Uji Coba 2 - Lead NTI dari Irfan

Data ini dibuat untuk mencoba alur dari Sales sampai Inside Sales dengan PT NTI.

| Data | Nama |
|---|---|
| Customer | `PT Uji Coba 2 (TEST)` |
| Lead | `Uji Coba Quotation NTI (TEST)` |
| Sales pemilik lead | Irfan |
| Task Inside Sales | `Uji Coba Quotation 2 (TEST)` |
| Inside Sales | Audi |
| Request Harga | `Uji Coba Harga 2 (TEST)` |
| Estimator | Vanesha |

Link langsung:

- Estimator: `/estimator/requests/16`
- Task Inside Sales: `/lead-tasks/16`
- Lead Sales: `/leads/52`
- Daftar quotation: `/sales/proposals`

### Langkah

1. Login **Vanesha**, buka request harga di atas.
2. Isi 4 item dengan contoh harga berikut:

   | Item | Qty | Harga supplier | Include PPN? |
   |---|---|---|---|
   | IP PABX 32 Extension | 1 unit | 22.400.000 | Ya |
   | Switch Managed 24 Port Gigabit | 2 unit | 8.000.000 | Tidak |
   | Kabel UTP Cat6 + Instalasi | 100 meter | 56.000 | Ya |
   | Jasa Survey & Desain Sistem | 1 lot | 5.000.000 | Tidak |

3. Simpan. Kalau mau alur lengkap, login **Irfan** dan klik Accept Prices.
4. Login **Audi**, buka task Inside Sales.
5. Tambahkan 4 produk yang sama, pilih riwayat harga baru dari Estimator.
6. Isi target margin yang sama untuk semua item, misalnya `20`.
7. Buat quotation.
8. Login **Irfan**, buka daftar quotation atau lead, lalu cek dan edit quotation.

Hasil perhitungan dengan contoh harga di atas dan margin 20%:

| Item | Harga modal bersih | Harga jual |
|---|---|---|
| IP PABX 32 Extension | 20.000.000 | 25.000.000 |
| Switch Managed 24 Port Gigabit | 16.000.000 | 20.000.000 |
| Kabel UTP Cat6 + Instalasi | 5.000.000 | 6.250.000 |
| Jasa Survey & Desain Sistem | 5.000.000 | 6.250.000 |
| **Total** | **46.000.000** | **57.500.000** |

Margin total: `11.500.000` atau `20%`.
PPN 12%: `6.900.000`.
Grand total: `64.400.000`.

---

## 13. Uji Ulang Setelah Perbaikan Sinkronisasi

Data baru untuk memastikan qty dan harga dari Estimator benar-benar ikut ke Inside Sales.

| Data | Nama |
|---|---|
| Customer | `PT Uji Coba 3 (TEST)` |
| Lead | `Uji Coba Quotation NTI 3 (TEST)` |
| Sales | Irfan |
| Task Inside Sales | `Uji Coba Quotation 3 (TEST)` |
| Inside Sales | Audi |
| Request Harga | `Uji Coba Harga 3 (TEST)` |
| Estimator | Vanesha |

Link langsung:

- Estimator: `/estimator/requests/17`
- Task Inside Sales: `/lead-tasks/17`
- Lead Sales: `/leads/53`

### Langkah

1. Login **Vanesha**, jawab request harga dengan 4 item dan harga yang sama seperti skenario sebelumnya.
2. Login **Audi**, buka task Inside Sales.
3. Panel **Item dari Estimator** akan muncul.
4. Klik **Gunakan** pada setiap item.
5. Qty, satuan, harga modal, dan harga jual otomatis terisi.
6. Pastikan target margin `20` dan harga jual sesuai.
7. Klik **Add** untuk keempat item.
8. Coba turunkan harga jual salah satu item sampai di bawah modal.
   - Tombol Add harus menolak dan menampilkan pesan.
9. Klik **Create Quotation**.
   - Tidak boleh ada peringatan qty berbeda.
10. Login **Irfan**, cek quotation dan PDF.

### Hasil yang diharapkan

- Qty Switch `2` dan Kabel `100` otomatis terisi.
- Harga jual PABX `25.000.000` dan Jasa `6.250.000` otomatis dari target margin.
- Total sebelum PPN `57.500.000`.
- PPN `6.900.000`.
- Grand total `64.400.000`.
- Tidak ada item dengan margin minus.

# Tutorial Uji Alur PPN, Riwayat Harga, dan Margin

**Status:** Panduan review
**Menu:** Estimator, Inside Sales, Sales Quotation, Admin Panel
**Data uji:** Customer `PT Uji Coba 3DY (TEST)`
**Login per peran:** Admin: Yeski, Estimator: Vanesha, Inside Sales: Audi, Sales: Felisia

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
| Customer | `PT Uji Coba 3DY (TEST)` | - |
| Lead | `Uji Coba PPN & Margin (TEST)` | Felisia (Sales WANI) |
| Task Inside Sales | `Uji Coba PPN & Margin (TEST)` | Audi (Inside Sales) |
| Request Harga | `Simulasi harga supplier termasuk PPN` | Felisia (peminta), Vanesha (Estimator) |
| Produk | `IP PABX 32 Extension` (`PABX-IP`) | - |
| Riwayat harga produk | 8 riwayat harga modal | - |

Lead dipegang Sales, task dipegang Inside Sales, dan request harga diminta
oleh Sales lalu dijawab Estimator. Jadi setiap peran bisa mengecek menu dan
tampilannya masing-masing.

**Penting:** buka **link preview**, bukan website utama. Website utama belum
memakai kode baru.

Link langsung ke data uji (tempel di belakang URL preview):

- Task Inside Sales: `/lead-tasks/15`
- Request Harga: `/estimator/requests/15`
- Daftar Quotation Sales: `/sales/proposals`
- Pengaturan Admin: `/admin-panel/pricing`

---

## 3. Persiapan

1. Buka link preview yang diberikan di chat.
2. Siapkan akun berikut:
   - **Yeski** untuk Admin Panel.
   - **Vanesha** (`vanesha@tridayaapp.com`) untuk Estimator.
   - **Audi** (`audi@tridayaapp.com`) untuk Inside Sales.
   - **Felisia** (`felisia@tridayaapp.com`) untuk Sales pemilik lead.
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

### Langkah

1. Buka menu **Estimator → Request Harga**.
2. Cari request **`Simulasi harga supplier termasuk PPN`** untuk customer
   `PT Uji Coba 3DY (TEST)`.
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

## 5. Skenario 2 - Inside Sales Memilih Riwayat Harga dan Margin

**Login:** Audi (Inside Sales)

Task ini belum punya item. Audi harus menambahkan produk dulu, lalu membuat
quotation. Setelah quotation dibuat, baru muncul di menu Sales milik Felisia.

### Langkah

1. Buka menu **Inside Sales → Dashboard**.
2. Cari task **`Uji Coba PPN & Margin (TEST)`**.
3. Buka task tersebut.
4. Di bagian **Quotation Items**, pilih produk `IP PABX 32 Extension`.
5. Setelah produk dipilih, dropdown **Price History** akan muncul.
6. Perhatikan pilihan paling atas. Harus ada harga baru dari langkah Estimator:
   - Harga bersih sekitar `Rp 1.000.000`.
   - Keterangan `incl. VAT` dengan harga asli `Rp 1.120.000`.
7. Pilih harga baru tersebut.
   - Harga modal terisi `1.000.000`.
   - Harga jual belum otomatis berubah kalau sudah pernah diisi.
8. Isi **Target Margin (%)** dengan `20`.
   - Harga jual otomatis menjadi `Rp 1.250.000`.
   - Margin tampil `Rp 250.000 (20,00%)`.
9. Coba pilih harga lama dari dropdown.
   - Harga modal berubah.
   - Harga jual tidak ikut berubah.
   - Persentase margin ikut menyesuaikan.
10. Pilih kembali harga baru, lalu isi target margin `20` lagi.
11. Klik **Add**.
12. Cek baris item yang muncul:
    - Harga modal `Rp 1.000.000`.
    - Margin `Rp 250.000 (20,00%)`.
    - Target `20%`.
13. Klik **Create Quotation**.
14. Isi data quotation:
    - Tanggal: hari ini.
    - Customer: otomatis dari lead.
    - PT / Kop: `WANI`.
    - Payment: `TT in Advance`.
    - Delivery: `Ready stock`.
    - Warranty: `1 year official distributor`.
15. Klik **Generate Quotation**.

### Hasil yang diharapkan

- Quotation draft terbentuk dengan nomor quotation baru.
- Item quotation memakai harga jual `Rp 1.250.000`.
- PPN `12%` dihitung dari harga jual sebelum PPN:
  - PPN: `Rp 150.000`.
  - Total: `Rp 1.400.000`.
- Margin item tetap `Rp 250.000 (20,00%)`.
- PDF quotation bisa diunduh dari halaman task.
- Audi bisa melihat nomor quotation dan mengunduh PDF. Untuk membuka detail
  dan mengedit, login sebagai **Felisia** (Sales pemilik lead).

### Yang perlu dinilai dari UX

- Apakah dropdown riwayat harga mudah ditemukan setelah memilih produk?
- Apakah keterangan tanggal, harga, supplier, dan masa berlaku cukup jelas?
- Apakah pengisian target margin terasa membantu atau justru mengubah harga
  jual secara mengejutkan?
- Apakah label `Target Margin (%)`, `Selling Price`, dan `Cost Price` mudah
  dibedakan?

---

## 6. Skenario 3 - Sales Melihat dan Mengedit Quotation

**Login:** Felisia (Sales WANI)

### Langkah

1. Buka menu **Sales → Quotation**.
2. Buka quotation untuk customer `PT Uji Coba 3DY (TEST)`.
3. Cek ringkasan:
   - Harga jual: `Rp 1.250.000`.
   - Harga modal: `Rp 1.000.000`.
   - Margin: `Rp 250.000 (20,00%)`.
   - PPN: `12%` atau `Rp 150.000`.
   - Total: `Rp 1.400.000`.
4. Klik **Edit**.
5. Ubah **Margin %** item dari `20` menjadi `25`.
   - Harga jual otomatis menjadi sekitar `Rp 1.333.333,34`.
   - Margin menjadi sekitar `Rp 333.333,34 (25,00%)`.
   - PPN otomatis menyesuaikan.
6. Simpan.
7. Buka kembali detail quotation.
8. Unduh PDF.
9. Cek isi PDF:
   - Kop PT WANI.
   - Scope of supply dan lead time masih ada.
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

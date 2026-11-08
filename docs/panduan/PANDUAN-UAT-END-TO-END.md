# Panduan UAT End-to-End — Lead → Sales → Admin → Teknisi → Closing

- **Tanggal panduan:** 3 November 2026
- **Tujuan:** mengecek alur lengkap dari lead masuk sampai project closing.
- **Environment testing:** worktree `/home/yeski/3dyapp-quote`
- **Branch:** `feature/sys-quotation-guard`
- **Commit terakhir saat panduan dibuat:** `77543a5c`
- **Penting:** perubahan **belum di-merge ke `main`**. Testing harus lewat port `8001`, bukan domain/IP biasa.

---

## 0. Reminder Sebelum Mulai

Centang dulu sebelum testing:

- [x] Jalankan worktree, bukan website `main`.
- [x] Buka `http://IP-SERVER:8001`, bukan `http://IP-SERVER`.
- [x] Gunakan incognito/private window.
- [ ] Jangan campur akun Sales dan Admin dalam satu tab.
- [x] Gunakan customer/lead dummy untuk testing.
- [ ] Jangan menghapus data lama yang sudah ada; buat data baru dengan prefix tanggal.
- [ ] Jika tampilan tidak berubah: hard refresh `Ctrl+Shift+R`, lalu `php artisan view:clear`.
- [ ] Ingat: database worktree masih memakai database yang sama dengan website utama.

Perintah menjalankan:

```bash
cd /home/yeski/3dyapp-quote
php artisan view:clear
php artisan route:clear
php artisan serve --host=0.0.0.0 --port=8001
```

Biarkan terminal tetap terbuka.

---

## 1. Akun Testing

| Peran | Akun | Kegunaan |
|---|---|---|
| Marketing | **Anggie** — `anggie@tridayaapp.com` | Buat lead dan assign ke Sales |
| Sales | **Irfan** — `irfan@tridayaapp.com` | Follow up, quotation, accept, upload PO, convert project |
| Inside Sales | **Audi** / **Dini** | Upload file quotation dari task Inside Sales |
| Lead Technician / Pricing | **Amir Mukhtarin** — `amir@tridayaapp.com` | Technical request / price request |
| Technician | **Ardi** / **Fanuel** | Update progress project |
| Admin | **Vanesha** — `vanesha@tridayaapp.com` | Handoff, Supplier PO, receipt, delivery, invoice, BAST |
| Finance | **Finance Tester** — `finance@tridayaapp.com` | Saat ini read-only untuk invoice/payment; gunakan Admin untuk membuat invoice |
| Management | **Ayu** / **Yanita** | Cek read-only lintas divisi |

Gunakan password biasa. Kalau lupa, reset lewat **Admin Panel → Users** memakai akun super-admin.

---

## 2. Alur Besar yang Diuji

```text
Marketing buat Lead
→ Sales follow up / meeting
→ (Opsional) Technical Request / Price Request / Inside Sales task
→ Sales buat Quotation berisi item & harga
→ Ready → Send → Customer Accept
→ Upload PO Customer
→ Admin melihat handoff
→ Admin buat Supplier PO
→ Barang datang → Goods Receipt → Verify
→ Sales Convert to Project
→ Admin buat Surat Jalan
→ Teknisi update progress
→ Admin/Finance buat Invoice
→ Payment
→ BAST
→ Project Closing
```

---

## 3. Tahap 1 — Marketing Membuat Lead

**Login:** Anggie (Marketing)

**Menu:** Marketing → Leads → Create Lead

Isi:

- Nama customer/company: misalnya `PT UAT 2026-11-04`
- PIC, telepon, WhatsApp, email
- Alamat
- Kebutuhan: misalnya `Instalasi CCTV 4 kamera`
- PT Group: NTI/MGK/TPS/WANI
- Assign Sales: **Irfan**
- Status: Hot/Warm

**Hasil yang diharapkan:**

- Lead tersimpan.
- Lead muncul di **Sales → My Leads** milik Irfan.
- Marketing melihat lead di daftar Marketing.

**Kalau gagal:**

- Pastikan akun Anggie punya role marketing.
- Pastikan field wajib terisi.
- Cek error merah di atas form.

---

## 4. Tahap 2 — Sales Follow Up / Meeting

**Login:** Irfan (Sales)

**Menu:** Sales → My Leads → buka lead baru

Lakukan:

1. Acknowledge lead jika tombolnya tersedia.
2. Tambah **Log Follow Up**:
   - tipe: WhatsApp/Telepon/Email/Kunjungan
   - tanggal
   - catatan kebutuhan customer
3. Opsional: buat **Meeting** lewat menu Sales → Meeting.
4. Isi meeting report jika meeting dilakukan.

**Hasil yang diharapkan:**

- Aktivitas masuk ke timeline lead.
- Lead terlihat di pipeline Sales.
- Tidak ada error saat menyimpan follow up/meeting.

---

## 5. Tahap 3 — Kolaborasi Internal (Opsional, Pilih Minimal Satu)

### Opsi A — Technical Request

**Login:** Irfan

**Menu:** Sales → Request → tab Engineer → New Request

Isi kebutuhan survey/instalasi, lalu simpan.

**Login:** Amir (Lead Technician)

- Review request.
- Assign teknisi.
- Teknisi update progress dan hasil.
- Tandai selesai.

**Hasil:**

- Request terlihat di hub Sales.
- Teknisi menerima penugasan.
- Hasil teknis bisa menjadi referensi quotation.

### Opsi B — Price Request

**Login:** Irfan

**Menu:** Sales → Request → tab Price → New Price Request

Upload file quotation awal dan catatan.

**Login:** Amir / Pricing Engineer

- Upload balasan perhitungan.
- Sales menerima/menyetujui versi harga.

**Hasil:**

- Price Request menjadi `completed`.
- Sales bisa membuat quotation yang tertaut ke price request.
- Catatan: harga **tidak otomatis masuk** ke item quotation; tetap diisi manual.

### Opsi C — Inside Sales Task

**Login:** Irfan

**Menu:** Sales → Request → tab Inside Sales → buat task

Assign ke Audi/Dini.

**Login:** Audi/Dini

- Upload file quotation ke task.

**Login:** Irfan

- Klik **Accept as Quotation**.

**Hasil:**

- Quotation `draft` baru terbentuk dengan file lampiran.
- Quotation hasil accept file **belum punya item/harga**.
- Buka **Edit**, tambahkan item dan harga sebelum Ready.

---

## 6. Tahap 4 — Sales Membuat Quotation

**Login:** Irfan

**Menu:** Sales → Quotation → Create

Isi:

1. Pilih lead.
2. Tanggal quotation dan valid until.
3. Payment scheme:
   - Full 100%, atau
   - DP + pelunasan, atau
   - Tempo/Net days.
4. Isi minimal satu item:
   - Description
   - Qty
   - Unit
   - Harga
   - Diskon jika ada
5. Upload file quotation resmi jika ada.
6. Notes.

**Hasil yang diharapkan:**

- Quotation tersimpan sebagai `draft`.
- Total quotation > 0.
- Item terlihat di halaman detail quotation.
- Jika hanya upload file tanpa item, quotation tetap draft tetapi **belum bisa Ready**.

**Kalau quotation berasal dari Inside Sales:**

- Buka quotation hasil accept.
- Klik **Edit**.
- Tambahkan item/harga.
- Simpan.

---

## 7. Tahap 5 — Ready → Send → Customer Accept

**Login:** Irfan (Sales owner)

Di halaman detail quotation:

1. Klik **Ready**.
   - Status: `draft → ready`.
2. Klik **Send**.
   - Status: `ready → sent`.
3. Klik **Accept**.
   - Status: `sent → accepted/Won`.
   - Lead menjadi `won`.

**Hasil yang diharapkan:**

- Quotation accepted.
- Lead won.
- Panel **Customer PO** muncul.
- Tombol Convert to Project muncul.

**Kalau tombol tidak muncul:**

- Pastikan login sebagai Irfan, bukan Admin.
- Pastikan status quotation sudah `sent`.
- Hard refresh.

---

## 8. Tahap 6 — Upload PO Customer

Masih di halaman quotation accepted, bagian **Customer PO**:

Isi:

- PO Number
- PO Date
- Delivery Deadline
- Delivery Address
- Payment Scheme
- DP % atau Term Days sesuai skema
- File PO customer

Klik **Upload PO**.

**Hasil yang diharapkan:**

- Badge berubah menjadi **PO Received — Ready for Purchasing**.
- File PO bisa diunduh.
- Quotation masuk antrean Admin.

**Kalau gagal:**

- Pastikan file PO dipilih.
- Pastikan field wajib terisi.
- Pastikan status quotation sudah accepted.

---

## 9. Tahap 7 — Admin Menerima Handoff

**Login:** Vanesha (Admin)

**Menu:** Admin → Admin Hub

Atau langsung:

```text
/admin/hub
```

Buka tab **Procurement & AP**.

**Hasil yang diharapkan:**

- Quotation accepted + PO muncul di panel:
  - **Quotation Deals Ready for Supplier PO**
- Buka juga:

```text
/admin/purchasing
```

Quotation muncul di antrean **Ready for Purchasing**.

**Catatan:**

- Admin Hub hanya menampilkan yang sudah accepted + PO.
- Quotation draft/ready/sent tidak muncul di antrean ini.
- Admin bisa melihat semua quotation lewat **Sales → Quotation**, tetapi tidak bisa Send/Accept/Upload PO.

---

## 10. Tahap 8 — Admin Membuat Supplier PO

**Login:** Vanesha

**Menu:** Admin → Supplier PO → Create

Atau dari Admin Hub → quotation → Create Supplier PO.

Langkah:

1. Pastikan supplier sudah ada.
   - Jika belum: **Admin → Suppliers → Create**.
2. Pilih supplier.
3. Pilih quotation accepted + PO.
4. Isi item, quantity, harga beli.
5. Simpan.
6. Buka Supplier PO → **Mark as Ordered**.

**Hasil yang diharapkan:**

- Supplier PO tersimpan.
- Status berubah `draft → ordered`.
- Quotation hilang dari antrean “Ready for Supplier PO” (ini normal, karena sudah mulai diproses).
- Supplier PO muncul di daftar Supplier PO.

---

## 11. Tahap 9 — Terima Barang

**Login:** Vanesha

**Menu:** Admin → Goods Receipts → Create

Langkah:

1. Pilih Supplier PO yang statusnya `ordered`.
2. Isi tanggal terima.
3. Isi quantity diterima.
4. Isi serial number jika produk wajib serial.
5. Upload surat jalan supplier jika ada.
6. Simpan sebagai **Draft**.
7. Buka detail receipt.
8. Klik **Verify Receipt**.

**Hasil yang diharapkan:**

- Status receipt: `draft → verified`.
- Quantity received di Supplier PO bertambah.
- Supplier PO menjadi `partially_received` atau `received`.

**Penting:**

- Selama receipt masih draft, barang **belum dianggap diterima**.
- Create Delivery akan kosong jika receipt belum diverifikasi.
- Pesan “All received goods for this project have been delivered” bisa muncul karena receipt masih draft.

---

## 12. Tahap 10 — Sales Convert ke Project

**Login:** Irfan (Sales owner)

**Menu:** Sales → Quotation → buka quotation accepted

Langkah:

1. Klik **Convert to Project**.
2. Pilih jenis pekerjaan.
3. Isi tanggal mulai.
4. Isi catatan untuk teknisi.
5. Simpan.

**Hasil yang diharapkan:**

- Project terbentuk.
- Project terhubung ke quotation.
- Project muncul di daftar project teknisi/Admin.
- Supplier PO/receipt yang sudah ada ikut tertaut ke project.

**Penting:**

- Saat ini **hanya Sales owner** yang bisa convert.
- Admin **belum bisa** convert project. Ini gap yang sudah tercatat di plan.
- Surat Jalan membutuhkan project, jadi project harus dibuat sebelum delivery.

---

## 13. Tahap 11 — Admin Membuat Surat Jalan

**Login:** Vanesha

**Menu:** Admin → Deliveries → Create

Langkah:

1. Pilih project.
2. Pilih item yang sudah diterima.
3. Isi quantity yang dikirim.
4. Isi serial number jika ada.
5. Simpan.
6. Buka delivery → **Mark Shipped**.
7. **Mark Delivered**.

**Pengiriman parsial:**

- Boleh membuat beberapa Surat Jalan untuk satu project.
- Contoh: Camera 4 unit, kirim 2 dulu.
- Surat Jalan kedua hanya akan menampilkan sisa quantity yang belum dikirim.

**Hasil yang diharapkan:**

- Delivery status `prepared → shipped → delivered`.
- Quantity delivered di Supplier PO bertambah.
- Delivery muncul di daftar Admin.

**Catatan:**

- PDF Surat Jalan belum tersedia.
- Tanda tangan/scan bukti terima belum wajib.
- Ekspedisi/resi/tracking belum tersedia.
- Status delivered saat ini masih bisa diubah tanpa bukti.

---

## 14. Tahap 12 — Teknisi Mengerjakan Project

**Login:** Ardi / Fanuel (Technician)

**Menu:** Teknisi → My Tasks / Project

Langkah:

1. Buka project.
2. Update progress.
3. Isi aktivitas/tugas.
4. Upload hasil pekerjaan/dokumentasi jika tersedia.
5. Ubah progress menjadi 100% atau status Done.

**Hasil yang diharapkan:**

- Progress project terlihat oleh Admin dan Sales.
- Project siap untuk BAST/closing.

---

## 15. Tahap 13 — Invoice Pelanggan

**Login:** Vanesha (Admin)  
**Catatan:** role Finance saat ini masih read-only untuk invoice/payment.

**Menu:** Admin → Invoice → Create

Langkah:

1. Pilih customer.
2. Pilih project.
3. Cek prefill:
   - Customer PO number
   - Payment terms
   - Amount sisa quotation
   - Due date untuk tempo
4. Simpan.

**Hasil yang diharapkan:**

- Invoice tersimpan.
- Status `unpaid`.
- Invoice terhubung ke project dan quotation.
- Invoice muncul di Admin Hub / daftar invoice.

**Catatan:**

- Invoice belum punya line item.
- PDF invoice belum digenerate.
- Faktur Pajak belum ada.
- Over-invoice belum divalidasi.

---

## 16. Tahap 14 — Payment

**Login:** Vanesha (Admin)

**Menu:** Invoice → buka invoice → Record Payment

Langkah:

1. Isi amount.
2. Isi tanggal bayar.
3. Isi metode.
4. Upload bukti transfer.
5. Simpan.

**Hasil yang diharapkan:**

- Status invoice `unpaid → partial` jika sebagian.
- Status invoice `partial → paid` jika lunas.
- Paid amount dan remaining amount benar.

---

## 17. Tahap 15 — BAST

**Login:** Vanesha

**Menu:** Admin → BAST → pilih project

Langkah:

1. Isi nomor BAST.
2. Isi tanggal BAST.
3. Upload file BAST yang sudah ditandatangani.
4. Isi nama/tanggal tanda tangan 3DY.
5. Isi nama/tanggal tanda tangan customer.
6. Simpan.

**Hasil yang diharapkan:**

- BAST tersimpan.
- Status BAST siap.
- Project belum tentu langsung closing.

**Syarat closing otomatis:**

- Semua Surat Jalan berstatus `delivered`.
- BAST lengkap.
- Progress project 100% atau status Done.
- Semua invoice pelanggan lunas.

---

## 18. Tahap 16 — Closing Project

**Login:** Vanesha / Management

Cek:

- Project detail.
- `commercial_closed_at` terisi jika semua syarat terpenuhi.
- Status project menjadi selesai/closing.
- Quotation/handoff status di Sales ikut terlihat.
- Admin Hub KPI “Closed” bertambah.

**Kalau belum closing, cek satu per satu:**

- [ ] Semua delivery sudah `delivered`.
- [ ] Receipt sudah `verified`.
- [ ] BAST sudah diupload dan ditandatangani.
- [ ] Progress project 100% / status Done.
- [ ] Semua invoice sudah `paid` (bukan unpaid/partial).

---

## 19. Yang Belum Dibangun — Jangan Dianggap Bug

Saat testing, hal-hal berikut memang **belum ada** di batch ini:

- PDF resmi Quotation.
- PDF Supplier PO.
- PDF Surat Jalan.
- PDF Invoice.
- Faktur Pajak.
- Upload scan Surat Jalan bertanda tangan.
- Ekspedisi, resi, tracking, biaya kirim.
- Invoice line item.
- Multi-company/kop per PT.
- Customer PO parsial/multiple.
- Verifikasi PO oleh Admin.
- Retail order tanpa project.
- Closing jasa murni tanpa barang.
- Permission Finance untuk membuat invoice/payment.
- Fallback convert project untuk Admin/Management.

Kalau hal di atas belum ada, itu **sesuai rencana**, bukan kegagalan testing.

---

## 20. Checklist Ringkas Testing

### Marketing & Sales

- [ ] Lead baru dibuat Marketing.
- [ ] Lead muncul di Sales.
- [ ] Follow up/meeting tersimpan.
- [ ] Technical request/price request/inside sales task (pilih minimal satu).
- [ ] Quotation dibuat dengan item dan harga.
- [ ] Quotation Ready.
- [ ] Quotation Send.
- [ ] Quotation Accept.
- [ ] PO customer diunggah.

### Admin & Purchasing

- [ ] Quotation muncul di Admin Hub/Purchasing.
- [ ] Supplier tersedia.
- [ ] Supplier PO dibuat.
- [ ] Supplier PO di-mark ordered.
- [ ] Goods Receipt dibuat.
- [ ] Goods Receipt di-verify.
- [ ] Surat Jalan dibuat.
- [ ] Surat Jalan shipped.
- [ ] Surat Jalan delivered.
- [ ] Pengiriman parsial diuji.

### Project & Teknisi

- [ ] Sales convert quotation ke project.
- [ ] Project muncul di teknisi.
- [ ] Teknisi update progress.
- [ ] Progress mencapai 100%/Done.

### Finance & Closing

- [ ] Invoice dibuat.
- [ ] Invoice terhubung ke project/quotation.
- [ ] Payment dicatat.
- [ ] Status invoice partial/paid.
- [ ] BAST diunggah.
- [ ] Project closing otomatis.

---

## 21. Reminder Cepat

1. **Testing hanya di `:8001`**, bukan domain utama.
2. **Sales owner** yang boleh Send/Accept/Upload PO/Convert.
3. **Admin tidak bisa convert project** — minta Sales owner.
4. **Receipt harus Verify** sebelum Create Delivery.
5. **Delivery parsial boleh**, buat surat jalan kedua untuk sisa barang.
6. **Closing butuh semua syarat**, bukan hanya BAST.
7. Kalau halaman terasa “stuck”, hard refresh lalu `php artisan view:clear`.
8. Jangan menguji data customer asli tanpa backup; worktree memakai database yang sama.

---

## 22. Kalau Ada Temuan

Catat:

- Tanggal/jam
- Akun yang dipakai
- Menu/URL
- Langkah yang dilakukan
- Hasil yang muncul
- Hasil yang diharapkan
- Screenshot

Contoh format:

```text
Tanggal: 2026-11-04 10:30
Akun: Vanesha (admin)
Menu: Admin → Deliveries → Create
Langkah: pilih project Q/2026/10/0003
Hasil: dropdown project kosong
Harapan: project muncul
Screenshot: ...
```

# Audit Alur Quotation Sales → Admin, Faktur Pajak & Surat Jalan

- **Tanggal:** 2 November 2026
- **Update keputusan:** 3 November 2026 (PO parsial, invoice timing, termin, faktur pajak, surat jalan, Finance) + koreksi A–E (ekspedisi, nullability retail, jasa murni, pengiriman parsial, cancelled invoice)
- **Repo:** `/var/www/3dyapp` (branch `main`, commit `39c5101d`)
- **Sifat dokumen:** analisis kondisi saat ini + rencana perbaikan. **Belum ada kode yang diubah.**
- **Sumber:** pembacaan kode `main` + pengecekan data database pengembangan saat ini.

---

## 0. Ringkasan Eksekutif

Fondasi modul sudah ada dan cukup banyak:

- Quotation (`proposals` + `proposal_items`) dengan status, item, diskon, pajak nominal, payment terms, customer PO, dan link ke project.
- Request harga ke pricing engineer (`price_requests`) dengan versi balasan.
- Task Inside Sales (`lead_tasks` + `lead_task_attachments`) dengan tombol “Accept as Quotation”.
- Handoff Sales → Admin (`admin/purchasing`) berbasis quotation `accepted` + PO customer.
- Purchasing: Supplier PO, terima barang, surat jalan, invoice supplier.
- Finance: invoice pelanggan, pembayaran, BAST, closing komersial.

Namun alurnya **belum utuh sebagai satu sistem**. Ada empat titik putus utama:

1. **Quotation dari Inside Sales dan Price Request belum benar-benar sinkron secara data.** Yang tersambung baru file/link, bukan item dan nilai quotation.
2. **Quotation bisa berstatus siap dikirim/diterima walau item dan totalnya kosong.** Di data saat ini semua quotation bernilai Rp 0.
3. **Handoff ke Admin belum punya verifikasi PO customer, belum bisa dikembalikan ke Sales, dan hanya Sales owner yang bisa convert project.** Admin bisa memulai Supplier PO, tetapi tidak bisa menyelamatkan alur jika Sales lupa convert.
4. **Invoice belum menjadi dokumen tagihan yang lengkap, Faktur Pajak belum ada sama sekali, dan Surat Jalan baru berupa catatan internal, belum dokumen cetak yang sah.**

Kesimpulan singkat:

> Sistem sudah punya “rangka” end-to-end, tetapi belum punya aturan gerbang (gate) dan dokumen resmi yang membuat alur Sales → Admin benar-benar terkunci, sinkron, dan bisa dipakai harian tanpa kerja manual di luar sistem.

### 0.1 Koreksi & Penajaman 3 November 2026

Lima koreksi berikut mengubah sebagian rancangan awal dan **wajib dipakai** saat eksekusi:

| Kode | Topik | Koreksi |
|---|---|---|
| **A** | Surat Jalan via ekspedisi pihak ketiga | Tambah `delivered_by_type = expedition`, simpan `expedition_name`, `tracking_number`, `shipping_cost`. Untuk ekspedisi, bukti `delivered` boleh berupa foto resi + screenshot status tracking / foto serah terima, **tidak wajib** lembar fisik kembali ke kantor. |
| **B** | Nullability `projects` untuk retail | `work_type_id` di schema aktual masih **NOT NULL** dan harus dibuat nullable. `pic_engineer_id` sudah tidak ada di schema `main` (sudah diganti `pic_engineer` varchar nullable), sedangkan `account_manager_id` sudah nullable. Jadi target migrasi: **`work_type_id` nullable**, dan pastikan `pic_engineer`/`account_manager_id` tetap nullable. |
| **C** | Jasa murni tanpa barang & tanpa instalasi | Tambah jalur `requires_delivery = false` dan `requires_installation = false`. Trigger invoice/closing adalah **BAST Jasa / Konfirmasi Serah Terima Hasil Pekerjaan**, bukan Surat Jalan. |
| **D** | Pengiriman parsial (multiple surat jalan per PO) | `goods_delivery_items` harus menunjuk `customer_purchase_order_item_id` dan menyimpan `quantity_delivered`, sehingga sisa barang per PO customer bisa dihitung. |
| **E** | Invoice cancelled & status termin | Saat invoice `cancelled`, sistem wajib melepas `proposal_payment_schedules.invoice_id` (set null) dan mengembalikan termin ke status **Siap Ditagih**. |

---

## 1. Metode & Bukti

Yang diperiksa:

- `app/Http/Controllers/ProposalController.php`
- `app/Models/Proposal.php`
- `app/Http/Controllers/LeadTaskController.php`
- `app/Http/Controllers/PriceRequestController.php`
- `app/Http/Controllers/PurchasingHandoffController.php`
- `app/Http/Controllers/SupplierPurchaseOrderController.php`
- `app/Http/Controllers/GoodsReceiptController.php`
- `app/Http/Controllers/GoodsDeliveryController.php`
- `app/Http/Controllers/AdminController.php`
- `app/Services/AdminService.php`
- `app/Models/Invoice.php`, `Project.php`, `SupplierPurchaseOrder.php`, `SupplierInvoice.php`
- `resources/views/sales/proposals/*`, `resources/views/admin/*`
- `routes/web.php`
- migrasi `proposals`, `invoices`, `supplier_purchase_orders`, `goods_deliveries`, `projects`
- data aktual lewat `php artisan tinker` (read-only)

### 1.1 Data aktual yang ditemukan

Data ini adalah data di database yang sedang dipakai saat audit, bukan kesimpulan untuk semua data produksi:

| Data | Jumlah | Catatan |
|---|---:|---|
| Quotation | 4 | **semua `grand_total` = 0** |
| Quotation `accepted` | 0 | belum ada deal accepted di data ini |
| Quotation accepted + PO | 0 | belum ada handoff nyata |
| Quotation hasil “Accept as Quotation” dari Inside Sales | 3 | **0 item, total 0**, hanya file terlampir |
| Quotation lain | 1 | 1 item, tetapi `unit_price` = 0 |
| Price Request | 4 | 3 `completed` |
| Price Request completed yang terhubung ke quotation | 0 | **tidak ada yang linked** |
| Lead Task | 6 | 3 attachment |
| Attachment Inside Sales yang sudah di-accept | 3 | semuanya menghasilkan quotation |
| Project | 10 | **0 project punya `proposal_id`** |
| Supplier PO | 0 | modul procurement belum pernah dipakai dengan data nyata |
| Goods Delivery | 0 | belum pernah ada surat jalan |
| Invoice | 3 | **0 invoice terhubung ke proposal** |
| Invoice dengan file | 3 | file diunggah, bukan digenerate sistem |

Data ini penting: alur quotation → PO → project → supplier PO → surat jalan → invoice **belum pernah terbukti berjalan end-to-end dengan data nyata** di database ini.

---

## 2. Peta Alur yang Ada Sekarang

```text
Marketing
  └─ Lead
      ├─ Follow Up / Meeting (follow_ups, lead_activities)
      │
      ├─ [A] Sales buat Quotation langsung
      │      └─ Proposal (draft → ready → sent → viewed → revision → accepted)
      │
      ├─ [B] Sales minta Inside Sales buat quotation
      │      └─ Lead Task → Inside Sales upload file
      │            └─ Sales “Accept as Quotation”
      │                  └─ Proposal draft BARU, isinya hanya file (0 item, total 0)
      │
      └─ [C] Sales minta Price Request ke Pricing Engineer
             └─ Engineer upload versi hitungan
                   └─ Sales “Accept” versi → Price Request completed
                         └─ Sales buat Quotation manual, opsional link price_request_id
                               └─ Item dan harga TIDAK otomatis masuk

Quotation accepted (Won)
  └─ Sales upload PO customer (file, nomor, tanggal, deadline, alamat)
      └─ Admin/Purchasing melihat antrean handoff
          └─ Admin buat Supplier PO
              └─ Supplier PO issued
                  └─ Terima barang (partial, serial number)
                      └─ Surat Jalan ke customer
                          └─ Invoice customer (DP/termin/pelunasan)
                              └─ Payment
                                  └─ BAST
                                      └─ Closing komersial
```

---

## 3. Temuan per Tahap

### 3.1 Follow Up & Meeting

**Kondisi sekarang:**

- Follow up dan meeting tersimpan di `follow_ups`, terhubung ke lead, dengan reminder dan report meeting.
- Meeting tidak terhubung langsung ke quotation. Riwayat aktivitas hanya menjadi konteks lead.
- Lead yang sudah pernah meeting tetap bisa langsung dibuatkan quotation tanpa aturan “meeting harus selesai” atau “follow up terakhir sudah diisi”.

**Dampak:**

- Tidak ada masalah besar untuk pencatatan aktivitas.
- Tetapi tidak ada jejak formal “kebutuhan customer → quotation” yang bisa dipakai Admin untuk memahami dasar penawaran.
- Admin tidak melihat meeting/follow up; Admin hanya melihat quotation dan PO.

**Rekomendasi:**

- Tidak perlu memaksa meeting sebelum quotation.
- Cukup tambahkan ringkasan “sumber kebutuhan” di quotation (dari lead `kebutuhan` atau catatan meeting terakhir) agar Admin paham konteks saat handoff.

---

### 3.2 Quotation dari Sales Langsung

**Yang sudah benar:**

- Tabel `proposals` dan `proposal_items` sudah menjadi sumber data quotation.
- Status: `draft`, `pending_technical`, `ready`, `sent`, `viewed`, `revision`, `accepted`, `rejected`, `expired`, `cancelled`.
- `accepted` di UI dipetakan menjadi **Won**.
- Ada item, quantity, unit, harga, diskon, subtotal, total biaya, margin.
- Ada payment scheme (`full`, `dp`, `tempo`), DP %, term days, payment terms, delivery terms.
- Ada preview cetak (`Print / Save as PDF`) dan lampiran quotation.

**Kekurangan:**

1. **Tidak ada validasi isi quotation sebelum `ready` / `sent` / `accepted`.**
   - `store` boleh membuat quotation tanpa item asal ada file.
   - `markReady` hanya mengecek status `draft`.
   - `send` hanya mengecek status `ready`.
   - `respond(accepted)` hanya mengecek status, bukan isi.
   - Akibatnya quotation Rp 0 bisa dikirim dan diterima. Ini terjadi di data saat ini.

2. **Tidak ada PDF final yang diarsipkan saat customer accept.**
   - Preview hanya HTML + `window.print()`.
   - Tidak ada file PDF final yang tersimpan sebagai dokumen customer.
   - Jika quotation berubah setelah accept (secara data), tidak ada snapshot resmi.

3. **Tidak ada revisi/versioning yang benar.**
   - `revision` hanya status.
   - Tidak ada `revision_number`, tidak ada arsip versi lama.
   - Setelah `accepted`, quotation terkunci; tidak ada mekanisme change order kalau PO customer berbeda dari quotation.

4. **Penomoran quotation memakai `COUNT(*) + 1`.**
   - Ada unique index, jadi tidak akan dobel, tetapi rawan gagal saat dua request bersamaan.
   - Belum memakai tabel sequence/lock yang aman.

5. **Preview quotation masih generik “3DY GROUP”.**
   - Tidak ada kop per PT (NTI/MGK/TPS/WANI), NPWP perusahaan, alamat, atau nomor seri per PT.
   - Jika masing-masing PT adalah badan hukum berbeda, ini belum memenuhi syarat dokumen resmi.

6. **Tidak ada bukti customer menerima quotation.**
   - `accepted` hanya klik Sales.
   - Bukti baru muncul nanti berupa file PO customer, bukan tanda tangan quotation.

---

### 3.3 Quotation dari Inside Sales (Lead Task)

**Alur yang ada:**

1. Sales/management membuat `LeadTask` untuk Inside Sales.
2. Inside Sales upload file quotation ke task.
3. Sales klik **Accept as Quotation**.
4. Sistem membuat `Proposal` baru berstatus `draft`:
   - `created_by` = Sales yang accept
   - `lead_id` = lead task
   - `internal_notes` = catatan file asal
   - **tanpa item**, hanya `ProposalAttachment` berisi file yang sama.
5. Sales harus membuka quotation dan mengisi item/harga secara manual.

**Ini sudah “tersambung”, tetapi belum “sinkron”.**

Yang tersambung:

- File dari task menjadi lampiran quotation.
- Ada relasi `lead_task_attachments.proposal_id`.
- Quotation muncul di menu Quotation.

Yang belum sinkron:

- File quotation (Excel/PDF/Word) **tidak dibaca** menjadi item dan total.
- Quotation hasil accept langsung berstatus `draft` dengan **0 item dan Rp 0**.
- Tidak ada validasi bahwa Sales sudah melengkapi item sebelum `ready`/`sent`.
- Tidak ada aturan satu task hanya boleh menghasilkan satu quotation aktif.
- Satu task bisa punya beberapa versi file; setiap versi bisa di-accept menjadi quotation baru. Di data saat ini ada dua quotation untuk lead yang sama dari task berbeda/versi berbeda.
- Task tidak otomatis selesai ketika quotation di-accept.
- Tidak ada link balik dari quotation ke `lead_task_id` (hanya dari attachment ke proposal).

**Dampak langsung:**

- Admin bisa menerima quotation dengan total 0.
- Supplier PO bisa dibuat dari quotation 0 item; harga supplier 0.
- Invoice bisa dibuat dari quotation 0.
- Data laporan sales tidak bisa dihitung.

**Rekomendasi:**

- Setelah “Accept as Quotation”, jangan langsung `draft` yang bisa dikirim. Wajibkan Sales melengkapi minimal:
  - item (boleh 1 baris jasa),
  - harga jual,
  - total > 0,
  - payment terms.
- Atau ubah form Inside Sales agar mengisi item terstruktur, bukan hanya file.
- Tambahkan `lead_task_id` / `source_task_id` di `proposals` dan aturan satu task = satu quotation aktif (revisi memakai mekanisme revisi, bukan quotation baru).
- Tandai task `done` otomatis saat quotation final diterima customer.

---

### 3.4 Quotation dari Price Request

**Alur yang ada:**

1. Sales membuat `PriceRequest` + upload quotation awal.
2. Pricing engineer upload versi hitungan (`price_request_replies`).
3. Sales melihat versi, lalu `acceptReply`.
4. Price Request menjadi `completed`, versi terakhir ditandai `accepted_at`.
5. Sales bisa membuat quotation dengan `price_request_id` sebagai link.

**Yang sudah benar:**

- Ada versioning balasan engineer.
- Ada status dan diskusi.
- Ada link `price_requests.proposal_id`.
- Quotation yang sudah tertaut tidak bisa membuat quotation kedua dari price request yang sama.

**Yang belum sinkron:**

- `acceptReply` hanya menyimpan status dan file balasan. **Tidak menyalin item/harga ke quotation.**
- Quotation yang dibuat dari price request tetap manual; item dan harga tidak diambil dari balasan engineer.
- Tidak ada validasi bahwa Price Request harus `completed` dan punya balasan `accepted` sebelum quotation dibuat.
- `proposals` tidak menyimpan `accepted_reply_id` atau versi balasan yang dipakai. Jadi tidak bisa diaudit “harga ini diambil dari versi berapa”.
- Di data saat ini: 3 price request `completed`, **0 terhubung ke quotation**.
- Price Request dan Quotation bisa hidup sendiri-sendiri; tidak ada peringatan jika quotation yang dikirim tidak memakai harga yang sudah di-accept.

**Rekomendasi:**

- Wajibkan Price Request `completed` + ada reply `accepted_at` sebelum quotation bisa dibuat dari link tersebut.
- Simpan `price_request_reply_id` di quotation (atau di item jasa) sebagai referensi versi harga.
- Saat Sales mengisi item jasa, tampilkan panel “harga accepted dari Price Request versi X” dan minta konfirmasi.
- Jangan parsing PDF/Excel; cukup link referensi + input manual yang terkontrol.

---

### 3.5 Customer PO

**Kondisi sekarang:**

- Customer PO disimpan di `proposals`:
  - `po_number`
  - `po_date`
  - `po_document_path`
  - `delivery_deadline`
  - `delivery_address`
- Upload hanya boleh saat quotation `accepted`.
- File wajib jika belum ada file; jika sudah ada, boleh update nomor/tanggal tanpa upload ulang.
- Nomor PO, tanggal PO, deadline **tidak wajib**.
- `isReadyForPurchasing()` hanya mengecek `status === accepted` + `po_document_path` terisi.
- Convert project mengecek file + nomor PO + payment scheme; **tidak** mengecek deadline.
- UI checklist convert menampilkan deadline sebagai syarat, tetapi backend tidak menolak jika deadline kosong.

**Kekurangan:**

1. **Definisi “PO lengkap” tidak konsisten.**
   - Handoff Admin: cukup file.
   - Convert: file + nomor + payment scheme.
   - UI: file + nomor + payment scheme + deadline.
   - Akibatnya Admin bisa memproses PO yang datanya belum lengkap.

2. **Tidak ada verifikasi PO oleh Admin.**
   - Admin tidak punya tombol “PO sesuai / perlu revisi / tolak”.
   - Tidak ada status `po_verified`, `verified_by`, `verified_at`, atau catatan verifikasi.
   - Antrean hanya turunan dari data, bukan proses kerja.

3. **Tidak ada nilai PO customer.**
   - Tidak bisa membandingkan nilai PO dengan quotation.
   - Tidak bisa mendeteksi PO lebih kecil/lebih besar dari quotation.
   - Tidak ada change order.

4. **Nomor PO tidak unik.**
   - Satu nomor PO customer bisa dipakai di beberapa quotation.
   - Tidak ada validasi duplikat per customer.

5. **Tidak ada dukungan PO parsial/multiple PO.**
   - Satu quotation hanya punya satu set field PO.
   - Jika customer mengirim PO untuk sebagian item, atau PO tambahan, tidak ada tempatnya.

6. **Sales owner/creator masih bisa mengubah info PO setelah Admin mulai proses.**
   - Tidak ada lock setelah Admin verifikasi.

**Rekomendasi:**

- Tambahkan status verifikasi PO di quotation atau tabel `customer_purchase_orders`.
- Wajibkan minimal: file, nomor, tanggal, nilai PO, deadline, alamat kirim.
- Validasi nomor PO unik per customer.
- Jika PO customer berbeda dari quotation, wajib change order/revisi quotation sebelum convert.

---

### 3.6 Convert ke Project

**Yang sudah benar:**

- Hanya dari quotation `accepted`.
- Dicek belum pernah convert (unique `projects.proposal_id`).
- Menyalin customer, `proposal_id`, `quotation_number`, `sales_user_id`, `delivery_deadline`, deskripsi.
- Menautkan Supplier PO / Goods Receipt / Supplier Invoice yang dibuat sebelum project lahir.
- Mengirim notifikasi ke lead-technician dan Admin.
- Lead conversion diarahkan ke quotation, bukan membuat project kosong.

**Kekurangan:**

1. **Hanya Sales owner/creator yang bisa convert.**
   - `convertToProject` memakai `authorizeManage`, yang mensyaratkan `isOwner`.
   - Admin/management tidak bisa convert jika Sales tidak ada.
   - `LeadController@convert` untuk admin/teknisi diarahkan ke quotation, tetapi di quotation Admin tetap tidak bisa menekan convert.
   - Ini bottlenecks operasional.

2. **Tidak ada `requires_installation` yang dihitung dari isi quotation.**
   - `projects.requires_installation` default `true`.
   - `convertToProject` tidak mengisi field ini.
   - Akibatnya semua project dianggap butuh instalasi/BAST.

3. **Tidak ada `requires_delivery`.**
   - Project jasa murni tidak punya barang, tetapi closing mensyaratkan minimal satu `goods_delivery`.
   - Project jasa tidak bisa closing otomatis.

4. **Barang/jual putus tetap dipaksa menjadi project.**
   - PRD `prd_project_workflow.md` menyebut order tanpa instalasi seharusnya diproses invoice + delivery saja.
   - Code saat ini selalu membuat project, dan `work_type_id` wajib.
   - Surat jalan juga mensyaratkan `project_id`.
   - Jadi jual putus tidak punya jalur resmi.

5. **Backend vs UI tidak konsisten soal deadline.**
   - UI checklist: deadline wajib.
   - Backend: deadline tidak wajib.
   - Project bisa lahir tanpa deadline, lalu Admin kehilangan alat anti-denda.

6. **Inside Sales yang membuat quotation juga bisa convert.**
   - Karena `isOwner` memakai `created_by`, Inside Sales bisa accept/upload PO/convert.
   - Ini melampaui peran “penyusun draft” dan berpotensi mengambil keputusan komersial Sales.

**Rekomendasi:**

- Pisahkan hak:
  - Inside Sales: buat/edit draft.
  - Sales owner: accept, upload PO, convert.
  - Management/Admin: fallback dengan audit log.
- Hitung `requires_installation` dan `requires_delivery` dari item quotation (mis. `item_type` barang/jasa/instalasi).
- Sediakan jalur “order tanpa project” atau minimal `requires_delivery = false` + closing manual.
- Samakan validasi deadline di backend dan UI.

---

### 3.7 Admin Menerima Quotation & PO

**Kondisi sekarang:**

- Admin/Purchasing membuka `/admin/purchasing`.
- Antrean “ready” = quotation `accepted` + `po_document_path` terisi.
- Admin melihat:
  - nomor quotation, customer, sales owner
  - nomor/tanggal PO customer
  - delivery deadline, payment terms
  - item quotation
  - daftar Supplier PO yang sudah dibuat
  - tombol buat Supplier PO
  - download file PO customer
- Notifikasi `PurchasingHandoffNotification` dikirim saat PO diunggah dan saat project dibuat.
- `handoffStage()` menampilkan tahap: `Waiting for Customer PO → PO Received → In Purchasing → Project Created → BAST → Closed`.

**Yang sudah benar:**

- Admin tidak perlu mengetik ulang item quotation.
- Admin bisa mulai Supplier PO sebelum project dibuat.
- Ada notifikasi dan antrean.
- Sales dan Admin membaca status handoff yang sama.

**Kekurangan:**

1. **Tidak ada status “PO diverifikasi Admin”.**
   - Antrean hanya derived; tidak ada aksi Admin untuk menerima/menolak PO.
   - Tidak ada catatan “kurang NPWP”, “alamat kirim belum jelas”, dsb.

2. **Tidak ada tombol “Return to Sales”.**
   - Jika data quotation/PO tidak lengkap, Admin tidak bisa mengembalikan dengan alasan.
   - Sales tidak mendapat notifikasi perbaikan.

3. **Antrean tidak menampilkan accepted tanpa PO.**
   - Hanya jumlah `waitingCount`, bukan daftar.
   - Admin tidak bisa follow up quotation yang sudah deal tapi belum ada PO.

4. **Admin tidak bisa melihat semua quotation dari menu Quotation.**
   - `ProposalController@index` membatasi user yang tidak punya `manage-sales-leads` ke quotation miliknya/lead-nya.
   - Admin punya `manage-admin`, bukan `manage-sales-leads`, sehingga daftar quotation Admin kosong.
   - Admin hanya bisa masuk lewat handoff accepted+PO, bukan melihat draft/accepted tanpa PO.

5. **Keuangan/Finance bisa mendapat notifikasi tetapi 403 saat membuka detail.**
   - Notifikasi dikirim ke pemegang `view-admin-hub`, termasuk `finance`.
   - `PurchasingHandoffController@show` hanya full access untuk `manage-admin`, `manage-sales-leads`, atau `ceo`.
   - Finance tidak punya salah satunya → 403.

6. **Handoff detail tidak menampilkan file quotation asli.**
   - Hanya item dan file PO customer.
   - Admin tidak bisa mengunduh quotation final dari halaman handoff (harus tahu URL quotation).

7. **Setelah satu Supplier PO dibuat, quotation hilang dari antrean.**
   - Query `quotationDealQuery` memakai `whereDoesntHave('supplierPurchaseOrders')`.
   - Jika quotation punya 10 item dan Admin baru memesan 1 item ke Supplier A, quotation tidak lagi muncul di “Quotation Deals Ready for Supplier PO”.
   - Tidak ada laporan “item quotation belum dipesan”.

8. **Tidak ada cara menandai handoff selesai untuk quotation jasa tanpa barang.**
   - Quotation jasa accepted + PO tidak butuh Supplier PO.
   - Antrean hanya hilang jika ada Supplier PO; jadi bisa menggantung atau memaksa membuat Supplier PO yang tidak relevan.

**Rekomendasi:**

- Tambahkan status handoff eksplisit:
  - `waiting_po`
  - `po_received`
  - `po_verified`
  - `in_procurement`
  - `ready_to_deliver`
  - `delivered`
  - `invoiced`
  - `closed`
  - `returned_to_sales`
- Tambahkan aksi Admin: verify, return, assign PIC, catatan.
- Tambahkan daftar accepted tanpa PO dan accepted dengan PO belum diverifikasi.
- Tambahkan laporan “sisa item quotation yang belum dipesan”.
- Beri akses baca handoff ke Finance atau jangan kirim notifikasi ke Finance.

---

### 3.8 Menu Admin & Legacy PO

**Temuan penting:**

- Ada dua konsep PO yang tercampur di UI:
  1. **Customer PO** — PO dari pelanggan ke 3DY, disimpan di `proposals`.
  2. **Supplier PO** — PO dari 3DY ke supplier, disimpan di `supplier_purchase_orders`.
- Masih ada menu lama **“Purchase Order”** (`admin.pos.*`) yang memakai tabel `purchase_orders` legacy.
- `AdminController@posStore`, `posUpdate`, `posDestroy` semuanya `abort(403)`.
- Tetapi tombol **“+ Buat PO”** di Admin Hub dan tab Purchasing masih mengarah ke `admin.pos.create`.
- Artinya tombol tersebut membuka form, tetapi submit selalu 403.
- Tabel `purchase_orders` saat ini kosong; data PO customer sebenarnya ada di quotation.

**Dampak:**

- Admin bingung mana PO yang benar.
- Tombol “Buat PO” terlihat rusak.
- Menu “Purchase Order” tidak berguna dan berpotensi disalahpahami sebagai PO customer.
- Reporting/document center masih menyebut `purchase_orders` legacy.

**Rekomendasi:**

- Ganti label menu lama menjadi **“Customer PO (Legacy)”** dan sembunyikan dari sidebar, atau hapus dari menu setelah data dimigrasikan.
- Arahkan semua tombol “Buat PO” ke **Supplier PO** (`admin.supplier-pos.create`).
- Pertahankan konsep Customer PO hanya di quotation/handoff.
- Perbaiki document center agar tidak bergantung pada `purchase_orders` legacy.

---

### 3.9 Purchasing: Supplier PO, Terima Barang, Surat Jalan

**Yang sudah benar:**

- Master supplier.
- Supplier PO bisa dibuat dari quotation, item bisa dipecah menjadi beberapa komponen.
- Ada status `draft`, `ordered`, `partially_received`, `received`, `cancelled`.
- Terima barang mendukung partial receipt dan serial number.
- Surat jalan punya nomor, tanggal, penerima, item, serial number, status `prepared/shipped/delivered`.
- Ada invoice supplier (AP) dan pembayaran supplier.
- Ada laporan procurement dan antrean deadline.

**Kekurangan:**

1. **Supplier PO tidak punya dokumen cetak/PDF/upload.**
   - Tidak ada template PO supplier.
   - Tidak ada file PO yang bisa dikirim ke supplier dari sistem.
   - Admin harus membuat dokumen di luar sistem.

2. **Supplier PO tidak bisa diedit setelah dibuat.**
   - Hanya bisa issue atau cancel.
   - Koreksi harga/jumlah harus cancel + buat baru.

3. **Prefill harga Supplier PO memakai `products.cost_price`, bukan `proposal_items.unit_cost`.**
   - Jika item quotation tidak punya produk, harga default 0.
   - Admin bisa tidak sengaja membuat PO supplier bernilai 0.

4. **Tidak ada validasi sisa quantity quotation.**
   - Admin bisa memesan lebih banyak dari quantity quotation.
   - Tidak ada peringatan “item ini belum dipesan” atau “sudah dipesan sebagian”.

5. **Terima barang tidak bisa dibatalkan/dikoreksi setelah verified.**
   - Tidak ada reversal.
   - Kesalahan input serial/quantity hanya bisa diperbaiki lewat database.

6. **Surat Jalan belum dokumen resmi.**
   - Ada record, tetapi tidak ada cetak/PDF.
   - Tidak ada alamat pengiriman snapshot.
   - Tidak ada tanda tangan penerima / bukti terima.
   - Tidak ada lampiran scan surat jalan.
   - Tidak ada link ke invoice/PO customer.
   - Tidak ada dukungan ekspedisi pihak ketiga (nama ekspedisi, resi, tracking, biaya kirim).
   - Belum ada penelusuran pengiriman parsial per item Customer PO.
   - Status `delivered` hanya diubah manual.

7. **Tidak ada stock ledger.**
   - Barang masuk/keluar hanya berbasis dokumen, bukan saldo stok.
   - Mungkin cukup untuk 3DY, tetapi perlu dikonfirmasi.

---

### 3.10 Invoice Pelanggan

**Yang sudah benar:**

- Invoice punya customer, project, proposal, customer PO number, billing address snapshot, payment terms snapshot.
- Tipe invoice: `dp`, `termin`, `pelunasan`, `full`.
- Status: `unpaid`, `partial`, `paid`, `cancelled`.
- Pembayaran mendukung cicilan, bukti transfer, dan status otomatis.
- Prefill DP dan sisa tagihan dari quotation.
- Ada snapshot agar perubahan customer/quotation tidak mengubah invoice lama.

**Kekurangan:**

1. **Invoice tidak punya line item.**
   - Hanya `amount` + `notes`.
   - Tidak bisa mencetak rincian barang/jasa, quantity, harga satuan, diskon.
   - Tidak bisa menjadi invoice resmi tanpa file eksternal.

2. **Tidak ada PDF invoice yang digenerate sistem.**
   - Admin hanya bisa upload file invoice.
   - Tidak ada template kop PT, NPWP, nomor PO, rincian, total, terbilang.

3. **Tidak ada Faktur Pajak.**
   - Tidak ada nomor seri faktur, tanggal faktur, DPP, PPN, PPh, atau file e-Faktur.
   - `tax` di quotation hanya nominal, bukan tarif PPN.
   - Customer punya NPWP, tetapi tidak di-snapshot ke invoice.
   - Tidak ada dokumen `faktur_pajak` di Document Center.

4. **Tidak ada validasi total invoice terhadap nilai quotation.**
   - Admin bisa membuat invoice melebihi `grand_total`.
   - Tidak ada kontrol sisa tagihan per quotation di level store/update.

5. **Tidak ada jadwal termin.**
   - `payment_scheme` hanya `full/dp/tempo`.
   - `invoice_type` punya `termin`, tetapi tidak ada schedule termin (mis. 30/40/30).
   - Invoice berikutnya tidak muncul sebagai antrean setelah DP.

6. **`ready_to_invoice` terlalu dini.**
   - Query hanya “project punya proposal dan belum punya invoice”.
   - Tidak menunggu BAST/barang dikirim.
   - Setelah DP terbit, project hilang dari antrean “ready to invoice” meskipun pelunasan belum ditagih.

7. **Tidak ada reminder jatuh tempo.**
   - Ada KPI overdue di dashboard, tetapi tidak ada notifikasi otomatis ke Admin/Finance/Sales.

8. **Finance read-only.**
   - Role `finance` hanya punya `view-admin`, bukan `manage-admin`.
   - Tidak bisa membuat invoice/payment.
   - Jika SOP kantor menempatkan Finance sebagai penerbit invoice, ini perlu permission terpisah.

9. **Tidak ada link invoice ke surat jalan.**
   - Tidak bisa membuktikan invoice tertentu didasarkan pada pengiriman tertentu.
   - Tidak ada “invoice belum dibuat untuk delivery ini”.

10. **Nomor invoice global, bukan per PT.**
    - Format `INV-YYYY-####`.
    - Jika tiap PT punya seri sendiri, ini belum mendukung.

---

### 3.11 Faktur Pajak

**Status saat ini: belum ada modulnya.**

Yang tersedia hanya:

- `customers.npwp` dan file NPWP.
- `proposals.tax` berupa nominal.
- Invoice dengan `amount`, `billing_address`, `payment_terms`, `customer_po_number`.

Yang belum ada:

- Nomor seri Faktur Pajak.
- Tanggal Faktur Pajak.
- Kode transaksi.
- DPP.
- Tarif PPN dan nilai PPN.
- PPh 23 (jika ada).
- NPWP snapshot saat invoice terbit.
- File e-Faktur / PDF Faktur Pajak.
- Relasi Faktur Pajak ↔ Invoice.
- Status “faktur sudah diterbitkan / belum”.
- Dokumen `faktur_pajak` di Document Center.

**Artinya:**

Saat ini Faktur Pajak **dikerjakan di luar sistem** (mis. e-Faktur/Excel) lalu mungkin diunggah sebagai file invoice. Sistem tidak bisa menjawab:

- Invoice mana yang sudah difakturkan?
- Nomor seri fakturnya apa?
- Berapa DPP/PPN-nya?
- Apakah NPWP customer saat itu sudah benar?

Ini gap terbesar di sisi Finance.

---

### 3.12 Surat Jalan

**Status saat ini: ada record, belum dokumen.**

Yang tersedia:

- `goods_deliveries`: nomor, project, customer, tanggal, penerima, status, catatan.
- `goods_delivery_items`: item, part number, quantity, serial numbers.
- Status `prepared → shipped → delivered`.

Yang belum ada:

- Cetak/PDF surat jalan.
- Alamat pengiriman snapshot.
- Nama pengirim/driver/ekspedisi.
- Untuk ekspedisi: nama ekspedisi, nomor resi, tracking, biaya kirim.
- Tanda tangan penerima atau bukti foto/resi/screenshot tracking.
- Lampiran scan surat jalan.
- Nomor PO customer / invoice reference di surat jalan.
- Hubungan ke invoice.
- Pengiriman parsial per item Customer PO.
- Pembatalan/koreksi setelah `delivered`.
- Masuk ke Document Center.

**Artinya:**

Saat ini “Surat Jalan” hanya tracker internal. Untuk dokumen yang ditandatangani customer, Admin masih membuat di luar sistem.

---

### 3.13 BAST & Closing

**Yang sudah benar:**

- `projects.bast_number`, `bast_date`, `bast_document_id`.
- Tanda tangan 3DY + customer.
- `bast_ready_at`.
- `evaluateCommercialClosing()` otomatis.
- Admin bisa upload BAST dan melihat dokumen.

**Kekurangan:**

1. **BAST tidak digenerate dari template.**
   - Hanya upload file yang sudah ditandatangani.
   - Tidak ada cetak BAST dari sistem.

2. **Closing mensyaratkan goods delivery dan invoice.**
   - Project jasa tanpa barang tidak bisa closing otomatis.
   - `requires_installation` default true dan tidak dihitung dari quotation.
   - Jasa murni (`requires_delivery=false`, `requires_installation=false`) belum punya jalur BAST Jasa/konfirmasi serah terima.

3. **Tidak ada closing manual dengan alasan.**
   - Jika ada kasus khusus, tidak ada override yang tercatat.

4. **Faktur Pajak tidak menjadi syarat closing.**
   - Mungkin memang bukan syarat, tetapi perlu keputusan SOP.

---

## 4. Matriks Gap & Prioritas

| Prioritas | Gap | Dampak | Perbaikan inti |
|---|---|---|---|
| **P0** | Quotation 0 item/0 total bisa ready/sent/accepted | Admin menerima deal kosong; Supplier PO/invoice 0 | Validasi isi + total > 0 sebelum ready/sent/accept |
| **P0** | Inside Sales accept file → quotation 0 item | Data quotation tidak sinkron; Admin tidak bisa proses | Wajibkan item terstruktur; atau form Inside Sales isi item |
| **P0** | Price Request accepted tidak linked ke quotation | Harga yang dipakai tidak bisa diaudit | Wajib completed + simpan `accepted_reply_id` |
| **P0** | PO customer tidak wajib nomor/tanggal/nilai/deadline | Admin memproses PO tidak lengkap | Validasi lengkap + status verifikasi |
| **P0** | Admin tidak bisa verify/return PO | Tidak ada kontrol kualitas handoff | Aksi verify/return + notifikasi |
| **P0** | Hanya Sales owner yang bisa convert | Admin macet jika Sales tidak ada | Fallback Admin/Management + audit |
| **P0** | `requires_installation` selalu true; jasa tidak bisa closing | Project jasa/jual putus tidak selesai | Hitung dari item + `requires_delivery` |
| **P0** | Faktur Pajak tidak ada | Tidak bisa audit pajak | Modul Faktur Pajak |
| **P0** | Invoice tanpa line item/PDF | Tidak bisa jadi invoice resmi | `invoice_items` + PDF |
| **P0** | Surat Jalan tidak bisa dicetak | Dokumen pengiriman di luar sistem | Print/PDF + alamat + tanda tangan |
| **P1** | Partial sourcing tidak terlacak | Item quotation bisa terlewat | Laporan sisa item + status sourcing |
| **P1** | Legacy PO menu + tombol “Buat PO” 403 | Admin bingung | Hapus/relabel + arahkan ke Supplier PO |
| **P1** | Finance dapat notifikasi tapi 403 | Notifikasi tidak berguna | Perbaiki permission/recipient |
| **P1** | Sales punya `view-admin` | Sales bisa lihat invoice/payment semua customer | Pisahkan permission read admin |
| **P1** | Invoice bisa melebihi quotation | Over-invoicing | Validasi total invoice |
| **P1** | Tidak ada termin schedule | Penagihan berikutnya terlewat | Payment schedule + queue |
| **P1** | Tidak ada reminder jatuh tempo | Invoice telat | Command + notifikasi |
| **P1** | Supplier PO tanpa PDF/dokumen | Admin kerja manual | Template Supplier PO |
| **P1** | Delivery tanpa link invoice | Tidak bisa rekonsiliasi | Link delivery ↔ invoice |
| **P1** | Surat Jalan belum mendukung ekspedisi & pengiriman parsial | Kirim luar kota/bertahap tidak terlacak | `expedition`, resi, tracking, `customer_purchase_order_item_id` + `quantity_delivered` |
| **P1** | Jasa murni tanpa barang/instalasi tidak punya gate | Invoice/closing jasa tersendat | `requires_delivery=false`, BAST Jasa/konfirmasi serah terima |
| **P1** | Invoice cancelled tidak melepas termin | Termin macet, tidak bisa ditagih ulang | Release `invoice_id` + status `ready_to_invoice` |
| **P2** | Quotation letterhead generic | Dokumen belum per PT | Company profile per PT |
| **P2** | Nomor invoice/PO global | Tidak sesuai seri PT | Sequence per PT |
| **P2** | Tidak ada stock ledger | Stok tidak terpantau | Bila dibutuhkan |
| **P2** | Tidak ada supplier quote comparison | Harga supplier tidak kompetitif | Modul perbandingan |
| **P2** | Receipt/delivery tidak bisa dikoreksi | Salah input permanen | Reversal/correction |
| **P2** | Sales tracker terlalu kasar | Sales tidak tahu progres procurement | Tracker detail tanpa nominal supplier |

---

## 5. Yang Sudah Baik — Jangan Dironbak

- Tabel `proposals` / `proposal_items` sudah tepat sebagai sumber Quotation. Jangan buat tabel `quotations` baru.
- `projects.proposal_id` unique sudah mencegah dua project dari satu quotation.
- Customer PO dan Supplier PO sudah dipisahkan secara tabel.
- Supplier PO item mendukung pemecahan komponen dan snapshot harga.
- Goods receipt mendukung partial receipt + serial number.
- Invoice sudah punya snapshot billing address/payment terms/customer PO.
- Payment sudah otomatis mengubah status invoice.
- BAST dan closing sudah ada fondasinya.
- Test suite untuk quotation, handoff, supplier purchasing, invoice, BAST sudah cukup banyak; jangan merusak kontrak yang sudah ada tanpa test baru.

---

## 6. Rancangan Target yang Disarankan

### 6.1 Prinsip

1. **Satu sumber data per hal.**
   - Quotation tetap `proposals`.
   - Customer PO tetap di quotation atau tabel turunannya, bukan di `purchase_orders` legacy.
   - Supplier PO tetap `supplier_purchase_orders`.
   - Invoice tetap `invoices`.
   - Faktur Pajak menjadi tabel/relasi baru, bukan menumpang di `notes`.

2. **Setiap perpindahan tahap punya gate.**
   - Tidak bisa ready/sent/accepted jika quotation kosong.
   - Tidak bisa handoff jika PO belum lengkap.
   - Tidak bisa convert jika PO belum diverifikasi.
   - Tidak bisa closing jika dokumen wajib belum ada.

3. **Setiap dokumen resmi bisa dicetak/diarsipkan.**
   - Quotation final PDF.
   - Customer PO (upload).
   - Supplier PO PDF.
   - Surat Jalan PDF.
   - Invoice PDF.
   - Faktur Pajak PDF.
   - BAST PDF.

4. **Sales tidak melihat harga supplier.**
   - Biaya modal, margin, dan nominal Supplier PO hanya untuk Admin/Purchasing/Management/CEO.

### 6.2 Perubahan Data yang Disarankan

#### Tabel baru `companies` (WAJIB — dikonfirmasi 2 Nov 2026)

Karena setiap company punya kop, NPWP, dan nomor seri dokumen sendiri:

- `id`, `code` (NTI/MGK/TPS/WANI), `legal_name`, `npwp`, `address`, `phone`, `email`, `logo_path`, `signatory_name`, `bank_accounts` (JSON), `quotation_prefix`, `invoice_prefix`, `po_prefix`, `is_active`.
- Semua dokumen resmi menyimpan `company_id` **dan snapshot** data company saat dokumen terbit (nama, NPWP, alamat, kop).
- Penomoran quotation/invoice/PO memakai sequence per company, bukan global.
- `projects`, `proposals`, `invoices`, `supplier_purchase_orders`, dan `goods_deliveries` wajib tahu company-nya.
- Sumber company: `leads.pt_group` / `customers.pt_group`, dengan opsi override di quotation jika customer bertransaksi dengan PT berbeda.

#### `leads` / `proposals` — jalur Retail vs Project (WAJIB — dikonfirmasi 2 Nov 2026)

Karena ada order retail tanpa instalasi:

- Tambah `order_type` di lead/quotation: `project` atau `retail`.
- Tambah `requires_installation` dan `requires_delivery` di quotation, diwarisi ke project/order.
- Retail tidak membuat project teknisi; cukup **retail order** (bisa memakai tabel order terpisah, atau `projects` dengan `project_type=retail` dan disembunyikan dari dashboard teknisi).
- Retail tetap butuh: quotation accepted → customer PO → delivery → invoice → payment → selesai.
- Retail tidak butuh BAST/progress teknisi, kecuali kebetulan ada instalasi.

#### `proposals`

- `sales_owner_id` — snapshot Sales pemilik lead.
- `source_type` — `direct`, `inside_sales`, `price_request`.
- `source_task_id` — link ke `lead_tasks`.
- `revision_number` — 0, 1, 2, ...
- `tax_rate` — opsional, jika ingin PPN terstruktur.
- `accepted_pdf_path` / `accepted_document_id` — PDF final saat customer accept.
- `po_amount` — nilai PO customer.
- `po_verified_at`, `po_verified_by`, `po_status` — verifikasi Admin.
- `handoff_status` — status eksplisit.
- `requires_installation`, `requires_delivery` — snapshot aturan project.

#### `proposal_items`

- `item_type` — `barang`, `jasa`, `instalasi`, `lisensi`.
- `price_request_reply_id` — jika item jasa diambil dari balasan engineer.
- `item_notes` — catatan internal per item.

#### Tabel baru `customer_purchase_orders` (WAJIB — PO parsial/multiple dikonfirmasi)

Karena customer boleh mengirim PO parsial/multiple, satu quotation tidak cukup menyimpan satu set field PO:

- `id`, `proposal_id`, `company_id`, `po_number`, `po_date`, `amount`, `file_path`, `delivery_deadline`, `delivery_address`, `status`, `verified_by`, `verified_at`, `notes`.
- `customer_purchase_order_items`: `customer_po_id`, `proposal_item_id`, `quantity`, `amount`, agar jelas item mana yang sudah tercakup PO.
- Sisa item quotation yang belum tercakup PO dihitung otomatis; Admin tidak bisa memproses Supplier PO untuk item yang belum ada PO-nya.
- Invoice dapat merujuk ke satu atau beberapa customer PO.

Field lama di `proposals` (`po_number`, `po_date`, `po_document_path`, `delivery_deadline`, `delivery_address`) tetap dipertahankan sementara untuk kompatibilitas, lalu di-backfill ke tabel baru.

#### `invoices`

- `invoice_items` (tabel baru): description, quantity, unit, unit_price, discount, subtotal, tax_rate, tax_amount.
- `company_id`, `npwp_snapshot`, `company_npwp`, `company_name_snapshot`, `company_address_snapshot`.
- `tax_rate`, `tax_inclusive`, `dpp`, `ppn_amount`.
- `pph23_rate`, `pph23_amount`, `withholding_proof_path`.
- `tax_invoice_number` (NSFP), `tax_invoice_date`, `tax_invoice_file_path`, `tax_invoice_status`.
- `proposal_payment_schedule_id` — termin yang ditagih.
- `delivery_id` atau pivot `invoice_delivery`.
- `invoice_pdf_path`.
- `payment_schedule` / `due_date` per termin.

#### `proposal_payment_schedules` (tabel baru)

- `proposal_id`, `label`, `percentage`, `amount`, `milestone`, `due_days`, `invoice_id`, `sort_order`.
- `status`: `pending`, `ready_to_invoice`, `invoiced`, `paid`, `cancelled`.
- Total percentage harus 100%; total amount harus sama dengan `grand_total` (kecuali penyesuaian tercatat).
- **Saat invoice `cancelled`:** set `invoice_id = null` dan `status = ready_to_invoice` agar termin bisa ditagih ulang.

#### `payments`

- `withholding_amount` / `pph23_amount` jika customer memotong PPh 23.
- `withholding_proof_path` untuk Bupot.
- `net_received_amount` — uang yang benar-benar masuk rekening.

#### `goods_deliveries`

- `company_id` + snapshot kop company.
- `delivery_address` snapshot.
- `recipient_name`, `recipient_signature_path`, `recipient_signed_at`.
- `delivery_photo_path` (opsional).
- `attachment_path` (scan surat jalan).
- `delivered_by_type`: `courier` (kurir kantor), `technician`, `expedition`, `other`.
- `delivered_by_user_id` (jika kurir/teknisi internal), `delivered_by_name`.
- `driver_name`, `vehicle_number`.
- `expedition_name`, `tracking_number`, `shipping_cost` (untuk JNE Trucking, Indah Kargo, Lalamove, Dakota, dll.).
- `proof_of_delivery_path` — foto resi / screenshot tracking / foto serah terima.
- `customer_po_id` / `customer_po_number`.
- `invoice_id` nullable.
- `cancelled_at`, `cancelled_by`, `cancel_reason`.
- Surat jalan PDF wajib memuat: kop company, nomor, tanggal, alamat, PO customer, daftar barang + serial, kolom tanda tangan pengirim dan penerima.
- Khusus ekspedisi: lembar fisik tidak wajib kembali; bukti `delivered` boleh foto resi + screenshot status tracking.

#### `goods_delivery_items`

- `customer_purchase_order_item_id` — item PO customer yang dikirim.
- `quantity_delivered` — quantity yang benar-benar dikirim pada surat jalan ini.
- Sisa barang per PO dihitung dari `quantity` PO customer item - total `quantity_delivered` yang sudah `delivered`.
- Satu PO customer boleh punya banyak surat jalan (pengiriman parsial).

#### `projects`

- `order_type`: `project` / `retail`.
- `requires_installation` diisi dari quotation, bukan default true.
- `requires_delivery` (baru).
- `closed_manually_at/by/reason` untuk kasus khusus.
- **Nullability:** `work_type_id` harus diubah menjadi `nullable()` untuk retail. Di schema `main` saat ini, `pic_engineer_id` sudah tidak ada (sudah diganti `pic_engineer` varchar nullable) dan `account_manager_id` sudah nullable — pastikan keduanya tetap nullable.

#### `lead_tasks`

- `proposal_id` (link ke quotation hasil accept).
- `accepted_at`, `accepted_by`.
- Status `done` otomatis saat quotation final.

#### Dokumen

Tambah tipe:
- `quotation_final`
- `customer_po`
- `supplier_po`
- `surat_jalan`
- `faktur_pajak`
- `invoice`
- `bast`
- `bast_jasa` (untuk jasa murni tanpa barang/instalasi)

---

## 7. Roadmap Eksekusi yang Disarankan

### Batch 0 — Data & guardrail (P0, tanpa fitur baru besar)

Tujuan: menghentikan data sampah dan alur setengah jadi.

1. Validasi quotation:
   - minimal 1 item ATAU file + total > 0;
   - `markReady`/`send`/`respond accepted` menolak total 0;
   - `grand_total` tidak boleh 0 untuk quotation yang akan handoff.
2. Validasi PO customer:
   - wajib file, nomor, tanggal, deadline, nilai;
   - `isReadyForPurchasing` mengecek semua;
   - convert memakai aturan yang sama.
3. Samakan UI checklist dan backend.
4. Tambahkan status verifikasi PO Admin.
5. Perbaiki notifikasi Finance (atau jangan kirim ke Finance).
6. Hapus/relabel menu legacy `purchase_orders`; arahkan “Buat PO” ke Supplier PO.
7. Perbaiki `ProposalController@index` agar Admin bisa melihat semua quotation (read-only) atau minimal daftar accepted.
8. Perbaiki `requires_installation`/`requires_delivery` saat convert.
9. Tambahkan `companies` + `order_type` (`project`/`retail`) sebagai fondasi; backfill PT group.
10. Migrasi nullability `projects.work_type_id` untuk retail; pastikan `pic_engineer`/`account_manager_id` tetap nullable.
11. Tambahkan test untuk semua guard di atas.

**DoD:** tidak ada quotation Rp 0 yang bisa masuk handoff; Admin bisa melihat dan memverifikasi PO; tidak ada tombol 403.

---

### Batch 1 — Sinkronisasi Quotation

1. Inside Sales:
   - form upload + isi item/total (atau wajibkan Sales melengkapi sebelum ready);
   - satu task = satu quotation aktif;
   - task otomatis `done` setelah customer accept.
2. Price Request:
   - wajib `completed` + reply accepted sebelum quotation;
   - simpan `price_request_reply_id`;
   - tampilkan referensi harga accepted di form quotation.
3. Quotation:
   - `sales_owner_id`, `source_type`, `source_task_id`;
   - `revision_number`;
   - PDF final saat accepted;
   - arsip versi lama;
   - larang edit setelah accepted, kecuali lewat revisi/change order.
4. Test: accept file → quote terisi; price request → quote terisi; zero quote ditolak.

**DoD:** tidak ada quotation hasil Inside Sales/Price Request yang 0 item; semua quotation punya sumber yang bisa diaudit.

---

### Batch 2 — Handoff Sales → Admin

1. Tabel `customer_purchase_orders` + `customer_purchase_order_items` (PO parsial/multiple).
2. UX cepat “Cakup Seluruh Item Quotation” untuk kasus 1 PO normal.
3. Status handoff eksplisit.
4. Aksi Admin: verify / return / assign / catatan.
5. Antrean:
   - accepted tanpa PO,
   - PO masuk belum diverifikasi,
   - siap procurement,
   - sisa item belum dipesan,
   - jasa/retail tanpa Supplier PO.
6. Fallback convert untuk Admin/Management dengan audit.
7. Notifikasi dua arah (Admin ↔ Sales).
8. Tampilkan sisa quantity quotation yang belum tercakup PO.
9. Supplier PO hanya bisa dibuat untuk item yang sudah tercakup customer PO verified.

**DoD:** Admin tahu persis quotation mana yang harus dikerjakan, bisa mengembalikan yang kurang, dan tidak ada handoff menggantung.

---

### Batch 3 — Procurement & Dokumen

1. Supplier PO PDF/print + nomor per company.
2. Surat Jalan dua fase:
   - `shipped`: cetak template + kolom tanda tangan pengirim/kurir/teknisi/penerima;
   - `delivered`: wajib bukti terima (scan/foto tanda tangan untuk kurir/teknisi; resi + screenshot tracking untuk ekspedisi).
3. `delivered_by_type` (`courier`/`technician`/`expedition`/`other`), nama pengantar, tanda tangan penerima, foto/scan.
4. Untuk ekspedisi: `expedition_name`, `tracking_number`, `shipping_cost`, `proof_of_delivery_path`.
5. Pengiriman parsial: `goods_delivery_items.customer_purchase_order_item_id` + `quantity_delivered`; laporan sisa barang per PO.
6. Link delivery ↔ invoice dan customer PO.
7. Laporan sisa item quotation.
8. Koreksi/reversal receipt/delivery.
9. Test print, ekspedisi, partial delivery, dan akses dokumen.

**DoD:** Admin bisa mengirim PO ke supplier dan surat jalan ke customer tanpa membuat dokumen di luar sistem.

---

### Batch 4 — Finance: Invoice & Faktur Pajak

1. `invoice_items` + PDF invoice + kop/NPWP per company.
2. Gate invoice per tipe:
   - `dp`: PO verified;
   - `full`/CBD: PO verified, boleh sebelum kirim;
   - `termin`/`pelunasan`: BAST (instalasi), Surat Jalan (retail), atau BAST Jasa (jasa murni).
3. Termin multi-tahap:
   - `proposal_payment_schedules`;
   - preset Full, DP+pelunasan, custom 3+;
   - antrean termin berikutnya;
   - invoice `cancelled` melepas `invoice_id` + status `ready_to_invoice`.
4. Faktur Pajak:
   - `tax_rate` fleksibel (bukan hardcode 11%);
   - DPP, PPN, PPh 23, NSFP, tanggal, file e-Faktur;
   - NPWP snapshot;
   - status belum/sudah difakturkan;
   - Bupot customer.
5. Rekonsiliasi payment dengan potongan PPh 23 (tidak dianggap underpaid).
6. Validasi total invoice vs quotation, vs customer PO, vs termin.
7. Reminder jatuh tempo.
8. Permission Finance `manage-invoices`, `manage-payments`, `manage-tax-invoices` (sudah dikonfirmasi boleh).
9. Test: DP → pelunasan; faktur; over-invoice; due date; PPh 23.

**DoD:** invoice dan faktur pajak bisa diterbitkan dan diaudit dari sistem; tidak ada tagihan yang terlewat.

---

### Batch 5 — Closing & Reporting

1. `requires_delivery` + closing jasa.
2. Closing retail tanpa BAST/progress teknisi.
3. Closing jasa murni dengan BAST Jasa / konfirmasi serah terima hasil pekerjaan.
4. Closing manual dengan alasan.
5. Sales tracker detail (tanpa nominal supplier).
6. Admin dashboard:
   - handoff,
   - procurement,
   - invoice,
   - faktur,
   - delivery,
   - BAST.
7. Audit log untuk semua aksi handoff/verifikasi/closing.

**DoD:** satu customer bisa dilacak dari lead sampai closing tanpa bertanya di grup WhatsApp.

---

## 8. Keputusan yang Sudah Dikonfirmasi & Pertanyaan Tersisa

### 8.1 Sudah dikonfirmasi (2 November 2026)

| No | Pertanyaan | Jawaban | Konsekuensi teknis |
|---|---|---|---|
| 1 | Kop/NPWP/nomor seri per company | **Ya, semua company punya sendiri** | Wajib tabel `companies`, company_id di dokumen, penomoran per company, snapshot kop/NPWP |
| 2 | Project vs retail | **Tidak selalu project; ada retail tanpa instalasi** | Wajib `order_type` di lead/quotation, jalur retail order tanpa teknisi/BAST |
| 3 | PO parsial/multiple | **Wajib mendukung parsial/multiple** | Wajib `customer_purchase_orders` + item allocation; ada tombol “Cakup Seluruh Item Quotation” untuk kasus normal |
| 4 | Invoice sebelum kirim/BAST | **Fleksibel per tipe invoice** | DP: cukup PO verified. Full/CBD: boleh sebelum kirim. Termin/pelunasan: butuh BAST (jika instalasi) atau Surat Jalan (jika retail) |
| 5 | Termin | **Multi-tahap (3+), UI preset cepat** | `payment_schedule` fleksibel; preset Full, DP+pelunasan, custom 3+ termin |
| 6 | Faktur Pajak | **Sistem sebagai jembatan rekonsiliasi; diterbitkan/input Finance** | `tax_rate` tidak hardcode 11%; simpan DPP/PPN/PPh 23/NSFP/file e-Faktur; Finance + Super Admin |
| 7 | Surat jalan tanda tangan | **Wajib; biasanya kurir kantor, kadang teknisi; luar kota via ekspedisi** | `delivered_by_type` termasuk `expedition`; kurir/teknisi wajib scan tanda tangan, ekspedisi boleh resi + screenshot tracking; status `delivered` wajib bukti |
| 8 | Finance boleh buat invoice/payment | **Admin dan Finance boleh** | Pisahkan permission `manage-invoices` / `manage-payments`; Finance read-only untuk Supplier PO/teknis |

### 8.2 Masih perlu diputuskan

| No | Pertanyaan | Status | Dampak sementara |
|---|---|---|---|
| 9 | Supplier PO harus dicetak/dikirim dari sistem? | **Belum dijawab** | Sementara sediakan PDF/print; apakah dikirim via email/WA menyusul |
| 10 | Sales boleh lihat harga modal/margin? | **Belum dijawab** | Sementara ikuti plan V2: Sales tidak melihat cost/margin |
| 11 | Company default untuk data lama | **Belum dijawab** | Perlu saat backfill `company_id` untuk quotation/project/invoice lama |
| 12 | Apakah semua project lama dianggap butuh instalasi? | **Belum dijawab** | Perlu saat backfill `order_type`/`requires_installation` |

### 8.3 Keputusan desain yang mengikuti jawaban

1. **Company bukan sekadar `pt_group`.** `pt_group` boleh tetap jadi penanda akses, tetapi dokumen resmi harus punya `company_id` dan snapshot legal entity.
2. **Retail bukan project.** Retail order tidak muncul di dashboard teknisi, tidak butuh BAST, tetapi tetap butuh quotation, PO, surat jalan, invoice, dan payment.
3. **PO customer adalah entitas sendiri.** Satu quotation bisa punya banyak PO; satu PO bisa mencakup sebagian item. Ini yang membuat handoff Admin menjadi akurat.
4. **Surat jalan adalah dokumen resmi.** Bukan hanya record; harus bisa dicetak, ditandatangani, dan diarsipkan.
5. **Finance punya hak transaksi.** Finance tidak lagi read-only untuk invoice/payment; tetapi tetap tidak otomatis boleh mengelola Supplier PO.

### 8.4 Rekomendasi desain Retail

Ada dua opsi:

**Opsi A (disarankan untuk tahap awal): pakai `projects` sebagai order header.**

- Tambah `projects.order_type` = `project` / `retail`.
- Retail tetap punya record order, tetapi:
  - tidak muncul di dashboard teknisi;
  - tidak butuh BAST/progress;
  - tidak butuh `work_type_id` (harus dibuat nullable);
  - tetap punya delivery, invoice, payment.
- Migrasi nullability: `work_type_id` saat ini **NOT NULL** dan wajib diubah menjadi nullable. Di schema `main` aktual, `pic_engineer_id` sudah tidak ada (diganti `pic_engineer` varchar nullable), `account_manager_id` sudah nullable — jadi yang perlu diubah hanya `work_type_id`, sambil memastikan dua kolom lain tetap nullable.
- Menu “Retail” adalah filter `order_type=retail`; menu “Project” filter `order_type=project`.
- Kelebihan: tidak menduplikasi tabel delivery/invoice/supplier PO; semua relasi yang sudah ada tetap dipakai.
- Kekurangan: istilah “project” di database tetap ada, meskipun UI menyebutnya retail order.

**Opsi B: tabel `retail_orders` terpisah.**

- Lebih bersih secara konsep, tetapi harus menduplikasi relasi delivery/invoice/payment/supplier PO atau membuat polymorphic relation.
- Lebih mahal dan berisiko.

**Rekomendasi:** pakai Opsi A dulu, dengan `order_type` + `requires_installation` + `requires_delivery`. Jika volume retail besar dan alurnya benar-benar berbeda, baru pecah ke tabel sendiri.

### 8.5 Rekomendasi desain Customer PO Parsial

- Satu quotation → banyak `customer_purchase_orders`.
- Satu customer PO → banyak `customer_purchase_order_items`.
- Setiap item PO menunjuk `proposal_item_id` + quantity + amount.
- Sisa quantity quotation dihitung dari total PO yang sudah verified.
- Supplier PO hanya boleh dibuat untuk item yang sudah tercakup customer PO verified.
- Invoice merujuk customer PO dan item yang dikirim/ditagih.
- Jika customer PO hanya berisi total tanpa rincian, Admin wajib mengalokasikan quantity saat verifikasi.
- Satu Customer PO boleh dikirim bertahap lewat beberapa Surat Jalan; sisa quantity per item dihitung dari `goods_delivery_items.quantity_delivered`.

### 8.6 Gate Invoice per Tipe (sementara disepakati)

Aturan validasi saat invoice diterbitkan:

| Tipe invoice | Syarat minimal | Catatan |
|---|---|---|
| `dp` | Customer PO sudah diverifikasi | Boleh sebelum barang dikirim; dipakai untuk memulai procurement/instalasi |
| `full` (CBD / Cash Before Delivery) | Customer PO sudah diverifikasi | Boleh terbit 100% sebelum barang dikirim, khusus retail/customer baru |
| `termin` | Jika `requires_installation=true`: BAST sudah diunggah. Jika `requires_installation=false` dan `requires_delivery=true`: Surat Jalan minimal `shipped`. Jika keduanya `false` (jasa murni): BAST Jasa / konfirmasi serah terima | Termin mengikuti milestone payment schedule |
| `pelunasan` | Jika `requires_installation=true`: BAST sudah diunggah. Jika `requires_installation=false` dan `requires_delivery=true`: Surat Jalan minimal `delivered`. Jika keduanya `false` (jasa murni): BAST Jasa / konfirmasi serah terima | Tidak boleh mendahului milestone |

Catatan:

- Gate ini harus dicek di backend, bukan hanya UI.
- Jika `requires_installation`/`requires_delivery` belum diisi dengan benar, gate tidak bisa bekerja. Karena itu keduanya harus diisi sejak quotation.
- **Jasa murni** (mis. jasa 3D scanning, reverse engineering, file CAD): `requires_installation=false` + `requires_delivery=false`. Deliverable berupa file digital (Google Drive/email), jadi trigger penagihan adalah **BAST Jasa / Konfirmasi Serah Terima Hasil Pekerjaan**, bukan Surat Jalan.
- Untuk retail, Surat Jalan `delivered` wajib punya bukti terima; kurir/teknisi berupa tanda tangan, ekspedisi berupa resi + screenshot tracking; lihat §8.9.
- Untuk ekspedisi, bukti `delivered` boleh foto resi + screenshot tracking; tidak wajib lembar fisik kembali.

### 8.7 Termin Multi-Tahap

Struktur data:

- `proposal_payment_schedules` (atau JSON `payment_schedule` di `proposals`):
  - `label` (DP, Termin 1, Termin 2, Pelunasan),
  - `percentage`,
  - `amount`,
  - `milestone` (PO, barang tiba, BAST, training, dll.),
  - `due_days` opsional,
  - `invoice_id` setelah ditagih,
  - `status`: `pending`, `ready_to_invoice`, `invoiced`, `paid`, `cancelled`.
- `invoice_type=termin` harus menunjuk salah satu schedule.
- Total percentage wajib 100%; total amount wajib sama dengan `grand_total` (kecuali ada penyesuaian yang dicatat).
- UI:
  - preset **Full 100%**;
  - preset **DP X% + Pelunasan (100-X)%**;
  - **Custom multi-tahap** dengan baris dinamis.
- Antrean Finance menampilkan termin berikutnya yang siap ditagih berdasarkan milestone.
- **Invoice cancelled:** lepas `invoice_id` (set null), set `status=ready_to_invoice`, dan termin kembali muncul di antrean penagihan. Ini harus dilakukan di service/observer saat status invoice berubah menjadi `cancelled`, bukan hanya di UI.

### 8.8 Faktur Pajak

Posisi sistem:

- Sistem **bukan** penerbit e-Faktur resmi. e-Faktur tetap dibuat di aplikasi DJP oleh Finance.
- Sistem menjadi **jembatan rekonsiliasi & arsip**: mencatat NSFP, tanggal, DPP, PPN, PPh 23, file PDF, dan status.

Field yang perlu disimpan di invoice:

- `tax_rate` — tarif PPN (jangan hardcode 11%; siap 12% atau perubahan lain).
- `tax_inclusive` — harga quotation termasuk PPN atau belum.
- `dpp` — Dasar Pengenaan Pajak.
- `ppn_amount` — nilai PPN.
- `pph23_rate` — tarif PPh 23 (mis. 2%).
- `pph23_amount` — nilai PPh 23.
- `tax_invoice_number` — NSFP.
- `tax_invoice_date`.
- `tax_invoice_file_path` — PDF e-Faktur.
- `tax_invoice_status` — `belum_difakturkan` / `sudah_terbit`.
- `withholding_proof_path` — Bukti Potong (Bupot) dari customer, opsional.

Rekonsiliasi pembayaran:

- Jika customer memotong PPh 23, uang masuk = DPP + PPN - PPh 23.
- Invoice tidak boleh dianggap underpaid hanya karena PPh 23.
- Saat mencatat payment, sediakan opsi “dipotong PPh 23” + upload Bupot.
- Sisa yang dianggap lunas = `ppn_amount + dpp - pph23_amount` (sesuai skema).

Hak akses:

- Finance + Super Admin: isi/ubah Faktur Pajak.
- Sales: hanya lihat/download file faktur untuk dikirim ke customer.

### 8.9 Surat Jalan Dua Fase + Ekspedisi + Pengiriman Parsial

Fase 1 — **shipped** (cetak & berangkat):

- Admin mencetak Surat Jalan dari sistem.
- Isi: kop company, nomor surat jalan, tanggal, nomor PO customer, alamat kirim, daftar item + serial number, kolom tanda tangan pengirim, kurir/teknisi/ekspedisi, dan penerima.
- Status berubah `prepared → shipped`.
- `delivered_by_type` diisi:
  - `courier` — kurir kantor;
  - `technician` — teknisi yang mengantar;
  - `expedition` — JNE Trucking, Indah Kargo, Lalamove, Dakota, dll.;
  - `other`.
- Jika teknisi: simpan `delivered_by_user_id`.
- Jika ekspedisi: simpan `expedition_name`, `tracking_number`, `shipping_cost`.

Fase 2 — **delivered** (terima & arsip):

- Admin/Finance mencatat:
  - nama penerima,
  - tanggal diterima,
  - bukti terima.
- **Kurir/teknisi:** upload scan/foto Surat Jalan yang sudah ditandatangani; status `delivered` hanya boleh jika file bukti ada.
- **Ekspedisi:** bukti boleh berupa:
  - foto resi pengiriman, **dan/atau**
  - screenshot status `delivered` di web tracking ekspedisi,
  - foto serah terima barang dari ekspedisi.
- Simpan `recipient_signature_path` / `signed_delivery_note_path` / `proof_of_delivery_path`, `recipient_signed_at`.
- Jika bukti belum ada, status tetap `shipped` dan Finance tidak boleh menagih pelunasan tempo.
- Jangan menahan tagihan berbulan-bulan hanya karena menunggu lembar fisik dari ekspedisi.

Pengiriman parsial:

- Satu Customer PO boleh punya **banyak Surat Jalan**.
- `goods_delivery_items.customer_purchase_order_item_id` menunjuk item PO customer.
- `goods_delivery_items.quantity_delivered` menyimpan quantity yang dikirim pada surat jalan tersebut.
- Sisa barang per PO = `quantity` PO item - total `quantity_delivered` dari surat jalan berstatus `delivered`.
- Admin bisa melihat “Sisa Barang Belum Dikirim” per Customer PO.

### 8.10 Permission Finance & Admin

Permission baru yang disarankan:

- `manage-invoices` — buat/edit invoice pelanggan.
- `manage-payments` — catat pembayaran customer.
- `manage-tax-invoices` — isi/upload Faktur Pajak.
- `view-supplier-pos` / `manage-supplier-pos` — tetap untuk Admin/Purchasing.
- `view-technician` / `manage-technician` — tetap read-only untuk Finance.

Role:

- **Admin:** `manage-invoices`, `manage-payments`, `manage-tax-invoices` (untuk retail/langsung), plus Supplier PO.
- **Finance:** `manage-invoices`, `manage-payments`, `manage-tax-invoices`; read-only Supplier PO/teknis.
- **Sales:** tidak lagi memegang `view-admin` global; jika perlu melihat status invoice customer, buat route scoped atau policy.

---

### 8.11 Data lama yang perlu diputuskan

Saat migrasi `companies` dan `customer_purchase_orders`, data lama perlu backfill:

- Quotation lama: company diambil dari `lead.pt_group`; jika kosong, perlu default company.
- Customer PO lama: field `proposals.po_*` dipindahkan menjadi satu baris `customer_purchase_orders`; item allocation diisi semua item quotation (karena PO lama dianggap mencakup seluruh quotation).
- Project lama: `order_type=project`, `requires_installation=true` (sesuai kondisi sekarang), `requires_delivery=true`.
- Invoice lama: `company_id` dari customer/PT; jika kosong, default company.
- Perlu keputusan: company default untuk data lama, dan apakah semua project lama dianggap butuh instalasi.

---

## 9. Kesimpulan

Yang sudah ada:

- Quotation, price request, inside sales task, customer PO, supplier PO, goods receipt, surat jalan, invoice, payment, BAST, closing.

Yang belum:

- Quotation yang benar-benar terisi dari Inside Sales/Price Request.
- Gate yang mencegah quotation/PO kosong.
- Verifikasi dan return PO oleh Admin.
- Jalur project untuk jasa/jual putus.
- Partial sourcing tracking.
- Invoice resmi dengan line item/PDF.
- Faktur Pajak.
- Surat Jalan cetak resmi, termasuk ekspedisi dan pengiriman parsial.
- Gate invoice/closing untuk jasa murni tanpa barang/instalasi.
- Role/permission yang bersih antara Sales, Admin, Finance.

Urutan perbaikan yang paling masuk akal:

1. **Kunci data quotation** (Batch 0–1).
2. **Kunci handoff PO** (Batch 2).
3. **Lengkapi dokumen procurement** (Batch 3).
4. **Lengkapi invoice + faktur pajak** (Batch 4).
5. **Rapikan closing & reporting** (Batch 5).

Tanpa Batch 0–1, modul Admin di hilir akan tetap menerima data kosong. Tanpa Batch 4, Finance akan tetap bekerja di luar sistem. Jadi dua area itu adalah prioritas utama.

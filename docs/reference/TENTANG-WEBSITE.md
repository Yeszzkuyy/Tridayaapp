# Tentang Website Ini — Tridaya App (3DY App)

> Aplikasi web operasional internal **Tridaya Group** — satu platform CRM + ERP
> yang dipakai semua divisi, dari lead masuk sampai proyek selesai dan tertagih.
> Moto internalnya: *Full Visibility, Zero Guesswork* — semua divisi melihat
> kondisi terbaru satu customer yang sama, tanpa menebak-nebak.

## Website ini bergerak di bidang apa?

**Jasa teknik / system integrator IT.** Tridaya Group menaungi beberapa
perusahaan (`pt_group`: NTI, MGK, TPS, WANI) yang menjual, memasang, dan
merawat sistem untuk customer korporat. Cirinya terlihat dari alur kerja di
aplikasi: pra-penjualan (survei, demo/POC, sizing, penawaran harga/quotation)
→ instalasi & BAST → penagihan (invoice/PO/payment). Ini bukan toko online
atau aplikasi retail — ini **alat kerja karyawan internal**.

## Maksud dan tujuan website

Berawal dari kebutuhan sederhana: admin teknisi kesulitan melacak histori
tiap customer. Website ini menjawabnya dengan menjadi **satu sumber kebenaran**:

1. Setiap customer punya riwayat lengkap: lead, meeting, follow-up, project,
   dokumen instalasi, invoice — semua saling terhubung.
2. Setiap divisi bekerja di modulnya masing-masing, tapi datanya satu.
3. Manajemen bisa memantau progres per customer lintas divisi tanpa bertanya
   satu per satu ke tiap tim.

## Isi website per modul

| Modul | Isinya |
|---|---|
| **Dashboard** | Ringkasan umum + grafik (ApexCharts) kondisi terkini bisnis. |
| **Leads (Marketing)** | Pencatatan calon customer/opportunity, pipeline kanban drag-and-drop, import lead dari Excel, konversi lead menang menjadi project. |
| **WhatsApp Center** | Inbox 4 akun WhatsApp company + histori percakapan per lead/customer. |
| **Partners** | Data rekanan yang menjadi sumber atau mitra lead. |
| **Customers (Sales)** | Master customer + kontak/PIC; simpul yang menghubungkan lead, meeting, project, dan invoice. |
| **Meetings & Follow-ups** | Catatan hasil kunjungan (kebutuhan, keluhan, sistem existing) dan tindak lanjutnya. |
| **Manage Sales (Management)** | Melihat lead dari marketing, mengisi solusi/progress, dan menugaskan (assign) ke sales. |
| **Projects (Teknisi)** | Pengerjaan proyek: task, support, dokumen instalasi per kategori, plus alur teknis survey → sizing → request harga → instalasi. |
| **Jadwal & Kalender** | Jadwal teknisi (FullCalendar) yang tersinkron ke Google Calendar. |
| **Invoices, PO, Payments (Admin)** | Penagihan per customer/project: invoice diterbitkan, PO diproses, payment dicatat sampai lunas. |
| **Monitoring** | Satu halaman rekap progres per customer lintas divisi + aktivitas terakhir, untuk manajemen. |
| **Trash** | Keranjang: mengembalikan (restore) atau menghapus permanen customer & project. |
| **Admin Panel** | Khusus super admin: kelola user, role/permission, audit log, dan data master. |
| **AI Assistant & Knowledge Base** | Asisten AI (Gemini) + basis pengetahuan internal untuk membantu kerja. |
| **Notifikasi & Profil** | Notifikasi + web push, profil/avatar, dan pengaturan akun. |

## Siapa yang memakai (role pengguna)?

| Role | Peran |
|---|---|
| `super-admin` | Akses penuh + admin panel. |
| `manager` | Melihat semua divisi + monitoring. |
| `marketing` / `marketing-lead` | Mengelola lead dan pipeline. |
| `sales` | Mengelola customer, meeting, follow-up. |
| `teknisi` | Mengerjakan project dan jadwal. |
| `admin` | Invoice, PO, payment, trash. |
| `management` | Assign lead marketing ke sales. |
| `guest` | Akses default terbatas. |

Hak aksesnya granular (`manage-*` vs `view-*` per divisi) memakai
`spatie/laravel-permission`.

## Alur bisnis end-to-end (Lead → Selesai)

1. **Lead masuk (Marketing)** — dicatat lengkap dengan sumbernya
   (WA, email, telepon, canvasing, event, website, referal), status awal `new`.
2. **Kualifikasi** — di-follow-up sampai `contacted → qualified → proposal`
   (bisa drag-and-drop di kanban); management mengisi solusi dan menugaskan
   ke sales; sales mencatat meeting & follow-up.
3. **Menang → Project** — lead `won` dikonversi menjadi project
   (nama + status + jenis pekerjaan).
4. **Pengerjaan (Teknisi)** — task, support, survey/sizing/request
   harga/instalasi, dijadwalkan di kalender, didokumentasikan (terlihat juga
   dari halaman lead).
5. **Penagihan (Admin)** — invoice → PO → payment sampai lunas.
6. **Pengawasan** — manajemen memantau semuanya dari halaman monitoring.

## Teknologi yang dipakai (ringkas)

Backend **Laravel (PHP)** + **MySQL** (produksi), login bawaan Laravel Breeze,
tampilan **Tailwind CSS** + Livewire/Alpine.js, kalender FullCalendar, grafik
ApexCharts, sinkron Google Calendar, notifikasi WhatsApp gateway + web push,
dan AI Gemini untuk asisten kantor.

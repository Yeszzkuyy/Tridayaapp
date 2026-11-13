# Aturan Kerja Multi-Sesi

Repo ini dikerjakan oleh lebih dari satu sesi AI/developer secara paralel.
Sejak konsolidasi, setiap sesi punya **folder kerja (worktree) sendiri** —
ikuti aturan berikut supaya tidak saling menimpa kerjaan.

## 0. Protokol Komunikasi & Konfirmasi User (WAJIB DIPATUHI AGENT)

Saat berinteraksi dengan User (Yeski), kamu **DILARANG** memberikan penjelasan
yang terlalu panjang, bertele-tele, atau penuh istilah teknis kode (seperti
menulis potongan kode PHP/Eloquent/SQL panjang di chat). Gunakan bahasa
produk/operasional kantor yang sederhana, singkat, dan langsung ke inti.

### 0.1 Aturan Gaya Bahasa (Singkat, Padat, Non-Teknis)

- **Bahasa:** Bahasa Indonesia yang santai, jelas, dan mudah dipahami dari sudut
  pandang pengguna website (Sales, Admin, Teknisi, Manajemen).
- **Larangan jargon teknis di chat:** jangan menjelaskan logika dengan kalimat
  seperti *"Saya akan mengubah `hasManyThrough` di `LeadController@pipeline`
  baris 69"*. Ganti menjadi bahasa hasil: *"Saya akan merapikan papan Pipeline
  agar loading-nya cepat dan tidak error saat data Customer kosong."* Sebutkan
  nama file hanya di baris ringkas jika diperlukan.
- **Batas panjang jawaban:** maksimal **3–5 poin pendek** per bagian atau sesuai dengan kebutuhan yang memang perlu dijelaskan. Jangan
  menulis paragraf panjang.

### 0.2 Protokol "Interview Me" (Konfirmasi Dulu Sebelum Eksekusi)

Sebelum mengubah kode, atau saat masih ada alur yang bisa berdampak ke cara kerja
orang kantor, kamu **WAJIB berhenti dan bertanya/konfirmasi dulu** kepada User.

Saat bertanya, **DILARANG** memberikan pertanyaan teknis kode. Gunakan format
pertanyaan **"Tujuan & Hasil Akhir"** berikut (maksimal 1–3 pertanyaan sekali
tanya):

> ### 🤔 Konfirmasi Dulu Sebelum Dikerjakan
> **1. Tujuan yang Mau Dicapai:**
> - [Jelaskan 1 kalimat singkat fitur/perubahan apa yang sedang mau diselesaikan]
>
> **2. Hal yang Perlu Kamu Putuskan:**
> - **Pertanyaan:** [Tanyakan dengan bahasa sederhana tentang aturan kerja di kantor]
>   - **Opsi A (Rekomendasi):** [Apa yang terjadi] → **Hasilnya nanti di layar:** [Gambaran jelas tampilan/alurnya bagi user]
>   - **Opsi B:** [Alternatif lain] → **Hasilnya nanti di layar:** [Gambaran jelas tampilan/alurnya bagi user]
>
> **3. Dampak ke Rencana Berikutnya:**
> - [1 kalimat tentang bagaimana pilihan ini memengaruhi menu lain ke depannya, misal ke menu Sales, Admin, atau Teknisi]

Tunggu sampai User menjawab "Oke opsi A" atau memberi arahan sebelum kamu
mengeksekusi perubahan file.

**Pengecualian:** kalau User sudah menulis "kerjakan", "oke", "lanjut", atau
instruksi sudah spesifik, konfirmasi dianggap selesai. Jangan bertanya ulang
hanya karena protokol ini.

### 0.3 Format Standar Saat Melaporkan Rencana Kerja (Plan Mode)

Jika User meminta rencana perbaikan/fitur, jawab **HANYA** dengan struktur
ringkas ini:

> ### 📋 Rencana Perubahan Singkat
> **🎯 Tujuan & Hasil Akhir:**
> - [1-2 kalimat: Setelah ini selesai, tampilan/fitur di menu X akan menjadi seperti apa]
>
> **🛠️ Apa Saja yang Akan Dilakukan:**
> - [Poin 1 - bahasa non-teknis]
> - [Poin 2 - bahasa non-teknis]
> *(File yang disentuh: `nama_file_1`, `nama_file_2`)*
>
> **❓ Butuh Konfirmasi Darimu:**
> - [Tanyakan 1-2 hal penting sesuai protokol "Interview Me", atau tanyakan: *"Apakah hasil akhirnya sudah sesuai harapanmu untuk langsung aku kerjakan?"*]

### 0.4 Format Standar Setelah Selesai Mengerjakan Tugas (Build Mode)

Setiap kali kamu selesai mengubah kode dan menjalankan test, laporkan hasilnya
dengan format di bawah ini. Tujuannya agar User bisa langsung mencocokkan
perubahan dengan layar yang dilihatnya, tanpa menebak bagian mana yang berubah.

> ### ✅ Ringkasan Pekerjaan Selesai
> **1. Perubahan per Menu:**
> - **Menu [Nama Menu] → [Sub-menu/Halaman]:**
>   - Bagian yang berubah: [field/tombol/alur yang diubah]
>   - Hasil sekarang: [tampilan atau perilaku baru yang terlihat user]
> - *(ulangi untuk setiap menu yang disentuh, satu poin per menu)*
>
> **2. Cara Testing di Website:**
> - **Login sebagai:** [role/user, mis. Sales NTI / Audi]
> - **Buka menu:** [Menu → Sub-menu]
> - **Langkah:** [1-3 langkah singkat]
> - **Hasil yang diharapkan:** [apa yang harus terlihat atau terjadi]
> - *(ulangi untuk skenario utama lainnya, termasuk sisi Management/Admin bila terkena dampak)*
>
> **3. Status & Konfirmasi:**
> - Test otomatis: [jumlah lulus/gagal]
> - Build/merge: [sudah/belum]
> - **Berhenti di sini. Jangan lanjut ke tahap atau menu berikutnya sebelum User menyatakan hasilnya sudah sesuai.**
> - Jika ada yang belum sesuai, tanyakan bagian mana yang perlu diperbaiki dan perbaiki dulu; jangan menumpuk perubahan baru.

**Aturan tambahan:**

- Setiap menu yang berubah wajib punya minimal satu langkah testing.
- Skenario testing wajib menyebut **user/role**, **menu**, **langkah**, dan
  **hasil yang diharapkan**.
- Jika belum ada user/role yang cocok untuk testing, tulis **"belum bisa
  dites"** beserta alasannya. Jangan mengarang langkah testing.
- Jangan menulis "sudah selesai" untuk bagian yang belum diverifikasi di layar.
- Kalau User menemukan hasil yang belum sesuai, perbaiki bagian itu dulu dan
  ulangi langkah testing. Jangan mulai mengerjakan tahap berikutnya.

Contoh singkat:

```text
Menu Sales → Customer
- Bagian yang berubah: daftar customer dibatasi ke pemiliknya.
- Hasil sekarang: Sales hanya melihat customer dari lead/project miliknya.

Testing:
- Login sebagai Sales NTI.
- Buka menu Customer.
- Hasil yang diharapkan: customer milik Sales lain tidak muncul dan detailnya
  tidak bisa dibuka lewat URL.
```

### 0.5 Batasan

- Protokol ini mengatur gaya komunikasi di chat, bukan kualitas kode.
- Aturan teknis di §7, §7.1, §7.2, §8, dan §9 tetap wajib dipatuhi.
- Laporan WhatsApp ke Senior tetap mengikuti §13, bukan format §0.4.

### 0.6 Mekanisme Proteksi Konteks (Self-Correction)

- Sebelum kamu mengirimkan respons chat ke User (Yeski), kamu WAJIB melakukan validasi mandiri: *"Apakah jawaban saya mengandung istilah teknis kode seperti nama method, framework logic, atau query SQL di luar tanda kurung ringkas?"*
- Jika YA, kamu WAJIB menulis ulang jawaban tersebut ke dalam bahasa operasional kantor (Sales/Admin/Teknisi) sesuai aturan §0.1 sebelum tombol kirim ditekan.


## 1. Satu sesi = satu folder = satu branch

| Folder | Branch | Topik |
|---|---|---|
| `/var/www/3dyapp` | `main` | **Integrasi + yang dilayani website.** Jangan kerja fitur di sini |
| `/var/www/3dyapp-mkt` | `feature/div-marketing` | Lead, partner |
| `/var/www/3dyapp-tek` | `feature/div-teknisi` | Dashboard/jadwal teknisi |
| `/var/www/3dyapp-user` | `feature/user-management-avatar` | User, role, menu, avatar |
| `/var/www/3dyapp-mon` | `feature/div-mon` | Monitoring (progress per customer) |
| `/var/www/3dyapp-cus` | `feature/div-cus` | Customer |
| `/var/www/3dyapp-dash` | `feature/general-dashboard` | Dashboard umum |
| `/home/yeski/3dyapp-management` | `feature/management` | Management (Activity Log, assign lead) |
| `/var/www/3dyapp-sec` | `feature/security-hardening` | Security hardening |
| `/var/www/3dyapp-sales` | `feature/sales-flow` | Sales (dashboard, my-leads, meeting, follow-up) |
| `/home/raafi/3dyapp-sales` | `feature/sales-workspace` | Sales workspace (My Leads, Follow Up, DAR) |
| `/home/yeski/3dyapp-inside` | `feature/sales-inside` | Inside Sales (dashboard, quotation, PDF per PT) |
| `/var/www/3dyapp-notif` | `feature/sys-notif-center` | Notification Center (kartu, realtime Reverb, bell semua role) |
| `/home/raafi/3dyapp-notifpop` | `feature/sys-notif-popup` | Pop up notifikasi pojok layar (gaya toast 21st.dev) |
| `/home/raafi/3dyapp-i18n` | `feature/i18n-english` | i18n (konsistensi bahasa semua menu) + tampilan settings |
| `/home/raafi/3dyapp-price` | `feature/sales-price-sync` | Price Request sales+teknisi satu tabel (sementara, hapus setelah merge) |
| `/home/yeski/3dyapp-panel` | `feature/div-panel` | Admin Panel (invoice, PO, payment) |
| `/home/raafi/3dyapp-contacts` | `feature/cus-contact-source` | Satu sumber kontak customer (PIC) |
| `/home/raafi/3dyapp-doc` | `feature/sys-document-center` | Document Center lintas modul + tab Dokumen Customer |
| `/home/raafi/3dyapp-price-chat` | `feature/sales-price-chat` | Diskusi Price Request (Enter kirim, Shift+Enter baris baru) |
| `/home/raafi/3dyapp-tekflow` | `feature/tek-project-workflow` | Integrasi workflow project + engineer (survey, sizing, price request, instalasi, BAST) |
| `/home/yeski/3dyapp-sys` | `feature/sys-dropdown-standard` | Toast, konfirmasi, dropdown standar (lintas divisi) |

Worktree baru juga butuh: `composer install`, symlink `.env`, dan
`ln -s /var/www/3dyapp/public/build /var/www/3dyapp-<nama>/public/build`.
Catatan: `/var/www` sekarang hanya bisa ditulis root — worktree baru boleh
ditaruh di `/home/<user>/3dyapp-<nama>` bila tidak bisa membuat folder di
`/var/www` (lihat entry i18n di atas).

Rencana pembagian berikutnya (buat worktree-nya saat mulai dikerjakan):
`feature/div-trash` (Trash), `feature/div-panel` (Admin Panel).

Butuh folder untuk topik baru?

```bash
git worktree add /var/www/3dyapp-<nama> feature/<branch>
ln -sf /var/www/3dyapp/.env /var/www/3dyapp-<nama>/.env
```

**DILARANG pindah branch di dalam worktree milik sesi lain.**
Satu worktree = satu branch sampai fitur selesai.

## 2. Alur kerja (satu arah)

```
fitur (di worktree divisi)  →  merge ke main  →  semua worktree git pull
```

- Sebelum mulai: `git pull origin <branch-mu>`
- Sebelum merge ke `main`: pastikan `php artisan test` lulus
- Setelah `main` berubah: kembali `git pull` di worktree masing-masing
- Jangan cherry-pick antar branch divisi — kalau butuh perubahan bersama,
  merge `main` ke branch-mu

## 3. Checkpoint WIP, commit final satu paket

Commit + push = backup, tapi backup-nya berupa **branch**, bukan tumpukan
commit kecil di `main`.

- Selama perubahan masih bolak-balik ("belum pas" → ubah → cek ulang), simpan
  progres dengan `wip(<divisi>/<menu>): ...`.
- Begitu perubahan sudah sesuai maksud user (user sudah cek dan bilang sesuai),
  **squash** semua `wip` jadi **satu commit paket** dengan penjelasan utuh
  (`scripts/squash-wip.sh`). Aturan lengkap di §9.
- `main` hanya boleh berisi commit paket — `wip(...)` dilarang masuk `main`.

## 4. Pindah branch hanya dengan tree bersih

Commit dulu atau `git stash push -m "pesan jelas"`.
**Dilarang keras `git reset --hard` selama sesi lain masih aktif.**

## 5. Website dilayani dari `main`

Folder utama (`/var/www/3dyapp`) harus selalu berada di branch `main`.
Setelah merge ke main: `git pull`, lalu `npm run build` jika ada perubahan CSS/JS.

**Selalu `npm run build` dari `/var/www/3dyapp` (main), JANGAN dari worktree
divisi.** `public/build` tiap worktree di-symlink ke `public/build` main,
sehingga build dari worktree basi menimpa CSS/JS website dengan hasil sisiran
Tailwind atas view lama — kelas yang cuma dipakai view baru ikut terbuang
dan halaman live berantakan.

**Cache view & user web server:** setelah menjalankan `php artisan test` atau
perintah artisan lain di folder main sebagai user selain `www-data`, file cache
view ikut dimiliki user tersebut. Web server (`www-data`) lalu gagal `touch`
saat Blade memperbarui cache → **seluruh halaman 500**. Setelah menjalankan
test/artisan di main, bersihkan cache view sebagai pemilik web server:

```bash
sudo -u www-data php artisan view:clear
```

**Cache file juga ikut:** perintah artisan/test sebagai user lain membuat
`storage/framework/cache/data/**` milik user tersebut (mode 644), sehingga
proses lain yang bukan pemiliknya gagal menulis cache dan halaman bisa
**500** (gejala: `/teknisi/dashboard` & `/teknisi/jadwal` error
`file_put_contents` / `fopen ... Permission denied`). Setelah menjalankan
artisan/test di main, rapikan juga izin cache:

```bash
sudo chmod -R g+rwX storage/framework/cache storage/framework/views \
     storage/framework/sessions storage/app storage/logs
```

## 6. Jangan commit

- Folder `backups/` (sudah di-gitignore)
- Kredensial / `.env`
- File hasil eksperimen tanpa persetujuan

## 7. Tombol (konvensi UI)

Semua tombol di aplikasi ini wajib konsisten dalam bentuk, sudut lengkung (**wajib `rounded-xl`**),
ukuran, feedback interaktif, dan warna agar tidak ada tombol yang terasa belang.

### Peta Aksi Standar:

| Aksi | Pola standar / Komponen | Detail Style |
|---|---|---|
| Simpan / Submit utama (Save) | `<x-primary-button>` | `group relative overflow-hidden rounded-xl bg-accent-600 hover:bg-accent-700 text-white px-6 py-2.5 text-sm font-medium` + shine + `hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110 active:scale-95` |
| Batal (Cancel, di form/modal) | `<x-secondary-button>` | `group relative overflow-hidden rounded-xl bg-accent-500 hover:bg-accent-600 text-white px-6 py-2.5 text-sm font-medium text-center active:scale-95` + shine |
| Aksi tabel/baris/filter/navigasi | `<x-icon-button>` | `rounded-xl` — idle putih + border tipis + **ikon berwarna per aksi**, hover fill warna aksi solid + ikon putih + shine + `hover:scale-110 active:scale-95` |
| **CTA utama tunggal per halaman** (New Request, Add Meeting, Add Follow Up, Add Survey, dst.) | `<x-icon-button variant="solid">` | `rounded-xl h-10 bg-accent-600 text-white` + `shadow-accent-500/20` + shine + `active:scale-95`. Ikon `h-4 w-4` stroke 3.5. Animasi `reveal` **opsional** (lihat bawah). |
| **CTA utama dengan efek "flow"** (pill→kotak + lingkaran membesar, mis. Field Work → Add Survey / Add Installation) | `<x-flow-button>` | Port template 21st.dev `flow-button` dengan warna `accent` (varian `neutral` tersedia) + ikon kontekstual (`icon="clipboard-check"`). `h-10 px-8`, `rounded-[100px] hover:rounded-xl`, `border-[1.5px] accent-600/40`, lingkaran `group-hover:w-[220px]` accent, panah keluar kanan. **Maksimal 2 aksi berdampingan**. |
| **Navigasi kembali (Back)** — **STANDAR** | `<x-back-button>` | `rounded-xl`. Idle: surface `bg-[var(--card-bg)]` (JANGAN `bg-white` — override dark-mode `!important` mematikan hover), panah accent. Hover: **panel accent-600 meluncur dari kiri** (`-translate-x-full → 0`, 500ms), teks/panah jadi putih, panah geser kiri dengan bayangan panah tertinggal (delay 100ms). `h-10 pr-4 text-sm font-semibold`, `active:scale-95`, `motion-reduce` dihormati. |
| Hapus / destructive (icon-only) | `<x-icon-button icon="delete">` | `rounded-xl` — idle putih + ikon merah, hover background merah solid + ikon putih |
| Konfirmasi hapus (tombol teks modal) | `<x-danger-button>` | `rounded-xl border border-red-200 text-red-600`, hover background merah solid + teks putih + shine + `active:scale-95` |

**Aturan Bentuk, Radius & Ukuran (Wajib Seragam):**
- **Sudut Lengkung Wajib `rounded-xl`:** Seluruh tombol (`primary-button`, `secondary-button`, `danger-button`, `icon-button`, `add-button`) wajib menggunakan `rounded-xl` (12px). Dilarang keras mencampur `rounded-lg` atau `rounded-md`.
- **Tinggi Elemen:** Tombol teks standar (`primary`, `secondary`, `danger`) memakai `px-6 py-2.5 text-sm font-medium` (tinggi ~40px). Tombol icon-only standar (`<x-icon-button>`) memakai `h-10 w-10` (`size="md"`), dan tombol icon dengan teks (`label="..."`) memakai `h-10 px-4 text-sm font-medium gap-2`. Seluruhnya sejajar 1:1 dengan tinggi field input form (`h-10`).
- **Ukuran ikon tombol berlabel (WAJIB):** ikon memakai **`h-4 w-4` (16px) + `stroke-width="3.5"`** agar sejajar dengan teks `text-sm` (14px) dan terasa tegas. **Dilarang** memakai ikon `h-5 w-5` di tombol berlabel karena membuat ikon terlihat lebih besar dari teksnya. Icon-only tetap `h-5 w-5` + stroke 2.
  - **Rumus ketebalan tampil:** `stroke-width × (ukuran ikon / 24)`. Contoh: 3.5 pada 16px ≈ **2.33px** (tegas); 2.5 pada 20px ≈ 2.08px; 2 pada 20px ≈ 1.67px (terlalu tipis). Saat mengecilkan ikon, naikkan `stroke-width` agar ketebalan tampil tidak ikut menipis.
  - Varian label di `<x-add-button>` wajib ikut aturan ini (`h-4 w-4` + stroke 3.5).
- **Animasi `reveal` (opsional, bukan default):** `<x-icon-button variant="solid" reveal>` meringkas tombol jadi icon-only (`px-2.5`) lalu melebarkan label saat hover (`max-w-0 → max-w-44`, ikon `rotate-90`, 500ms). Pakai hanya bila tombol aksi itu memang berdiri sendiri di header halaman; menu lain cukup varian statis (label selalu tampil). Wajib tetap isi `title`/`aria-label` supaya terbaca saat collapsed, dan `motion-reduce:transition-none` sudah otomatis.
- **Hierarki CTA (WAJIB):** **satu CTA primer per halaman** (`variant="solid"` atau `<x-flow-button>`). Aksi sekunder, baris tabel, filter, dan navigasi wajib icon-only. Jangan menaruh dua CTA primer yang bersaing di header yang sama.
- **Sumber ikon (WAJIB):** nama ikon harus ada di `<x-icon>`. `<x-icon-button>` otomatis fallback ke `<x-icon>` bila nama tidak ada di peta lokalnya, sehingga tombol tidak pernah tampil kosong — tetap cek `title`/`aria-label` tetap terisi.
- **Tombol Back (WAJIB):** semua navigasi kembali memakai **`<x-back-button>`** (label default "Back"), **bukan** `<x-icon-button icon="back" />` atau tombol teks buatan sendiri. Bila satu baris punya CTA + Back (mis. `Edit Customer` + `Back`), pasangkan `<x-flow-button>`/`<x-icon-button variant="solid">` untuk CTA dan `<x-back-button>` untuk Back.
- **Flow button:** `<x-flow-button>` (pill → kotak + lingkaran membesar) maksimal **2 tombol berdampingan**; warna mengikuti `variant` (`accent` default, `neutral` opsional) dan ikon aksi masuk dari kiri saat hover. Ikon `h-4 w-4` stroke 2.5.
- **Feedback Interaktif:** Seluruh tombol wajib memiliki respon sentuhan instan `active:scale-95` dan transisi halus `transition-all duration-300` ditambah efek kilau (*shine effect*).

**Warna per ikon** (tiap ikon punya identitas warna sendiri — biar tombol
berjajar tidak menyatu/monoton) — otomatis dari `icon=` di `x-icon-button`:

| Ikon | Idle | Hover |
|---|---|---|
| `view` | ikon indigo-600 | bg indigo-600 + ikon putih |
| `customer` | ikon sky-600 | bg sky-600 + ikon putih |
| `project` | ikon violet-600 | bg violet-600 + ikon putih |
| `edit` | ikon amber-600 | bg amber-600 + ikon putih |
| `delete` | ikon red-600 | bg red-600 + ikon putih |
| `add`, `sparkles` (positif) | ikon green-600 | bg green-600 + ikon putih |
| `whatsapp` | ikon emerald-600 | bg emerald-600 + ikon putih |
| `mail` | ikon cyan-600 | bg cyan-600 + ikon putih |
| `download`, `upload`, `import`, `print` | ikon teal-600 | bg teal-600 + ikon putih |
| `reset`, `more` | ikon slate-500 | bg slate-600 + ikon putih |
| lainnya (filter/back/search/...) | ikon accent theme | bg accent + ikon putih |

Varian dark mode sudah otomatis di komponen (`dark:bg-slate-800`,
`dark:border-slate-700`, hover warna solid) — jangan tulis ulang di
view. **Semua warna tombol wajib solid** — dilarang warna transparan
(`/15`, `/25`, `bg-white/50`) di light maupun dark mode.
Tooltip pill gelap hanya muncul bila prop `tooltip` diisi eksplisit
(tanpa itu cukup `title` bawaan browser — jangan pasang tooltip di semua
tombol baris tabel, nanti menyala bareng saat satu tombol di-hover).

Efek standar tombol teks (Save/Cancel/konfirmasi): `group relative overflow-hidden` + span shine
(`pointer-events-none absolute inset-0 -translate-x-full -skew-x-12 bg-gradient-to-r
from-transparent via-white/60 to-transparent transition-transform duration-700 ease-out
group-hover:translate-x-full`) + `transition-all duration-300 hover:shadow-xl
hover:shadow-accent-500/50 hover:brightness-110 active:scale-95`.

**Bentuk tombol (wajib):** hanya **Save** (submit utama) dan **Cancel**
(batal di form) yang boleh berupa tombol teks. Semua aksi lain **wajib
icon-only** via `<x-icon-button>` (pakai `label` bila teksnya perlu tetap
terlihat) — jangan tulis ulang, cukup `as`/`icon`/`title` (`href` bila link,
`type="submit"` bila submit):

```blade
<x-icon-button icon="filter" type="submit" title="Filter" />
<x-icon-button as="a" icon="reset" href="..." title="Reset" />
<x-icon-button as="a" icon="view" href="..." title="View" />
<x-icon-button as="a" icon="edit" href="..." title="Edit" />
<x-icon-button icon="delete" type="submit" title="Delete" />
<x-icon-button as="a" icon="add" href="..." label="Add Schedule" title="Add Schedule" />
```

Ikon bawaan komponen: `filter reset leads add import back sparkles view edit
customer project delete search download upload print mail whatsapp more` —
ikon lain pakai slot SVG sendiri.

Aksi baris tabel (view/edit/delete/WA) juga icon-only: `<x-icon-button>` +
`title` (contoh: `title="View details"`). Dilarang tombol teks
macam "Cari", "Lihat", "+ Catat ..." — ganti icon-only.

**Bahasa tombol (wajib):** default **Inggris** via `__('...')`
(`title="Filter"` bukan "Saring", `title="Reset"` bukan "Atur Ulang",
`title="Back"` bukan "Kembali"). Teks Indonesia hanya lewat file
terjemahan, jangan hardcode di view. View lama yang masih Indonesia
dirapikan bertahap setiap menyentuh file itu.

**Larangan:** jangan pakai `bg-slate-*`, `bg-gray-*`, atau `bg-white` sebagai
background tombol — CSS dark-mode global mengoverride kelas tersebut sehingga
tombol menyatu dengan background.

Setiap menambah **kelas Tailwind baru**: jalankan ulang `npm run build` setelah
merge ke main, kalau tidak kelasnya tidak akan ter-compile.

## 7.1 Standar Desain UI/UX ("Lynk Dashboard Style") & Aturan Anti-Filler

Seluruh halaman (terutama Dashboard, Monitoring, KPI, dan List/Detail) wajib mengikuti standar bahasa desain **"Lynk Dashboard Style"** agar tampilan aplikasi konsisten, modern, luwes, kaya grafis fungsional, dan bebas dari teks sampah (*filler text*):

1. **Aturan Global "No Filler Text" & Open Header Style:**
   - **DILARANG** menulis sub-judul dekoratif/basa-basi di bawah judul halaman atau di bawah judul kartu (seperti *"Latest figures from..."*, *"Monitor project status..."*, *"4 indicators"*, *"Summary of your agenda"*).
   - **Open Header Style:** Jangan membungkus sapaan halaman (`"Welcome back, [Name] 👋"`) atau judul utama halaman di dalam kotak/card ber-border. Biarkan menyatu langsung dengan background halaman di sisi kiri, dan letakkan pill tanggal (`rounded-full border px-4 py-2 text-xs font-semibold`) serta tombol aksi utama di sisi kanan sejajar dengan judul.
   - **Topbar Breadcrumb:** Dilarang menduplikasi nama grup/halaman di topbar (gunakan format ringkas `[Icon Grup] / Sub-menu`, dan jangan tampilkan tanggal ganda di topbar kanan).

2. **Anatomi Standar Kartu KPI (Lynk KPI Card):**
   - **Bagian Atas:** Letakkan **Icon Box** (`h-11 w-11 rounded-xl` dengan warna translucent lembut) di **sebelah kiri**, berdampingan langsung (`flex items-center gap-3.5`) dengan Label (`text-xs font-medium text-slate-500 dark:text-slate-400`) di atas dan Angka Utama (`text-2xl sm:text-3xl font-extrabold tabular-nums`) di bawahnya.
   - **Bagian Tengah (Trend Text):** **DILARANG** memasang garis pembatas horizontal (`border-t`/`hr`) di tengah kartu KPI dan dilarang memasang ikon chevron `>`. Tampilkan sub-metrik sebagai teks bersih dengan panah tren (`↗ +X new this month` atau rincian ringkas `X Open · Y On Progress`).
   - **Ambient Corner Light:** Tambahkan pendaran cahaya lembut (*ambient glow*) di pojok kanan atas kartu (`pointer-events-none absolute -right-10 -top-10 h-28 w-28 rounded-full bg-<color>-500/15 blur-2xl dark:bg-<color>-500/20`) sesuai warna aksen kartu.
   - **Bagian Bawah (Data-Driven SVG Sparkline — Wajib Berbasis Data Riil):**
     - Gunakan helper `App\Support\SparklineBuilder::fromSeries($monthlySeries, $currentValue)` yang menarik data agregat historis 6 bulan ke belakang dari database (`SparklineBuilder::monthlyCounts(...)`).
     - **Aturan Kejujuran Data (Zero/Flat Rule):** Jika nilai metrik saat ini `0` atau seluruh data historis bernilai `0`/datar, `SparklineBuilder` **wajib** merender **garis lurus mendatar (`M0,43 L240,43`)** — dilarang keras merender kurva naik palsu pada metrik bernilai `0`. Jika tren riil naik/turun, kurva Cubic Bezier otomatis mengikuti titik data riil tersebut.

3. **Distribusi Status (Side-by-Side Donut Chart & 100% Complete Legend):**
   - Untuk widget distribusi status (seperti *Project Status Breakdown*, *Work Progress*, *Lead Pipeline*), gunakan layout **2 kolom berdampingan di dalam kartu** (`flex flex-col sm:flex-row items-center justify-between gap-6`):
     - **Kiri:** `<x-donut-chart :size="176" :strokeWidth="24">` dengan angka total di tengah lubang cincin.
     - **Kanan:** Daftar Legend vertikal yang menampilkan **seluruh status aktif (`count > 0`)** lengkap dengan dot warna, nama status, dan `jumlah (persentase%)` sehingga total segmen Donut dan Legend selalu genap **100%** tanpa celah.

4. **Clean Lists (No Nested Card Borders), Date Block & System Avatar:**
   - **DILARANG** membungkus setiap baris item di dalam kartu list (*Agenda*, *Recent Activity*, *Latest Projects*, *Company Board*) dengan border kotak bersarang (*nested boxes*). Gunakan baris list bersih dengan hover halus (`rounded-xl p-2.5 transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-700/35`).
   - Gunakan **Date Block Box** bergaya kalender (`h-11 w-11 rounded-xl` berisi tanggal besar `03` di atas dan bulan 3 huruf `NOV` di bawah) atau **Soft Colored Icon Box** di sisi kiri item, serta **mini progress bar** (`w-16 h-1.5 rounded-full`) + pill status di sisi kanan.
   - Untuk aktivitas yang dilakukan oleh `System` (`user === null`), `<x-user-avatar>` wajib merender **ikon SVG Sparkles** di dalam lingkaran indigo lembut, bukan tanda tanya `?`.

5. **Standar Animasi & Interaktivitas (`partials/lynk-dashboard-motion.blade.php`):**
   - **Staggered Fade-In Up (`.lynk-enter`):** Durasi `650ms` dengan easing `cubic-bezier(0.22, 1, 0.36, 1)` dan delay berurutan (`0ms` header, `100ms–340ms` kartu KPI, `420ms–500ms` baris tengah, `580ms–660ms` baris bawah).
   - **Smooth Count-Up (`data-countup`):** Durasi **`1800ms` (1.8 detik)** dengan *easeOutExpo* (`1 - Math.pow(2, -10 * p)`) agar pergerakan angka dari `0` menuju nilai akhir terlihat jelas dan halus.
   - **SVG Line Draw & Progress Fill:** Garis sparkline menggambar dari kiri ke kanan (`.lynk-spark-line` dengan `pathLength="100"`, durasi `1400ms`), area fill muncul halus (`.lynk-spark-area`, delay `300ms`, durasi `1000ms`), cincin Donut Chart berputar (`1200ms`), dan mini progress bar memanjang (`.lynk-progress-bar`, durasi `1000ms`). Seluruh animasi wajib menghormati `prefers-reduced-motion: reduce`.
   - **Card Container Styling:** Gunakan `rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800/60 dark:bg-slate-800/90` dengan hover `-translate-y-1 transition-all duration-300 hover:shadow-lg` pada kartu KPI.
   - **Larangan Warna HEX Acak Dark Mode:** Jangan gunakan `dark:bg-[#232b3e]` atau `dark:border-[#334070]`; gunakan token Tailwind slate konsisten (`dark:bg-slate-800/90`, `dark:border-slate-800/60`, `dark:text-slate-100`).

## 7.2 Aturan Anti-Slop (Adopsi "design-taste-frontend")

Diadaptasi dari skill Anti-Slop `design-taste-frontend` (tasteskill). Skill aslinya
untuk landing page/portfolio, jadi yang dipakai di sini hanya prinsip yang relevan
untuk aplikasi internal (dashboard, list, detail, form, modal). Aturan hero, logo
wall, bento, scroll-stacking, dan scroll cue **tidak berlaku** di repo ini. §7
(tombol) dan §7.1 (Lynk) tetap menang bila terjadi benturan.

### A. Baca konteks sebelum menulis UI

1. Tentukan jenis layar (dashboard / list / detail / form / modal) dan role pemakainya.
2. Cek komponen & token yang sudah ada: `x-icon-button`, `x-flow-button`,
   `x-back-button`, `x-donut-chart`, `SparklineBuilder`, `lynk-*`. Jangan bikin pola
   baru bila komponen yang ada sudah cukup.
3. Untuk pekerjaan UI baru atau redesign, tulis satu baris **"Reading this as: ..."**
   di rencana sebelum coding. Kalau brief benar-benar ambigu, tanya **satu**
   pertanyaan; jangan mengarang arah desain.
4. Redesign: audit layar lama dulu, pertahankan yang sudah sesuai §7/§7.1, ubah
   hanya bagian yang diminta.

### B. Anti-Default (DILARANG sebagai default)

- Gradient ungu/biru ala "AI", neon glow, dan glassmorphism generik.
- Tiga kartu fitur identik berjajar sebagai pengganti struktur konten. Kartu KPI
  dashboard tetap mengikuti §7.1.
- Animasi infinite di semua elemen, atau motion tanpa alasan yang jelas.
- Font Inter sebagai default (repo memakai Plus Jakarta Sans + Outfit).
- Emoji sebagai ikon; ikon dekoratif yang tidak menyampaikan makna.
- Data palsu yang terlihat rapi: `99.99%`, `1234567`, nama "John Doe"/"Acme",
  "Lorem ipsum", testimoni atau logo klien fiktif.
- Label mikro-meta: `01 / 04`, `Stage 1`, `v1.4.2`, `last sync 4s ago`,
  `Scroll to explore`.
- Em-dash (`—`) dan en-dash (`–`) di teks yang dilihat user; pakai hyphen biasa.
  Teks lama dirapikan bertahap saat file itu disentuh.
- Teks dekoratif di bawah judul ("Latest figures from...", "4 indicators") —
  sudah dilarang di §7.1.

### C. Tipografi & warna

- Maksimal 2 keluarga font. Hierarki lewat weight + warna, bukan H1 raksasa.
- Body `max-w-[65ch]` + `leading-relaxed`; jangan center teks panjang.
- Italic di display wajib `leading-[1.1]` + ruang descender.
- Satu accent per halaman (token `accent-*`). Dilarang hex acak, `bg-slate-*`/
  `bg-gray-*` untuk tombol (§7), dan mencampur warm/cool gray.
- Kontras WCAG AA: teks 4.5:1, teks besar 3:1. Placeholder, helper, dan focus ring
  ikut dicek.
- Dark mode memakai token yang sama; jangan mengubah tema per section.

### D. Layout & material

- Kartu hanya bila elevation menyampaikan hierarki; selain itu pakai `divide-y`,
  border tipis, atau whitespace.
- Radius konsisten: tombol `rounded-xl`, kartu `rounded-2xl`. Jangan campur
  `rounded-md`/`rounded-lg` (§7).
- Dilarang kartu bersarang di dalam kartu list (§7.1).
- Shadow diberi tint warna background, bukan hitam pekat.
- Setiap grid multi-kolom wajib punya perilaku mobile eksplisit
  (`grid-cols-1 sm:grid-cols-2 ...`), bukan asumsi "Tailwind sudah urus".
- Satu tema per halaman: jangan menyisipkan section terang di halaman gelap
  (atau sebaliknya).
- Jangan menaruh paragraf penjelas kecil melayang di pojok kanan header section;
  taruh di bawah judul atau susun 2 kolom yang sejajar.

### E. State & interaksi

- Wajib ada state: loading, empty, error, success.
- Utamakan skeleton yang mengikuti bentuk konten; spinner generik hanya untuk aksi
  singkat.
- Empty state menjelaskan cara mengisi data, bukan sekadar "No data".
- Form: label di atas input, helper opsional, error di bawah input. Placeholder
  bukan pengganti label.
- Tombol wajib punya `active:scale-95`, focus ring, dan teks yang tidak wrap di
  desktop.
- Satu intent = satu label CTA. Jangan "Simpan" + "Submit" untuk aksi yang sama di
  satu halaman.

### F. Copy & data

- Semua teks UI lewat `__('...')`; default Inggris, Indonesia lewat `lang/id.json`
  (§7).
- Dilarang filler/AI-copy: "Elevate", "Seamless", "Unleash", "Next-Gen",
  "Revolutionize", atau kalimat mock-puitis.
- Angka harus berasal dari data riil atau diberi label contoh. Dilarang angka
  presisi palsu.
- Data demo/test memakai nama & konteks Indonesia yang wajar, bukan "John Doe".
- Baca ulang semua string sebelum selesai: judul, label, tombol, error, alt text.
  Ganti kalimat yang ambigu atau terasa seperti terjemahan mesin.

### G. Aksesibilitas & performa

- Fokus keyboard terlihat di semua elemen interaktif; jangan `outline-none` tanpa
  pengganti.
- Ikon-only wajib punya `title`/`aria-label` (§7).
- Hormati `prefers-reduced-motion`; animasi hanya untuk hierarki, feedback, atau
  transisi state.
- Jangan menambah dependency UI/animasi baru tanpa persetujuan; satu sistem desain
  per proyek.

### H. Pre-Flight Check (sebelum bilang "selesai")

1. Buka halaman di light & dark mode, desktop & mobile.
2. Cek kontras teks, tombol, placeholder, dan focus ring.
3. Cek state: loading, empty, error, sukses.
4. Cek keyboard: Tab, Enter, Esc, dan focus ring.
5. Cek tidak ada teks filler, em-dash, angka palsu, atau nama generik.
6. Cek tombol mengikuti §7 (radius, ikon, hierarki CTA).
7. Cek tidak ada kartu bersarang, 3 kartu identik, atau section theme flip.
8. Jalankan `npm run build` dari `/var/www/3dyapp` bila ada kelas Tailwind baru.

## 7.3 Standar Feedback Aksi: Toast & Kartu Konfirmasi

Satu sistem feedback untuk seluruh aplikasi. Tujuannya: setiap aksi user
langsung terlihat hasilnya, dan setiap aksi berisiko selalu minta persetujuan
dengan tampilan yang sama.

### A. Toast (pop up pojok kanan bawah)

1. **Satu antrean, satu tampilan.** Semua pesan aksi memakai
   `resources/js/toast-store.js` + `ToastViewport` + `NotificationToast`
   (dipasang lewat Notification Center di header). Dilarang membuat pop up
   sendiri, `alert()`, atau kotak pesan hijau/merah di atas halaman untuk
   konfirmasi aksi.
2. **Sumber pesan yang sah:**
   - `session('success' | 'warning' | 'error')` dari controller.
   - Middleware `FlashActionToast` untuk aksi mutasi yang belum punya pesan
     (otomatis: "Saved successfully.", "Deleted successfully.", dst.).
   - `window.toast(message, ok, action)` untuk respons AJAX.
   - Notifikasi baru & pengingat dari server (kartu yang sama, ikon berwarna
     sesuai jenis, klik membuka halaman terkait).
3. **Isi kartu:** ikon dalam kotak `h-10 w-10 rounded-xl` berwarna lembut,
   judul tebal per jenis (Berhasil / Gagal / Peringatan / Informasi),
   keterangan maksimal 3 baris, tombol aksi opsional, tombol silang.
4. **Perilaku:** maksimal 3 kartu menumpuk ala Sonner, hover mengembang dan
   menghentikan hitungan hilang, pengingat tampil 12 detik, hormati
   `prefers-reduced-motion`.
5. **Gaya kartu wajib selaras panel lonceng:** `rounded-2xl`, border slate,
   `shadow-2xl`, mode gelap `zinc-900`. Jangan memakai warna border tebal
   berbeda-beda per jenis; warna cukup pada ikon/judul.

### B. Kartu konfirmasi (pengganti `confirm()`)

1. Semua aksi yang butuh persetujuan (hapus, batal, tolak, terima, kirim,
   arsip, pindah ke Trash, ubah status) **wajib** memakai
   `<x-confirm-dialog>` yang dipasang sekali di `layouts/app.blade.php`.
2. **Dilarang** memakai `window.confirm()`, `confirm()`, atau
   `onclick="return confirm(...)"` di view mana pun. Interceptor global ada di
   `resources/js/confirm-dialog.js`.
3. **Cara pakai:** beri atribut pada `<form>`, `<a>`, atau tombol ber-aksi:

   ```blade
   <form action="..." method="POST"
         data-confirm="{{ __('The accepted price is locked and will be used in the quotation.') }}"
         data-confirm-title="{{ __('Accept this price as final?') }}"
         data-confirm-tone="success"
         data-confirm-label="{{ __('Yes, accept price') }}"
         data-confirm-details="{{ json_encode([
             ['label' => __('Customer'), 'value' => $customer->name],
             ['label' => __('Request'), 'value' => '#'.$request->id],
         ], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }}">
   ```

   | Atribut | Isi |
   |---|---|
   | `data-confirm` | penjelasan akibat aksi (wajib) |
   | `data-confirm-title` | judul singkat, biasanya pertanyaan |
   | `data-confirm-tone` | `success` (terima), `danger` (hapus/batal/tolak), `warning` (arsip/pindah), `info` |
   | `data-confirm-label` | label tombol aksi, mis. "Yes, accept price" |
   | `data-confirm-cancel` | label tombol batal (opsional) |
   | `data-confirm-details` | JSON baris rincian data penting |

4. **Isi wajib:** judul singkat, penjelasan akibat, rincian data penting
   (customer, nomor request, nominal) bila ada, dan label tombol yang
   menyebut aksinya, bukan "OK".
5. **Perilaku wajib:** fokus awal di tombol batal, Esc & klik latar = batal,
   Tab terkunci di dua tombol, anti dobel-submit, dan setelah disetujui aksi
   berjalan seperti biasa (submit loader tetap tampil).
6. **Verifikasi:** `php artisan test --filter=UiFeedbackStandardTest` menolak
   view yang masih memakai `confirm()` atau `x-status-card`.

## 7.4 Standar Dropdown & Menu Aksi

1. **Komponen wajib.** Dropdown pilihan (filter, sort, status, form) memakai
   `<x-glide-select>`. Menu aksi (tombol tiga titik) memakai `<x-dropdown>` +
   `<x-dropdown-link>`. Jangan membuat dropdown baru dari nol bila komponen
   ini cukup.
2. **Warna sorotan wajib terlihat.** Baris menu memakai token
   `--menu-hover-bg` (light `#eef2f7`, dark `#2f3a58`). Dilarang memakai
   `hover:bg-gray-100`, `hover:bg-slate-50`, atau `--card-bg-hover`
   (`#f8fafc`) untuk baris menu karena menyatu dengan kartu putih.
3. **Trigger dropdown** memakai `--input-bg-hover` saat hover/terbuka agar
   terasa seperti field aktif.
4. **Item terpilih** dibedakan dengan ikon centang accent; pill hover hanya
   menandai posisi kursor/keyboard.
5. **Radius & mode gelap.** Trigger dan panel mengikuti field (`rounded-xl`),
   baris opsi `rounded-lg`. Mode gelap memakai token yang sama, bukan hex
   acak.
6. **Keyboard wajib:** panah atas/bawah, Enter memilih, Esc menutup, Tab
   keluar, dan focus ring terlihat.
7. **Dropdown baru dilarang memakai `<select>` native.** Pilihan native lama
   dimigrasi bertahap setiap file itu disentuh.

## 8. Database Safety Rules

1. NEVER run:
   - php artisan migrate:fresh
   - php artisan migrate:refresh
   - php artisan db:wipe
   - DROP DATABASE
   - DROP TABLE

2. These commands are destructive and require explicit user approval.

3. Before running any destructive database command:
   - Explain exactly what will be deleted.
   - Ask for confirmation.
   - Do not execute until the user explicitly says yes.

4. Normal development migrations are allowed:
   - php artisan migrate
   - php artisan migrate:status
   - php artisan make:migration

5. Never assume that resetting the database is acceptable just because
   migrations or tests are failing.

6. If a migration problem occurs, investigate the cause first instead
   of using migrate:fresh as a shortcut.

## 9. Format commit & branch (wajib, biar `git log` gampang dibaca)

Format commit: `<tipe>(<divisi>/<menu>): <perubahan singkat>`

| Bagian | Pilihan |
|---|---|
| `<tipe>` | `feat` (fitur baru), `fix` (perbaikan), `style` (tampilan UI), `refactor` (restrukturisasi tanpa ubah perilaku), `perf` (performa), `revert` (batalkan commit lain), `test`, `chore`, `docs`, `wip` (checkpoint, **dilarang di `main`**) |
| `<divisi>` | `mkt \| tek \| user \| mon \| cus \| dash \| mng \| sec \| sales \| auth \| trash \| panel \| i18n \| sys` |
| `<menu>` | nama menu huruf kecil, misal `leads`, `jadwal`, `avatar`, `monitoring`. Boleh dikosongkan untuk `i18n`/`sys` |

Contoh benar:

```text
feat(mkt/leads): tambah filter status + tombol WA per baris
fix(tek/jadwal): kalender full Inggris + locale aware
style(auth/login): kecilkan card, ilustrasi dulu di mobile
feat(i18n): terjemahkan modul customer ke Inggris
chore(sys): rotate laravel.log + hapus .bak lama
```

Larangan commit:

- Tanpa scope: `update`, `fix bug`, `wip` (tanpa scope), `tes`, `asdasd`
- Satu commit campur 2 divisi — pecah jadi 2 commit
- Satu commit > 15 file tanpa alasan — pecah per menu

### Satu commit = satu paket perubahan

**Definisi paket:** satu perubahan yang dirasakan user, dalam satu menu.
Bukan satu file, bukan satu baris, dan bukan satu iterasi.

**Kapan commit:** hanya setelah (a) kamu cek sendiri di UI/test, dan (b) user
bilang sudah sesuai. Sebelum itu → `wip(...)`.

**Body wajib untuk `feat`, `fix`, `style`, `refactor`, `perf`** (2–6 baris,
wrap ~72 karakter): apa yang berubah, kenapa/maksud user, dan cara
verifikasinya.

```text
feat(sales/meeting): form Add Meeting dengan mode Online/Offline + foto bukti

- Form Add Meeting punya pilihan mode Online/Offline yang tersimpan dan tampil
  di detail meeting.
- Foto bukti bisa diunggah sampai 5 foto, bertahap, maksimal 1MB per foto.
- Diverifikasi: Meeting > Add Meeting, simpan 3 foto + mode Online, detail
  meeting menampilkan keduanya dengan benar.
```

`chore`, `docs`, `test` boleh judul saja.

**Larangan commit paket:**

- Commit lanjutan berjudul "sesuaikan", "revisi", "lanjutan", "perbaiki typo",
  atau "update" untuk perubahan yang masih satu paket — squash ke commit paketnya.
- Commit sebelum user menyatakan perubahan sudah sesuai.
- `wip(...)` di branch `main`.
- Satu commit paket > 15 file; pecah per sub-fitur (tetap satu tema per commit).

**Definition of Done sebelum commit:**

1. Perubahan sesuai permintaan user (sudah dicek ulang, bukan asumsi).
2. Tidak ada `dd()`, `dump()`, `console.log`, atau kode mati/komentar eksperimen.
3. `php artisan test` (atau test terkait) lulus bila menyentuh logika.
4. `npm run build` dari `/var/www/3dyapp` bila menambah kelas Tailwind baru.
5. Tidak ada file sisa: `.bak`, screenshot debug, `backups/`, file log.
6. Pesan commit pakai format di atas + body penjelasan.

**Alur squash:**

```bash
git log --oneline main..HEAD          # lihat tumpukan wip
scripts/squash-wip.sh -m "feat(sales/meeting): ..."
```

Hook `.githooks/commit-msg` menegakkan format, panjang header, body wajib,
deklarasi breaking change, dan larangan `wip` di `main`. Aktifkan sekali dengan
`scripts/setup-hooks.sh`. Bypass darurat: `git commit --no-verify` (hanya dengan
persetujuan user).

Nama branch: `feature/<divisi>-<topik-singkat>` (contoh:
`feature/mkt-filter-lead`, `feature/tek-jadwal-inggris`).
Satu-satunya branch i18n adalah `feature/i18n-english` —
dilarang membuat `feature/i18n-<modul>` baru.

### 9.1 Disiplin commit (adopsi atomic-commits)

Aturan ini melengkapi §9, bukan menggantikan. Sumber: `atomic-commits-plugin`
(thoughtbot) dan `conventional-commits-framework`, disesuaikan dengan format repo.

**Satu commit = satu jenis pekerjaan**

- `refactor` tidak boleh mengubah perilaku; pisahkan dari `feat`/`fix`.
- `feat`/`fix` tidak boleh menyelipkan refactor besar atau cleanup kode mati.
- Kalau satu commit punya dua tujuan yang tidak saling bergantung, pecah.
  Kata "+" masih boleh untuk beberapa perubahan dalam satu paket user.

**Setiap commit harus deployable**

- Test/lint/build lulus di commit itu, bukan hanya di commit terakhir.
- Tidak ada fitur setengah jadi, route tanpa view, import mati, atau dead code.

**Rencanakan sebelum coding**

- Urutan: refactor → fitur → cleanup.
- Stage selektif (`git add -p` atau path spesifik), lalu review `git diff --staged`.
- Commit body multi-baris lewat `git commit -F - <<'EOF'` agar aman dari quoting.

**Ukuran & format**

- Header maksimal **90 karakter** (target 72); ringkasan tanpa titik di akhir.
- Body 2–6 baris, wrap ~72 karakter.
- Target diff **≤200 baris**; kalau lebih, cari seam yang bisa dipisah.

**Breaking change**

- Header pakai `!`: `feat(panel/invoice)!: format nomor invoice baru`
- Body wajib punya baris `BREAKING-CHANGE: <apa yang rusak + cara migrasi>`.
- Hook `.githooks/commit-msg` menolak `!` tanpa deklarasi ini.

**Atribusi & larangan**

- Identitas git tetap user; jangan mengubah `user.name`/`user.email`.
- Default tanpa trailer AI (`Co-Authored-By`/`Assisted-by`). Kalau nanti
  diputuskan dipakai, satu format tetap untuk seluruh repo.
- Dilarang commit otomatis tanpa permintaan eksplisit user.
- `--no-verify` hanya dengan persetujuan user.
- Dilarang menempelkan prompt/tool log ke body commit.

## 10. Kebersihan storage & worktree (anti-30GB)

Kenapa bengkak: tiap worktree menduplikat `vendor/` (~390MB) +
`node_modules/` (~620MB) ≈ 1GB. 30 worktree ≈ 30GB.

1. Maks **9 worktree aktif** sesuai tabel §1 (+ `trash` + `panel` saat
   dikerjakan). Lebih dari itu: hapus dulu sebelum bikin baru.
2. **Dilarang** folder `*.bak` di `/var/www/` — backup cukup via
   `git push`, bukan copy folder.
3. Worktree non-aktif > 7 hari: hapus `vendor/` + `node_modules/`-nya
   saja (hemat ~1GB/worktree, bisa `composer install` /
   `npm install` lagi). Jangan hapus branch-nya.
4. `laravel.log` > 10MB wajib truncate (`: > storage/logs/laravel.log`).
   Dilarang commit file log.
5. Setiap selesai hapus worktree: wajib `git worktree prune`.
6. Cek bulanan: `git worktree list` +
   `git branch --merged main` — yang sudah merge langsung hapus.

Cek cepat:

```bash
git worktree list --verbose
du -sh /var/www/3dyapp-*/vendor /var/www/3dyapp-*/node_modules 2>/dev/null | sort -rh | head
ls -lh storage/logs/laravel.log
```

## 11. Siklus hidup branch (kapan bikin, kapan hapus)

1. Bikin branch hanya dari `main` yang sudah `git pull` terbaru.
2. Branch eksperimen (login/sidebar/profile/notif): maksimal **1 aktif**,
   umur max **7 hari**.
3. Sudah merge ke `main` → hapus max 1 minggu:
   `git branch -d <branch> && git push origin --delete <branch>`.
4. Worktree `ahead` (punya commit belum di-merge): cek isi dulu
   (`git log main..<branch> --oneline`), merge yang relevan,
   discard sisanya — baru hapus worktree-nya.
5. Dilarang bikin worktree baru tanpa entry di tabel §1.

## 12. Matriks role (acuan: `RoleAndPermissionSeeder.php`)

| Role | Permission inti | Catatan |
|---|---|---|
| `super-admin` | semua | Bypass semua gate |
| `management` | `manage-sales-leads` + semua `view-*` + `monitor-marketing` | Satu-satunya hub management; sidebar hanya recap divisi |
| `lead-marketing` | `manage-marketing`, `view-marketing`, `monitor-marketing`, `view-technician` | + read-only teknisi (dok. instalasi) |
| `marketing` / `sales` / `admin` / `technician` | `manage/view-*` divisinya | `sales` += `view-admin`, `admin` += `view-sales` (saling read-only) |
| `lead-technician` | teknisi + `monitor-technical`, `view-sales`, `view-admin` | Koordinasi + cek progres divisi lain |
| `inside-sales` | `manage-inside-sales` | Hanya task sendiri; anakan sales |
| `prakerin-technician` / `prakerin-admin` | `view-technician` / `view-admin` | Read-only divisinya |
| `ceo` | semua `view-*` | Read-only, tanpa manage |

Aturan: mutasi selalu di belakang `manage-*`, baca di belakang `view-*`
— menambah `view-*` ke role tidak pernah membuka aksi tulis.
**Multi-role didukung**: user boleh pegang 2+ role (mis. management+sales).
Sidebar tampilkan menu kerja penuh di divisi yang ada `manage-*`-nya,
recap saja di divisi lain (lihat `$tekRecap/$mktRecap/$salesRecap` di
`sidebar.blade.php`). Pre-sales = pakai role `sales`, tanpa role baru.
Purchasing (nanti): route `admin.purchasing.*`, baca `view-admin`,
tulis `manage-admin` — sales/prakerin-admin otomatis read-only.

## 13. Format Laporan Progres Kerja (Update 2-Harian ke Senior)

Aturan bagi AI dan developer (Yeski & Raafi) saat membuat laporan update progres
website untuk Senior via WhatsApp setiap 2 hari sekali. Pelaksananya: skill
`report-update-progress-web` (`~/.config/opencode/skills/`, global).

1. **Gaya Bahasa & Nada Bicara**:
   - Wajib **semi-formal terstruktur**, luwes, dan **100% terasa alami ditulis manusia** (programmer ke senior).
   - Selalu gunakan sapaan **"Pak"** (bukan "Mas", "Kak", atau lainnya).
   - Sapaan pembuka mengikuti jam WIB: 00–10 "Selamat pagi", 11–13 "Selamat siang", 14–17 "Selamat sore", 18–23 "Selamat malam".
   - Dilarang keras memakai bahasa robotik atau menyertakan watermark/footer mesin (seperti *"Generated by AI/Bot"*, *"Dibuat otomatis oleh sistem"*).

2. **Struktur Pesan WhatsApp**:
   - **Pembuka**: `Selamat <waktu> Pak, izin update progres website N hari terakhir :` — **tanpa** menyebut nama pelapor.
   - **📋 Sudah dikerjakan**: dikelompokkan per **sub-judul Menu** (mis. `Menu Sales & Quotation`, `Menu Admin / Panel`, `Login System`), lalu bullet `-` berisi kalimat utuh: perubahan **dan** dampaknya.
   - **🎯 Yang Akan Kami Kerjakan Berikutnya**: langkah konkret berikutnya.
   - **Tidak ada** blok bukti/screenshot, blok kendala, maupun penutup "Mohon arahan…". Bila memang perlu ditambahkan, taruh setelah blok 📋 tanpa mengubah bagian lain.

3. **Format Standar Acuan (Salin & Sesuaikan)**:

```text
Selamat sore Pak, izin update progres website 2 hari terakhir :

📋 Sudah dikerjakan:
Menu Sales & Quotation
- Penambahan tampilan KPI personal dan target di dashboard sales.
- Alur quotation sekarang jadi satu pintu. Lead tidak bisa lagi di-convert langsung ke proyek — harus lewat quotation yang sudah accepted.
Menu Admin / Panel
- Penambahan modal Purchasing dan Finance di menu Admin.
Login System
- Ditambahkan fitur login lewat Google yang otomatis terauthorize.

🎯 Yang Akan Kami Kerjakan Berikutnya:
- Melanjutkan tautan Price Request ke Quotation yang belum selesai.
- Melakukan full testing sistem termasuk menu admin.
```

4. **Larangan**: menempelkan hash commit, nama branch, atau path file; melaporkan
   commit `chore`/`docs`/`ci` internal; mengarang kendala, target, atau nama orang.

## 14. Format Dokumen Rencana (PLAN-*.md)

Semua rencana perubahan website disimpan di `docs/plans/`. Satu file = satu rencana.

### 14.1 Nama file

`docs/plans/<divisi>/PLAN-[MENU_UTAMA]-[SUB_MENU_ATAU_FITUR].md`

- Huruf kapital, pemisah hyphen, tanpa spasi.
- Folder divisi: `sales`, `estimator`, `inside-sales`, `admin`, `teknisi`,
  `marketing`, `management`, `customer`, `dashboard`, `sistem`.
- `MENU_UTAMA` mengikuti divisi/branch:

| Divisi / branch | Prefix file |
|---|---|
| Marketing | `PLAN-MARKETING-*` |
| Sales | `PLAN-SALES-*` |
| Estimator | `PLAN-ESTIMATOR-*` |
| Inside Sales | `PLAN-INSIDE-SALES-*` |
| Admin / Panel | `PLAN-ADMIN-*` |
| Teknisi | `PLAN-TEKNISI-*` |
| Customer | `PLAN-CUSTOMER-*` |
| Management | `PLAN-MANAGEMENT-*` |
| Dashboard | `PLAN-DASHBOARD-*` |
| Lintas sistem / notifikasi | `PLAN-SISTEM-*` |

- Contoh: `docs/plans/sales/PLAN-SALES-QUOTATION.md`,
  `docs/plans/admin/PLAN-ADMIN-INVOICE.md`,
  `docs/plans/teknisi/PLAN-TEKNISI-JADWAL.md`.
- Untuk satu paket pekerjaan lintas divisi, tambahkan penanda work yang sama
  di nama file, mis. `PLAN-SALES-INTERVIEW-USERSALES.md`, dan buat index di
  `docs/plans/00-<WORK>-INDEX.md`.
- Dilarang mencampur dua divisi dalam satu file; pecah per divisi.

### 14.2 Header wajib

Setiap file diawali:

```md
# PLAN-SALES-QUOTATION: Judul Rencana

**Status Pengerjaan:** Belum Dikerjakan
**Work:** INTERVIEW-USERSALES
**PIC:** Raafi
**Menu:** Sales / Quotation
**Branch:** feature/sales-quotation
**Terakhir diperbarui:** 2026-11-08
```

`Work` dan `PIC` wajib diisi bila satu pekerjaan dibagi ke beberapa divisi
dan/atau beberapa PIC, supaya file plan mudah dikenali sebagai satu paket.

Nilai `Status Pengerjaan` hanya: `Belum Dikerjakan`, `Siap Dikerjakan`,
`Selesai`.

### 14.3 Isi minimal

- Tujuan & hasil akhir (bahasa user, bukan bahasa kode).
- Ruang lingkup, termasuk yang **tidak** dikerjakan.
- Langkah pengerjaan.
- Kriteria selesai / cara verifikasi.
- Dependensi ke menu atau divisi lain.

### 14.4 Aturan status

- `Belum Dikerjakan`: belum ada kode sama sekali.
- `Siap Dikerjakan`: scope dan keputusan sudah jelas, siap dieksekusi.
- `Selesai`: sudah di-merge ke `main` dan sudah dicek user.
- Status diperbarui pada commit `docs(...)` yang sama dengan perubahan rencana.
- Rencana `Selesai` tetap disimpan; jangan hapus tanpa persetujuan user.

### 14.5 Larangan

- Jangan menaruh rencana di root repo atau folder lain; semua di
  `docs/plans/<divisi>/`.
- Jangan memakai nama bebas seperti `plan.md`, `rencana.md`, atau `todo.md`.
- Catatan audit dan referensi teknis bukan PLAN; taruh di `docs/audit/` atau
  `docs/reference/`.
- Folder `docs/rencana-kerja/` sudah dihapus; jangan dibuat lagi.

## 15. Penanganan Konflik Database & Migrasi

Setiap sesi AI/Developer yang membuat file migrasi database baru WAJIB mematuhi aturan berikut:
- **Gunakan Timestamp Unik:** Jangan membuat file migrasi secara manual dengan menebak urutan waktu. Selalu gunakan perintah `php artisan make:migration`.
- **Dilarang Mengubah Migrasi yang Sudah Di-merge ke `main`:** Jika ada perubahan struktur tabel setelah file di-merge ke `main`, buat file migrasi baru (rollback/alter), JANGAN mengedit file migrasi lama.
- **Sinkronisasi Schema:** Setelah melakukan `git pull` dari `main` di worktree divisi Anda, segera jalankan `php artisan migrate` untuk memastikan state database lokal selaras dengan versi terbaru.

## 16. Skill Proyek 3DY & Pemilihan Otomatis

OpenCode menemukan skill dari `.opencode/skills/` (proyek),
`~/.config/opencode/skills/` (global), serta folder kompatibilitas
`.agents/skills` dan `.claude/skills`. Skill proyek 3DY memakai ID `3dy-*`
dan **wajib dipilih serta dimuat otomatis** berdasarkan konteks tugas, tanpa
menunggu user menyebut namanya.

### 16.1 Peta pemicu

| Konteks tugas | Skill yang dimuat |
|---|---|
| Struktur menu/modul, alur data, penempatan kode, worktree, migration | `3dy-architecture` |
| Route/controller/policy/permission, 403, menu hilang, akses PT, data sensitif, upload, webhook | `3dy-security` |
| Lead, follow-up, meeting, quotation, PO, convert project, delivery, instalasi, BAST, invoice, payment | `3dy-crm-workflow` |
| Menulis/menjalankan test, bug fix, verifikasi sebelum merge | `3dy-testing` |
| Logika Laravel/PHP umum | `laravel-development` |
| Review diff/PR sebelum merge | `code-review-and-quality` |
| Desain halaman/komponen UI | `ui-ux-pro-max` |
| Landing page/portfolio (bukan dashboard internal) | `design-taste-frontend` |
| Audit keamanan menyeluruh | `security-audit` + `3dy-security` |
| Performa/query lambat | `performance-optimization` |
| Spec/rencana fitur baru | `spec-driven-development` + `docs/plans/<divisi>/PLAN-*.md` |
| Requirement masih ambigu | `interview-me` |

### 16.2 Aturan pemuatan

1. Muat **1-3 skill paling relevan** sebelum mengubah kode. Jangan bertanya
   "mau pakai skill apa" bila pemicunya sudah jelas.
2. **Urutan prioritas:** skill 3DY lebih dulu, lalu skill umum pendamping.
   `AGENTS.md` selalu menang bila isi skill berbeda.
3. Jangan memuat skill yang tidak relevan hanya karena tersedia. Skill yang
   butuh tool tidak tersedia (mis. Chrome DevTools MCP) boleh dilewati.
4. Skill `3dy-*` adalah skill internal yang dipercaya dan sudah diizinkan di
   `.opencode/opencode.json`. Tidak perlu minta izin user untuk memuatnya.
5. Skill bukan izin istimewa: §7 (tombol), §8 (database), §9 (commit), dan
   §15 (migration) tetap berlaku penuh.
6. Jangan menempelkan isi skill ke chat. Terapkan langkahnya dan laporkan
   hasilnya sesuai §0.4.

### 16.3 ID unik & kepemilikan skill

- Sebelum membuat skill baru, cek ID yang sudah ada di
  `.opencode/skills/`, `~/.config/opencode/skills/`, `.agents/skills/`, dan
  `.claude/skills/`. Skill proyek menimpa skill global dengan ID sama.
- Jangan membuat ID duplikat. Jangan menyalin skill global ke proyek.
- Jangan mengubah, menghapus, atau memindahkan skill global/pihak ketiga.
- Skill baru hanya dibuat bila fungsinya benar-benar belum tercakup, dengan
  persetujuan user, deskripsi pemicu yang jelas, dan folder
  `.opencode/skills/<id>/SKILL.md`.
- Jika skill gagal dimuat, periksa path, nama file `SKILL.md`, ID, dan
  permission. Jangan langsung membuat skill baru sebagai pengganti.


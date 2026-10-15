# Aturan Kerja Multi-Sesi

Repo ini dikerjakan oleh lebih dari satu sesi AI/developer secara paralel.
Sejak konsolidasi, setiap sesi punya **folder kerja (worktree) sendiri** —
ikuti aturan berikut supaya tidak saling menimpa kerjaan.

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
| `/var/www/3dyapp-management` | `feature/management` | Management (Activity Log, assign lead) |
| `/var/www/3dyapp-sec` | `feature/security-hardening` | Security hardening |
| `/var/www/3dyapp-sales` | `feature/sales-flow` | Sales (dashboard, my-leads, meeting, follow-up) |
| `/var/www/3dyapp-ai` | `feature/ai-assistant` | AI Assistant (floating chatbot) |

Worktree baru juga butuh: `composer install`, symlink `.env`, dan
`ln -s /var/www/3dyapp/public/build /var/www/3dyapp-<nama>/public/build`.

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

## 3. Commit kecil tapi sering

Commit + push = backup. Pesan commit menjelaskan fiturnya.

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

## 6. Jangan commit

- Folder `backups/` (sudah di-gitignore)
- Kredensial / `.env`
- File hasil eksperimen tanpa persetujuan

## 7. Tombol (konvensi UI)

Semua tombol aksi non-utama memakai gaya **putih + border tipis, berwarna
saat hover** (acuan: komponen `x-icon-button`). Peta aksi:

| Aksi | Pola standar |
|---|---|
| Simpan / Submit utama (Save) | `group relative overflow-hidden rounded-xl bg-accent-600 hover:bg-accent-700 text-white px-6 py-2.5 text-sm font-medium` + shine + `hover:shadow-xl hover:shadow-accent-500/50 hover:brightness-110` (acuan persis: `customers/create.blade.php`) |
| Batal (Cancel, di form/modal) | pola sama, `bg-accent-500 hover:bg-accent-600` + `text-center` |
| Aksi (view/edit/delete/add/filter/back/...) | `<x-icon-button>` — idle putih + border + **ikon berwarna per aksi**, hover fill warna aksi + ikon putih + shine + scale |
| Hapus / destructive | `<x-icon-button icon="delete">` — idle putih + ikon merah, hover background merah solid + ikon putih |
| Konfirmasi hapus (tombol teks di modal) | putih + `border-red-200 text-red-600`, hover background merah solid + teks putih + shine |

**Warna per aksi** (biar tombol berjajar tidak monoton) — otomatis dari `icon=`
di `x-icon-button`:

| Ikon | Idle | Hover |
|---|---|---|
| `view` | ikon indigo-600 | bg indigo-600 + ikon putih |
| `edit` | ikon amber-600 | bg amber-600 + ikon putih |
| `delete` | ikon red-600 | bg red-600 + ikon putih |
| `add`, `sparkles` (positif) | ikon green-600 | bg green-600 + ikon putih |
| lainnya (filter/reset/back/search/download/mail/...) | ikon accent theme | bg accent + ikon putih |

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
| `<tipe>` | `feat` (fitur baru), `fix` (perbaikan), `style` (tampilan), `test`, `chore`, `docs` |
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

- Tanpa scope: `update`, `fix bug`, `wip`, `tes`, `asdasd`
- Satu commit campur 2 divisi — pecah jadi 2 commit
- Satu commit > 15 file tanpa alasan — pecah per menu

Nama branch: `feature/<divisi>-<topik-singkat>` (contoh:
`feature/mkt-filter-lead`, `feature/tek-jadwal-inggris`).
Satu-satunya branch i18n adalah `feature/i18n-english` —
dilarang membuat `feature/i18n-<modul>` baru.

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

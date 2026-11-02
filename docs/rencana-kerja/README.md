# Rencana Kerja & Pembagian Sesi

Folder ini berisi pembagian pekerjaan hasil audit manual November 2026, dibagi **2 sesi** supaya dua agent bisa jalan paralel tanpa bertabrakan branch/file.

## Isi folder

| File | Isi |
|---|---|
| [rencana-induk.md](rencana-induk.md) | Konteks, 21 temuan + 3 temuan tambahan, prasyarat, peta kepemilikan file, detail A1–A12 dan B1–B11, aturan commit, checklist |
| [sesi/A-handoff.md](sesi/A-handoff.md) | Brief Sesi A — Sales → Admin handoff (12 item) |
| [sesi/B-notif-teknisi.md](sesi/B-notif-teknisi.md) | Brief Sesi B — Notifikasi & Teknisi (11 item) |

## Dua sesi

| Sesi | Worktree | Branch | Tema |
|---|---|---|---|
| **A** | `/home/yeski/3dyapp-handoff` | `feature/handoff-admin` | Handoff Sales → Admin |
| **B** | `/home/yeski/3dyapp-notif-tek` | `feature/notif-teknisi` | Notifikasi & Teknisi |

Kedua entry sudah ditambahkan ke tabel `AGENTS.md` §1.

## Cara pakai

1. **Mulai sesi baru:** baca `rencana-induk.md` sampai selesai, lalu file `sesi/<A|B>.md` milikmu.
2. **Kerjakan hanya item di brief-mu.** Kalau butuh file milik sesi lain, tunggu merge atau minta ke user — jangan edit.
3. **Selama belum sesuai:** commit `wip(<divisi>/<menu>): ...`.
4. **Setelah user menyatakan sesuai:** squash jadi satu commit paket **per divisi** (`AGENTS.md` §9 — dilarang campur 2 divisi dalam satu commit).
5. **Sebelum merge ke `main`:** `php artisan test` lulus.
6. **Setelah merge ke `main`:** worktree lain `git pull origin main`, lalu lanjut.

## Aturan anti-tabrakan (ringkas)

- Satu worktree = satu branch, jangan pindah branch di worktree sesi lain (`AGENTS.md` §1).
- File yang sudah ada hanya diubah pemiliknya (peta lengkap di `rencana-induk.md` §3.3).
- File baru bebas, asal nama tidak sama.
- `routes/web.php` → tambah route di blok modul sendiri; konflik: pertahankan keduanya.
- `lang/en.json` & `lang/id.json` → dipakai bersama; konflik: **keep both**.
- Migrasi: timestamp berbeda; cek folder sebelum membuat file baru.
- `npm run build` hanya dari `/var/www/3dyapp` (main) — `AGENTS.md` §5.

## Urutan fase

| Fase | Fokus | Sesi | Estimasi |
|---|---|---|---|
| 0 | Quick win (MKT-1, SAL-4, TEK-4) | A, B | 0,5–1 hari |
| 1 | Handoff Sales → Admin (P0) | A | 3–4 hari |
| 2 | Notifikasi sehat + akses hasil kerja | B (+A8) | 3–4 hari |
| 3 | UX Sales & Inside Sales | A | 2–3 hari |
| 4 | Alur teknisi (jadwal, dashboard) | B | 4–5 hari |
| 5 | Field Work general + BAST | B (+A) | 5–7 hari |

## Menandai progres

Update kolom **Status** dan **Tanggal merge** di tabel §9 `rencana-induk.md` setiap item selesai **merge ke `main`**. Jangan tandai selesai sebelum merge.

# Verifikasi Akses Commit & Push (User Safira)

Catatan singkat hasil pengecekan akses repository dari workspace user `safira`.

## Hasil

| Pemeriksaan | Status |
|---|---|
| Identitas commit (`user.name` / `user.email`) | `Safira` / `safira@tridayaapp.com` |
| Kunci SSH ke GitHub | Terdaftar di akun `Yeszzkuyy` |
| Kunci host GitHub di `known_hosts` | Ditambahkan (sebelumnya belum ada) |
| Commit ke branch lokal | Berhasil |
| Push ke branch sementara | Berhasil, commit terbaca di server |
| Branch sementara dibersihkan | Ya |
| Commit + push ke `main` | Berhasil (commit ini) |

## Catatan

- Gejala awal: `Host key verification failed` karena `~/.ssh/known_hosts` belum berisi kunci GitHub.
- Perbaikan sekali jalan: `ssh -o StrictHostKeyChecking=accept-new -T git@github.com`.
- Workspace ini tidak perlu konfigurasi ulang untuk commit dan push berikutnya.

#!/usr/bin/env bash
# Rapikan izin storage setelah menjalankan artisan/test sebagai user lain.
# Gejala bila lupa: halaman 500 dengan "Permission denied" pada
# storage/framework/cache atau "Unable to create a directory" saat upload.
set -euo pipefail
cd "$(dirname "$0")/.."

sudo chmod -R g+rwX storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod g+s {} \;

echo "Izin storage & bootstrap/cache sudah dirapikan."

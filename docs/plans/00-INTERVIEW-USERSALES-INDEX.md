# INTERVIEW-USERSALES: Pembagian Plan per Divisi

**Status Pengerjaan:** Belum Dikerjakan
**Work:** INTERVIEW-USERSALES
**Sumber:** `docs/archive/WAWANCARA-USER-SALES.md`
**Terakhir diperbarui:** 2026-11-12

## Pembagian tugas

| PIC | Divisi | File |
|---|---|---|
| **Raafi** | Sales | [PLAN-SALES-INTERVIEW-USERSALES](sales/PLAN-SALES-INTERVIEW-USERSALES.md) |
| **Raafi** | Teknisi | [PLAN-TEKNISI-INTERVIEW-USERSALES](teknisi/PLAN-TEKNISI-INTERVIEW-USERSALES.md) |
| **Yeski** | Estimator | [PLAN-ESTIMATOR-INTERVIEW-USERSALES](estimator/PLAN-ESTIMATOR-INTERVIEW-USERSALES.md) |
| **Yeski** | Inside Sales | [PLAN-INSIDE-SALES-INTERVIEW-USERSALES](inside-sales/PLAN-INSIDE-SALES-INTERVIEW-USERSALES.md) |
| **Yeski** | Admin / Purchasing | [PLAN-ADMIN-PURCHASING-INTERVIEW-USERSALES](admin/PLAN-ADMIN-PURCHASING-INTERVIEW-USERSALES.md) |

## Urutan pengerjaan yang disarankan

1. **Estimator** - pisahkan harga modal dari harga jual.
2. **Sales (quotation)** - nomor baku, satu lead satu quotation, revisi.
3. **Inside Sales** - riwayat harga, margin, integrasi jasa.
4. **Teknisi** - layout plan dan biaya jasa.
5. **Admin/Purchasing** - PI, purchasing saving, logistik.

## Dependensi utama

- Harga modal dari Estimator dipakai Inside Sales.
- Aturan nomor quotation dan satu lead satu quotation dimiliki Sales,
  tetapi dipakai Inside Sales saat membuat draf.
- Biaya jasa dari Teknisi masuk ke item quotation Inside Sales.
- PI dan status pengiriman berasal dari Admin/Purchasing, lalu dibaca Sales.

## Catatan

- Semua file di atas memakai penanda **INTERVIEW-USERSALES** agar jelas
  masih satu paket pekerjaan.
- Plan lama sudah dipindahkan ke folder divisinya masing-masing.
- Plan Estimator sudah diimplementasikan di branch `feature/sales-inside`
  dan menunggu verifikasi user sebelum merge ke `main`.

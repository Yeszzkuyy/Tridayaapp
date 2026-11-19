# Database Teknisi Tridaya Group V1

## Company

- NTI
- MGK
- TPS
- WANI

## Customer

Menyimpan data customer.

Field:
- Nama Customer
- Alamat
- PIC Customer
- Nomor HP
- Email
- Catatan

## Account Manager

Field:
- Nama
- Email
- Nomor HP

## Engineer

Field:
- Nama
- Email
- Nomor HP
- Jabatan
- Status

## Activity Types

- Survey
- Meeting Online
- Meeting Offline
- Demo Produk
- POC
- Quotation
- PO
- Instalasi
- Maintenance
- Troubleshooting
- BAST

## Project

Field:
- Nama Project
- Company
- Customer
- Account Manager
- PIC Engineer
- Scope Pekerjaan
- Lokasi Site
- Tanggal Mulai
- Tanggal Selesai
- Status
- Keterangan

## Dokumen

Field:
- Nama File
- Kategori
- Project
- Uploader
- Tanggal Upload

Kategori:
- Survey
- Dokumentasi
- BAST
- Quotation
- Drawing
- Foto
- Video
- Lainnya

## Project Notes

Field:
- Project
- Catatan
- Dibuat Oleh
- Tanggal

## Master Harga Instalasi

Field:
- Nama Pekerjaan
- Satuan
- Harga


TABLE: customers

- id
- company_name
- address
- phone
- email
- notes

--------------------------------

TABLE: customer_contacts

- id
- customer_id
- name
- position
- phone
- email
- is_primary

--------------------------------

TABLE: projects

- id
- customer_id
- company_id
- account_manager_id
- project_name
- work_type_id
- pic_engineer_id
- status
- description

--------------------------------

TABLE: project_supports

- id
- project_id
- user_id

--------------------------------

TABLE: project_documents

- id
- project_id
- document_type
- file_name
- file_path
- notes

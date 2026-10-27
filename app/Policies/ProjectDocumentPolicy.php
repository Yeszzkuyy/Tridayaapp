<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use App\Support\DocumentAccess;
use App\Support\PtAccess;

class ProjectDocumentPolicy
{
    /**
     * Pemilik dokumen untuk otorisasi: project-nya, atau customer-nya
     * bila dokumen umum tanpa project.
     */
    private function ownerOf(ProjectDocument $document): ?object
    {
        return $document->project ?? $document->customer;
    }

    /**
     * Cek apakah user boleh melihat/mengunduh dokumen.
     * Izin dibuka untuk semua user yang punya akses ke customer/project
     * (view-customer, view-technician, view-sales, dan varian manage-nya),
     * dokumen transaksi (invoice/po/payment) tetap memerlukan view-admin.
     */
    public function view(User $user, ProjectDocument $document): bool
    {
        // Dokumen yang sedang di Trash memakai aturan Trash (role + PT +
        // pemilik), bukan aturan dokumen aktif.
        if ($document->trashed()) {
            return DocumentAccess::canViewInTrash($user, $document);
        }

        $owner = $this->ownerOf($document);
        if (! $owner) {
            return false;
        }

        // Dokumen transaksi (invoice/po/payment) tidak boleh diakses lewat
        // route dokumen umum — harus lewat modul admin masing-masing.
        // Dokumen hasil modul (survey/sizing/instalasi) mengikuti aturan
        // project/customer seperti dokumen biasa.
        if ($document->isTransactionDocument()) {
            return $user->hasAnyPermission(['view-admin', 'manage-admin']);
        }

        // Semua user yang punya view-customer boleh melihat dokumen customer
        // (termasuk dokumen umum tanpa project).
        if ($user->hasPermissionTo('view-customer')) {
            return true;
        }

        // Untuk dokumen proyek, cek izin divisi teknisi/sales.
        if ($user->hasAnyPermission(['view-technician', 'manage-technician', 'view-sales', 'manage-sales'])) {
            return true;
        }

        return false;
    }

    /**
     * Cek apakah user boleh mengunggah dokumen baru.
     */
    public function upload(User $user, ?Project $project = null, ?Customer $customer = null): bool
    {
        if (! $user->can('manage-technician') && ! $user->can('manage-sales-leads')) {
            return false;
        }

        // Jika ada project, cek sales hanya boleh upload Survey untuk project miliknya.
        if ($project) {
            $isSalesAllowed = (int) ($project->sales_user_id ?? 0) === (int) $user->id
                || ($project->customer && PtAccess::canWritePt($user, $project->customer->pt_group));
            if (! $isSalesAllowed) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cek apakah user boleh mengunggah versi baru dokumen yang sudah ada.
     * Mengikuti aturan yang sama dengan upload + milik dokumen.
     */
    public function uploadVersion(User $user, ProjectDocument $document): bool
    {
        if (! $this->upload($user, $document->project, $document->customer)) {
            return false;
        }

        // Dokumen transaksi & dokumen milik modul tidak diganti versi lewat
        // sini — keduanya punya alur sendiri (admin / menu modul).
        if (filled($document->doc_type)) {
            return false;
        }

        return true;
    }

    /**
     * Cek apakah user boleh mengedit metadata (nama, kategori, catatan).
     */
    public function update(User $user, ProjectDocument $document): bool
    {
        // Dokumen transaksi (invoice/po/payment) dikelola hanya di modul Admin;
        // tidak boleh diubah melalui route project-dokumen.
        if ($document->isTransactionDocument()) {
            return false;
        }

        // File milik modul (survey/sizing/instalasi) hanya dikelola dari menu
        // modulnya supaya nama file & daftar dokumen selalu sama.
        if ($document->isModuleDocument()) {
            return false;
        }

        if ($document->project) {
            return $user->can('update', $document->project);
        }

        $owner = $document->customer;
        if (! $owner) {
            return false;
        }

        return $user->can('manage-technician') || $user->can('update', $owner);
    }

    /**
     * Cek apakah user boleh mengarsipkan (soft delete) dokumen.
     *
     * Semua jenis dokumen (termasuk modul Survey/Sizing/Instalasi) lewat
     * Trash. Kewenangan mengikuti role + modul + kepemilikan.
     */
    public function archive(User $user, ProjectDocument $document): bool
    {
        // Dokumen transaksi (invoice/po/payment) tidak boleh diarsipkan
        // melalui route project-dokumen — dikelola di modul Admin.
        if ($document->isTransactionDocument()) {
            return false;
        }

        return DocumentAccess::canManage($user, $document);
    }

    /**
     * Cek apakah user boleh memulihkan dokumen dari arsip.
     */
    public function restore(User $user, ProjectDocument $document): bool
    {
        // Dokumen transaksi (invoice/po/payment) tidak boleh dipulihkan
        // melalui route project-dokumen — dikelola di modul Admin.
        if ($document->isTransactionDocument()) {
            return false;
        }

        return DocumentAccess::canManage($user, $document);
    }

    /**
     * Cek apakah user boleh menghapus permanen (force delete).
     * Kewenangannya sama dengan hapus/restore.
     */
    public function forceDelete(User $user, ProjectDocument $document): bool
    {
        // Dokumen transaksi (invoice/po/payment) tidak boleh dihapus
        // permanen melalui route project-dokumen — dikelola di modul Admin.
        if ($document->isTransactionDocument()) {
            return false;
        }

        return DocumentAccess::canManage($user, $document);
    }

    /**
     * Cek apakah user boleh melihat daftar dokumen terarsip.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['view-customer', 'view-technician', 'manage-technician', 'view-sales', 'manage-sales']);
    }
}

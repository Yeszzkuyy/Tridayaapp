<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $table = 'projects';

    protected $fillable = [
        'customer_id',
        'sales_user_id',
        'teknisi_user_id',
        'account_manager_id',
        'work_type_id',
        'project_status_id',
        'pic_engineer', // ← ganti dari pic_engineer_id
        'support_technicians', // ← tambahkan
        'project_name',
        'project_code',
        'quotation_number',
        'proposal_id',
        'progress',
        'requires_installation',
        'bast_number',
        'bast_date',
        'bast_document_id',
        'delivery_deadline',
        'commercial_closed_at',
        'start_date',
        'end_date',
        'description',
        'deleted_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'progress' => 'integer',
        'requires_installation' => 'boolean',
        'bast_date' => 'date',
        'delivery_deadline' => 'date',
        'commercial_closed_at' => 'datetime',
    ];

    // Relasi ke Customer
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Owner bisnis untuk Trash: ikut owner customer-nya.
     * withTrashed karena customer-nya sendiri bisa sedang di trash.
     */
    public function trashOwnerId(): ?int
    {
        return $this->customer()->withTrashed()->first()?->trashOwnerId();
    }

    // Relasi ke AccountManager
    public function accountManager()
    {
        return $this->belongsTo(AccountManager::class);
    }

    // Relasi ke WorkType
    public function workType()
    {
        return $this->belongsTo(WorkType::class);
    }

    // Relasi ke ProjectStatus (kalau pake)
    public function status()
    {
        return $this->belongsTo(ProjectStatus::class, 'project_status_id');
    }

    // Proposal asal convert (WON flow sales).
    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    // Relasi ke Task
    public function tasks()
    {
        return $this->hasMany(ProjectTask::class);
    }

    // Relasi ke Activity
    public function activities()
    {
        return $this->hasMany(ProjectActivity::class);
    }

    // Relasi ke Document
    public function documents()
    {
        return $this->hasMany(ProjectDocument::class);
    }

    // Relasi ke Support
    public function supports()
    {
        return $this->hasMany(ProjectSupport::class);
    }

    // Relasi ke Technical Workflow
    public function surveys()
    {
        return $this->hasMany(Survey::class);
    }

    public function sizingProjects()
    {
        return $this->hasMany(SizingProject::class);
    }

    public function requestHargas()
    {
        return $this->hasMany(RequestHarga::class);
    }

    public function instalasis()
    {
        return $this->hasMany(Instalasi::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function supplierPurchaseOrders()
    {
        return $this->hasMany(SupplierPurchaseOrder::class);
    }

    public function supplierInvoices()
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function goodsDeliveries()
    {
        return $this->hasMany(GoodsDelivery::class);
    }

    public function bastDocument()
    {
        return $this->belongsTo(ProjectDocument::class, 'bast_document_id');
    }

    /**
     * Closing komersial (Tahap 5) hanya jika:
     * 1. seluruh surat jalan proyek berstatus delivered;
     * 2. instalasi selesai — BAST terisi dan progres 100%/status Done
     *    (dilewati bila proyek tidak butuh instalasi);
     * 3. semua invoice pelanggan lunas (invoice cancelled diabaikan).
     */
    public function evaluateCommercialClosing(): bool
    {
        if ($this->commercial_closed_at) {
            return true;
        }

        $deliveryQuery = $this->goodsDeliveries();
        $deliveryDone = (clone $deliveryQuery)->exists()
            && (clone $deliveryQuery)->where('status', '!=', 'delivered')->doesntExist();

        $installationDone = ! $this->requires_installation
            || (filled($this->bast_number)
                && ((int) $this->progress >= 100
                    || strcasecmp((string) $this->status?->name, 'Done') === 0));

        $invoiceQuery = $this->invoices();
        $invoiceDone = (clone $invoiceQuery)->exists()
            && (clone $invoiceQuery)->whereNotIn('status', ['paid', 'cancelled'])->doesntExist();

        if ($deliveryDone && $installationDone && $invoiceDone) {
            $this->forceFill(['commercial_closed_at' => now()])->save();

            return true;
        }

        return false;
    }
}

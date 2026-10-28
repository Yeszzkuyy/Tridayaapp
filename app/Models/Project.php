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
        'bast_signed_3dy_name',
        'bast_signed_3dy_at',
        'bast_signed_customer_name',
        'bast_signed_customer_at',
        'bast_ready_at',
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
        'bast_signed_3dy_at' => 'datetime',
        'bast_signed_customer_at' => 'datetime',
        'bast_ready_at' => 'datetime',
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
     * BAST dianggap siap untuk Finance bila dokumennya sudah tersimpan dan
     * sudah ditandatangani kedua pihak (3DY + pelanggan).
     */
    public function isBastReady(): bool
    {
        return filled($this->bast_document_id)
            && filled($this->bast_signed_3dy_at)
            && filled($this->bast_signed_customer_at);
    }

    /**
     * Sinkronkan penanda "siap Finance" setelah form BAST disimpan.
     * Timestamp pertama kali siap dipertahankan; bila syarat tidak lagi
     * terpenuhi (mis. dokumen/tanda tangan dihapus), penanda dikosongkan.
     */
    public function refreshBastReady(): bool
    {
        $ready = $this->isBastReady();

        $this->forceFill([
            'bast_ready_at' => $ready ? ($this->bast_ready_at ?? now()) : null,
        ])->save();

        return $ready;
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

        $procurementDone = $this->deliveryObligationsMet();

        $installationDone = ! $this->requires_installation
            || (filled($this->bast_number)
                && ((int) $this->progress >= 100
                    || strcasecmp((string) $this->status?->name, 'Done') === 0));

        $invoiceQuery = $this->invoices();
        $invoiceDone = (clone $invoiceQuery)->exists()
            && (clone $invoiceQuery)->whereNotIn('status', ['paid', 'cancelled'])->doesntExist();

        if ($deliveryDone && $procurementDone && $installationDone && $invoiceDone) {
            $this->forceFill(['commercial_closed_at' => now()])->save();

            return true;
        }

        return false;
    }

    /**
     * Semua item pada Supplier PO aktif project sudah dialokasikan ke
     * surat jalan. Jika project tidak memiliki Supplier PO (data legacy),
     * tidak ada kewajiban procurement yang bisa diperiksa.
     */
    private function deliveryObligationsMet(): bool
    {
        $items = SupplierPurchaseOrderItem::query()
            ->whereHas('purchaseOrder', fn ($q) => $q
                ->where('project_id', $this->id)
                ->whereIn('status', ['ordered', 'partially_received', 'received']))
            ->get(['id', 'quantity_ordered']);

        if ($items->isEmpty()) {
            return true;
        }

        $delivered = GoodsDeliveryItem::query()
            ->whereIn('supplier_purchase_order_item_id', $items->pluck('id'))
            ->whereHas('delivery')
            ->selectRaw('supplier_purchase_order_item_id, SUM(quantity) as total')
            ->groupBy('supplier_purchase_order_item_id')
            ->pluck('total', 'supplier_purchase_order_item_id');

        return $items->every(function (SupplierPurchaseOrderItem $item) use ($delivered) {
            $quantity = (float) ($delivered[$item->id] ?? 0);

            return $quantity + 0.0001 >= (float) $item->quantity_ordered;
        });
    }
}

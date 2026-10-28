<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proposal extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'draft', 'pending_technical', 'ready', 'sent', 'viewed', 'revision', 'accepted', 'rejected', 'expired', 'cancelled',
    ];

    public const PAYMENT_SCHEMES = ['full', 'dp', 'tempo'];

    protected $fillable = [
        'lead_id',
        'proposal_number',
        'created_by',
        'proposal_date',
        'valid_until',
        'customer',
        'subtotal',
        'discount',
        'tax',
        'grand_total',
        'notes',
        'internal_notes',
        'po_document_path',
        'po_number',
        'po_date',
        'delivery_deadline',
        'delivery_address',
        'payment_scheme',
        'dp_percent',
        'term_days',
        'payment_terms',
        'delivery_terms',
        'total_cost',
        'gross_profit',
        'gross_margin',
        'status',
        'sent_at',
        'sent_by',
    ];

    protected $casts = [
        'proposal_date' => 'date',
        'valid_until' => 'date',
        'po_date' => 'date',
        'delivery_deadline' => 'date',
        'dp_percent' => 'float',
        'term_days' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function items()
    {
        return $this->hasMany(ProposalItem::class);
    }

    public function project()
    {
        return $this->hasOne(Project::class);
    }

    /** Supplier PO (pesanan 3DY ke pemasok) — satu quotation boleh punya banyak. */
    public function supplierPurchaseOrders()
    {
        return $this->hasMany(SupplierPurchaseOrder::class);
    }

    public function recalculate(): void
    {
        $items = $this->items()->get();
        $subtotal = round((float) $items->sum('subtotal'), 2);
        $discount = round(min((float) ($this->discount ?? 0), $subtotal), 2);
        $tax = round(max(0, (float) ($this->tax ?? 0)), 2);

        // Penjualan bersih = subtotal setelah diskon, SEBELUM pajak.
        $netSales = round($subtotal - $discount, 2);

        // Modal hanya dihitung bila SEMUA item punya harga modal.
        // Item tanpa harga modal / quotation lama = NULL (belum lengkap),
        // bukan 0 — jangan menampilkan margin palsu.
        $incomplete = $items->isEmpty()
            || $items->contains(fn ($item) => $item->unit_cost === null);

        $totalCost = null;
        $grossProfit = null;
        $grossMargin = null;

        if (! $incomplete) {
            $totalCost = round((float) $items->sum(
                fn ($item) => (float) $item->quantity * (float) $item->unit_cost
            ), 2);
            $grossProfit = round($netSales - $totalCost, 2);
            // Margin negatif dibiarkan apa adanya; penjualan bersih 0 = margin NULL.
            $grossMargin = $netSales == 0.0 ? null : round($grossProfit / $netSales * 100, 2);
        }

        $this->forceFill([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'grand_total' => $subtotal - $discount + $tax,
            'total_cost' => $totalCost,
            'gross_profit' => $grossProfit,
            'gross_margin' => $grossMargin,
        ])->save();
    }

    /** Penjualan bersih = subtotal setelah diskon, sebelum pajak. */
    public function netSales(): float
    {
        return round((float) $this->subtotal - (float) $this->discount, 2);
    }

    /** false bila ada item tanpa harga modal atau data historis sebelum fitur ini. */
    public function hasCompleteCostData(): bool
    {
        return $this->total_cost !== null;
    }

    /** Jumlah item yang belum punya harga modal (untuk penanda UI). */
    public function missingCostItemsCount(): int
    {
        return $this->items()->whereNull('unit_cost')->count();
    }

    /**
     * Quotation siap diproses Purchasing = sudah accepted DAN customer PO
     * sudah diunggah. Antrean admin diturunkan dari kondisi ini (tanpa
     * membuat entri handoff baru) sehingga request berulang tidak
     * menggandakan data.
     */
    public function isReadyForPurchasing(): bool
    {
        return $this->status === 'accepted' && filled($this->po_document_path);
    }

    /** Status customer PO untuk antrean Purchasing. */
    public function purchasingStatusLabel(): string
    {
        if ($this->status !== 'accepted') {
            return self::statusLabel($this->status);
        }

        return $this->isReadyForPurchasing()
            ? __('PO Received — Ready for Purchasing')
            : __('Waiting for Customer PO');
    }

    /** Label skema pembayaran untuk form/daftar. */
    public static function paymentSchemeLabel(?string $scheme): string
    {
        return match ($scheme) {
            'full' => __('Full Payment 100%'),
            'dp' => __('Down Payment + Settlement'),
            'tempo' => __('Tempo / Net Days'),
            default => __('Not set yet'),
        };
    }

    /**
     * Ringkasan syarat bayar yang ditampilkan ke pelanggan & Admin.
     * `payment_terms` (teks bebas) selalu menang bila diisi Sales.
     */
    public function paymentTermsSummary(): string
    {
        if (filled($this->payment_terms)) {
            return $this->payment_terms;
        }

        return match ($this->payment_scheme) {
            'full' => __('Full payment 100%'),
            'dp' => $this->dp_percent !== null
                ? __('DP :dp% + settlement :rest%', [
                    'dp' => self::formatPercent((float) $this->dp_percent),
                    'rest' => self::formatPercent(100 - (float) $this->dp_percent),
                ])
                : __('Down payment + settlement'),
            'tempo' => $this->term_days
                ? __('Net :days days', ['days' => $this->term_days])
                : __('Tempo'),
            default => __('Not set yet'),
        };
    }

    /** Nominal DP yang diharapkan (null bila bukan skema DP). */
    public function expectedDpAmount(): ?float
    {
        if ($this->payment_scheme !== 'dp' || $this->dp_percent === null) {
            return null;
        }

        return round((float) $this->grand_total * (float) $this->dp_percent / 100, 2);
    }

    /** Field handoff yang masih kosong (untuk peringatan di UI Admin). */
    public function handoffWarnings(): array
    {
        $warnings = [];

        if (blank($this->po_number)) {
            $warnings[] = __('Customer PO number is missing.');
        }
        if (blank($this->delivery_deadline)) {
            $warnings[] = __('Delivery deadline is missing.');
        }
        if (blank($this->payment_scheme)) {
            $warnings[] = __('Payment terms are missing.');
        }

        return $warnings;
    }

    private static function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => __('Draft'),
            'pending_technical' => __('Pending Technical'),
            'ready' => __('Ready'),
            'sent' => __('Sent'),
            'viewed' => __('Viewed'),
            'revision' => __('Revision Requested'),
            // Keputusan PRD: di UI/KPI, accepted tampil sebagai "WON".
            'accepted' => __('Won'),
            'rejected' => __('Rejected'),
            'expired' => __('Expired'),
            'cancelled' => __('Cancelled'),
            default => $status,
        };
    }

    /** Warna badge per status (satu sumber untuk index/show/lead). */
    public static function statusBadgeClasses(string $status): string
    {
        return match ($status) {
            'draft' => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
            'pending_technical' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
            'ready' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            'sent' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
            'viewed' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300',
            'revision' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
            'accepted' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            'expired' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
            'cancelled' => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
            default => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    public const STATUSES = ['unpaid', 'partial', 'paid', 'cancelled'];
    public const TYPES = ['dp', 'termin', 'pelunasan', 'full'];

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'project_id',
        'proposal_id',
        'customer_po_number',
        'invoice_type',
        'amount',
        'paid_amount',
        'status',
        'idempotency_key',
        'issue_date',
        'due_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'issue_date' => 'date',
        'due_date' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * File invoice yang diunggah dari menu admin (morph ProjectDocument).
     */
    public function documents()
    {
        return $this->morphMany(ProjectDocument::class, 'documentable');
    }

    /**
     * Total pembayaran yang sudah masuk (pakai withSum bila tersedia).
     */
    public function paidAmount(): float
    {
        if (isset($this->attributes['payments_sum_amount'])) {
            return (float) $this->attributes['payments_sum_amount'];
        }

        // paid_amount adalah cache yang dihitung ulang dari tabel payments
        // oleh AdminService::syncInvoiceStatus (satu sumber: payments).
        if ($this->exists && array_key_exists('paid_amount', $this->attributes)) {
            return (float) $this->attributes['paid_amount'];
        }

        $paid = $this->relationLoaded('payments')
            ? $this->payments->sum('amount')
            : $this->payments()->sum('amount');

        return (float) $paid;
    }

    public function remainingAmount(): float
    {
        return max(0, (float) $this->amount - $this->paidAmount());
    }

    public function isFullyPaid(): bool
    {
        return $this->paidAmount() >= (float) $this->amount;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'unpaid' => __('Unpaid'),
            'partial' => __('Partial'),
            'paid' => __('Paid'),
            'cancelled' => __('Cancelled'),
            default => ucfirst($status),
        };
    }

    public static function statusBadgeClasses(string $status): string
    {
        return match ($status) {
            'unpaid' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'partial' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
            'paid' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            default => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
        };
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'dp' => __('Down Payment'),
            'termin' => __('Termin'),
            'pelunasan' => __('Settlement'),
            'full' => __('Full'),
            default => ucfirst($type),
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'project_id',
        'amount',
        'status',
        'issue_date',
        'due_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
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
}

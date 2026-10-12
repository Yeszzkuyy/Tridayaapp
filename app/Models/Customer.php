<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Pastikan ini ada

class Customer extends Model
{
    use SoftDeletes; // Pastikan ini ada

    protected $fillable = [
        'name',
        'contact_person',
        'company',
        'pt_group',
        'address',
        'phone',
        'whatsapp',
        'email',
        'notes',
        'status',
        'deleted_by',
        'whatsapp_account_id',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public function contacts()
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Owner bisnis untuk Trash: sales yang memegang lead customer ini.
     * Tepat 1 sales -> owner. 0 / lebih dari 1 -> tak tentu (super-admin only).
     * deleted_by TIDAK dipakai di sini (murni audit trail).
     */
    public function trashOwnerId(): ?int
    {
        $ids = $this->leads()->withTrashed()->whereNotNull('assigned_to')
            ->distinct()->pluck('assigned_to');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function followUps()
    {
        return $this->hasMany(FollowUp::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function whatsappAccount()
    {
        return $this->belongsTo(WhatsappAccount::class);
    }

    public function waLink(string $text = ''): ?string
    {
        $number = $this->whatsapp ?: $this->phone;
        $digits = preg_replace('/\D/', '', $number ?? '');

        if (!$digits) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62' . $digits;
        }

        return 'https://wa.me/' . $digits . ($text ? '?text=' . rawurlencode($text) : '');
    }
}
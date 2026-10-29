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
        'billing_address',
        'shipping_address',
        'finance_pic_name',
        'finance_pic_phone',
        'finance_pic_email',
        'phone',
        'whatsapp',
        'email',
        'npwp',
        'npwp_document_path',
        'npwp_document_name',
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

    /** Kontak primary aktif; null bila customer belum punya kontak utama. */
    public function primaryContact(): ?CustomerContact
    {
        if ($this->relationLoaded('contacts')) {
            return $this->contacts->firstWhere('is_primary', true);
        }

        return $this->contacts()->where('is_primary', true)->orderByDesc('id')->first();
    }

    /** Sudah punya data kontak orang (bukan cuma kolom legacy)? */
    public function hasContacts(): bool
    {
        return $this->relationLoaded('contacts')
            ? $this->contacts->isNotEmpty()
            : $this->contacts()->exists();
    }

    /**
     * Nama PIC default.
     *
     * Sumber utama tabel `customer_contacts`; kolom legacy hanya dipakai
     * bila customer memang belum punya kontak sama sekali.
     */
    public function picName(): ?string
    {
        $primary = $this->primaryContact();

        if ($primary) {
            return $primary->name;
        }

        return $this->hasContacts() ? null : $this->contact_person;
    }

    /** WhatsApp PIC default (bukan nomor perusahaan). */
    public function picWhatsapp(): ?string
    {
        return $this->primaryContact()?->whatsapp;
    }

    /** Telepon PIC default (bukan nomor perusahaan). */
    public function picPhone(): ?string
    {
        return $this->primaryContact()?->phone;
    }

    /** Email PIC default (bukan email perusahaan). */
    public function picEmail(): ?string
    {
        return $this->primaryContact()?->email;
    }

    /** Jabatan PIC default, bila diisi. */
    public function picPosition(): ?string
    {
        return $this->primaryContact()?->position;
    }

    /**
     * Alamat tagihan Invoice. Bila belum diisi, pakai alamat utama supaya
     * invoice lama/cepat tetap punya alamat.
     */
    public function billingAddress(): ?string
    {
        return $this->billing_address ?: $this->address;
    }

    /**
     * Alamat pengiriman barang. Fallback ke alamat utama.
     */
    public function shippingAddress(): ?string
    {
        return $this->shipping_address ?: $this->address;
    }

    /**
     * Kontak finance yang ditampilkan Admin saat menagih.
     */
    public function financeContact(): ?string
    {
        $parts = array_filter([
            $this->finance_pic_name,
            $this->finance_pic_phone,
            $this->finance_pic_email,
        ]);

        return $parts ? implode(' · ', $parts) : null;
    }

    public function documents()
    {
        return $this->hasMany(ProjectDocument::class);
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
        $digits = self::waNumber($this->whatsapp ?: $this->phone);

        if (! $digits) {
            return null;
        }

        return 'https://wa.me/' . $digits . ($text ? '?text=' . rawurlencode($text) : '');
    }

    /** Tautan WhatsApp untuk PIC default (bukan nomor perusahaan). */
    public function picWhatsappLink(): ?string
    {
        $digits = self::waNumber($this->picWhatsapp());

        return $digits ? 'https://wa.me/' . $digits : null;
    }

    /** Normalisasi nomor WA ke format internasional tanpa simbol. */
    public static function waNumber(?string $number): ?string
    {
        $digits = preg_replace('/\D/', '', $number ?? '');

        if (! $digits) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62' . $digits;
        }

        return $digits;
    }

    /**
     * Nama file NPWP untuk tampil & download: nama asli upload,
     * fallback ke nama lama untuk data sebelum kolom ini ada.
     */
    public function npwpDisplayName(): ?string
    {
        if (! $this->npwp_document_path) {
            return null;
        }

        if (! empty($this->npwp_document_name)) {
            return \App\Rules\SecureFile::sanitizeName($this->npwp_document_name);
        }

        $ext = pathinfo($this->npwp_document_path, PATHINFO_EXTENSION);

        return 'NPWP-' . \Illuminate\Support\Str::slug($this->name) . ($ext ? '.' . $ext : '');
    }
}
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

    /**
     * PIC utama customer. `contact_person` (kolom lama) hanya dipakai sebagai
     * cadangan untuk data yang belum punya baris di customer_contacts.
     */
    public function primaryPic(): ?CustomerContact
    {
        if ($this->relationLoaded('contacts')) {
            return $this->contacts->firstWhere('is_primary', true);
        }

        // Model yang belum tersimpan (mis. dipakai di unit test) tidak punya
        // baris contact untuk dibaca.
        if (! $this->exists) {
            return null;
        }

        return $this->contacts()->where('is_primary', true)->latest('id')->first();
    }

    public function picName(): ?string
    {
        return $this->primaryPic()?->name ?: $this->contact_person;
    }

    public function picPhone(): ?string
    {
        return $this->primaryPic()?->phone ?: $this->phone;
    }

    /**
     * Nomor WhatsApp untuk menghubungi PIC: WA PIC -> telp PIC -> WA customer.
     */
    public function picWhatsapp(): ?string
    {
        $contact = $this->primaryPic();

        return $contact?->whatsapp ?: ($contact?->phone ?: $this->whatsapp);
    }

    /**
     * Sinkronkan data PIC ke customer_contacts (primary) sekaligus mengisi
     * kolom `contact_person` sebagai mirror.
     *
     * Non-destruktif: nilai null/string kosong tidak dikirim, jadi data yang
     * sudah ada tidak terhapus saat form dikirim dengan field kosong.
     * `$onlyMissing` dipakai saat data berasal dari form Lead: nama PIC tetap
     * diperbarui, tapi telp/WA/email lama milik PIC tidak ditimpa nomor
     * customer.
     *
     * @param  array{name?: string|null, position?: string|null, phone?: string|null, whatsapp?: string|null, email?: string|null, created_by?: int|null}  $attributes
     */
    public function syncPrimaryContact(array $attributes, bool $onlyMissing = false): ?CustomerContact
    {
        $attributes = array_filter($attributes, fn ($value) => filled($value));

        if (blank($attributes['name'] ?? null)) {
            return $this->primaryPic();
        }

        $contact = $this->primaryPic();

        if ($contact) {
            if ($onlyMissing) {
                $attributes = array_filter(
                    $attributes,
                    fn ($value, $key) => $key === 'name' || blank($contact->{$key}),
                    ARRAY_FILTER_USE_BOTH
                );
            }

            $contact->fill($attributes);
            if ($contact->isDirty()) {
                $contact->save();
            }
        } else {
            $contact = $this->contacts()->create([
                'name' => $attributes['name'],
                'position' => $attributes['position'] ?? null,
                'phone' => $attributes['phone'] ?? null,
                'whatsapp' => $attributes['whatsapp'] ?? null,
                'email' => $attributes['email'] ?? null,
                'is_primary' => true,
                'created_by' => $attributes['created_by'] ?? auth()->id(),
            ]);
        }

        $this->syncContactMirror();

        return $contact;
    }

    /**
     * Salin nama PIC primary ke kolom `contact_person`. Tidak pernah
     * mengosongkan kolom supaya data lama tetap aman.
     */
    public function syncContactMirror(): void
    {
        $name = $this->primaryPic()?->name;

        if (filled($name) && $name !== $this->contact_person) {
            $this->forceFill(['contact_person' => $name])->saveQuietly();
        }
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

    public function waLink(string $text = '', ?string $number = null): ?string
    {
        $number = $number ?: ($this->picWhatsapp() ?: $this->whatsapp ?: $this->phone);
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
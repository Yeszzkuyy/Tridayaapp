<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable; // ← Tambahkan ini
use Laravel\Ai\Concerns\HasConversations;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasConversations, HasFactory, HasPushSubscriptions, HasRoles, Notifiable, SoftDeletes; // ← Tambahkan SoftDeletes

    protected $fillable = [
        'name',
        'email',
        'password',
        'password_changed_at',
        'avatar',
        'cover',
        'preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'preferences' => 'array',
        ];
    }

    public function preference(string $key, mixed $default = null): mixed
    {
        return data_get($this->preferences, $key, $default);
    }

    /**
     * true bila user sudah pernah mengganti password bawaannya sendiri
     * (via form password / reset). null = masih password awal → belum aman.
     */
    public function hasSecurePassword(): bool
    {
        return $this->password_changed_at !== null;
    }

    /**
     * true bila avatar user memakai border animasi (khusus founder).
     */
    public function hasAnimatedAvatarBorder(): bool
    {
        return $this->email === 'yehezkielmayogi.ptnti@gmail.com';
    }

    /**
     * Alasan akun ini TIDAK boleh dihapus (null = boleh).
     * Melindungi dari lockout & penghapusan massal tidak sengaja.
     * Alur hapus akun karyawan yang benar: keluarkan dari
     * CompanyUserSeeder, baru hapus.
     */
    public function deletionBlockReason(): ?string
    {
        $superAdminExists = \Spatie\Permission\Models\Role::where('name', 'super-admin')->exists();

        if ($superAdminExists && $this->hasRole('super-admin') && static::role('super-admin')->count() <= 1) {
            return __('Tidak bisa menghapus super-admin terakhir (sistem akan terkunci).');
        }

        if (str_ends_with((string) $this->email, '@tridayaapp.com')) {
            return __('Akun karyawan dilindungi — keluarkan dulu dari CompanyUserSeeder bila memang harus dihapus.');
        }

        return null;
    }
}

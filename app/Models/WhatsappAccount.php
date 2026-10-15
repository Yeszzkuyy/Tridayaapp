<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappAccount extends Model
{
    use SoftDeletes;

    public const GROUPS = ['NTI', 'MGK', 'TPS', 'WANI'];

    public const GATEWAY_GREEN = 'green_api';

    public const GATEWAY_META = 'meta';

    protected $fillable = [
        'name',
        'phone_number',
        'account_code',
        'gateway_type',
        'gateway_instance',
        'gateway_token',
        'gateway_status',
        'assigned_to',
        'is_active',
        'bot_enabled',
        'bot_instructions',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'bot_enabled' => 'boolean',
        'gateway_token' => 'encrypted',
    ];

    protected $hidden = ['gateway_token'];

    public function isMetaGateway(): bool
    {
        return $this->gateway_type === self::GATEWAY_META;
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages()
    {
        return $this->hasMany(WhatsappMessage::class);
    }
}
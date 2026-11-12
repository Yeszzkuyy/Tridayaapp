<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalVersion extends Model
{
    protected $fillable = [
        'proposal_id',
        'version',
        'label',
        'snapshot',
        'total_before',
        'total_after',
        'change_summary',
        'changed_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'snapshot' => 'array',
        'total_before' => 'float',
        'total_after' => 'float',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

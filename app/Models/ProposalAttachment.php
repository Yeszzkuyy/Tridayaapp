<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * File quotation (PDF/Excel/Word) yang dilampirkan ke sebuah quotation,
 * baik hasil upload manual maupun hasil accept dari Inside Sales.
 */
class ProposalAttachment extends Model
{
    protected $fillable = [
        'proposal_id',
        'file_path',
        'original_name',
        'mime',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    /** Ukuran file dalam bentuk manusiawi: 812 KB / 1.4 MB. */
    public function humanSize(): string
    {
        $bytes = (int) $this->size;

        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1).' MB';
        }

        return max(1, (int) round($bytes / 1024)).' KB';
    }
}

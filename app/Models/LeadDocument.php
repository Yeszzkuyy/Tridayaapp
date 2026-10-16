<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadDocument extends Model
{
    public const CATEGORIES = [
        'proposal',
        'quotation',
        'company_profile',
        'technical',
        'requirement',
        'supporting',
        'other',
    ];

    public static function categoryLabel(?string $category): string
    {
        return match ($category) {
            'proposal' => __('Proposal'),
            'quotation' => __('Quotation'),
            'company_profile' => __('Company Profile'),
            'technical' => __('Technical Documents'),
            'requirement' => __('Customer Requirements'),
            'supporting' => __('Supporting Documents'),
            'other' => __('Others'),
            default => '-',
        };
    }

    protected $fillable = [
        'lead_id',
        'file_name',
        'file_path',
        'mime_type',
        'category',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}

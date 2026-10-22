<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'customer_id',
        'doc_type',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'notes',
        'uploaded_by',
        'document_category_id',
        'documentable_type',
        'documentable_id',
    ];

    public const DOC_TYPES = ['invoice', 'po', 'payment'];

    public function getTypeLabelAttribute()
    {
        return match ($this->doc_type) {
            'invoice' => 'INV',
            'po' => 'PO',
            'payment' => 'PAY',
            default => null,
        };
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Record pemilik file (Invoice / PurchaseOrder / Payment). Null untuk
     * file yang diunggah langsung dari tab Documents customer.
     */
    public function documentable()
    {
        return $this->morphTo();
    }

    public function category()
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
    public function getFileExtensionAttribute()
    {
        return strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));
    }
    
    public function getIsImageAttribute()
    {
        return in_array($this->file_extension, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp']);
    }
    
    public function getIsPdfAttribute()
    {
        return $this->file_extension === 'pdf';
    }
    
    public function getIsOfficeAttribute()
    {
        return in_array($this->file_extension, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']);
    }
    
    public function getIsVideoAttribute()
    {
        return in_array($this->file_extension, ['mp4', 'webm', 'ogg', 'mov', 'avi']);
    }
}
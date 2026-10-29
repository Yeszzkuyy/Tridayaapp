<?php

namespace App\Support;

use App\Rules\SecureFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Satu baris hasil Document Center (read-only view atas file milik modul).
 *
 * Objek ini TIDAK disimpan di database: hanya bentuk seragam dari file yang
 * sudah ada di tabel sumbernya (project_documents, proposal_attachments, dsb),
 * supaya satu file tetap satu baris dan tidak pernah disalin.
 */
class DocumentCenterRow
{
    public function __construct(
        public readonly string $source,
        public readonly int $sourceId,
        public readonly ?int $parentId,
        public readonly string $name,
        public readonly ?string $number,
        public readonly ?string $refNumber,
        public readonly string $category,
        public readonly string $subcategory,
        public readonly ?int $customerId,
        public readonly ?int $projectId,
        public readonly ?string $customerName,
        public readonly ?string $projectName,
        public readonly ?string $projectCode,
        public readonly ?string $docDate,
        public readonly ?string $uploadedAt,
        public readonly ?string $uploaderName,
        public readonly ?string $status,
        public readonly ?string $refType,
        public readonly ?int $refId,
        public readonly bool $available,
        public readonly bool $canPreview,
        public readonly ?string $previewUrl,
        public readonly ?string $downloadUrl,
        public readonly ?string $sourceUrl,
    ) {}

    /** Kunci unik antar sumber, dipakai untuk panel detail. */
    public function uid(): string
    {
        return $this->source.':'.$this->sourceId;
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'customer' => __('Customer'),
            'quotation' => __('Quotation'),
            'purchasing' => __('Purchasing'),
            'project' => __('Project'),
            'technical' => __('Survey & Installation'),
            'finance' => __('Finance'),
            default => __('Others'),
        };
    }

    public function subcategoryLabel(): string
    {
        return match ($this->subcategory) {
            'general' => __('General Document'),
            'invoice' => __('Customer Invoice'),
            'po' => __('Legacy Customer PO'),
            'payment' => __('Payment Proof'),
            'survey' => __('Survey Report'),
            'sizing' => __('Sizing'),
            'instalasi' => __('Installation Report'),
            'bast' => __('BAST'),
            'quotation' => __('Quotation File'),
            'customer_po' => __('Customer PO'),
            'supplier_invoice' => __('Supplier Invoice'),
            'supplier_payment' => __('Supplier Payment Proof'),
            'goods_receipt' => __('Goods Receipt'),
            'lead_proposal' => __('Lead Proposal'),
            'lead_quotation' => __('Lead Quotation'),
            'lead_company_profile' => __('Company Profile'),
            'lead_technical' => __('Technical Documents'),
            'lead_requirement' => __('Customer Requirements'),
            'lead_supporting' => __('Supporting Documents'),
            'lead_other' => __('Lead Attachment'),
            'npwp' => __('NPWP'),
            default => Str::headline($this->subcategory),
        };
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            'project' => __('Project Documents'),
            'quotation' => __('Quotation'),
            'customer_po' => __('Quotation'),
            'supplier_invoice' => __('Supplier Invoice'),
            'supplier_payment' => __('Supplier Payment'),
            'goods_receipt' => __('Goods Receipt'),
            'lead' => __('Lead'),
            'npwp' => __('Customer'),
            default => __('Documents'),
        };
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
    }

    public function statusLabel(): ?string
    {
        if (blank($this->status)) {
            return null;
        }

        return match ($this->status) {
            'diproses' => __('In Process'),
            'selesai' => __('Completed'),
            'dibatalkan' => __('Cancelled'),
            default => Str::headline((string) $this->status),
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'paid', 'selesai', 'accepted' => 'green',
            'partial', 'diproses' => 'yellow',
            'cancelled', 'dibatalkan', 'rejected' => 'red',
            'unpaid', 'draft' => 'slate',
            default => 'slate',
        };
    }

    public function docDateLabel(): string
    {
        return $this->formatDate($this->docDate);
    }

    public function uploadedAtLabel(): string
    {
        return $this->formatDate($this->uploadedAt);
    }

    private function formatDate(?string $value): string
    {
        if (blank($value)) {
            return '-';
        }

        try {
            return Carbon::parse($value)->translatedFormat('d M Y');
        } catch (\Throwable $e) {
            return '-';
        }
    }

    /** Data untuk panel detail (Alpine). */
    public function toPanel(): array
    {
        return [
            'uid' => $this->uid(),
            'name' => $this->name,
            'category' => $this->categoryLabel(),
            'subcategory' => $this->subcategoryLabel(),
            'source' => $this->sourceLabel(),
            'customer' => $this->customerName,
            'project' => $this->projectName,
            'number' => $this->number,
            'ref_number' => $this->refNumber,
            'doc_date' => $this->docDateLabel(),
            'uploaded_at' => $this->uploadedAtLabel(),
            'uploader' => $this->uploaderName,
            'status' => $this->statusLabel(),
            'available' => $this->available,
            'can_preview' => $this->canPreview,
            'preview_url' => $this->previewUrl,
            'download_url' => $this->downloadUrl,
            'source_url' => $this->sourceUrl,
            'file_ext' => $this->extension(),
        ];
    }

    public static function canPreviewExtension(?string $name): bool
    {
        $extension = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));

        return in_array($extension, SecureFile::INLINE_EXTENSIONS, true);
    }
}

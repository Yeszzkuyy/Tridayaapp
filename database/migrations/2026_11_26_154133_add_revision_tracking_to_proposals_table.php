<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pelacakan revisi immutable pada quotation.
 *
 * Nomor quotation dibentuk dari identitas dokumen (PT + tahun + nomor urut)
 * dan versi (revision_number). Data lama tetap dipertahankan: hanya diisi
 * kolom pelacakannya, nomor quotation yang sudah terbit tidak diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->foreignId('parent_quotation_id')->nullable()->after('lead_task_id')
                ->constrained('proposals')->nullOnDelete();
            $table->boolean('is_latest')->default(true)->after('parent_quotation_id');
            $table->unsignedInteger('sequence_number')->nullable()->after('proposal_number');
            $table->unsignedInteger('revision_number')->default(0)->after('sequence_number');
            $table->string('company_code', 10)->nullable()->after('revision_number');
            $table->unsignedSmallInteger('quotation_year')->nullable()->after('company_code');
            $table->string('user_initials', 10)->nullable()->after('quotation_year');

            $table->index(['company_code', 'quotation_year', 'sequence_number'], 'proposals_quotation_identity_index');
            $table->index(['is_latest', 'lead_id'], 'proposals_latest_lead_index');
        });

        $this->backfillExistingQuotations();

        // Satu dokumen (PT + tahun + nomor urut) hanya boleh punya satu versi aktif.
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX proposals_one_latest_version_unique ON proposals (company_code, quotation_year, sequence_number) WHERE is_latest = true');
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS proposals_one_latest_version_unique');
        }

        Schema::table('proposals', function (Blueprint $table) {
            $table->dropIndex('proposals_quotation_identity_index');
            $table->dropIndex('proposals_latest_lead_index');

            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['parent_quotation_id']);
            }

            $table->dropColumn([
                'parent_quotation_id',
                'is_latest',
                'sequence_number',
                'revision_number',
                'company_code',
                'quotation_year',
                'user_initials',
            ]);
        });
    }

    /**
     * Isi kolom pelacakan dari nomor quotation lama tanpa mengubah nomornya.
     * Format yang dikenali:
     * - lama:      NTI/001/I/2026
     * - baru:      001R1/NTI-I/2026
     * - transisi:  Q/2026/11/0001 atau nomor bebas lain
     */
    private function backfillExistingQuotations(): void
    {
        $companies = array_keys((array) config('companies', []));
        $defaultCompany = in_array('WANI', $companies, true) ? 'WANI' : ($companies[0] ?? 'WANI');

        $users = DB::table('users')->select('id', 'name', 'initials')->get()->keyBy('id');
        $leads = DB::table('leads')->select('id', 'pt_group', 'assigned_to')->get()->keyBy('id');
        $used = [];

        foreach (DB::table('proposals')->orderBy('id')->get() as $proposal) {
            [$sequence, $revision, $company, $initials, $year] = $this->parseNumber((string) $proposal->proposal_number);

            $lead = $proposal->lead_id ? $leads->get($proposal->lead_id) : null;

            $company = strtoupper((string) ($company
                ?: $proposal->letterhead_pt
                ?: $lead?->pt_group
                ?: $defaultCompany));
            if (! in_array($company, $companies, true)) {
                $company = $defaultCompany;
            }

            $year = (int) ($year
                ?: ($proposal->proposal_date ? (int) substr((string) $proposal->proposal_date, 0, 4) : 0)
                ?: ($proposal->created_at ? (int) substr((string) $proposal->created_at, 0, 4) : 0)
                ?: (int) now()->year);

            $creator = $proposal->created_by ? $users->get($proposal->created_by) : null;
            $initials = $this->initials(
                $initials
                ?: $creator?->initials
                ?: $this->nameInitials((string) ($creator?->name ?? ''))
                ?: 'X'
            );

            $sequence = (int) ($sequence ?: 0);
            if ($sequence <= 0) {
                $sequence = 1;
            }

            while (isset($used[$company.'|'.$year.'|'.$sequence])) {
                $sequence++;
            }
            $used[$company.'|'.$year.'|'.$sequence] = true;

            DB::table('proposals')->where('id', $proposal->id)->update([
                'sequence_number' => $sequence,
                'revision_number' => (int) $revision,
                'company_code' => $company,
                'quotation_year' => $year,
                'user_initials' => $initials,
                'is_latest' => true,
            ]);
        }
    }

    /** @return array{0:?int,1:int,2:?string,3:?string,4:?int} */
    private function parseNumber(string $number): array
    {
        $number = trim($number);

        if (preg_match('#^(\d{1,6})(?:R(\d+))?/([A-Z]{2,10})-([A-Z]+)/(\d{4})$#', $number, $m)) {
            return [(int) $m[1], (int) ($m[2] ?: 0), $m[3], $m[4], (int) $m[5]];
        }

        if (preg_match('#^([A-Z]{2,10})/(\d{1,6})(?:R(\d+))?/([A-Z]+)/(\d{4})$#', $number, $m)) {
            return [(int) $m[2], (int) ($m[3] ?: 0), $m[1], $m[4], (int) $m[5]];
        }

        $sequence = null;
        $revision = 0;
        $year = null;

        // Q/2026/11/0010 — tahun di segmen kedua, urutan di segmen terakhir.
        if (preg_match('#^[A-Z]+/(\d{4})/[^/]+/(\d{1,6})$#i', $number, $m)) {
            $year = (int) $m[1];
            $sequence = (int) $m[2];
        } elseif (preg_match('#(\d{4})#', $number, $m)) {
            $year = (int) $m[1];
        }

        if (preg_match('#R(\d+)#i', $number, $m)) {
            $revision = (int) $m[1];
        }

        return [$sequence, $revision, null, null, $year];
    }

    private function initials(string $value): string
    {
        $value = strtoupper(preg_replace('/[^A-Za-z]/', '', $value) ?? '');

        return $value !== '' ? substr($value, 0, 10) : 'X';
    }

    /** Inisial dari nama: huruf awal maksimal dua kata (Adi Santosa → AS). */
    private function nameInitials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $letters = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_substr($part, 0, 1);
        }

        return $letters;
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rapikan inisial quotation lama yang sempat terisi nama lengkap saat
 * backfill. Nomor dokumen yang sudah terbit TIDAK diubah; hanya metadata
 * `user_initials` yang dipakai untuk nomor revisi berikutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = DB::table('users')->select('id', 'name', 'initials')->get()->keyBy('id');

        DB::table('proposals')
            ->select('id', 'created_by', 'user_initials')
            ->orderBy('id')
            ->chunkById(200, function ($proposals) use ($users) {
                foreach ($proposals as $proposal) {
                    $creator = $proposal->created_by ? $users->get($proposal->created_by) : null;

                    if (! $creator) {
                        continue;
                    }

                    $raw = trim((string) ($creator->initials ?? ''));

                    if ($raw === '') {
                        $raw = $this->nameInitials((string) ($creator->name ?? ''));
                    }

                    $initials = $this->normalize($raw !== '' ? $raw : (string) $proposal->user_initials);

                    if ($initials !== (string) $proposal->user_initials) {
                        DB::table('proposals')->where('id', $proposal->id)->update([
                            'user_initials' => $initials,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Normalisasi data; tidak ada rollback yang aman.
    }

    private function normalize(string $value): string
    {
        $value = strtoupper(preg_replace('/[^A-Za-z]/', '', $value) ?? '');

        return $value !== '' ? substr($value, 0, 10) : 'X';
    }

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

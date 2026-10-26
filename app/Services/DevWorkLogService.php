<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class DevWorkLogService
{
    /**
     * Ambil commit git dalam N hari terakhir atau sejumlah limit.
     *
     * @param int $days
     * @return array<int, array{hash: string, author: string, date: string, subject: string, category: string, summary: string}>
     */
    public function getRecentCommits(int $days = 2): array
    {
        $since = now()->subDays($days)->startOfDay()->format('Y-m-d H:i:s');
        
        try {
            // Gunakan Process facade Laravel untuk membaca git log
            $command = sprintf('git log --since="%s" --pretty=format:"%%h|%%an|%%ad|%%s" --date=short -n 50', $since);
            $result = Process::path(base_path())->run($command);

            if (! $result->successful() || empty(trim($result->output()))) {
                // Fallback jika since kosong: ambil 15 commit terakhir
                $result = Process::path(base_path())->run('git log -n 15 --pretty=format:"%h|%an|%ad|%s" --date=short');
            }

            if (! $result->successful()) {
                return [];
            }

            $lines = explode("\n", trim($result->output()));
            $commits = [];

            foreach ($lines as $line) {
                $parts = explode('|', $line, 4);
                if (count($parts) < 4) {
                    continue;
                }

                [$hash, $author, $date, $subject] = $parts;
                $parsed = $this->parseCommitSubject(trim($subject));

                $commits[] = [
                    'hash' => trim($hash),
                    'author' => trim($author),
                    'date' => trim($date),
                    'subject' => trim($subject),
                    'category' => $parsed['category'],
                    'summary' => $parsed['summary'],
                ];
            }

            return $commits;
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    /**
     * Parse conventional commit message: type(scope): description
     *
     * @param string $subject
     * @return array{category: string, summary: string}
     */
    public function parseCommitSubject(string $subject): array
    {
        // Pola: feat(sales/kpi): modul evaluasi ... atau fix: ...
        if (preg_match('/^([a-z]+)(?:\(([^)]+)\))?:\s*(.+)$/i', $subject, $matches)) {
            $type = strtolower($matches[1]);
            $scope = !empty($matches[2]) ? trim($matches[2]) : '';
            $desc = trim($matches[3]);

            $category = $scope ? Str::headline(str_replace(['/', '-'], ' ', $scope)) : Str::headline($type);

            return [
                'category' => $category,
                'summary' => ucfirst($desc),
            ];
        }

        return [
            'category' => 'Fitur & Sistem',
            'summary' => ucfirst($subject),
        ];
    }

    /**
     * Susun draf pesan WhatsApp sesuai format standar Section 13 AGENTS.md.
     *
     * @param int $days
     * @param array<string, mixed> $customFields
     * @return string
     */
    public function generateWhatsAppSummary(int $days = 2, array $customFields = []): string
    {
        $commits = $this->getRecentCommits($days);

        // Kelompokkan commit berdasarkan kategori
        $grouped = [];
        foreach ($commits as $c) {
            $cat = $c['category'];
            if (! isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $c['summary'];
        }

        $pekerjaanLines = [];
        if (! empty($grouped)) {
            foreach ($grouped as $cat => $items) {
                // Ambil unik dan gabungkan
                $unique = array_values(array_unique($items));
                $joined = implode('; ', array_slice($unique, 0, 3));
                $pekerjaanLines[] = "• [{$cat}]: {$joined}";
            }
        } else {
            $pekerjaanLines[] = "• [Umum]: Pemeliharaan dan pengujian alur fitur aplikasi.";
        }

        $pekerjaanText = implode("\n", $pekerjaanLines);

        $bukti = ! empty($customFields['bukti']) 
            ? trim($customFields['bukti']) 
            : "• " . url('/');

        $kendala = ! empty($customFields['kendala']) 
            ? trim($customFields['kendala']) 
            : "• Tidak ada kendala teknis yang menghambat";

        $nextSteps = ! empty($customFields['next_steps']) 
            ? trim($customFields['next_steps']) 
            : "• Optimasi performa halaman dan pemeliharaan modul aktif\n• Lanjutan sinkronisasi request dan pengujian berkala";

        return <<<TEXT
Halo Pak, izin menyampaikan rekap progres pekerjaan {$days} hari terakhir (Yeski & Raafi) ya:

📋 *Pekerjaan Selesai:*
{$pekerjaanText}

📸 *Bukti Hasil:*
{$bukti}

⚠️ *Kendala & Catatan:*
{$kendala}

🎯 *Next Steps:*
{$nextSteps}

TEXT;
    }
}

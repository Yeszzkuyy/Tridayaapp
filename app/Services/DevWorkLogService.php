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
            // Gunakan -c safe.directory=* agar user www-data tidak terkena 'dubious ownership'
            $baseCmd = 'git -c safe.directory=* --no-pager log';
            $command = sprintf('%s --since="%s" --pretty=format:"%%h|%%an|%%ad|%%s" --date=short -n 50', $baseCmd, $since);
            
            $result = Process::path(base_path())->run($command);
            $rawOutput = $result->successful() ? trim($result->output()) : '';

            // Jika kosong (misal weekend / rentang hari sedikit commit), ambil fallback 15 commit terbaru
            if (empty($rawOutput)) {
                $fallbackCmd = sprintf('%s -n 15 --pretty=format:"%%h|%%an|%%ad|%%s" --date=short', $baseCmd);
                $fallbackRes = Process::path(base_path())->run($fallbackCmd);
                if ($fallbackRes->successful()) {
                    $rawOutput = trim($fallbackRes->output());
                }
            }

            // Fallback sekunder bila Process facade terhambat environment FPM: shell_exec
            if (empty($rawOutput) && function_exists('shell_exec')) {
                $shellCmd = sprintf('cd %s && %s -n 15 --pretty=format:"%%h|%%an|%%ad|%%s" --date=short 2>/dev/null', escapeshellarg(base_path()), $baseCmd);
                $rawOutput = trim((string) @shell_exec($shellCmd));
            }

            if (empty($rawOutput)) {
                return [];
            }

            $lines = explode("\n", $rawOutput);
            $commits = [];

            foreach ($lines as $line) {
                $parts = explode('|', $line, 4);
                if (count($parts) < 4) {
                    continue;
                }

                [$hash, $author, $date, $subject] = $parts;
                $subject = trim($subject);

                // Lewati commit merge otomatis agar laporan murni hasil kerjaan fitur/bugfix
                if (preg_match('/^Merge (branch|pull request|remote-tracking)/i', $subject)) {
                    continue;
                }

                $parsed = $this->parseCommitSubject($subject);

                $commits[] = [
                    'hash' => trim($hash),
                    'author' => trim($author),
                    'date' => trim($date),
                    'subject' => $subject,
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
        // Pola konvensi: tipe(divisi/menu): keterangan
        if (preg_match('/^[a-z]+(?:\(([^)]+)\))?:\s*(.+)$/i', $subject, $matches)) {
            $scope = !empty($matches[1]) ? trim($matches[1]) : '';
            $desc = trim($matches[2]);

            // Jika ada scope seperti 'mkt/leads' atau 'tek/jadwal' atau 'panel/worklog'
            if ($scope) {
                $category = Str::headline(str_replace(['/', '-'], ' ', $scope));
            } else {
                $category = 'Fitur Sistem';
            }

            return [
                'category' => $category,
                'summary' => ucfirst($desc),
            ];
        }

        return [
            'category' => 'Fitur Sistem',
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

        // Kelompokkan commit berdasarkan kategori modul
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
                $pekerjaanLines[] = "• [{$cat}]: {$joined}.";
            }
        } else {
            $pekerjaanLines[] = "• [Umum]: Pengujian alur kerja, perapihan tampilan sistem, dan sinkronisasi data antar divisi.";
        }

        $pekerjaanText = implode("\n", $pekerjaanLines);

        // Bukti hasil langsung diset untuk dilampirkan via chat WhatsApp langsung
        $bukti = ! empty($customFields['bukti']) 
            ? trim($customFields['bukti']) 
            : "• (Screenshot / bukti hasil terlampir langsung di chat ini, Pak)";

        $kendala = ! empty($customFields['kendala']) 
            ? trim($customFields['kendala']) 
            : "• Tidak ada kendala teknis yang menghambat";

        $nextSteps = ! empty($customFields['next_steps']) 
            ? trim($customFields['next_steps']) 
            : "• Lanjut pengujian alur modul terkait dan monitoring kestabilan sistem\n• Sinkronisasi feedback serta persiapan integrasi fitur berikutnya";

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

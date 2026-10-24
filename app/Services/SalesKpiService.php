<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Proposal;
use App\Models\PurchaseOrder;
use App\Models\SalesTarget;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SalesKpiService
{
    /** Nilai target default jika belum diset oleh Management */
    public const DEFAULT_TARGET_REVENUE = 50000000; // Rp 50.000.000
    public const DEFAULT_TARGET_DEALS = 3; // 3 closing deal

    /**
     * Hitung KPI personal seorang user sales untuk bulan dan tahun tertentu.
     */
    public function getUserKpi(User $user, int $month, int $year): array
    {
        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endOfMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // 1. Ambil target dari database atau fallback ke default
        $targetRecord = SalesTarget::where('user_id', $user->id)
            ->forPeriod($month, $year)
            ->first();

        $targetRevenue = $targetRecord ? (float) $targetRecord->target_revenue : (float) self::DEFAULT_TARGET_REVENUE;
        $targetDeals = $targetRecord ? (int) $targetRecord->target_deals : (int) self::DEFAULT_TARGET_DEALS;
        $isCustomTarget = $targetRecord !== null;

        // 2. Hitung Deals Closing (WON)
        // Ambil proposal accepted
        $acceptedProposals = Proposal::where('status', 'accepted')
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhereHas('lead', fn ($l) => $l->where('assigned_to', $user->id));
            })
            ->whereBetween('updated_at', [$startOfMonth, $endOfMonth])
            ->get();

        $proposalRevenue = (float) $acceptedProposals->sum('grand_total');

        // Ambil lead yang statusnya 'won' dalam bulan ini
        $wonLeads = Lead::where('assigned_to', $user->id)
            ->where('status', 'won')
            ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('closed_at', [$startOfMonth, $endOfMonth])
                    ->orWhere(function ($w) use ($startOfMonth, $endOfMonth) {
                        $w->whereNull('closed_at')
                            ->whereBetween('updated_at', [$startOfMonth, $endOfMonth]);
                    });
            })
            ->get();

        // Ambil PO yang dibuat oleh user atau terhubung ke project sales ini
        $purchaseOrders = PurchaseOrder::where('created_by', $user->id)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->where('status', '!=', 'dibatalkan')
            ->get();

        $poRevenue = (float) $purchaseOrders->sum('amount');

        // Jumlah deal closing adalah kombinasi distinct deal (proposal accepted atau won lead)
        $actualDeals = max($acceptedProposals->count(), $wonLeads->count());
        if ($actualDeals === 0 && $purchaseOrders->count() > 0) {
            $actualDeals = $purchaseOrders->count();
        }

        // Realisasi omset (ambil nominal terbesar antara proposal won vs PO vs estimasi)
        $actualRevenue = max($proposalRevenue, $poRevenue);

        // Jika belum ada nominal proposal/PO tapi ada lead won, beri nilai baseline
        if ($actualRevenue == 0 && $wonLeads->count() > 0) {
            // Jika ada won lead tanpa proposal nominal
            $actualRevenue = 0;
        }

        // 3. Persentase pencapaian
        $revenuePct = $targetRevenue > 0 ? round(($actualRevenue / $targetRevenue) * 100, 1) : 0;
        $dealsPct = $targetDeals > 0 ? round(($actualDeals / $targetDeals) * 100, 1) : 0;

        // Overall progress (rata-rata terbobot omset & deal)
        $overallPct = round(($revenuePct * 0.6) + ($dealsPct * 0.4), 1);

        $revenueShortfall = max(0, $targetRevenue - $actualRevenue);
        $dealsShortfall = max(0, $targetDeals - $actualDeals);

        // 4. Statistik pipeline pendukung
        $activeLeadsCount = Lead::where('assigned_to', $user->id)
            ->whereNotIn('status', ['won', 'lost'])
            ->count();

        $totalAssignedLeads = Lead::where('assigned_to', $user->id)->count();
        $conversionRate = $totalAssignedLeads > 0 ? round(($actualDeals / $totalAssignedLeads) * 100, 1) : 0;

        // 5. Pesan Motivasi / Congratulation Dinamis
        $celebration = $this->buildCelebrationMessage(
            $user->name,
            $actualRevenue,
            $targetRevenue,
            $actualDeals,
            $targetDeals,
            $revenuePct,
            $dealsPct,
            $dealsShortfall
        );

        return [
            'user' => $user,
            'period_month' => $month,
            'period_year' => $year,
            'target_revenue' => $targetRevenue,
            'target_deals' => $targetDeals,
            'is_custom_target' => $isCustomTarget,
            'target_notes' => $targetRecord?->notes,
            'actual_revenue' => $actualRevenue,
            'actual_deals' => $actualDeals,
            'revenue_pct' => $revenuePct,
            'deals_pct' => $dealsPct,
            'overall_pct' => $overallPct,
            'revenue_shortfall' => $revenueShortfall,
            'deals_shortfall' => $dealsShortfall,
            'active_leads' => $activeLeadsCount,
            'conversion_rate' => $conversionRate,
            'celebration' => $celebration,
        ];
    }

    /**
     * Hitung leaderboard seluruh anggota tim Sales untuk bulan & tahun tertentu.
     */
    public function getLeaderboard(int $month, int $year): Collection
    {
        $salesUsers = User::role('sales')
            ->orderBy('name')
            ->get();

        $board = $salesUsers->map(function ($sales) use ($month, $year) {
            return $this->getUserKpi($sales, $month, $year);
        });

        // Urutkan berdasarkan realisasi omset terbesar, lalu jumlah deal
        $sorted = $board->sort(function ($a, $b) {
            if ($b['actual_revenue'] == $a['actual_revenue']) {
                if ($b['actual_deals'] == $a['actual_deals']) {
                    return $b['overall_pct'] <=> $a['overall_pct'];
                }
                return $b['actual_deals'] <=> $a['actual_deals'];
            }
            return $b['actual_revenue'] <=> $a['actual_revenue'];
        })->values();

        // Berikan nomor peringkat (Rank)
        return $sorted->map(function ($item, $index) {
            $item['rank'] = $index + 1;
            return $item;
        });
    }

    /**
     * Ringkasan agregat tim untuk pandangan Management.
     */
    public function getTeamSummary(int $month, int $year): array
    {
        $leaderboard = $this->getLeaderboard($month, $year);

        $totalTargetRevenue = (float) $leaderboard->sum('target_revenue');
        $totalActualRevenue = (float) $leaderboard->sum('actual_revenue');
        $totalTargetDeals = (int) $leaderboard->sum('target_deals');
        $totalActualDeals = (int) $leaderboard->sum('actual_deals');

        $teamRevenuePct = $totalTargetRevenue > 0 ? round(($totalActualRevenue / $totalTargetRevenue) * 100, 1) : 0;
        $teamDealsPct = $totalTargetDeals > 0 ? round(($totalActualDeals / $totalTargetDeals) * 100, 1) : 0;
        $teamOverallPct = round(($teamRevenuePct * 0.6) + ($teamDealsPct * 0.4), 1);

        $topPerformer = $leaderboard->first();

        return [
            'total_target_revenue' => $totalTargetRevenue,
            'total_actual_revenue' => $totalActualRevenue,
            'team_revenue_pct' => $teamRevenuePct,
            'total_target_deals' => $totalTargetDeals,
            'total_actual_deals' => $totalActualDeals,
            'team_deals_pct' => $teamDealsPct,
            'team_overall_pct' => $teamOverallPct,
            'top_performer' => $topPerformer,
            'total_sales_count' => $leaderboard->count(),
        ];
    }

    /**
     * Susun pesan perayaan / motivasi kontekstual.
     */
    private function buildCelebrationMessage(
        string $name,
        float $actualRev,
        float $targetRev,
        int $actualDeals,
        int $targetDeals,
        float $revPct,
        float $dealsPct,
        int $dealsShortfall
    ): array {
        // Kasus 1: Target tercapai penuh (>= 100%)
        if (($actualRev >= $targetRev && $targetRev > 0) || ($actualDeals >= $targetDeals && $targetDeals > 0)) {
            return [
                'type' => 'congratulations',
                'badge' => 'TARGET TERCAPAI 🏆',
                'title' => __('Selamat :name, target bulan ini sukses tercapai!', ['name' => $name]),
                'description' => __('Luar biasa! Anda mencatatkan :deals closing deal dengan pencapaian omset :pct% dari target. Pertahankan performa juara ini!', [
                    'deals' => $actualDeals,
                    'pct' => $revPct,
                ]),
                'bg_gradient' => 'from-emerald-500/10 via-teal-500/5 to-transparent border-emerald-500/30',
                'icon_color' => 'text-emerald-500 bg-emerald-100 dark:bg-emerald-950/60',
            ];
        }

        // Kasus 2: Sudah ada closing deal tapi belum 100%
        if ($actualDeals > 0 || $actualRev > 0) {
            return [
                'type' => 'progress',
                'badge' => 'ON TRACK 🚀',
                'title' => __('Hebat :name, :count project berhasil closing!', ['name' => $name, 'count' => $actualDeals]),
                'description' => __('Progres Anda saat ini :pct% dari target. Tinggal :shortfall closing lagi untuk menuntaskan 100% target bulan ini!', [
                    'pct' => $revPct,
                    'shortfall' => max(1, $dealsShortfall),
                ]),
                'bg_gradient' => 'from-blue-500/10 via-indigo-500/5 to-transparent border-blue-500/30',
                'icon_color' => 'text-blue-500 bg-blue-100 dark:bg-blue-950/60',
            ];
        }

        // Kasus 3: Belum ada closing bulan ini (Motivasi)
        return [
            'type' => 'motivation',
            'badge' => 'FOKUS TARGET 💪',
            'title' => __('Semangat :name, target bulan ini siap diraih!', ['name' => $name]),
            'description' => __('Target bulan ini: :deals Closing Deals & Rp :target. Terus gencarkan follow up dan dekati prospek Anda hari ini!', [
                'deals' => $targetDeals,
                'target' => number_format($targetRev, 0, ',', '.'),
            ]),
            'bg_gradient' => 'from-amber-500/10 via-orange-500/5 to-transparent border-amber-500/30',
            'icon_color' => 'text-amber-500 bg-amber-100 dark:bg-amber-950/60',
        ];
    }
}

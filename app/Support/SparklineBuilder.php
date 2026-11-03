<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SparklineBuilder
{
    /**
     * Build SVG line & area paths from a numerical time-series.
     *
     * Rules:
     * - If $currentValue is 0 (when provided), or all values in $series are 0,
     *   render a flat horizontal baseline (M0,43 L240,43).
     * - If all values in $series are equal (> 0), render a flat mid-level line.
     * - Otherwise map each point to the 240x52 viewBox and interpolate with
     *   monotone horizontal-tangent Cubic Bezier segments.
     *
     * @param  array<int, int|float>  $series
     * @return array{line: string, area: string, is_flat: bool, series: array<int, float>}
     */
    public static function fromSeries(array $series, int|float|null $currentValue = null): array
    {
        $values = array_values(array_map(fn ($v) => (float) $v, $series));

        if ($currentValue !== null && (float) $currentValue <= 0) {
            return self::flatPaths(43.0, array_fill(0, max(count($values), 6), 0.0));
        }

        if (count($values) === 0) {
            return self::flatPaths(43.0, [0, 0, 0, 0, 0, 0]);
        }

        if (count($values) === 1) {
            $values = array_fill(0, 6, $values[0]);
        }

        $max = max($values);
        $min = min($values);

        if ($max <= 0) {
            return self::flatPaths(43.0, $values);
        }

        if (abs($max - $min) < 0.0001) {
            return self::flatPaths(28.0, $values);
        }

        $n = count($values);
        $range = $max - $min;
        $points = [];

        for ($i = 0; $i < $n; $i++) {
            $x = round(($i / ($n - 1)) * 240, 1);
            // Map min -> y=42 (bottom), max -> y=8 (top)
            $y = round(42 - ((($values[$i] - $min) / $range) * 34), 1);
            $points[] = [$x, $y];
        }

        $d = 'M'.$points[0][0].','.$points[0][1];

        for ($i = 1; $i < $n; $i++) {
            [$prevX, $prevY] = $points[$i - 1];
            [$currX, $currY] = $points[$i];
            $midX = round(($prevX + $currX) / 2, 1);
            $d .= " C{$midX},{$prevY} {$midX},{$currY} {$currX},{$currY}";
        }

        return [
            'line' => $d,
            'area' => $d.' L240,52 L0,52 Z',
            'is_flat' => false,
            'series' => $values,
        ];
    }

    /**
     * Aggregate a model query into monthly counts over the last $months months.
     *
     * @return array<int, int>
     */
    public static function monthlyCounts(
        Builder $query,
        string $dateColumn = 'created_at',
        int $months = 6,
        ?Carbon $now = null
    ): array {
        $now = ($now ?? Carbon::now('Asia/Jakarta'))->copy();
        $start = $now->copy()->subMonths($months - 1)->startOfMonth();

        $buckets = [];
        for ($i = 0; $i < $months; $i++) {
            $key = $start->copy()->addMonths($i)->format('Y-m');
            $buckets[$key] = 0;
        }

        $dates = (clone $query)
            ->whereNotNull($dateColumn)
            ->where($dateColumn, '>=', $start)
            ->pluck($dateColumn);

        foreach ($dates as $dt) {
            $key = Carbon::parse($dt)->format('Y-m');
            if (array_key_exists($key, $buckets)) {
                $buckets[$key]++;
            }
        }

        return array_values($buckets);
    }

    /**
     * @param  array<int, float>  $series
     * @return array{line: string, area: string, is_flat: bool, series: array<int, float>}
     */
    private static function flatPaths(float $y, array $series): array
    {
        $line = "M0,{$y} L240,{$y}";

        return [
            'line' => $line,
            'area' => "{$line} L240,52 L0,52 Z",
            'is_flat' => true,
            'series' => $series,
        ];
    }
}

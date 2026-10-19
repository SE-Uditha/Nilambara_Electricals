<?php

namespace App\Modules\Reports\Services;

use Carbon\CarbonImmutable;

class ReportDateRange
{
    /**
     * @param  array{period?: string, date_from?: string, date_to?: string}  $filters
     * @return array{date_from: string, date_to: string, start: CarbonImmutable, end: CarbonImmutable, preset: string}
     */
    public function resolve(array $filters): array
    {
        $timezone = (string) config('business.timezone', 'Asia/Colombo');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $preset = $filters['period'] ?? 'today';

        [$from, $to] = match ($preset) {
            'yesterday' => [$today->subDay(), $today->subDay()],
            'week' => [$today->startOfWeek(), $today],
            'month' => [$today->startOfMonth(), $today],
            'custom' => [
                isset($filters['date_from']) ? CarbonImmutable::parse($filters['date_from'], $timezone)->startOfDay()
                    : (isset($filters['date_to']) ? CarbonImmutable::parse($filters['date_to'], $timezone)->startOfDay() : $today),
                isset($filters['date_to']) ? CarbonImmutable::parse($filters['date_to'], $timezone)->startOfDay()
                    : (isset($filters['date_from']) ? CarbonImmutable::parse($filters['date_from'], $timezone)->startOfDay() : $today),
            ],
            default => [$today, $today],
        };

        return [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'start' => $from,
            'end' => $to->endOfDay(),
            'preset' => $preset,
        ];
    }
}

<?php

namespace App\Services\Numbering;

use App\Models\NumberingSequence;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class NumberingService
{
    /**
     * Generate the next document number for a given doc type.
     *
     * Reads format & reset_period from settings:
     *   numbering.{docType}.format
     *   numbering.{docType}.reset_period  (yearly | monthly | never)
     *
     * @param  array<string, mixed>  $context  Extra tokens, e.g. ['cust' => 361]
     */
    public function next(string $docType, array $context = []): string
    {
        $format = setting("numbering.{$docType}.format");
        $resetPeriod = setting("numbering.{$docType}.reset_period", 'yearly');

        if (! is_string($format) || $format === '') {
            throw new RuntimeException("Numbering format for [{$docType}] is not configured.");
        }

        [$periodYear, $periodMonth] = $this->resolvePeriod($resetPeriod);

        return DB::transaction(function () use ($docType, $periodYear, $periodMonth, $format, $context): string {
            /** @var NumberingSequence $sequence */
            $sequence = NumberingSequence::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    [
                        'doc_type' => $docType,
                        'period_year' => $periodYear,
                        'period_month' => $periodMonth,
                    ],
                    ['last_number' => 0]
                );

            $sequence->increment('last_number');

            return $this->applyTokens($format, [
                'seq' => $sequence->last_number,
                'year' => now()->year,
                'month' => now()->month,
                'day' => now()->day,
                ...$context,
            ]);
        });
    }

    /**
     * Replace tokens in a format string.
     *
     * Tokens supported:
     *   {YY}, {YYYY}, {MM}, {DD}, {YYMM}
     *   {seq:Nd}, {cust:Nd}, {cat} or any custom from $context
     *
     * @param  array<string, mixed>  $context
     */
    public function applyTokens(string $format, array $context): string
    {
        $now = now();

        $result = strtr($format, [
            '{YY}' => $now->format('y'),
            '{YYYY}' => $now->format('Y'),
            '{MM}' => $now->format('m'),
            '{DD}' => $now->format('d'),
            '{YYMM}' => $now->format('ym'),
        ]);

        // Padded numeric tokens like {seq:04d}
        $result = preg_replace_callback(
            '/\{(\w+):(\d+)d\}/',
            function (array $matches) use ($context): string {
                $name = $matches[1];
                $pad = (int) $matches[2];
                $value = $context[$name] ?? null;
                if (! is_numeric($value)) {
                    return $matches[0];
                }

                return str_pad((string) (int) $value, $pad, '0', STR_PAD_LEFT);
            },
            $result
        ) ?? $result;

        // Simple non-padded tokens like {cat}
        $result = preg_replace_callback(
            '/\{(\w+)\}/',
            function (array $matches) use ($context): string {
                $value = $context[$matches[1]] ?? null;
                if ($value === null) {
                    return $matches[0];
                }

                return (string) $value;
            },
            $result
        ) ?? $result;

        return $result;
    }

    /**
     * Resolve current period year and month based on reset_period setting.
     *
     * @return array{0:int,1:?int}
     */
    private function resolvePeriod(string $resetPeriod): array
    {
        return match ($resetPeriod) {
            'yearly' => [now()->year, null],
            'monthly' => [now()->year, now()->month],
            'never' => [0, null],
            default => throw new InvalidArgumentException("Unknown reset_period [{$resetPeriod}]"),
        };
    }
}

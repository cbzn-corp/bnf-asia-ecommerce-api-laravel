<?php

declare(strict_types=1);

namespace App\Support\Analytics;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class AnalyticsPeriod
{
    private const MAX_RANGE_DAYS = 366;

    /**
     * @param  array{days?: int|null, from?: string|null, to?: string|null}  $options
     * @return array{
     *     since: \DateTimeImmutable,
     *     until: \DateTimeImmutable,
     *     periodDays: int,
     *     periodFrom: string,
     *     periodTo: string
     * }
     */
    public static function resolve(array $options): array
    {
        if (! empty($options['from']) || ! empty($options['to'])) {
            if (empty($options['from']) || empty($options['to'])) {
                throw new BadRequestHttpException('Both from and to dates are required for a custom range.');
            }

            $since = self::parseDateStart((string) $options['from']);
            $until = self::parseDateEnd((string) $options['to']);

            if ($since > $until) {
                throw new BadRequestHttpException('The from date must be on or before the to date.');
            }

            $periodDays = self::daySpanInclusive($since, $until);
            if ($periodDays > self::MAX_RANGE_DAYS) {
                throw new BadRequestHttpException('Date range cannot exceed '.self::MAX_RANGE_DAYS.' days.');
            }

            return [
                'since' => $since,
                'until' => $until,
                'periodDays' => $periodDays,
                'periodFrom' => $options['from'],
                'periodTo' => $options['to'],
            ];
        }

        $days = $options['days'] ?? 30;
        if (! is_numeric($days) || $days < 1 || $days > self::MAX_RANGE_DAYS) {
            throw new BadRequestHttpException('days must be between 1 and '.self::MAX_RANGE_DAYS.'.');
        }

        $until = new \DateTimeImmutable('today 23:59:59');
        $since = (new \DateTimeImmutable('today'))->modify('-'.(int) $days.' days')->setTime(0, 0, 0);

        return [
            'since' => $since,
            'until' => $until,
            'periodDays' => (int) $days,
            'periodFrom' => self::toIsoDate($since),
            'periodTo' => self::toIsoDate($until),
        ];
    }

    private static function parseDateStart(string $isoDate): \DateTimeImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $isoDate)) {
            throw new BadRequestHttpException('Invalid from date. Use YYYY-MM-DD.');
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $isoDate.' 00:00:00');
        if ($date === false) {
            throw new BadRequestHttpException('Invalid from date.');
        }

        return $date;
    }

    private static function parseDateEnd(string $isoDate): \DateTimeImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $isoDate)) {
            throw new BadRequestHttpException('Invalid to date. Use YYYY-MM-DD.');
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $isoDate.' 23:59:59');
        if ($date === false) {
            throw new BadRequestHttpException('Invalid to date.');
        }

        return $date;
    }

    private static function toIsoDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    private static function daySpanInclusive(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        $start = new \DateTimeImmutable($from->format('Y-m-d'));
        $end = new \DateTimeImmutable($to->format('Y-m-d'));

        return (int) $start->diff($end)->days + 1;
    }
}

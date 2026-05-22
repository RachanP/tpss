<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

class ThaiDate
{
    public static function parseToIso(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            return self::isoFromParts((int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $value, $matches)) {
            return self::isoFromDisplayParts((int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $value, $matches)) {
            return self::isoFromDisplayParts((int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        return null;
    }

    public static function formatForInput(DateTimeInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        if ($date instanceof DateTimeInterface) {
            return self::formatDisplayDate(Carbon::instance($date));
        }

        $value = trim((string) $date);
        $iso = self::parseToIso($value);
        if (! $iso) {
            return $value;
        }

        return self::formatDisplayDate(Carbon::createFromFormat('!Y-m-d', $iso));
    }

    public static function formatDateTimeThai(DateTimeInterface|string|null $dateTime): string
    {
        if ($dateTime === null || $dateTime === '') {
            return '';
        }

        try {
            $date = $dateTime instanceof DateTimeInterface
                ? Carbon::instance($dateTime)
                : Carbon::parse((string) $dateTime);
        } catch (\Throwable) {
            return trim((string) $dateTime);
        }

        return self::formatDisplayDate($date) . ' ' . $date->format('H:i:s');
    }

    private static function isoFromDisplayParts(int $day, int $month, int $year): ?string
    {
        if ($year >= 2400) {
            $year -= 543;
        } elseif ($year < 1900 || $year > 2100) {
            return null;
        }

        return self::isoFromParts($year, $month, $day);
    }

    private static function isoFromParts(int $year, int $month, int $day): ?string
    {
        if ($year < 1900 || $year > 2100 || ! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private static function formatDisplayDate(Carbon $date): string
    {
        return $date->format('d/m/') . ($date->year + 543);
    }
}

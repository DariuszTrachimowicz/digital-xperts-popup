<?php

declare(strict_types=1);

namespace DigitalXperts\Popup;

defined('ABSPATH') || exit;

/**
 * Daty „od” i „do” popupu i paska. Zapis w formacie pola datetime-local (2026-11-27T00:00) w strefie czasowej strony.
 * Serwer pomija tylko wygasłe; resztę sprawdza przeglądarka z data-start i data-end, bo pamięć podręczna stron
 * trzyma HTML z chwili zapisu i bez tego pasek zaplanowany na jutro nie pojawiłby się do czyszczenia pamięci.
 */
final class Schedule
{
    public static function clean(string $value): string
    {
        $value = trim($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value) === 1 && self::timestamp($value) > 0 ? $value : '';
    }

    public static function timestamp(string $value): int
    {
        if ($value === '') {
            return 0;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value, wp_timezone());
        // createFromFormat przyjmuje 2026-02-31 jako 3 marca; taka data jest błędem, nie terminem.
        return $date && $date->format('Y-m-d\TH:i') === $value ? $date->getTimestamp() : 0;
    }

    public static function ended(array $settings): bool
    {
        $end = self::timestamp((string) ($settings['end'] ?? ''));
        return $end > 0 && $end <= time();
    }

    public static function attributes(array $settings): string
    {
        return 'data-start="' . self::timestamp((string) ($settings['start'] ?? '')) . '" data-end="' . self::timestamp((string) ($settings['end'] ?? '')) . '"';
    }

    /** @return array{0:string,1:string} Stan do kolorowego komunikatu w panelu: on, warn albo off, i jego opis. */
    public static function state(array $settings, string $name, bool $hasContent): array
    {
        if (empty($settings['enabled'])) {
            return array('off', $name . ' jest wyłączony. Odwiedzający go nie widzą.');
        }
        if (! $hasContent) {
            return array('warn', $name . ' jest włączony, ale nie ma treści, więc się nie pokaże.');
        }
        if (self::ended($settings)) {
            return array('warn', $name . ' jest włączony, ale data „do” już minęła.');
        }
        $start = self::timestamp((string) ($settings['start'] ?? ''));
        if ($start > time()) {
            return array('warn', $name . ' jest włączony i pokaże się od ' . wp_date('j.m.Y, H:i', $start) . '.');
        }
        return array('on', $name . ' jest włączony. Odwiedzający go widzą.');
    }
}

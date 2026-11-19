<?php

declare(strict_types=1);

namespace DigitalXperts\Popup;

defined('ABSPATH') || exit;

/** Biblioteka zapisanych ustawień: wspólna dla popupu (asm_popup_library) i paska (dxp_bar_library). */
final class Library
{
    private const LIMIT = 50;

    /** @return array<string,array{name:string,saved:int,settings:array<string,mixed>}> */
    public static function all(string $option): array
    {
        $library = get_option($option, array());
        return is_array($library) ? $library : array();
    }

    // Bez pól enabled, start i end: wczytany wpis nie włącza się sam i bierze daty obecnej kampanii.
    public static function remember(string $option, string $name, array $settings): bool
    {
        $name = sanitize_text_field($name);
        $library = self::all($option);
        if ($name === '' || count($library) >= self::LIMIT) {
            return false;
        }
        unset($settings['enabled'], $settings['start'], $settings['end']);
        $library[bin2hex(random_bytes(6))] = array('name' => mb_substr($name, 0, 80), 'saved' => time(), 'settings' => $settings);
        return update_option($option, $library, false);
    }

    /** @param array<string,array{name:string,settings:array<string,mixed>}> $templates @return array<string,mixed>|null */
    public static function recall(string $option, string $id, array $templates): ?array
    {
        if (str_starts_with($id, 'tpl:')) {
            return $templates[substr($id, 4)]['settings'] ?? null;
        }
        $entry = self::all($option)[$id] ?? null;
        return is_array($entry) && is_array($entry['settings'] ?? null) ? $entry['settings'] : null;
    }

    public static function forget(string $option, string $id): bool
    {
        $library = self::all($option);
        if (! isset($library[$id])) {
            return false;
        }
        unset($library[$id]);
        return update_option($option, $library, false);
    }
}

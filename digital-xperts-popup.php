<?php
/**
 * Plugin Name: Digital Xperts Popup
 * Plugin URI: https://github.com/DariuszTrachimowicz/digital-xperts-popup
 * Description: Popup i pasek informacyjny dla odwiedzających: gotowe style i okazje, biblioteka popupów, daty wyświetlania i podgląd przed włączeniem.
 * Version: 1.0.0
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Author: Dariusz Trachimowicz
 * Author URI: https://d-x.pl/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: digital-xperts-popup
 * Update URI: https://d-x.pl/wtyczki/popup/
 */

// Popup wydzielony z Menedżera Strony AI 2.8.0 (tam od 2.4) i pasek informacyjny. Ustawienia popupu zostają
// w opcjach asm_popup i asm_popup_library, więc strony po przenosinach mają ten sam popup bez migracji.

namespace DigitalXperts\Popup;

defined('ABSPATH') || exit;

const VERSION = '1.0.0';
const SLUG = 'digital-xperts-popup';

\define('DXP_VERSION', VERSION);
\define('DXP_PLUGIN_FILE', __FILE__);

require_once __DIR__ . '/includes/Schedule.php';
require_once __DIR__ . '/includes/Popup.php';
require_once __DIR__ . '/includes/Bar.php';
require_once __DIR__ . '/includes/Admin.php';
require_once __DIR__ . '/includes/Updater.php';

// Menedżer Strony AI do 2.8.0 sam wyświetla popup. Wtedy ta wtyczka pokazuje tylko pasek, żeby popup nie wyszedł dwa razy.
function legacy_popup(): bool
{
    return class_exists('ASM\Popup\Popup');
}

add_action('plugins_loaded', static function (): void {
    if (! legacy_popup()) {
        (new Popup())->hooks();
    }
    (new Bar())->hooks();
    Updater::hooks();
    if (is_admin()) {
        (new Admin())->hooks();
    }
});

// Skrypty popupu i paska muszą działać od razu: LiteSpeed i podobne optymalizatory opóźniają JS do pierwszej interakcji.
add_filter('script_loader_tag', static function (string $tag, string $handle): string {
    return in_array($handle, array('asm-popup', 'dxp-bar'), true)
        ? str_replace('<script ', '<script data-no-optimize="1" data-no-defer="1" data-cfasync="false" ', $tag)
        : $tag;
}, 10, 2);

// Zapisane strony mają wbudowany popup i pasek (albo ich brak): bez czyszczenia odwiedzający widzą stary stan.
function purge_page_cache(): void
{
    if (has_action('litespeed_purge_all')) {
        do_action('litespeed_purge_all');
    }
    if (function_exists('w3tc_flush_all')) {
        w3tc_flush_all();
    }
    if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
    }
}

function asset_version(string $relative): string
{
    $path = plugin_dir_path(DXP_PLUGIN_FILE) . ltrim($relative, '/\\');
    return is_file($path) ? VERSION . '.' . filemtime($path) : VERSION;
}

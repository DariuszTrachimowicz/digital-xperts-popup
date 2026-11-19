<?php

declare(strict_types=1);

namespace DigitalXperts\Popup;

defined('ABSPATH') || exit;

/**
 * Pasek informacyjny: jedno zdanie i opcjonalny przycisk, na górze albo na dole strony.
 * Pasek na górze jest wypisany zaraz po <body> i widoczny bez czekania na skrypt, więc nie przesuwa treści
 * po wczytaniu strony. Skrypt tylko go usuwa, gdy odwiedzający go zamknął albo daty go wykluczają.
 */
final class Bar
{
    private const OPTION = 'dxp_bar';
    private bool $rendered = false;

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        return array(
            'enabled' => false,
            'message' => '',
            'button_text' => '',
            'button_url' => '',
            'position' => 'top',
            'sticky' => true,
            'style' => 'dark',
            'background' => '#111827',
            'text' => '#ffffff',
            'accent' => '#f59e0b',
            'dismiss_days' => 7,
            'start' => '',
            'end' => '',
        );
    }

    /** @return array<string,string> Style paska: własne kolory i gotowe style popupu (bez „Jak strona” i „Klasyczny”). */
    public static function styles(): array
    {
        return array('custom' => 'Własny') + array_diff_key(Popup::styles(), array('auto' => true, 'custom' => true, 'classic' => true));
    }

    /** @return array<string,mixed> */
    public static function settings(): array
    {
        $saved = get_option(self::OPTION, array());
        $defaults = self::defaults();
        return array_merge($defaults, array_intersect_key(is_array($saved) ? $saved : array(), $defaults));
    }

    /** @return array<string,mixed> */
    public static function save(array $data): array
    {
        $clean = self::clean($data);
        update_option(self::OPTION, $clean, true);
        purge_page_cache();
        return $clean;
    }

    /** @return array<string,mixed> */
    private static function clean(array $data): array
    {
        $defaults = self::defaults();
        $color = static fn(string $key): string => isset($data[$key]) && preg_match('/^#[0-9a-f]{6}$/i', (string) $data[$key])
            ? (string) $data[$key]
            : $defaults[$key];
        $style = (string) ($data['style'] ?? $defaults['style']);
        return array(
            'enabled' => ! empty($data['enabled']),
            'message' => mb_substr(sanitize_text_field((string) ($data['message'] ?? '')), 0, 300),
            'button_text' => mb_substr(sanitize_text_field((string) ($data['button_text'] ?? '')), 0, 60),
            'button_url' => esc_url_raw(trim((string) ($data['button_url'] ?? '')), array('http', 'https')),
            'position' => ($data['position'] ?? '') === 'bottom' ? 'bottom' : 'top',
            'sticky' => ! empty($data['sticky']),
            'style' => isset(self::styles()[$style]) ? $style : $defaults['style'],
            'background' => $color('background'),
            'text' => $color('text'),
            'accent' => $color('accent'),
            'dismiss_days' => max(0, min(365, (int) ($data['dismiss_days'] ?? 7))),
            'start' => Schedule::clean((string) ($data['start'] ?? '')),
            'end' => Schedule::clean((string) ($data['end'] ?? '')),
        );
    }

    public static function preview(): bool
    {
        return isset($_GET['dxp_bar_preview']) && current_user_can('manage_options'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    public static function previewUrl(): string
    {
        return add_query_arg('dxp_bar_preview', '1', home_url('/'));
    }

    private static function visible(array $settings): bool
    {
        return self::preview() || (! empty($settings['enabled']) && trim((string) $settings['message']) !== '' && ! Schedule::ended($settings));
    }

    public function hooks(): void
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue'));
        add_filter('script_loader_tag', array($this, 'scriptData'), 10, 2);
        add_action('wp_body_open', array($this, 'renderTop'), 1);
        add_action('wp_footer', array($this, 'render'));
    }

    public function enqueue(): void
    {
        if (! self::visible(self::settings())) {
            return;
        }
        wp_enqueue_style('dxp-bar', plugins_url('assets/bar.css', DXP_PLUGIN_FILE), array(), asset_version('assets/bar.css'));
        // W nagłówku: skrypt chowa zamknięty pasek, zanim przeglądarka go narysuje.
        wp_enqueue_script('dxp-bar', plugins_url('assets/bar.js', DXP_PLUGIN_FILE), array(), asset_version('assets/bar.js'), false);
    }

    // Skrypt w nagłówku działa przed paskiem w treści, więc dane paska dostaje we własnych atrybutach.
    public function scriptData(string $tag, string $handle): string
    {
        return $handle === 'dxp-bar' ? str_replace('<script ', '<script ' . self::dataAttributes(self::settings()) . ' ', $tag) : $tag;
    }

    private static function dataAttributes(array $settings): string
    {
        $revision = substr(hash('sha256', $settings['message'] . '|' . $settings['button_text'] . '|' . $settings['button_url']), 0, 12);
        return 'data-revision="' . $revision . '" data-dismiss-days="' . (int) $settings['dismiss_days'] . '" '
            . 'data-preview="' . (self::preview() ? '1' : '0') . '" ' . Schedule::attributes($settings);
    }

    public function renderTop(): void
    {
        if (self::settings()['position'] === 'top') {
            $this->render();
        }
    }

    // Motyw bez wp_body_open dostaje pasek w stopce, a skrypt przenosi go na początek strony.
    public function render(): void
    {
        if ($this->rendered || is_admin()) {
            return;
        }
        $settings = self::settings();
        if (! self::visible($settings)) {
            return;
        }
        $this->rendered = true;
        if (trim((string) $settings['message']) === '') {
            $settings['message'] = 'To jest podgląd paska. Wpisz treść w ustawieniach wtyczki.';
        }
        echo self::renderMarkup($settings); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function renderMarkup(array $settings): string
    {
        $settings = array_merge(self::defaults(), $settings);
        $escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $style = isset(self::styles()[$settings['style']]) ? (string) $settings['style'] : 'dark';
        if ($style === 'custom') {
            $hex = static fn(string $key): string => preg_match('/^#[0-9a-f]{6}$/i', (string) $settings[$key]) ? (string) $settings[$key] : self::defaults()[$key];
            $preset = array('bg' => $hex('background'), 'text' => $hex('text'), 'accent' => $hex('accent'), 'accent-text' => $hex('background'));
        } else {
            $preset = Popup::preset($style);
        }
        $vars = array();
        foreach (array('bg', 'text', 'accent', 'accent-text', 'deco') as $name) {
            if (isset($preset[$name])) {
                $vars[] = '--dxp-bar-' . $name . ':' . $preset[$name];
            }
        }
        $url = (string) $settings['button_url'];
        $button = $settings['button_text'] !== '' && preg_match('~^https?://~i', $url)
            ? '<a class="dxp-bar__btn" href="' . $escape($url) . '">' . $escape($settings['button_text']) . '</a>'
            : '';
        $classes = 'dxp-bar dxp-bar--' . ($settings['position'] === 'bottom' ? 'bottom' : 'top')
            . (! empty($settings['sticky']) ? ' dxp-bar--sticky' : '') . (isset($preset['deco']) ? ' dxp-bar--deco' : '');

        return '<div id="dxp-bar" class="' . $classes . '" role="region" aria-label="Informacja" '
            . self::dataAttributes($settings) . ' style="' . $escape(implode(';', $vars)) . '">'
            . '<div class="dxp-bar__inner"><p class="dxp-bar__msg">' . $escape($settings['message']) . '</p>' . $button . '</div>'
            . '<button type="button" class="dxp-bar__x" aria-label="Zamknij pasek">&times;</button>'
            . '</div>';
    }
}

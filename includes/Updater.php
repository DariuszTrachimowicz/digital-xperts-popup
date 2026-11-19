<?php
// Aktualizacje z wydań GitHuba przez natywny mechanizm WordPressa (nagłówek Update URI), wzorzec: Social Meta.
// Wydanie ma tag v1.2.3 i załącznik digital-xperts-popup-1.2.3.zip z katalogiem digital-xperts-popup/.
// shortcut: tylko publiczne repozytorium, prywatne wymaga tokenu w żądaniach i pobieraniu.

declare(strict_types=1);

namespace DigitalXperts\Popup;

defined('ABSPATH') || exit;

final class Updater
{
    private const CACHE = 'dxp_update_release';
    private const REPO = 'DariuszTrachimowicz/digital-xperts-popup';

    public static function hooks(): void
    {
        // Nazwa filtra pochodzi z hosta w nagłówku Update URI pliku wtyczki.
        add_filter('update_plugins_d-x.pl', array(self::class, 'check'), 10, 3);
        // Część hostingów blokuje odświeżanie listy aktualizacji, więc wydanie dopisujemy też przy jej odczycie.
        add_filter('site_transient_update_plugins', array(self::class, 'inject'));
        // Ostatni priorytet: CMS Portal (motywy CMSSuperHeroes, priorytet 20) zwraca false dla obcych slugów.
        add_filter('plugins_api', array(self::class, 'info'), PHP_INT_MAX, 3);
    }

    /** @return array{version:string,package:string,url:string}|null */
    public static function release(bool $fresh = false, bool $cacheOnly = false): ?array
    {
        $cached = get_site_transient(self::CACHE);
        if (! $fresh && is_array($cached)) {
            return $cached ?: null;
        }
        if ($cacheOnly) {
            return null;
        }
        $release = array();
        // Adres releases/latest przekierowuje na tag najnowszego wydania; API GitHuba ma limit 60 zapytań na godzinę na IP.
        $reply = wp_remote_head('https://github.com/' . self::REPO . '/releases/latest', array('timeout' => 8, 'redirection' => 0));
        $location = is_wp_error($reply) ? '' : (string) wp_remote_retrieve_header($reply, 'location');
        if (preg_match('~^https://github\.com/' . preg_quote(self::REPO, '~') . '/releases/tag/v(\d+\.\d+\.\d+)$~D', $location, $match) === 1) {
            $release = array(
                'version' => $match[1],
                'package' => 'https://github.com/' . self::REPO . '/releases/download/v' . $match[1] . '/' . SLUG . '-' . $match[1] . '.zip',
                'url' => $location,
            );
        }
        set_site_transient(self::CACHE, $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS);
        return $release ?: null;
    }

    /** @param mixed $transient @return mixed */
    public static function inject($transient)
    {
        if (! is_object($transient)) {
            return $transient;
        }
        // Na froncie tylko pamięć podręczna: zapytanie do GitHuba robi panel albo cron.
        $release = self::release(false, ! is_admin() && ! wp_doing_cron());
        if (! $release) {
            return $transient;
        }
        $file = plugin_basename(DXP_PLUGIN_FILE);
        $item = (object) array(
            'id' => 'github.com/' . self::REPO,
            'slug' => SLUG,
            'plugin' => $file,
            'new_version' => $release['version'],
            'url' => $release['url'],
            'package' => $release['package'],
            'requires_php' => '8.0',
        );
        $response = isset($transient->response) && is_array($transient->response) ? $transient->response : array();
        $noUpdate = isset($transient->no_update) && is_array($transient->no_update) ? $transient->no_update : array();
        if (version_compare($release['version'], VERSION, '>')) {
            $response[$file] = $item;
            unset($noUpdate[$file]);
        } else {
            $noUpdate[$file] = $item;
            unset($response[$file]);
        }
        $transient->response = $response;
        $transient->no_update = $noUpdate;
        return $transient;
    }

    /** @param mixed $update @return mixed */
    public static function check($update, $pluginData, $pluginFile)
    {
        if ($pluginFile !== plugin_basename(DXP_PLUGIN_FILE)) {
            return $update;
        }
        $release = self::release();
        return $release ? array(
            'slug' => SLUG,
            'version' => $release['version'],
            'url' => $release['url'],
            'package' => $release['package'],
            'requires_php' => '8.0',
        ) : $update;
    }

    /** @param mixed $result @return mixed */
    public static function info($result, $action, $args)
    {
        if ($action !== 'plugin_information' || ! is_object($args) || ($args->slug ?? '') !== SLUG) {
            return $result;
        }
        $release = self::release();
        return (object) array(
            'name' => 'Digital Xperts Popup',
            'slug' => SLUG,
            'version' => $release['version'] ?? VERSION,
            'author' => '<a href="https://d-x.pl/">Dariusz Trachimowicz</a>',
            'homepage' => 'https://github.com/' . self::REPO,
            'requires' => '6.2',
            'tested' => '7.2',
            'requires_php' => '8.0',
            'last_updated' => '2026-11-19',
            'download_link' => $release['package'] ?? '',
            'sections' => self::sections((string) file_get_contents(dirname(DXP_PLUGIN_FILE) . '/readme.txt')),
        );
    }

    /** @param array<int,string> $links @return array<int,string> */
    public static function rowMeta(array $links, string $file): array
    {
        if ($file !== plugin_basename(DXP_PLUGIN_FILE)) {
            return $links;
        }
        // WordPress sam dodaje ten link, gdy aktualizator poda dane wydania; drugi byłby zdublowany.
        foreach ($links as $link) {
            if (str_contains((string) $link, 'plugin=' . SLUG . '&')) {
                return $links;
            }
        }
        $url = self_admin_url('plugin-install.php?tab=plugin-information&plugin=' . SLUG . '&TB_iframe=true&width=772&height=600');
        $links[] = '<a href="' . esc_url($url) . '" class="thickbox open-plugin-details-modal" aria-label="Więcej informacji o Digital Xperts Popup">Zobacz szczegóły</a>';
        return $links;
    }

    /** @return array<string,string> Sekcje okna „Zobacz szczegóły” z readme.txt. */
    private static function sections(string $text): array
    {
        $map = array('Description' => 'description', 'Installation' => 'installation', 'Frequently Asked Questions' => 'faq', 'Changelog' => 'changelog');
        $parts = preg_split('/^== (.+?) ==$/m', str_replace("\r\n", "\n", $text), -1, PREG_SPLIT_DELIM_CAPTURE) ?: array();
        $sections = array();
        for ($i = 1; $i + 1 < count($parts); $i += 2) {
            $key = $map[trim($parts[$i])] ?? null;
            if ($key) {
                $sections[$key] = self::html($parts[$i + 1]);
            }
        }
        return $sections;
    }

    // Podzbiór składni readme WordPressa: nagłówki = x =, listy, kod i adresy.
    private static function html(string $text): string
    {
        $html = '';
        $list = '';
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if (preg_match('/^(?:(\d+)\. |[*-] )(.*)$/', $line, $m)) {
                $kind = $m[1] !== '' ? 'ol' : 'ul';
                if ($list !== $kind) {
                    $html .= ($list !== '' ? '</' . $list . '>' : '') . '<' . $kind . '>';
                    $list = $kind;
                }
                $html .= '<li>' . self::inline($m[2]) . '</li>';
                continue;
            }
            if ($list !== '') {
                $html .= '</' . $list . '>';
                $list = '';
            }
            if (preg_match('/^= (.+) =$/', $line, $m)) {
                $html .= '<h4>' . esc_html($m[1]) . '</h4>';
            } elseif ($line !== '') {
                $html .= '<p>' . self::inline($line) . '</p>';
            }
        }
        return $html . ($list !== '' ? '</' . $list . '>' : '');
    }

    private static function inline(string $text): string
    {
        $text = (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', esc_html($text));
        return (string) preg_replace('~https?://[^\s<]+[^\s<.,)]~', '<a href="$0">$0</a>', $text);
    }
}

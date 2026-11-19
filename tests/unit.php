<?php
// Uruchom: php -d zend.assertions=1 tests/unit.php
declare(strict_types=1);
if (ini_get('zend.assertions') !== '1') { fwrite(STDERR, "Uruchom z -d zend.assertions=1.\n"); exit(1); }
define('ABSPATH', __DIR__ . '/');
$GLOBALS['options'] = array();
$GLOBALS['autoload'] = array();
function get_option($k, $d = false) { return $GLOBALS['options'][$k] ?? $d; }
function update_option($k, $v, $a = null) { $GLOBALS['options'][$k] = $v; $GLOBALS['autoload'][$k] = $a; return true; }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function wp_kses_post($v) { return (string) $v; }
function esc_url_raw($v, $p = null) { return preg_match('~^https?://~', (string) $v) ? (string) $v : ''; }
function current_user_can($c) { return false; }
function has_action($hook) { return false; }
function add_action(...$a) {}
function add_filter(...$a) {}
function wp_timezone() { return new DateTimeZone('Europe/Warsaw'); }
require __DIR__ . '/../digital-xperts-popup.php';
use DigitalXperts\Popup\Bar;
use DigitalXperts\Popup\Popup;
use DigitalXperts\Popup\Schedule;

// Popup: trzy style podstawowe, 10 gotowych i 7 okazji.
$styles = Popup::styles();
assert(count($styles) === 20 && isset($styles['black-friday'], $styles['auto'], $styles['classic'], $styles['custom']));
assert(count(array_filter(Popup::swatches(), static fn($s) => $s['group'] === 'styl')) === 10);
assert(count(Popup::templates()) === 7);

// Zapisane ustawienia bez pola style zostają przy wyglądzie klasycznym, nieznany styl też.
$GLOBALS['options']['asm_popup'] = array('enabled' => true, 'title' => 'T', 'message' => 'M');
assert(Popup::settings()['style'] === 'classic' && Popup::settings()['start'] === '');
assert(Popup::save(array('style' => 'nie-ma-takiego', 'message' => 'M'))['style'] === 'classic');
assert($GLOBALS['autoload']['asm_popup'] === true);

// Gotowy styl wypisuje swoje zmienne CSS, a znaki specjalne są zakodowane w atrybucie.
$markup = Popup::renderMarkup(array('style' => 'elegant', 'title' => 'A', 'message' => 'B', 'button_text' => 'C'));
assert(str_contains($markup, 'asm-popup--elegant') && str_contains($markup, '--asm-popup-heading-font:Georgia,&quot;Times New Roman&quot;,serif'));
assert(str_contains($markup, 'data-start="0" data-end="0"'));

// Szablon okazji ma treść i styl o tej samej nazwie.
foreach (Popup::templates() as $key => $template) {
    assert(isset($styles[$template['settings']['style']]) && Popup::recall('tpl:' . $key) === $template['settings'], $key);
}

// Biblioteka: zapis bez pola enabled i bez dat, odczyt, usunięcie, pusta nazwa odrzucona.
assert(Popup::remember('', array('message' => 'x')) === false);
assert(Popup::remember('Zima <b>2026</b>', array('enabled' => true, 'message' => 'Tekst', 'style' => 'dark', 'start' => '2026-12-01T00:00')) === true);
$library = Popup::library();
$id = array_key_first($library);
assert($library[$id]['name'] === 'Zima 2026' && ! array_key_exists('enabled', $library[$id]['settings']) && ! array_key_exists('start', $library[$id]['settings']));
assert(Popup::forget($id) === true && Popup::library() === array());

// Daty: format pola datetime-local w strefie strony; błędne i nieistniejące daty są odrzucane.
assert(Schedule::clean('2026-11-27T00:00') === '2026-11-27T00:00');
assert(Schedule::clean('2026-02-31T10:00') === '' && Schedule::clean('jutro') === '' && Schedule::clean('2026-11-27 00:00') === '');
assert(Schedule::timestamp('2026-11-27T00:00') === (new DateTimeImmutable('2026-11-26T23:00:00Z'))->getTimestamp());
assert(Schedule::ended(array('end' => '2020-01-01T00:00')) && ! Schedule::ended(array('end' => '2099-01-01T00:00')) && ! Schedule::ended(array('end' => '')));

// Popup po dacie „do” nie jest wypisywany, przed datą „od” jest (decyduje przeglądarka, bo strony są w pamięci podręcznej).
$visible = new ReflectionMethod(Popup::class, 'visible');
assert($visible->invoke(null, array('enabled' => true, 'message' => 'x', 'end' => '2020-01-01T00:00')) === false);
assert($visible->invoke(null, array('enabled' => true, 'message' => 'x', 'start' => '2099-01-01T00:00')) === true);

// Pasek: domyślnie wyłączony, ciemny, na górze i przyklejony; style bez „Jak strona” i „Klasyczny”.
$bar = Bar::settings();
assert($bar['enabled'] === false && $bar['style'] === 'dark' && $bar['position'] === 'top' && $bar['sticky'] === true);
assert(isset(Bar::styles()['custom'], Bar::styles()['black-friday']) && ! isset(Bar::styles()['auto']) && ! isset(Bar::styles()['classic']));

// Zapis paska czyści dane: nieznane miejsce, styl i kolor wracają do domyślnych, treść bez HTML i przycięta.
$saved = Bar::save(array('enabled' => '1', 'message' => '<b>Dostawa</b> ' . str_repeat('x', 400), 'position' => 'side', 'style' => 'auto', 'background' => 'red', 'button_url' => 'javascript:alert(1)', 'dismiss_days' => 999, 'end' => '2026-13-01T00:00'));
assert($saved['enabled'] === true && str_starts_with($saved['message'], 'Dostawa x') && mb_strlen($saved['message']) === 300);
assert($saved['position'] === 'top' && $saved['style'] === 'dark' && $saved['background'] === '#111827' && $saved['button_url'] === '' && $saved['dismiss_days'] === 365 && $saved['end'] === '');
assert($GLOBALS['autoload']['dxp_bar'] === true);

// Znacznik paska: treść zakodowana, przycisk tylko z tekstem i adresem http(s), zmienne stylu i okazji.
$html = Bar::renderMarkup(array('message' => 'A & <b>B</b>', 'button_text' => 'Zobacz', 'button_url' => 'https://example.com/?a=1&b=2', 'style' => 'boze-narodzenie', 'position' => 'bottom', 'sticky' => false));
assert(str_contains($html, 'A &amp; &lt;b&gt;B&lt;/b&gt;') && str_contains($html, 'href="https://example.com/?a=1&amp;b=2"'));
assert(str_contains($html, 'dxp-bar--bottom') && ! str_contains($html, 'dxp-bar--sticky') && str_contains($html, 'dxp-bar--deco') && str_contains($html, '--dxp-bar-bg:linear-gradient'));
assert(! str_contains(Bar::renderMarkup(array('message' => 'x', 'button_text' => 'Zobacz', 'button_url' => '')), 'dxp-bar__btn'));
$custom = Bar::renderMarkup(array('message' => 'x', 'style' => 'custom', 'background' => '#ffffff', 'text' => '#000000', 'accent' => '#ff0000'));
assert(str_contains($custom, '--dxp-bar-bg:#ffffff;--dxp-bar-text:#000000;--dxp-bar-accent:#ff0000;--dxp-bar-accent-text:#ffffff'));

// Nowa treść zmienia identyfikator zamknięcia, więc pasek wraca do osób, które zamknęły poprzedni.
$revision = static fn(string $message): string => preg_match('/data-revision="([0-9a-f]{12})"/', Bar::renderMarkup(array('message' => $message)), $m) ? $m[1] : '';
assert($revision('Pierwsza') !== $revision('Druga') && $revision('Pierwsza') === $revision('Pierwsza'));
echo "OK\n";

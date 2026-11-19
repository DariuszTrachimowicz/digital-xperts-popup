<?php

declare(strict_types=1);

namespace DigitalXperts\Popup;

defined('ABSPATH') || exit;

// Panel „Popup i pasek”. Ekran popupu przeniesiony z Menedżera Strony AI 2.8.0 bez zmian w działaniu.
final class Admin
{
    private const PAGE = 'digital-xperts-popup';
    private const NONCE = 'dxp_admin';
    private const MESSAGES = array(
        'popup_saved' => 'Zapisano ustawienia popupu.',
        'library_remembered' => 'Zapisano w bibliotece.',
        'library_loaded' => 'Wczytano. Sprawdź treść i zapisz, a potem obejrzyj podgląd.',
        'library_forgotten' => 'Usunięto z biblioteki.',
        'library_failed' => 'Nie udało się wykonać operacji na bibliotece. Podaj nazwę albo usuń starsze wpisy (limit 50).',
        'bar_saved' => 'Zapisano ustawienia paska.',
    );

    public function hooks(): void
    {
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_enqueue_scripts', array($this, 'assets'));
        add_action('admin_post_dxp_save_popup', array($this, 'savePopup'));
        add_action('admin_post_dxp_library', array($this, 'library'));
        add_action('admin_post_dxp_save_bar', array($this, 'saveBar'));
        add_action('wp_ajax_dxp_toggle', array($this, 'toggle'));
        add_filter('plugin_action_links_' . plugin_basename(DXP_PLUGIN_FILE), array($this, 'actionLinks'));
        add_filter('plugin_row_meta', array(Updater::class, 'rowMeta'), 10, 2);
    }

    public function menu(): void
    {
        add_menu_page('Popup i pasek', 'Popup i pasek', 'manage_options', self::PAGE, array($this, 'render'), 'dashicons-megaphone', 4);
    }

    public function assets(string $hook): void
    {
        if ($hook !== 'toplevel_page_' . self::PAGE) {
            return;
        }
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_style('dxp-admin', plugins_url('assets/admin.css', DXP_PLUGIN_FILE), array(), asset_version('assets/admin.css'));
        wp_enqueue_script('dxp-admin', plugins_url('assets/admin.js', DXP_PLUGIN_FILE), array('wp-color-picker'), asset_version('assets/admin.js'), true);
    }

    public function actionLinks(array $links): array
    {
        array_unshift($links, '<a href="' . esc_url(self::url('popup')) . '">Ustawienia</a>');
        return $links;
    }

    public static function url(string $tab): string
    {
        return admin_url('admin.php?page=' . self::PAGE . '&tab=' . $tab);
    }

    public function render(): void
    {
        $tab = isset($_GET['tab']) && $_GET['tab'] === 'bar' ? 'bar' : 'popup'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $message = sanitize_key(wp_unslash((string) ($_GET['dxp_message'] ?? ''))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ?>
        <div class="wrap dxp-admin">
            <header class="dxp-header">
                <div>
                    <h1>Popup i pasek <span class="dxp-version"><?php echo esc_html(VERSION); ?></span></h1>
                    <p>Okno z komunikatem i pasek informacyjny dla odwiedzających stronę.</p>
                </div>
            </header>
            <?php if (isset(self::MESSAGES[$message])) : ?>
                <div class="notice notice-success"><p><?php echo esc_html(self::MESSAGES[$message]); ?></p></div>
            <?php endif; ?>
            <nav class="dxp-tabs" aria-label="Sekcje">
                <?php foreach (array('popup' => 'Popup', 'bar' => 'Pasek informacyjny') as $key => $label) : ?>
                    <a href="<?php echo esc_url(self::url($key)); ?>"<?php echo $tab === $key ? ' aria-current="page"' : ''; ?>><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>
            <?php $tab === 'bar' ? $this->bar() : $this->popup(); ?>
        </div>
        <?php
    }

    public function savePopup(): void
    {
        $this->guard();
        Popup::save(array(
            'enabled' => ! empty($_POST['popup_enabled']),
            'title' => wp_unslash((string) ($_POST['popup_title'] ?? '')),
            'message' => wp_unslash((string) ($_POST['popup_message'] ?? '')),
            'button_text' => wp_unslash((string) ($_POST['popup_button'] ?? '')),
            'button_url' => wp_unslash((string) ($_POST['popup_button_url'] ?? '')),
            'accent' => wp_unslash((string) ($_POST['popup_accent'] ?? '')),
            'dismiss_days' => (int) ($_POST['popup_days'] ?? 7),
            'style' => sanitize_key(wp_unslash((string) ($_POST['popup_style'] ?? ''))),
            'background' => wp_unslash((string) ($_POST['popup_background'] ?? '')),
            'text' => wp_unslash((string) ($_POST['popup_text'] ?? '')),
            'radius' => (int) ($_POST['popup_radius'] ?? 12),
            'custom_css' => wp_unslash((string) ($_POST['popup_css'] ?? '')),
            'start' => wp_unslash((string) ($_POST['popup_start'] ?? '')),
            'end' => wp_unslash((string) ($_POST['popup_end'] ?? '')),
        ));
        $this->redirect('popup_saved', 'popup');
    }

    public function saveBar(): void
    {
        $this->guard();
        Bar::save(array(
            'enabled' => ! empty($_POST['bar_enabled']),
            'message' => wp_unslash((string) ($_POST['bar_message'] ?? '')),
            'button_text' => wp_unslash((string) ($_POST['bar_button'] ?? '')),
            'button_url' => wp_unslash((string) ($_POST['bar_button_url'] ?? '')),
            'position' => sanitize_key(wp_unslash((string) ($_POST['bar_position'] ?? ''))),
            'sticky' => ! empty($_POST['bar_sticky']),
            'style' => sanitize_key(wp_unslash((string) ($_POST['bar_style'] ?? ''))),
            'background' => wp_unslash((string) ($_POST['bar_background'] ?? '')),
            'text' => wp_unslash((string) ($_POST['bar_text'] ?? '')),
            'accent' => wp_unslash((string) ($_POST['bar_accent'] ?? '')),
            'dismiss_days' => (int) ($_POST['bar_days'] ?? 7),
            'start' => wp_unslash((string) ($_POST['bar_start'] ?? '')),
            'end' => wp_unslash((string) ($_POST['bar_end'] ?? '')),
        ));
        $this->redirect('bar_saved', 'bar');
    }

    // Przełącznik „Pokazuj” zapisuje się od razu. Zmienia tylko pole enabled, reszta ustawień zostaje.
    public function toggle(): void
    {
        if (! current_user_can('manage_options') || ! check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Brak dostępu. Odśwież stronę i spróbuj ponownie.'), 403);
        }
        $enabled = ($_POST['enabled'] ?? '') === '1';
        $bar = ($_POST['part'] ?? '') === 'bar';
        $saved = $bar
            ? Bar::save(array_merge(Bar::settings(), array('enabled' => $enabled)))
            : Popup::save(array_merge(Popup::settings(), array('enabled' => $enabled)));
        if ((bool) $saved['enabled'] !== $enabled) {
            wp_send_json_error(array('message' => 'Nie udało się zapisać. Spróbuj ponownie.'), 500);
        }
        list($state, $text) = self::state($saved, $bar);
        wp_send_json_success(array('enabled' => $enabled, 'state' => $state, 'message' => 'Zapisano. ' . $text));
    }

    /** @return array{0:string,1:string} */
    private static function state(array $settings, bool $bar): array
    {
        return Schedule::state($settings, $bar ? 'Pasek' : 'Popup', trim((string) $settings['message']) !== '');
    }

    // Biblioteka popupu albo paska (pole library_part): zapis obecnych ustawień, wczytanie wpisu lub szablonu, usunięcie.
    public function library(): void
    {
        $this->guard();
        $tab = ($_POST['library_part'] ?? '') === 'bar' ? 'bar' : 'popup';
        $class = $tab === 'bar' ? Bar::class : Popup::class;
        $operation = sanitize_key(wp_unslash((string) ($_POST['library_action'] ?? '')));
        $id = sanitize_text_field(wp_unslash((string) ($_POST['library_id'] ?? '')));
        $current = $class::settings();
        if ($operation === 'save') {
            $ok = $class::remember(wp_unslash((string) ($_POST['library_name'] ?? '')), $current);
            $this->redirect($ok ? 'library_remembered' : 'library_failed', $tab);
        }
        if ($operation === 'load') {
            $settings = $class::recall($id);
            if ($settings !== null) {
                // Wczytanie nie zmienia tego, czy popup albo pasek jest włączony, ani dat.
                $class::save(array_merge($current, $settings, array('enabled' => ! empty($current['enabled']))));
            }
            $this->redirect($settings !== null ? 'library_loaded' : 'library_failed', $tab);
        }
        $this->redirect($operation === 'delete' && $class::forget($id) ? 'library_forgotten' : 'library_failed', $tab);
    }

    private function toggleField(string $part, bool $enabled, array $settings, string $label): void
    {
        list($state, $text) = self::state($settings, $part === 'bar');
        ?>
        <label class="dxp-check"><input type="checkbox" name="<?php echo esc_attr($part); ?>_enabled" value="1" data-toggle="<?php echo esc_attr($part); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce(self::NONCE)); ?>" data-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" <?php checked($enabled); ?>> <?php echo esc_html($label); ?></label>
        <p class="dxp-state is-<?php echo esc_attr($state); ?>" data-toggle-status role="status" aria-live="polite"><?php echo esc_html($text); ?></p>
        <?php
    }

    private function datesFields(string $part, array $settings, string $what): void
    {
        ?>
        <div class="dxp-colors">
            <div><label for="dxp-<?php echo esc_attr($part); ?>-start">Pokazuj od (opcjonalnie)</label><input id="dxp-<?php echo esc_attr($part); ?>-start" type="datetime-local" name="<?php echo esc_attr($part); ?>_start" value="<?php echo esc_attr($settings['start']); ?>"></div>
            <div><label for="dxp-<?php echo esc_attr($part); ?>-end">Pokazuj do (opcjonalnie)</label><input id="dxp-<?php echo esc_attr($part); ?>-end" type="datetime-local" name="<?php echo esc_attr($part); ?>_end" value="<?php echo esc_attr($settings['end']); ?>"></div>
        </div>
        <p class="description"><?php echo esc_html('Bez dat ' . $what . ' działa, dopóki go nie wyłączysz. Czas strony: ' . wp_timezone_string() . '.'); ?></p>
        <?php
    }

    private function styleField(string $name, string $current, array $keys, array $labels): void
    {
        $swatches = Popup::swatches();
        $groups = array('Podstawowe' => array(), 'Gotowe style' => array(), 'Okazje' => array());
        foreach ($keys as $key) {
            $group = isset($swatches[$key]) ? ($swatches[$key]['group'] === 'okazja' ? 'Okazje' : 'Gotowe style') : 'Podstawowe';
            $groups[$group][] = $key;
        }
        ?>
        <fieldset class="dxp-styles">
            <legend>Styl</legend>
            <?php foreach (array_filter($groups) as $group => $groupKeys) : ?>
                <p class="dxp-styles__group"><?php echo esc_html($group); ?></p>
                <div class="dxp-styles__grid">
                <?php foreach ($groupKeys as $key) :
                    $swatch = $swatches[$key] ?? null; ?>
                    <label class="dxp-style">
                        <input type="radio" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($key); ?>" <?php checked($current, $key); ?>>
                        <span class="dxp-style__swatch"<?php echo $swatch ? ' style="background:' . esc_attr($swatch['bg']) . ';color:' . esc_attr($swatch['text']) . '"' : ''; ?>>Aa<i<?php echo $swatch ? ' style="background:' . esc_attr($swatch['accent']) . '"' : ''; ?>></i></span>
                        <span class="dxp-style__name"><?php echo esc_html($labels[$key]); ?></span>
                    </label>
                <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </fieldset>
        <?php
    }

    private function popup(): void
    {
        if (legacy_popup()) {
            echo '<section class="dxp-surface"><h2>Popup działa jeszcze w Menedżerze Strony AI</h2><p>Zaktualizuj Menedżera Strony AI do wersji 2.9.0 lub nowszej. Wtedy popup przejdzie tutaj razem z treścią, wyglądem i biblioteką. Do tego czasu ustawiasz go w Menedżerze Strony AI, w zakładce Popup.</p></section>';
            return;
        }
        $popup = Popup::settings();
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="dxp-grid dxp-grid--wide">
            <?php wp_nonce_field(self::NONCE); ?>
            <input type="hidden" name="action" value="dxp_save_popup">
            <section class="dxp-surface">
                <h2>Treść popupu</h2>
                <?php $this->toggleField('popup', ! empty($popup['enabled']), $popup, 'Pokazuj popup odwiedzającym'); ?>
                <p class="description">Ten przełącznik zapisuje się od razu. Treść i wygląd zapisujesz przyciskiem „Zapisz popup”.</p>
                <label for="dxp-popup-title-field">Tytuł</label>
                <input id="dxp-popup-title-field" name="popup_title" value="<?php echo esc_attr($popup['title']); ?>">
                <label for="dxp_popup_message">Treść</label>
                <?php wp_editor((string) $popup['message'], 'dxp_popup_message', array('textarea_name' => 'popup_message', 'teeny' => true, 'media_buttons' => false, 'textarea_rows' => 8)); ?>
                <label for="dxp-popup-button-field">Tekst przycisku</label>
                <input id="dxp-popup-button-field" name="popup_button" value="<?php echo esc_attr($popup['button_text']); ?>">
                <label for="dxp-popup-url-field">Adres po kliknięciu przycisku (opcjonalnie)</label>
                <input id="dxp-popup-url-field" type="url" name="popup_button_url" placeholder="https://" value="<?php echo esc_attr($popup['button_url']); ?>">
                <p class="description">Bez adresu przycisk tylko zamyka okno.</p>
                <label for="dxp-popup-days-field">Po zamknięciu nie pokazuj przez (dni)</label>
                <input id="dxp-popup-days-field" class="dxp-short" type="number" min="0" max="365" name="popup_days" value="<?php echo (int) $popup['dismiss_days']; ?>">
                <p class="description">0 oznacza, że popup pokaże się przy każdej wizycie.</p>
                <?php $this->datesFields('popup', $popup, 'popup'); ?>
                <p class="description">Popup pojawia się od razu po wejściu na stronę, także przed zamknięciem banera cookies.</p>
            </section>
            <section class="dxp-surface">
                <h2>Wygląd</h2>
                <?php $this->styleField('popup_style', (string) $popup['style'], array_keys(Popup::styles()), Popup::styles()); ?>
                <p class="description">„Jak strona” bierze czcionki, kolory i wygląd przycisku z Twojej strony. „Własny” używa kolorów i zaokrąglenia poniżej. „Klasyczny” to pierwszy wygląd popupu. Gotowe style i okazje mają własne kolory.</p>
                <div class="dxp-colors">
                    <div><label for="dxp-popup-accent-field">Kolor akcentu i przycisku</label><input id="dxp-popup-accent-field" class="dxp-color" name="popup_accent" value="<?php echo esc_attr($popup['accent']); ?>"></div>
                    <div><label for="dxp-popup-bg-field">Tło okna (styl Własny)</label><input id="dxp-popup-bg-field" class="dxp-color" name="popup_background" value="<?php echo esc_attr($popup['background']); ?>"></div>
                    <div><label for="dxp-popup-text-field">Tekst (styl Własny)</label><input id="dxp-popup-text-field" class="dxp-color" name="popup_text" value="<?php echo esc_attr($popup['text']); ?>"></div>
                </div>
                <label for="dxp-popup-radius-field">Zaokrąglenie rogów w pikselach (styl Własny)</label>
                <input id="dxp-popup-radius-field" class="dxp-short" type="number" min="0" max="40" name="popup_radius" value="<?php echo (int) $popup['radius']; ?>">
                <label for="dxp-popup-css-field">Własny CSS (opcjonalnie)</label>
                <textarea id="dxp-popup-css-field" name="popup_css" rows="6" spellcheck="false" placeholder=".asm-popup__title { text-transform: uppercase; }"><?php echo esc_textarea($popup['custom_css']); ?></textarea>
                <p class="description">Klasy: .asm-popup__box, .asm-popup__title, .asm-popup__msg, .asm-popup__btn, .asm-popup__x.</p>
                <div class="dxp-actions">
                    <?php submit_button('Zapisz popup', 'primary', 'submit', false); ?>
                    <a class="button" target="_blank" rel="noopener" href="<?php echo esc_url(Popup::previewUrl()); ?>">Podgląd zapisanego popupu na stronie</a>
                </div>
                <p class="description">Podgląd widzi tylko zalogowany administrator, także gdy popup jest wyłączony albo poza datami. Najpierw zapisz zmiany.</p>
            </section>
        </form>
        <?php
        $this->librarySections('popup');
    }

    private function librarySections(string $part): void
    {
        $bar = $part === 'bar';
        $class = $bar ? Bar::class : Popup::class;
        $what = $bar ? 'pasek' : 'popup';
        $templates = array();
        foreach ($class::templates() as $key => $template) {
            $templates['tpl:' . $key] = $template;
        }
        ?>
        <div class="dxp-grid dxp-grid--wide">
            <section class="dxp-surface">
                <h2><?php echo esc_html($bar ? 'Biblioteka pasków' : 'Biblioteka popupów'); ?></h2>
                <p><?php echo esc_html('Zapisz obecny ' . $what . ' pod nazwą, żeby wrócić do niego później, na przykład co roku przed świętami.'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="dxp-copy-row">
                    <?php wp_nonce_field(self::NONCE); ?>
                    <input type="hidden" name="action" value="dxp_library">
                    <input type="hidden" name="library_part" value="<?php echo esc_attr($part); ?>">
                    <input type="hidden" name="library_action" value="save">
                    <input name="library_name" required maxlength="80" aria-label="Nazwa w bibliotece" placeholder="Nazwa, np. Promocja zimowa">
                    <button class="button" type="submit"><?php echo esc_html('Zapisz obecny ' . $what); ?></button>
                </form>
                <p class="description">Do biblioteki trafia ostatnio zapisana wersja: treść, przycisk i wygląd, bez dat.</p>
                <?php $this->libraryTable($class::library(), true, $part); ?>
            </section>
            <section class="dxp-surface">
                <h2><?php echo esc_html($bar ? 'Paski okazjonalne' : 'Popupy okazjonalne'); ?></h2>
                <p>Gotowa treść i wygląd na święta. Po wczytaniu możesz zmienić tekst i zapisać.</p>
                <?php $this->libraryTable($templates, false, $part); ?>
            </section>
        </div>
        <?php
    }

    private function libraryTable(array $entries, bool $deletable, string $part): void
    {
        if (! $entries) {
            echo '<p class="description">Biblioteka jest pusta.</p>';
            return;
        }
        $bar = $part === 'bar';
        $labels = $bar ? Bar::styles() : Popup::styles();
        $column = $bar ? 'Treść' : 'Tytuł';
        $what = $bar ? 'obecnego paska' : 'obecnego popupu';
        echo '<table class="widefat dxp-table"><thead><tr><th scope="col">Nazwa</th><th scope="col">' . $column . '</th><th scope="col">Styl</th><th scope="col">Działania</th></tr></thead><tbody>';
        foreach ($entries as $id => $entry) {
            $settings = (array) ($entry['settings'] ?? array());
            $name = (string) ($entry['name'] ?? '');
            $summary = (string) ($settings[$bar ? 'message' : 'title'] ?? '');
            echo '<tr><td data-label="Nazwa">' . esc_html($name) . '</td><td data-label="' . $column . '">' . esc_html($summary) . '</td><td data-label="Styl">' . esc_html($labels[$settings['style'] ?? ''] ?? '') . '</td><td data-label="Działania"><div class="dxp-row-actions">';
            foreach (array_filter(array('load' => 'Wczytaj', 'delete' => $deletable ? 'Usuń' : null)) as $operation => $label) {
                $question = $operation === 'load'
                    ? 'Wczytać „' . $name . '”? Zastąpi to treść i wygląd ' . $what . '.'
                    : 'Usunąć „' . $name . '” z biblioteki?';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-confirm="' . esc_attr($question) . '">';
                wp_nonce_field(self::NONCE);
                echo '<input type="hidden" name="action" value="dxp_library"><input type="hidden" name="library_part" value="' . esc_attr($part) . '"><input type="hidden" name="library_action" value="' . esc_attr($operation) . '"><input type="hidden" name="library_id" value="' . esc_attr((string) $id) . '">';
                echo '<button class="button button-small" type="submit" disabled data-needs-confirm>' . esc_html($label) . '</button></form>';
            }
            echo '</div></td></tr>';
        }
        echo '</tbody></table>';
    }

    private function bar(): void
    {
        $bar = Bar::settings();
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="dxp-grid dxp-grid--wide">
            <?php wp_nonce_field(self::NONCE); ?>
            <input type="hidden" name="action" value="dxp_save_bar">
            <section class="dxp-surface">
                <h2>Treść paska</h2>
                <?php $this->toggleField('bar', ! empty($bar['enabled']), $bar, 'Pokazuj pasek odwiedzającym'); ?>
                <p class="description">Ten przełącznik zapisuje się od razu. Treść i wygląd zapisujesz przyciskiem „Zapisz pasek”.</p>
                <label for="dxp-bar-message-field">Treść</label>
                <input id="dxp-bar-message-field" name="bar_message" maxlength="300" placeholder="Np. Darmowa dostawa od 200 zł do końca miesiąca." value="<?php echo esc_attr($bar['message']); ?>">
                <p class="description">Jedno albo dwa krótkie zdania. Na telefonie pasek ma kilka wierszy, więc im krócej, tym lepiej.</p>
                <div class="dxp-colors">
                    <div><label for="dxp-bar-button-field">Tekst przycisku (opcjonalnie)</label><input id="dxp-bar-button-field" name="bar_button" maxlength="60" placeholder="Zobacz ofertę" value="<?php echo esc_attr($bar['button_text']); ?>"></div>
                    <div><label for="dxp-bar-url-field">Adres przycisku</label><input id="dxp-bar-url-field" type="url" name="bar_button_url" placeholder="https://" value="<?php echo esc_attr($bar['button_url']); ?>"></div>
                </div>
                <p class="description">Przycisk pokazuje się, gdy wpiszesz i tekst, i adres.</p>
                <fieldset>
                    <legend>Miejsce</legend>
                    <label class="dxp-check"><input type="radio" name="bar_position" value="top" <?php checked($bar['position'], 'top'); ?>> Na górze strony</label>
                    <label class="dxp-check"><input type="radio" name="bar_position" value="bottom" <?php checked($bar['position'], 'bottom'); ?>> Na dole ekranu</label>
                    <label class="dxp-check"><input type="checkbox" name="bar_sticky" value="1" <?php checked(! empty($bar['sticky'])); ?>> Przyklejony: widoczny także po przewinięciu strony</label>
                </fieldset>
                <p class="description">Bez przyklejenia pasek na górze przewija się razem ze stroną, a pasek na dole jest pod stopką.</p>
                <label for="dxp-bar-days-field">Po zamknięciu nie pokazuj przez (dni)</label>
                <input id="dxp-bar-days-field" class="dxp-short" type="number" min="0" max="365" name="bar_days" value="<?php echo (int) $bar['dismiss_days']; ?>">
                <p class="description">0 oznacza, że pasek wróci przy następnej wizycie. Zmiana treści pokazuje pasek ponownie wszystkim.</p>
                <?php $this->datesFields('bar', $bar, 'pasek'); ?>
            </section>
            <section class="dxp-surface">
                <h2>Wygląd</h2>
                <?php $this->styleField('bar_style', (string) $bar['style'], array_keys(Bar::styles()), Bar::styles()); ?>
                <p class="description">Gotowe style i okazje mają te same kolory co popup. „Własny” używa kolorów poniżej.</p>
                <div class="dxp-colors">
                    <div><label for="dxp-bar-bg-field">Tło (styl Własny)</label><input id="dxp-bar-bg-field" class="dxp-color" name="bar_background" value="<?php echo esc_attr($bar['background']); ?>"></div>
                    <div><label for="dxp-bar-text-field">Tekst (styl Własny)</label><input id="dxp-bar-text-field" class="dxp-color" name="bar_text" value="<?php echo esc_attr($bar['text']); ?>"></div>
                    <div><label for="dxp-bar-accent-field">Przycisk (styl Własny)</label><input id="dxp-bar-accent-field" class="dxp-color" name="bar_accent" value="<?php echo esc_attr($bar['accent']); ?>"></div>
                </div>
                <div class="dxp-actions">
                    <?php submit_button('Zapisz pasek', 'primary', 'submit', false); ?>
                    <a class="button" target="_blank" rel="noopener" href="<?php echo esc_url(Bar::previewUrl()); ?>">Podgląd zapisanego paska na stronie</a>
                </div>
                <p class="description">Podgląd widzi tylko zalogowany administrator, także gdy pasek jest wyłączony albo poza datami. Najpierw zapisz zmiany.</p>
            </section>
        </form>
        <?php
        $this->librarySections('bar');
    }

    private function guard(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Brak uprawnień.');
        }
        check_admin_referer(self::NONCE);
    }

    private function redirect(string $message, string $tab): void
    {
        wp_safe_redirect(add_query_arg('dxp_message', $message, self::url($tab)));
        exit;
    }
}

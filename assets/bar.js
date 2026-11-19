(function () {
  'use strict';

  // Skrypt jest w nagłówku i ma dane paska we własnych atrybutach. Gdy optymalizator przeniesie go niżej
  // albo połączy z innymi, dane bierzemy z samego paska.
  var script = document.currentScript && document.currentScript.hasAttribute('data-revision') ? document.currentScript : null;
  var hide = script ? excluded(script) : null;
  if (hide) document.documentElement.classList.add('dxp-bar-off');

  function excluded(source) {
    var preview = source.getAttribute('data-preview') === '1';
    if (preview) return false;
    var now = Date.now() / 1000;
    var start = Number(source.getAttribute('data-start') || 0);
    var end = Number(source.getAttribute('data-end') || 0);
    return (start && now < start) || (end && now >= end) || dismissed(source);
  }

  function storageKey(source) {
    return 'dxp_bar_dismissed_' + String(source.getAttribute('data-revision') || 'default');
  }

  function days(source) {
    return Math.max(0, Number(source.getAttribute('data-dismiss-days') || 0));
  }

  function dismissed(source) {
    try {
      // 0 dni = „przy każdej wizycie”: zamknięcie pamiętamy do końca wizyty (karty).
      if (days(source) === 0) return window.sessionStorage.getItem(storageKey(source)) === '1';
      return Number(window.localStorage.getItem(storageKey(source)) || 0) > Date.now();
    } catch (error) {
      return false;
    }
  }

  function run() {
    var bar = document.getElementById('dxp-bar');
    if (!bar) return;
    if (hide === null ? excluded(bar) : hide) {
      bar.remove();
      return;
    }
    var body = document.body;
    var top = bar.classList.contains('dxp-bar--top');
    var sticky = bar.classList.contains('dxp-bar--sticky');
    // Pasek ze stopki (motyw bez wp_body_open) trafia na początek strony.
    if (top) {
      if (body.firstElementChild !== bar) body.insertBefore(bar, body.firstChild);
    } else if (body.lastElementChild !== bar) {
      body.appendChild(bar);
    }
    var adminBar = document.getElementById('wpadminbar');
    var space = null;
    var shifted = [];
    var basePadding = parseFloat(window.getComputedStyle(body).paddingBottom) || 0;
    var queued = false;

    // Przyklejony pasek na górze jest przypięty do ekranu (position: fixed), a jego miejsce zajmuje puste pole.
    // position: sticky nie działa na motywach z overflow: hidden na html albo body (np. d-x.pl).
    if (top && sticky) {
      space = document.createElement('div');
      space.id = 'dxp-bar-space';
      space.setAttribute('aria-hidden', 'true');
      body.insertBefore(space, bar.nextSibling);
      bar.classList.add('dxp-bar--pinned');
    }

    // Dolna krawędź paska administratora na ekranie; na telefonie ten pasek przewija się ze stroną.
    function adminOffset() {
      if (!adminBar) return 0;
      return Math.max(0, adminBar.getBoundingClientRect().bottom);
    }

    // Nagłówki i menu przypięte do ekranu albo do strony (fixed, absolute), które zachodzą na pasek.
    // Element szerokości całego ekranu i wysokości ponad 60 % ekranu to nakładka (np. menu na telefonie): zostaje.
    function candidate(node) {
      if (node === bar || node === space || node === body || node === document.documentElement || bar.contains(node)) return false;
      if (node.closest('#wpadminbar, #asm-popup')) return false;
      var style = window.getComputedStyle(node);
      if (style.position !== 'fixed' && style.position !== 'absolute') return false;
      if (style.position === 'absolute' && node.offsetParent && node.offsetParent !== body && node.offsetParent !== document.documentElement) return false;
      var rect = node.getBoundingClientRect();
      if (rect.height > window.innerHeight * 0.6) return false;
      // Element przewijany ze stroną liczy się tylko na samej górze strony; niżej przejeżdża pod przypiętym paskiem.
      if (style.position === 'absolute' && rect.top >= (space || bar).getBoundingClientRect().bottom - 1) return false;
      return !shifted.some(function (item) { return item.node === node || item.node.contains(node) || node.contains(item.node); });
    }

    function detect() {
      var rect = bar.getBoundingClientRect();
      if (rect.bottom <= 0) return;
      [rect.top + 2, rect.bottom - 2].forEach(function (y) {
        [0.05, 0.5, 0.95].forEach(function (x) {
          document.elementsFromPoint(window.innerWidth * x, y).forEach(function (node) {
            if (!candidate(node)) return;
            shifted.push({
              node: node,
              base: parseFloat(window.getComputedStyle(node).marginTop) || 0,
              saved: [node.style.getPropertyValue('margin-top'), node.style.getPropertyPriority('margin-top')]
            });
          });
        });
      });
    }

    // Zapis tylko przy zmianie: inaczej obserwator zmian strony budziłby się od własnych zapisów.
    function set(node, name, value, priority) {
      if (node.style.getPropertyValue(name) !== value) node.style.setProperty(name, value, priority || '');
    }

    // Przywraca tylko margin-top, który zmieniamy: pierwotną wartość i priorytet z atrybutu style.
    function restore(item) {
      var style = item.node.style;
      if (style.getPropertyValue('margin-top') === item.saved[0] && style.getPropertyPriority('margin-top') === item.saved[1]) return;
      if (item.saved[0] !== '') style.setProperty('margin-top', item.saved[0], item.saved[1]);
      else style.removeProperty('margin-top');
    }

    // Element schodzi tuż pod pasek: przypięty do ekranu pod jego widoczną dolną krawędź, przewijany ze stroną pod
    // miejsce paska na stronie. Położenie bez naszego marginesu liczymy w każdej klatce z bieżącego (obliczony margin-top
    // minus bazowy, także w trakcie animacji motywu), więc zmiana top przez motyw jest uwzględniona od razu.
    // Bez transform i translate: zmieniłyby układ odniesienia menu fixed w nagłówku i jego przesunięcie poziome.
    // shortcut: element ustawiony od dołu (bottom, bez top) nie schodzi od margin-top i zostaje tam, gdzie postawił go
    // motyw; margines zatrzymuje limit (cap), więc nic nie rośnie. Dorobić osobny sposób, gdy trafi się taki motyw.
    function place() {
      queued = false;
      var height = bar.offsetHeight;
      if (space) {
        set(space, 'height', height + 'px');
        set(bar, 'top', adminOffset() + 'px');
      }
      if (!top && sticky) set(body, 'padding-bottom', (basePadding + height) + 'px');
      if (!top) return;
      detect();
      var barBottom = bar.getBoundingClientRect().bottom;
      var slotBottom = (space || bar).getBoundingClientRect().bottom;
      var cap = height + (adminBar ? adminBar.offsetHeight : 0);
      shifted.forEach(function (item) {
        var style = window.getComputedStyle(item.node);
        var position = style.position;
        var natural = item.node.getBoundingClientRect().top - ((parseFloat(style.marginTop) || 0) - item.base);
        var limit = position === 'fixed' ? barBottom : slotBottom;
        var offset = position === 'fixed' || position === 'absolute' ? Math.round(Math.min(Math.max(0, limit - natural), cap)) : 0;
        if (offset === 0) restore(item);
        else set(item.node, 'margin-top', (item.base + offset) + 'px', 'important');
      });
    }

    // Widoczny naprawdę, także gdy przezroczysty albo ukryty jest element nadrzędny.
    function shown(node) {
      if (typeof node.checkVisibility === 'function') return node.checkVisibility({ opacityProperty: true, visibilityProperty: true });
      for (var current = node; current && current !== document.documentElement; current = current.parentElement) {
        var style = window.getComputedStyle(current);
        if (style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity) <= 0.05) return false;
      }
      return true;
    }

    // Otwarte menu albo inna nakładka na cały ekran: pasek chowa się pod nią. Warstwa menu bywa zamknięta w warstwie
    // nagłówka (d-x.pl: z-index 10), więc samo niższe z-index paska nie wystarcza. Wąski panel boczny pasek zostawia.
    function overlaid() {
      var rect = bar.getBoundingClientRect();
      var y = rect.top + rect.height / 2;
      return [0.05, 0.5, 0.95].some(function (x) {
        return document.elementsFromPoint(window.innerWidth * x, y).some(function (node) {
          if (bar.contains(node) || node.closest('#wpadminbar')) return false;
          var style = window.getComputedStyle(node);
          var box = node.getBoundingClientRect();
          return style.position === 'fixed' && box.height > window.innerHeight * 0.6 && box.width >= window.innerWidth * 0.5
            && box.top <= rect.top && style.pointerEvents !== 'none' && shown(node);
        });
      });
    }

    function queue() {
      if (queued) return;
      queued = true;
      window.requestAnimationFrame(function () {
        // Klatka zaplanowana przed zamknięciem paska nie może z powrotem ustawić marginesu.
        if (!bar.isConnected) return;
        place();
        var covered = overlaid();
        if (bar.classList.contains('dxp-bar--covered') !== covered) bar.classList.toggle('dxp-bar--covered', covered);
      });
    }

    place();
    var refresh = queue;
    window.addEventListener('resize', refresh);
    window.addEventListener('scroll', queue, { passive: true });
    // Motywy przypinają nagłówek skryptem po wczytaniu strony.
    window.addEventListener('load', refresh);
    // Menu otwiera się zmianą klasy albo stylu; sprawdzamy także po animacji otwarcia.
    // Najwyżej co 200 ms: suwaki i animacje zmieniają style wiele razy na sekundę.
    var pending = 0;
    var observer = new MutationObserver(function () {
      if (pending) return;
      pending = window.setTimeout(function () { pending = 0; queue(); }, 200);
    });
    observer.observe(body, { subtree: true, attributes: true, attributeFilter: ['class', 'style', 'aria-expanded', 'hidden', 'open'] });

    bar.querySelector('.dxp-bar__x').addEventListener('click', function () {
      observer.disconnect();
      window.clearTimeout(pending);
      window.removeEventListener('resize', refresh);
      window.removeEventListener('scroll', queue);
      window.removeEventListener('load', refresh);
      shifted.forEach(function (item) { restore(item); });
      if (space) space.remove();
      if (!top && sticky) body.style.paddingBottom = '';
      bar.remove();
      if (bar.getAttribute('data-preview') === '1') return;
      try {
        if (days(bar) > 0) window.localStorage.setItem(storageKey(bar), String(Date.now() + days(bar) * 86400000));
        else window.sessionStorage.setItem(storageKey(bar), '1');
      } catch (error) {}
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
}());

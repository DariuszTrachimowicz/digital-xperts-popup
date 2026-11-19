(() => {
  'use strict';
  if (window.jQuery && window.jQuery.fn.wpColorPicker) window.jQuery('.dxp-color').wpColorPicker();
  // Przełącznik „Pokazuj” zapisuje się od razu, osobno dla popupu i paska.
  document.querySelectorAll('[data-toggle]').forEach(toggle => {
    const status = toggle.closest('label').nextElementSibling;
    // Wyłączone pole nie trafia do formularza: zapis formularza w trakcie AJAX wyłączyłby to, co właśnie włączono.
    let pending = false;
    toggle.form.addEventListener('submit', event => {
      if (!pending) return;
      event.preventDefault();
      show('busy', 'Poczekaj, aż przełącznik się zapisze, i zapisz ponownie.');
    });
    const show = (state, text) => { status.className = 'dxp-state is-' + state; status.textContent = text; };
    toggle.addEventListener('change', async () => {
      const wanted = toggle.checked;
      pending = true;
      toggle.disabled = true;
      show('busy', 'Zapisywanie.');
      try {
        const body = new URLSearchParams({ action: 'dxp_toggle', part: toggle.dataset.toggle, nonce: toggle.dataset.nonce, enabled: wanted ? '1' : '0' });
        const response = await fetch(toggle.dataset.url, { method: 'POST', body, credentials: 'same-origin', cache: 'no-store' });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.data && result.data.message ? result.data.message : 'Nie udało się zapisać. Spróbuj ponownie.');
        show(result.data.state, result.data.message);
      } catch (error) {
        // Stan pola wraca do zapisanego, żeby ekran nie pokazywał czegoś innego niż strona.
        toggle.checked = !wanted;
        show('error', error.message || 'Nie udało się zapisać. Spróbuj ponownie.');
      }
      toggle.disabled = false;
      pending = false;
      toggle.focus();
    });
  });
  document.querySelectorAll('[data-needs-confirm]').forEach(button => { button.disabled = false; });
  document.querySelectorAll('[data-confirm]').forEach(form => form.addEventListener('submit', event => { if (!window.confirm(form.dataset.confirm)) { event.preventDefault(); form.querySelector('button[type=submit]')?.focus(); } }));
})();

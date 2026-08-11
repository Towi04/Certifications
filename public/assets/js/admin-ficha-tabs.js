/**
 * Pestañas de ficha admin (URL sync + show/hide panels).
 * Marca el contenedor con data-admin-ficha y data-tab="activo".
 * Nav: [data-admin-ficha-nav] .admin-ficha-tab[data-tab-target]
 * Panels: .admin-ficha-panel[data-tab-panel]
 */
(function () {
  function activate(root, tabId) {
    if (!root || !tabId) return;
    root.setAttribute('data-tab', tabId);
    root.querySelectorAll('[data-admin-ficha-nav] .admin-ficha-tab').forEach(function (btn) {
      var on = btn.getAttribute('data-tab-target') === tabId;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    root.querySelectorAll('.admin-ficha-panel[data-tab-panel]').forEach(function (panel) {
      var on = panel.getAttribute('data-tab-panel') === tabId;
      panel.hidden = !on;
      panel.classList.toggle('is-active', on);
    });
    root.querySelectorAll('input[name="tab"]').forEach(function (input) {
      input.value = tabId;
    });
    try {
      var url = new URL(window.location.href);
      url.searchParams.set('tab', tabId);
      window.history.replaceState({}, '', url.toString());
    } catch (e) { /* ignore */ }
  }

  function init(root) {
    var initial = root.getAttribute('data-tab') || 'general';
    var nav = root.querySelector('[data-admin-ficha-nav]');
    if (!nav) return;
    activate(root, initial);
    nav.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-tab-target]');
      if (!btn || !nav.contains(btn)) return;
      e.preventDefault();
      activate(root, btn.getAttribute('data-tab-target'));
    });
  }

  document.querySelectorAll('[data-admin-ficha]').forEach(init);
})();

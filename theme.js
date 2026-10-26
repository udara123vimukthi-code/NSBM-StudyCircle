/* Shared theme preference. Runs in the head to avoid a light flash on navigation. */
(function () {
    'use strict';
    var key = 'studycircle-theme';
    var preference = null;
    var system = window.matchMedia('(prefers-color-scheme: dark)');
    try { preference = localStorage.getItem(key); } catch (error) { /* Private storage may be unavailable. */ }
    if (preference !== 'light' && preference !== 'dark') preference = null;
    function apply(theme) {
        document.documentElement.dataset.theme = theme;
        document.querySelectorAll('.theme-toggle').forEach(function (button) {
            var dark = theme === 'dark';
            button.hidden = false;
            button.textContent = dark ? '☀ Light mode' : '☾ Dark mode';
            button.setAttribute('aria-label', 'Switch to ' + (dark ? 'light' : 'dark') + ' theme');
            button.setAttribute('aria-pressed', String(dark));
        });
    }
    apply(preference || (system.matches ? 'dark' : 'light'));
    document.addEventListener('DOMContentLoaded', function () {
        apply(document.documentElement.dataset.theme);
        document.querySelectorAll('.theme-toggle').forEach(function (button) {
            button.addEventListener('click', function () {
                preference = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
                try { localStorage.setItem(key, preference); } catch (error) { /* Toggle still works for this page. */ }
                apply(preference);
            });
        });
    });
    system.addEventListener('change', function (event) {
        if (!preference) apply(event.matches ? 'dark' : 'light');
    });
    window.addEventListener('storage', function (event) {
        if (event.key !== key && event.key !== null) return;
        preference = event.newValue === 'dark' || event.newValue === 'light' ? event.newValue : null;
        apply(preference || (system.matches ? 'dark' : 'light'));
    });
}());

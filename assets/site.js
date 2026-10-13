(() => {
    for (const [id, className] of [['text-size', 'large-text'], ['contrast', 'high-contrast'], ['theme', 'dark-theme']]) {
        const button = document.getElementById(id);
        let enabled = false;
        try { enabled = localStorage.getItem(className) === 'true'; } catch (_) {}
        const apply = () => {
            document.body.classList.toggle(className, enabled);
            if (id === 'text-size') {
                document.documentElement.classList.toggle('large-text', enabled);
            }
            if (id === 'theme') {
                document.documentElement.dataset.bsTheme = enabled ? 'dark' : 'light';
            }
            button.setAttribute('aria-pressed', String(enabled));
        };
        apply();
        button.addEventListener('click', () => {
            enabled = !enabled;
            apply();
            try { localStorage.setItem(className, String(enabled)); } catch (_) {}
        });
    }
    document.getElementById('pull-form').addEventListener('submit', () => {
        document.querySelector('#pull-form button').disabled = true;
        document.getElementById('pull-status').textContent = 'Bezig met bijwerken. Dit kan tot 30 seconden duren.';
    });
})();

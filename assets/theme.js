// Apply the saved theme before the page paints to avoid a bright flash.
(() => {
    let dark = false;
    try { dark = localStorage.getItem('dark-theme') === 'true'; } catch (_) {}
    document.documentElement.dataset.bsTheme = dark ? 'dark' : 'light';
})();

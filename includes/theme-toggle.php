<script>
    (function () {
        'use strict';

        // Listen for clicks anywhere in the document and check if the
        // target (or an ancestor) is the theme toggle button.
        //
        // Using event delegation instead of `btn.addEventListener` means:
        //   - It works even if the button is added to the DOM after this script runs
        //   - It works even if the button is replaced by another script
        //   - It works if you have multiple toggle buttons on the same page
        document.addEventListener('click', function (e) {

            var btn = e.target.closest('#theme-toggle');
            if (!btn) return;

            var root = document.documentElement;

            // Add a short-lived transition class so colors fade smoothly
            root.classList.add('theme-transition');

            // Toggle the .dark class on <html>
            var isDark = root.classList.toggle('dark');

            // ---- Persist the choice (belt and suspenders) ----
            // 1. localStorage — fast, works on modern browsers
            try {
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
            } catch (err) {
                // Ignore — localStorage can throw in private mode
            }

            // 2. Cookie — fallback for browsers that block localStorage
            try {
                document.cookie = 'theme=' + (isDark ? 'dark' : 'light') +
                                  ';path=/;max-age=31536000;SameSite=Lax';
            } catch (err) {
                // Ignore — cookies may be disabled
            }

            // ---- Rebuild Lucide icons so sun/moon swap correctly ----
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }

            // Remove the transition class after the animation completes
            setTimeout(function () {
                root.classList.remove('theme-transition');
            }, 250);
        });
    })();
</script>
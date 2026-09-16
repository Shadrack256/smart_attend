<?php
/**
 * Shared <head> include.
 *
 * Expects (optional):
 *   $pageTitle   — string, page title
 *   $extraHead   — string, additional HTML to inject before </head>
 *
 * Uses $pdo (from config/db.php) to fetch brand settings and favicon.
 */
$pageTitle = $pageTitle ?? 'Smart Attend';

// Load brand settings + palette (safe fallback if $pdo isn't available)
$__brandColor = '#2f5bff';
$__brandName  = 'SmartAttend';
$__favicon    = '';
if (isset($pdo)) {
    $__brandColor = setting($pdo, 'brand_color', '#2f5bff');
    $__brandName  = setting($pdo, 'system_name', 'SmartAttend');
    $__favSetting = setting($pdo, 'favicon', '');
    if ($__favSetting) {
        $__favicon = '/smart_attend/uploads/branding/' . htmlspecialchars($__favSetting);
    }
}
$__palette = brand_palette($__brandColor);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> · <?= htmlspecialchars($__brandName) ?></title>

    <!-- ============================================================
         1) DARK MODE BOOTSTRAP — MUST run BEFORE Tailwind compiles.
         Reads the saved theme from localStorage (or the OS preference)
         and adds/removes the .dark class on <html> immediately. This
         prevents a light-mode flash when the page loads in dark mode.
         ============================================================ -->
    <script>
        (function () {
            try {
                var theme = null;

                // 1) Try localStorage
                try { theme = localStorage.getItem('theme'); } catch (e) {}

                // 2) Fall back to cookie
                if (!theme) {
                    var match = document.cookie.match(/(?:^|;\s*)theme=([^;]+)/);
                    if (match) theme = decodeURIComponent(match[1]);
                }

                // 3) Fall back to OS preference
                if (!theme) {
                    theme = (window.matchMedia &&
                            window.matchMedia('(prefers-color-scheme: dark)').matches)
                        ? 'dark' : 'light';
                }

                // Apply immediately
                var root = document.documentElement;
                if (theme === 'dark') root.classList.add('dark');
                else root.classList.remove('dark');
            } catch (e) {
                /* ignore */
            }
        })();
    </script>

    <!-- ============================================================
         2) TAILWIND CDN
         ============================================================ -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- ============================================================
         3) TAILWIND CONFIG — darkMode: 'class' is REQUIRED.
         Tells Tailwind to activate dark: variants when the `.dark`
         class is present on <html>, instead of relying on the OS.
         ============================================================ -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: <?= json_encode($__palette, JSON_PRETTY_PRINT) ?>,
                    },
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    boxShadow: {
                        card: '0 1px 2px rgba(16,24,40,.06), 0 1px 3px rgba(16,24,40,.10)',
                    },
                },
            },
        };
    </script>

    <!-- ============================================================
         4) LUCIDE ICONS
         ============================================================ -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- ============================================================
         5) FONTS
         ============================================================ -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- ============================================================
         6) FAVICON (dynamic, from settings)
         ============================================================ -->
    <?php if ($__favicon): ?>
        <link rel="icon" href="<?= $__favicon ?>">
    <?php else: ?>
        <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='<?= urlencode($__brandColor) ?>'/><text x='50' y='68' font-size='60' text-anchor='middle' fill='white' font-family='Inter,sans-serif' font-weight='bold'><?= strtoupper(substr($__brandName, 0, 1)) ?></text></svg>">
    <?php endif; ?>

    <!-- ============================================================
         7) BASE STYLES
         ============================================================ -->
    <style>
        html { -webkit-text-size-adjust: 100%; scroll-behavior: smooth; }
        body { font-family: 'Inter', system-ui, sans-serif; }

        /* FAQ accordion: hide default triangle */
        summary::-webkit-details-marker { display: none; }
        summary { list-style: none; }

        /* Smooth transition when the theme is toggled (added by JS briefly) */
        html.theme-transition,
        html.theme-transition * {
            transition: background-color .2s ease, border-color .2s ease, color .2s ease !important;
        }

        /* Hide scrollbar utility */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* Entrance animation */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-up { animation: fadeUp .6s ease-out both; }

        /* Pulsing dots for "live" indicators */
        @keyframes pulseDot {
            0%, 100% { opacity: 1; }
            50%      { opacity: .4; }
        }
        .pulse-dot { animation: pulseDot 1.5s ease-in-out infinite; }
    </style>

    <?= $extraHead ?? '' ?>
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-200 antialiased min-h-screen transition-colors">
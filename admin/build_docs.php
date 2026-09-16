<?php
require_once __DIR__ . '/../config/db.php';
require_role('admin');

// ==================================================================
// DEPENDENCIES
// ==================================================================
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    die("Composer dependencies not installed. Run: composer install");
}
require $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

$hasParsedown = class_exists('Parsedown');
$hasDompdf    = class_exists('Dompdf\Dompdf');

// ==================================================================
// BRANDING + SETTINGS
// ==================================================================
$brand      = app_settings($pdo);
$instName   = $brand['institution_name']    ?? ($brand['system_name'] ?? 'Institution');
$sysName    = $brand['system_name']         ?? 'SmartAttend';
$instAddr   = $brand['institution_address'] ?? '';
$instPhone  = $brand['institution_phone']   ?? '';
$instEmail  = $brand['institution_email']   ?? '';
$instWeb    = $brand['institution_website'] ?? '';
$sysLogo    = $brand['system_logo']         ?? '';
$brandColor = $brand['brand_color']         ?? '#2f5bff';
$footerText = trim($brand['footer_text'] ?? '');

// Embed logo as data URI (avoids Dompdf path issues)
$logoData = '';
if ($sysLogo) {
    $logoPath = __DIR__ . '/../uploads/branding/' . $sysLogo;
    if (is_file($logoPath)) {
        $mime = mime_content_type($logoPath) ?: 'image/png';
        $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
    }
}

// Sanitize brand color
if (!preg_match('/^#[0-9a-f]{6}$/i', $brandColor)) {
    $brandColor = '#2f5bff';
}

// ==================================================================
// HELPERS
// ==================================================================
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function format_size($bytes) {
    if ($bytes < 1024)        return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1024 / 1024, 2) . ' MB';
}

/**
 * Build the full HTML document for one markdown file.
 * Header and footer are placed inside reserved @page margins so they
 * never overlap the body content.
 */
function build_doc_html(string $bodyHtml, array $cfg): string {
    $compact = $cfg['compact'];
    $fs  = $compact ? '9.5pt'  : '10.5pt';
    $h1  = $compact ? '15pt'   : '18pt';
    $h2  = $compact ? '12pt'   : '14pt';
    $h3  = $compact ? '10.5pt' : '12pt';
    $mt  = $compact ? '14px'   : '20px';

    // Layout constants — header reserves 130px of top space, footer 55px of bottom
    $pageTop    = '160px';
    $pageBottom = '75px';
    $pageSide   = '45px';
    $hdrHeight  = '110px';
    $hdrTop     = '-140px';      // (page top margin) - (small gap) = -140px
    $ftrBottom  = '-55px';
    $ftrHeight  = '30px';

    $inst      = e($cfg['instName']);
    $sys       = e($cfg['sysName']);
    $addr      = e($cfg['instAddr']);
    $phone     = e($cfg['instPhone']);
    $email     = e($cfg['instEmail']);
    $web       = e($cfg['instWeb']);
    $footer    = e($cfg['footerText'] ?: ($cfg['instName'] . ' · ' . $cfg['sysName']));
    $generated = e(date('d M Y \a\t H:i'));
    $brand     = e($cfg['brandColor']);
    $logoImg   = $cfg['logoData']
        ? '<img src="' . $cfg['logoData'] . '" class="logo" alt="">'
        : '<div class="logo-ph">' . e(strtoupper(substr($cfg['instName'], 0, 1))) . '</div>';

    // Compose contact line
    $contactParts = [];
    if ($addr)  $contactParts[] = $addr;
    if ($phone) $contactParts[] = 'Tel: ' . $phone;
    if ($email) $contactParts[] = 'Email: ' . $email;
    if ($web)   $contactParts[] = $web;
    $contactLine = implode(' · ', $contactParts);

    return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
    /* ==================== PAGE GEOMETRY ====================
       The top margin MUST be larger than the header's offset+height
       so the body content starts below the letterhead. */
    @page {
        margin: {$pageTop} {$pageSide} {$pageBottom} {$pageSide};
    }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: {$fs};
        color: #1e293b;
        line-height: 1.55;
        margin: 0;
        padding: 0;
    }

    /* ==================== FIXED HEADER (letterhead on every page) ====================
       Positioned inside the reserved top margin. White background makes
       it opaque so body text never shows through. */
    header {
        position: fixed;
        top: {$hdrTop};
        left: 0;
        right: 0;
        height: {$hdrHeight};
        background: #ffffff;
        border-bottom: 2px solid #1e293b;
        padding-bottom: 6px;
    }
    header table {
        width: 100%;
        border-collapse: collapse;
        height: 100%;
    }
    header td {
        vertical-align: middle;
        padding: 0;
    }
    header .logo-cell {
        width: 80px;
        text-align: left;
    }
    header .logo {
        width: 66px;
        height: 66px;
        object-fit: contain;
        display: block;
    }
    header .logo-ph {
        width: 66px;
        height: 66px;
        line-height: 66px;
        text-align: center;
        background: {$brand};
        color: #ffffff;
        font-size: 26pt;
        font-weight: bold;
        border-radius: 6px;
    }
    header .name-cell {
        padding-left: 12px;
    }
    header .name-cell h1 {
        font-size: 12pt;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 0 0 3px 0;
        border: none;
        padding: 0;
        line-height: 1.15;
        page-break-after: avoid;
    }
    header .contact {
        font-size: 7pt;
        color: #64748b;
        margin: 0;
        line-height: 1.3;
    }
    header .stamp-cell {
        width: 140px;
        text-align: right;
    }
    header .stamp {
        font-size: 6.5pt;
        color: #94a3b8;
        text-align: right;
        margin: 0;
        line-height: 1.4;
    }

    /* ==================== FIXED FOOTER ==================== */
    footer {
        position: fixed;
        bottom: {$ftrBottom};
        left: 0;
        right: 0;
        height: {$ftrHeight};
        background: #ffffff;
        border-top: 1px solid #e2e8f0;
        padding-top: 5px;
        font-size: 7.5pt;
        color: #94a3b8;
    }
    footer table {
        width: 100%;
        border-collapse: collapse;
    }
    footer td {
        padding: 0;
        vertical-align: middle;
    }
    footer .left  { text-align: left; }
    footer .right { text-align: right; }
    footer .pagenum:after     { content: counter(page); }
    footer .totalpages:after  { content: counter(pages); }

    /* ==================== BODY CONTENT ====================
       Everything below renders in the @page content area, which starts
       below the header and ends above the footer. */
    main {
        margin: 0;
        padding: 0;
    }

    h1 {
        font-size: {$h1};
        color: #0f172a;
        margin: 0 0 8px 0;
        padding-bottom: 5px;
        border-bottom: 2px solid {$brand};
        page-break-after: avoid;
    }
    h1 + p {
        margin-top: 6px;
    }

    h2 {
        font-size: {$h2};
        color: #0f172a;
        margin: {$mt} 0 6px 0;
        page-break-after: avoid;
    }

    h3 {
        font-size: {$h3};
        color: #1e293b;
        margin: 14px 0 5px 0;
        page-break-after: avoid;
    }

    p { margin: 6px 0; }
    ul, ol { margin: 6px 0; padding-left: 20px; }
    li { margin: 2px 0; }
    strong { color: #0f172a; }

    code {
        font-family: DejaVu Sans Mono, monospace;
        font-size: 0.88em;
        background: #f1f5f9;
        padding: 1px 5px;
        border-radius: 3px;
        color: #0f172a;
    }

    pre {
        background: #f8fafc;
        border-left: 3px solid {$brand};
        padding: 10px 12px;
        border-radius: 4px;
        font-family: DejaVu Sans Mono, monospace;
        font-size: 0.82em;
        line-height: 1.35;
        white-space: pre-wrap;
        word-wrap: break-word;
        page-break-inside: avoid;
        overflow-x: hidden;
    }
    pre code {
        background: none;
        padding: 0;
        font-size: 1em;
    }

    blockquote {
        border-left: 3px solid #cbd5e1;
        padding: 4px 12px;
        margin: 10px 0;
        color: #64748b;
        font-style: italic;
        background: #f8fafc;
    }

    hr {
        border: none;
        border-top: 1px solid #e2e8f0;
        margin: 16px 0;
    }

    a { color: {$brand}; text-decoration: none; }

    table {
        width: 100%;
        border-collapse: collapse;
        margin: 12px 0;
        font-size: 0.9em;
        page-break-inside: auto;
    }
    table th {
        background: #1e293b;
        color: #ffffff;
        padding: 6px 8px;
        text-align: left;
        border: 1px solid #1e293b;
        font-weight: normal;
        font-size: 0.95em;
    }
    table td {
        padding: 5px 8px;
        border: 1px solid #cbd5e1;
        vertical-align: top;
    }
    table tr {
        page-break-inside: avoid;
    }
    table tr:nth-child(even) td {
        background: #f8fafc;
    }

    img { max-width: 100%; height: auto; }

    .page-break { page-break-before: always; }
</style>
</head>
<body>

<!-- ============ HEADER (appears on every page) ============ -->
<header>
    <table>
        <tr>
            <td class="logo-cell">{$logoImg}</td>
            <td class="name-cell">
                <h1>{$inst}</h1>
                <p class="contact">{$contactLine}</p>
            </td>
            <td class="stamp-cell">
                <p class="stamp">{$sys}<br>Generated<br>{$generated}</p>
            </td>
        </tr>
    </table>
</header>

<!-- ============ FOOTER (appears on every page) ============ -->
<footer>
    <table>
        <tr>
            <td class="left">{$footer}</td>
            <td class="right">Page <span class="pagenum"></span> of <span class="totalpages"></span></td>
        </tr>
    </table>
</footer>

<!-- ============ BODY ============ -->
<main>
{$bodyHtml}
</main>

</body></html>
HTML;
}

// ==================================================================
// POST HANDLER
// ==================================================================
$results = [];
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['build'])) {

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $error = 'Invalid request. Please reload and try again.';
    } elseif (!$hasParsedown) {
        $error = 'Parsedown is not installed. Run: composer require erusev/parsedown';
    } elseif (!$hasDompdf) {
        $error = 'Dompdf is not installed. Run: composer require dompdf/dompdf';
    } else {
        $docsDir = __DIR__ . '/../docs';

        if (!is_dir($docsDir)) {
            $error = "Docs folder not found at {$docsDir}. Create it and add .md files.";
        } else {
            $files = glob($docsDir . '/*.md');
            sort($files);

            if (!$files) {
                $error = 'No .md files found in /docs/.';
            } else {
                $rebuildOnly = isset($_POST['only_changed']);

                foreach ($files as $mdPath) {
                    $baseName = basename($mdPath, '.md');
                    $pdfName  = $baseName . '.pdf';
                    $pdfPath  = $docsDir . '/' . $pdfName;

                    // Skip if source hasn't changed since PDF was last built
                    if ($rebuildOnly && is_file($pdfPath) && filemtime($pdfPath) >= filemtime($mdPath)) {
                        $results[] = [
                            'source'  => basename($mdPath),
                            'target'  => $pdfName,
                            'size'    => filesize($pdfPath),
                            'success' => true,
                            'skipped' => true,
                        ];
                        continue;
                    }

                    try {
                        $markdown = file_get_contents($mdPath);
                        if ($markdown === false) {
                            throw new Exception("Could not read source file");
                        }

                        // Convert to HTML
                        $parsedown = new Parsedown();
                        $parsedown->setSafeMode(true);
                        $parsedown->setBreaksEnabled(true);
                        $parsedown->setUrlsLinked(true);
                        $bodyHtml = $parsedown->text($markdown);

                        // Compact mode for quick-starts and overviews
                        $isCompact = (bool)preg_match('/QUICK_START|OVERVIEW/i', $baseName);

                        // Wrap
                        $fullHtml = build_doc_html($bodyHtml, [
                            'compact'    => $isCompact,
                            'instName'   => $instName,
                            'instAddr'   => $instAddr,
                            'instPhone'  => $instPhone,
                            'instEmail'  => $instEmail,
                            'instWeb'    => $instWeb,
                            'sysName'    => $sysName,
                            'sysLogo'    => $sysLogo,
                            'logoData'   => $logoData,
                            'brandColor' => $brandColor,
                            'footerText' => $footerText,
                        ]);

                        // Render
                        $options = new Options();
                        $options->set('isRemoteEnabled', false);
                        $options->set('isHtml5ParserEnabled', true);
                        $options->set('isFontSubsettingEnabled', true);
                        $options->set('defaultFont', 'DejaVu Sans');
                        $options->set('chroot', realpath(__DIR__ . '/..'));

                        $dompdf = new Dompdf($options);
                        $dompdf->loadHtml($fullHtml);
                        $dompdf->setPaper('A4', 'portrait');
                        $dompdf->render();

                        $output = $dompdf->output();
                        if (file_put_contents($pdfPath, $output) === false) {
                            throw new Exception("Could not write PDF to {$pdfPath}");
                        }

                        $results[] = [
                            'source'  => basename($mdPath),
                            'target'  => $pdfName,
                            'size'    => filesize($pdfPath),
                            'success' => true,
                        ];
                    } catch (Exception $e) {
                        $results[] = [
                            'source'  => basename($mdPath),
                            'target'  => $pdfName,
                            'error'   => $e->getMessage(),
                            'success' => false,
                        ];
                    }
                }
            }
        }
    }
}

// ==================================================================
// RENDER PAGE
// ==================================================================
$pageTitle = 'Docs Builder';
require __DIR__ . '/partials/admin_header.php';

$title = 'Documentation Builder';
$subtitle = 'Turn markdown files in /docs/ into styled PDFs with institutional letterhead.';
require __DIR__ . '/partials/page_header.php';

$docsDir  = __DIR__ . '/../docs';
$mdFiles  = is_dir($docsDir) ? glob($docsDir . '/*.md')  : [];
$pdfFiles = is_dir($docsDir) ? glob($docsDir . '/*.pdf') : [];
sort($mdFiles);
sort($pdfFiles);

$successCount = count(array_filter($results, fn($r) => !empty($r['success']) && empty($r['skipped'])));
$skippedCount = count(array_filter($results, fn($r) => !empty($r['skipped'])));
$failedCount  = count(array_filter($results, fn($r) => empty($r['success'])));
?>

<!-- ============ DEPENDENCY WARNINGS ============ -->
<?php if (!$hasParsedown): ?>
    <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 dark:border-rose-500/40 dark:bg-rose-500/10 px-4 py-3 text-sm text-rose-800 dark:text-rose-300">
        <strong>Missing dependency:</strong> Parsedown. Run
        <code class="bg-white dark:bg-slate-800 px-1.5 py-0.5 rounded">composer require erusev/parsedown</code>
    </div>
<?php endif; ?>

<?php if (!$hasDompdf): ?>
    <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 dark:border-rose-500/40 dark:bg-rose-500/10 px-4 py-3 text-sm text-rose-800 dark:text-rose-300">
        <strong>Missing dependency:</strong> Dompdf. Run
        <code class="bg-white dark:bg-slate-800 px-1.5 py-0.5 rounded">composer require dompdf/dompdf</code>
    </div>
<?php endif; ?>

<!-- ============ ERROR BANNER ============ -->
<?php if ($error): ?>
    <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 dark:border-rose-500/40 dark:bg-rose-500/10 px-4 py-3 text-sm text-rose-800 dark:text-rose-300">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- ============ BUILD RESULTS ============ -->
<?php if ($results): ?>
    <div class="mb-6 rounded-xl border px-4 py-4
        <?= $failedCount > 0
            ? 'border-amber-200 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10'
            : 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/40 dark:bg-emerald-500/10' ?>">

        <div class="flex items-center gap-2 mb-3 text-sm font-semibold
            <?= $failedCount > 0
                ? 'text-amber-800 dark:text-amber-300'
                : 'text-emerald-800 dark:text-emerald-300' ?>">
            <i data-lucide="<?= $failedCount > 0 ? 'alert-circle' : 'check-circle' ?>" class="w-4 h-4"></i>
            Build complete —
            <?= $successCount ?> generated,
            <?= $skippedCount ?> skipped,
            <?= $failedCount ?> failed
        </div>

        <ul class="space-y-1.5 text-sm">
            <?php foreach ($results as $r): ?>
                <li class="flex items-start gap-2
                    <?= !empty($r['success'])
                        ? 'text-emerald-800 dark:text-emerald-300'
                        : 'text-rose-700 dark:text-rose-300' ?>">

                    <?php if (!empty($r['skipped'])): ?>
                        <i data-lucide="skip-forward" class="w-4 h-4 shrink-0 mt-0.5 text-slate-400"></i>
                        <span class="text-slate-500 dark:text-slate-400">
                            <strong><?= htmlspecialchars($r['source']) ?></strong>
                            already up to date
                        </span>
                    <?php elseif (!empty($r['success'])): ?>
                        <i data-lucide="file-check" class="w-4 h-4 shrink-0 mt-0.5"></i>
                        <span>
                            <strong><?= htmlspecialchars($r['source']) ?></strong>
                            →
                            <?= htmlspecialchars($r['target']) ?>
                            <span class="text-xs opacity-70">(<?= format_size($r['size']) ?>)</span>
                        </span>
                    <?php else: ?>
                        <i data-lucide="x-circle" class="w-4 h-4 shrink-0 mt-0.5"></i>
                        <span>
                            <strong><?= htmlspecialchars($r['source']) ?></strong>
                            failed — <?= htmlspecialchars($r['error'] ?? 'unknown error') ?>
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- ============ ACTION CARD ============ -->
<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card mb-6">
    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800">
        <h2 class="font-semibold text-slate-900 dark:text-white">Rebuild PDFs</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Reads every <code class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">.md</code> file in
            <code class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">/docs/</code> and produces a
            matching <code class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">.pdf</code>.
            Each PDF carries the institution letterhead on every page.
        </p>
    </div>

    <div class="p-6">
        <form method="POST" class="flex flex-wrap items-center gap-4">
            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
            <input type="hidden" name="build" value="1">

            <button class="inline-flex items-center gap-2 rounded-lg bg-brand-600 text-white px-5 py-2.5 text-sm font-semibold hover:bg-brand-700 shadow-sm transition">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i> Rebuild all PDFs
            </button>

            <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300 cursor-pointer">
                <input type="checkbox" name="only_changed" value="1"
                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Only rebuild changed files
            </label>
        </form>

        <div class="mt-4 grid sm:grid-cols-2 gap-4 text-xs text-slate-500 dark:text-slate-400">
            <div class="flex items-start gap-2">
                <i data-lucide="palette" class="w-4 h-4 shrink-0 mt-0.5 text-brand-500"></i>
                <div>
                    <strong class="text-slate-700 dark:text-slate-300">Institutional header</strong><br>
                    Logo, name, address, phone, email, and website on every page.
                </div>
            </div>
            <div class="flex items-start gap-2">
                <i data-lucide="hash" class="w-4 h-4 shrink-0 mt-0.5 text-brand-500"></i>
                <div>
                    <strong class="text-slate-700 dark:text-slate-300">Page numbers</strong><br>
                    Every page shows "Page X of Y" in the footer.
                </div>
            </div>
            <div class="flex items-start gap-2">
                <i data-lucide="file-text" class="w-4 h-4 shrink-0 mt-0.5 text-brand-500"></i>
                <div>
                    <strong class="text-slate-700 dark:text-slate-300">Compact mode</strong><br>
                    Files named <code>QUICK_START*</code> or <code>OVERVIEW*</code> use tighter styling.
                </div>
            </div>
            <div class="flex items-start gap-2">
                <i data-lucide="link" class="w-4 h-4 shrink-0 mt-0.5 text-brand-500"></i>
                <div>
                    <strong class="text-slate-700 dark:text-slate-300">Auto URL linking</strong><br>
                    Bare URLs in markdown become clickable links.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============ CURRENT FILES ============ -->
<div class="grid md:grid-cols-2 gap-6">

    <!-- Markdown sources -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <h2 class="font-semibold text-slate-900 dark:text-white">Markdown sources</h2>
            <span class="text-xs text-slate-500 dark:text-slate-400"><?= count($mdFiles) ?> file(s)</span>
        </div>

        <?php if (!$mdFiles): ?>
            <div class="px-5 py-12 text-center text-slate-400 text-sm">
                No <code>.md</code> files found in <code>/docs/</code>.
            </div>
        <?php else: ?>
        <div class="divide-y divide-slate-100 dark:divide-slate-800 max-h-[500px] overflow-y-auto">
            <?php foreach ($mdFiles as $f):
                $base = basename($f, '.md');
                $pdfPath = $docsDir . '/' . $base . '.pdf';
                $upToDate = is_file($pdfPath) && filemtime($pdfPath) >= filemtime($f);
            ?>
                <div class="px-5 py-3 flex items-center gap-3">
                    <i data-lucide="file-text" class="w-4 h-4 text-slate-400 shrink-0"></i>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium text-slate-900 dark:text-white truncate"><?= htmlspecialchars(basename($f)) ?></div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            <?= format_size(filesize($f)) ?> · updated <?= date('d M Y H:i', filemtime($f)) ?>
                        </div>
                    </div>
                    <?php if (is_file($pdfPath)): ?>
                        <?php if ($upToDate): ?>
                            <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400 inline-flex items-center gap-1">
                                <i data-lucide="check" class="w-3 h-3"></i> up to date
                            </span>
                        <?php else: ?>
                            <span class="text-xs font-medium text-amber-600 dark:text-amber-400 inline-flex items-center gap-1">
                                <i data-lucide="alert-circle" class="w-3 h-3"></i> stale
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-xs font-medium text-slate-400 inline-flex items-center gap-1">
                            <i data-lucide="circle-dashed" class="w-3 h-3"></i> no PDF
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Generated PDFs -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <h2 class="font-semibold text-slate-900 dark:text-white">Generated PDFs</h2>
            <span class="text-xs text-slate-500 dark:text-slate-400"><?= count($pdfFiles) ?> file(s)</span>
        </div>

        <?php if (!$pdfFiles): ?>
            <div class="px-5 py-12 text-center text-slate-400 text-sm">
                No PDFs yet. Click <strong>Rebuild all PDFs</strong> above.
            </div>
        <?php else: ?>
        <div class="divide-y divide-slate-100 dark:divide-slate-800 max-h-[500px] overflow-y-auto">
            <?php foreach ($pdfFiles as $f): ?>
                <div class="px-5 py-3 flex items-center gap-3">
                    <i data-lucide="file-check" class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0"></i>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium text-slate-900 dark:text-white truncate"><?= htmlspecialchars(basename($f)) ?></div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            <?= format_size(filesize($f)) ?> · generated <?= date('d M Y H:i', filemtime($f)) ?>
                        </div>
                    </div>
                    <a href="../docs/<?= htmlspecialchars(basename($f)) ?>" target="_blank" rel="noopener"
                       class="text-xs font-medium text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 shrink-0 inline-flex items-center gap-1">
                        <i data-lucide="external-link" class="w-3 h-3"></i> Open
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============ FOOTNOTE ============ -->
<div class="mt-6 text-xs text-slate-400 dark:text-slate-500 text-center">
    Every rebuild uses your current branding settings.
    Change the institution name, logo, or brand color in <strong>Settings</strong>, then rebuild.
</div>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>
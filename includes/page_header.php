<?php
/**
 * Shared page-title component.
 *
 * Emits a page's single semantic <h1> using the "eyebrow" style MOOP already
 * uses in its tool/section header bars (small, uppercase, letter-spaced). This
 * replaces the inline-styled <span> titles that were hand-rolled on each page,
 * so the styling lives in one place (css/display.css `.page-title-eyebrow`) and
 * every page reliably exposes exactly one <h1> (accessibility / audit #9).
 *
 * Color is inherited from the container, so the same call works on a colored
 * header bar (white text) or on a light background.
 *
 * Usage (inside a content file):
 *   <?= page_title('BLAST Search', 'fa fa-dna') ?>
 */
if (!function_exists('page_title')) {
    /**
     * @param string $text  Visible title (also the <h1> text content).
     * @param string $icon  Optional Font Awesome class, e.g. 'fa fa-dna'.
     * @param array  $opts  Optional 'class' (extra classes) and 'id'.
     */
    function page_title(string $text, string $icon = '', array $opts = []): string {
        $classes = 'page-title-eyebrow';
        if (!empty($opts['class'])) {
            $classes .= ' ' . $opts['class'];
        }
        $idAttr   = !empty($opts['id'])
            ? ' id="' . htmlspecialchars($opts['id'], ENT_QUOTES) . '"'
            : '';
        $iconHtml = $icon !== ''
            ? '<i class="' . htmlspecialchars($icon, ENT_QUOTES) . ' me-2"></i>'
            : '';
        return '<h1' . $idAttr . ' class="' . htmlspecialchars($classes, ENT_QUOTES) . '">'
             . $iconHtml . htmlspecialchars($text) . '</h1>';
    }
}

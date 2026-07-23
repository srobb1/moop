<?php
/**
 * On-page help UI — the two affordances that are not the glossary.
 *
 * MOOP's help lives in four tiers, and each tier has exactly ONE home for its content.
 * That is the whole point: the same explanation written in two places drifts, and a
 * reader cannot tell which copy is lying.
 *
 *   a word          -> gloss()            (lib/glossary.php)  content: metadata/glossary.json
 *   ONE field       -> field_help()       (here)              content: beside the control
 *   a SET of things -> help_modal()       (here)              content: generated from live data
 *   reasoning / why -> docs/*.md                              content: markdown
 *
 * Why these two live together: both are "help on demand" affordances that must never
 * add standing text to the page. Pages stay clean; the help is one click away and is
 * written to be read in seconds, not studied.
 *
 * ---------------------------------------------------------------------------
 * WHY A SHARED HELPER AND NOT PER-PAGE MARKUP
 *
 * Seven different help idioms had accumulated across the site — hand-written popover
 * markup, an `info-icon`-to-custom-modal pattern, collapsibles, a JS string route, and
 * a req_info() helper stranded inside admin/api/get_organism_modal.php (a file that
 * runs a switch and exit()s when included, so the function could never be reused).
 * Colour and shape therefore signalled nothing, and each new page added an eighth
 * variant. Same failure as the admin card headers before css/admin-cards.css — see
 * CLAUDE.md section 6. If one of these helpers lacks something a page needs, ADD IT
 * HERE so every page gets it; do not start a page-local variant.
 *
 * ---------------------------------------------------------------------------
 * WHY POPOVERS ARE SAFE HERE WHEN THEY WERE NOT BEFORE
 *
 * The old hand-written popovers sit dead on half the site, and that was blamed on
 * popovers. It was never the popover — it was PER-PAGE JS INIT. Bootstrap does not
 * auto-initialise popovers from the data-api, so every page had to remember to call
 * an init, and pages forgot. js/modules/field-help.js does one global, idempotent
 * init for the whole site, exactly as js/modules/glossary.js already does for .gloss.
 * A page author writes no JavaScript at all.
 *
 * help_modal() needs no init whatsoever: modals DO work from the Bootstrap data-api.
 */

/**
 * A small (i) button carrying help for ONE field or control.
 *
 * For the case where a reader is mid-task and must not lose their place. Hard cap of
 * roughly 40 words, no tables, no lists longer than three items — if the content does
 * not fit that, it was never field help; it belongs in a help_modal() card or in docs/.
 *
 * Three deliberate choices:
 *
 * - It is a real <button>, not a styled <span>. That makes it keyboard-focusable and
 *   reliably tappable on touch with no tabindex hack, and it announces as a control.
 * - Trigger is "focus", not "hover". Hover help is invisible on touch devices and
 *   undiscoverable everywhere else; click/tap/keyboard-focus opens it and a click
 *   anywhere else dismisses it.
 * - The same text is also written to the native title attribute as a no-JS fallback.
 *   field-help.js REMOVES that attribute once it has built the popover, so a working
 *   page never shows both a native tooltip and a popover for the same button.
 *
 * @param string $text  The help itself. Plain text — it is escaped into an attribute.
 * @param string $title Optional popover heading. Omit for a bare definition.
 * @return string HTML for the button.
 */
function field_help(string $text, string $title = ''): string {
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    // aria-label describes the control's purpose; the popover carries the content.
    // Without this a screen reader announces an icon button with no accessible name.
    $aria = $title !== '' ? ('Help: ' . $title) : 'More information';

    $html  = '<button type="button" class="field-help"';
    $html .= ' data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="top"';
    if ($title !== '') {
        $html .= ' data-bs-title="' . htmlspecialchars($title, ENT_QUOTES) . '"';
    }
    $html .= ' data-bs-content="' . htmlspecialchars($text, ENT_QUOTES) . '"';
    // No-JS fallback; removed by field-help.js on successful init.
    $html .= ' title="' . htmlspecialchars($text, ENT_QUOTES) . '"';
    $html .= ' aria-label="' . htmlspecialchars($aria, ENT_QUOTES) . '">';
    $html .= '<i class="fa fa-info-circle" aria-hidden="true"></i>';
    $html .= '</button>';

    return $html;
}

/**
 * The trigger that opens a help_modal().
 *
 * Kept as a helper rather than hand-written markup so every help modal on the site is
 * opened by the same affordance in the same place — a reader learns it once.
 *
 * @param string $modal_id The id passed to help_modal().
 * @param string $label    Optional visible text. Omit for a bare (i), which is the
 *                         default: a section header should not grow a word of chrome.
 * @param string $aria     Accessible name. Always set something meaningful when the
 *                         trigger is a bare icon.
 */
function help_modal_trigger(string $modal_id, string $label = '', string $aria = 'Help'): string {
    $html  = '<button type="button" class="field-help"';
    $html .= ' data-bs-toggle="modal" data-bs-target="#' . htmlspecialchars($modal_id, ENT_QUOTES) . '"';
    $html .= ' aria-label="' . htmlspecialchars($aria, ENT_QUOTES) . '">';
    $html .= '<i class="fa fa-info-circle" aria-hidden="true"></i>';
    if ($label !== '') {
        $html .= ' <span class="field-help-label">' . htmlspecialchars($label) . '</span>';
    }
    $html .= '</button>';
    return $html;
}

/**
 * A help modal built from sections of short cards.
 *
 * For explaining a SET of related things — annotation types, the parts of a results
 * table, the BLAST programs. The format is the constraint that keeps the help good:
 * a card is a label and about 25 words, so a wall of prose physically cannot be
 * pasted in. If a point needs a paragraph, it is reasoning and belongs in docs/.
 *
 * Sections are what make a rich modal navigable rather than a scroll. Their headings
 * stick to the top of the scroll area (pure CSS), so a reader always knows where they
 * are and can skim to the one section they came for.
 *
 * GENERATE CARDS FROM LIVE DATA WHENEVER THE DATA EXISTS. That is what makes this help
 * trustworthy: includes/ann_types_modal.php loops the same $ann_type_info the page uses
 * to colour its badges, so the help cannot describe a different site than the one on
 * screen, and an admin adding an annotation type updates the help without touching code.
 * Hand-written cards are for the cases where nothing backs them.
 *
 * @param string $modal_id Unique DOM id; the same value goes to help_modal_trigger().
 * @param string $title    Modal heading.
 * @param array  $sections [ ['heading' => string, 'cards' => [ card, ... ]], ... ]
 *                         A card is:
 *                           'label' => string   required; always escaped
 *                           'text'  => string   required; escaped unless 'html' => true
 *                           'color' => string   optional Bootstrap colour, renders the
 *                                               label as a badge (use when the page
 *                                               shows the same badge)
 *                           'html'  => bool     opt in to markup in 'text'. Default is
 *                                               ESCAPED: card text is usually authored
 *                                               here, but some is admin-supplied
 *                                               (annotation descriptions), and a default
 *                                               of "raw" would make every future
 *                                               data-driven modal an injection risk.
 *                                               Cards using gloss() or <code> set this.
 * @param array  $opts     'intro' => string  one line above the sections, escaped
 *                         'size'  => string  modal-dialog size class, default 'modal-lg'
 * @return string HTML for the modal. Place it anywhere in the page body.
 */
function help_modal(string $modal_id, string $title, array $sections, array $opts = []): string {
    $id    = htmlspecialchars($modal_id, ENT_QUOTES);
    $size  = $opts['size'] ?? 'modal-lg';
    $intro = trim((string)($opts['intro'] ?? ''));

    $h  = '<div class="modal fade help-modal" id="' . $id . '" tabindex="-1"';
    $h .= ' aria-labelledby="' . $id . '-label" aria-hidden="true">';
    $h .= '<div class="modal-dialog ' . htmlspecialchars($size, ENT_QUOTES) . ' modal-dialog-scrollable">';
    $h .= '<div class="modal-content">';

    $h .= '<div class="modal-header py-2">';
    $h .= '<h5 class="modal-title fw-bold" id="' . $id . '-label">' . htmlspecialchars($title) . '</h5>';
    $h .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
    $h .= '</div>';

    $h .= '<div class="modal-body">';
    if ($intro !== '') {
        $h .= '<p class="text-muted small mb-4">' . htmlspecialchars($intro) . '</p>';
    }

    foreach ($sections as $section) {
        $cards = $section['cards'] ?? [];
        if (!$cards) {
            continue;   // an empty section is a data gap, not a heading to render
        }

        // Each section is wrapped so its sticky heading is scoped to its OWN section.
        // Without the wrapper every heading shares .modal-body as its containing block,
        // so each one sticks for the entire remaining scroll: they pile up at the top
        // and cover the cards. Measured — three headings pinned at once, with card
        // titles half-hidden behind them.
        $h .= '<div class="help-modal-sec">';

        $heading = trim((string)($section['heading'] ?? ''));
        if ($heading !== '') {
            $h .= '<h6 class="help-modal-section">' . htmlspecialchars($heading) . '</h6>';
        }

        // Optional one-line note under the heading — a caveat or scope statement that
        // applies to the whole section rather than any one card.
        $note = trim((string)($section['note'] ?? ''));
        if ($note !== '') {
            $h .= '<p class="text-muted small mb-3">' . htmlspecialchars($note) . '</p>';
        }

        $h .= '<div class="row g-3">';
        foreach ($cards as $card) {
            $label = trim((string)($card['label'] ?? ''));
            $text  = (string)($card['text'] ?? '');
            if ($label === '' && $text === '') {
                continue;
            }
            $body = !empty($card['html']) ? $text : htmlspecialchars($text);

            // An 'accent' card is set apart from its siblings — a worked example, a caveat —
            // with a slightly different wash and border. Quiet on purpose: it should read as
            // "this one is different in kind", not as an alert.
            $card_cls = 'card h-100 help-card' . (!empty($card['accent']) ? ' help-card-accent' : '');
            $h .= '<div class="col-md-6"><div class="' . $card_cls . '"><div class="card-body">';
            if ($label !== '') {
                // Optional step number, rendered as the SAME .step-badge circle the page
                // uses for its numbered steps (css/display.css). Passing 'num' makes a help
                // card a visual echo of the step it describes, so the reader maps the help
                // onto the workflow in front of them. 'num' and 'color' are mutually
                // exclusive — a card is either a step or a badge, not both.
                $num = isset($card['num']) ? trim((string)$card['num']) : '';
                if ($num !== '') {
                    $h .= '<h6 class="card-title mb-2 d-flex align-items-center gap-2">'
                        . '<span class="step-badge">' . htmlspecialchars($num) . '</span>'
                        . '<span>' . htmlspecialchars($label) . '</span></h6>';
                } elseif (!empty($card['color'])) {
                    // Badge form — for when the page itself shows this same badge, so the
                    // eye can match the help to the thing it just looked at.
                    $h .= '<h6 class="card-title mb-2"><span class="badge bg-'
                        . htmlspecialchars((string)$card['color'], ENT_QUOTES) . '">'
                        . htmlspecialchars($label) . '</span></h6>';
                } else {
                    $h .= '<h6 class="card-title mb-2">' . htmlspecialchars($label) . '</h6>';
                }
            }
            $h .= '<p class="card-text small text-muted mb-0">' . $body . '</p>';
            $h .= '</div></div></div>';
        }
        $h .= '</div>';   // .row
        $h .= '</div>';   // .help-modal-sec
    }

    $h .= '</div></div></div></div>';
    return $h;
}

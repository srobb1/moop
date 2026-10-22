<?php
/**
 * One folding card per site-health check on the admin dashboard.
 *
 * Every check used to be drawn its own way -- a green alert, a red alert, a plain card, a
 * row of text -- so the eye had to learn each one, and a check that was fine simply
 * vanished, which reads the same as a check that never ran. Now each is the same card:
 * the header names the check and states its result in badges, the detail is folded
 * underneath, and the admin's open/closed choice is remembered per browser
 * (js/modules/remember-collapse.js). Header tone follows CLAUDE.md §6: the quiet adm-head
 * unless the check reports something that needs attention.
 *
 * The header markup matches _data_health_card.php's collapsible header.
 */

/**
 * @param string $id      element id stem; also the remembered-state key
 * @param string $icon    Font Awesome name without "fa-"
 * @param string $title   plain text
 * @param array  $badges  list of [text, colour] -- colour: success|warning|danger|info|secondary
 * @param string $tone    '' | 'warn' | 'danger'
 * @param string $body    HTML, rendered inside .card-body
 * @param bool   $open    start unfolded regardless of the remembered state (e.g. work in progress)
 */
function adm_status_card(string $id, string $icon, string $title, array $badges, string $tone,
                         string $body, bool $open = false): string {
    $head   = $tone === 'danger' ? 'adm-head-danger' : ($tone === 'warn' ? 'adm-head-warn' : 'adm-head');
    $border = $tone === 'danger' ? ' border-danger' : ($tone === 'warn' ? ' border-warning' : '');
    $body_id = htmlspecialchars($id, ENT_QUOTES) . 'Body';

    $h  = '<div class="card mb-3 adm-card' . $border . '" id="' . htmlspecialchars($id, ENT_QUOTES) . 'Card">';
    $h .= '<div class="card-header ' . $head . ' d-flex align-items-center justify-content-between gap-2 position-relative">';
    $h .= '<h5 class="mb-0">';
    $h .= '<button type="button" class="btn btn-link p-0 border-0 text-reset text-decoration-none fw-semibold stretched-link adm-collapse-toggle'
        . ($open ? '' : ' collapsed') . '" data-bs-toggle="collapse" data-bs-target="#' . $body_id . '"'
        . ' aria-expanded="' . ($open ? 'true' : 'false') . '" aria-controls="' . $body_id . '">';
    $h .= '<i class="fa fa-chevron-down adm-chevron me-1"></i>';
    $h .= '<i class="fa fa-' . htmlspecialchars($icon, ENT_QUOTES) . ' me-1"></i> ' . htmlspecialchars($title);
    $h .= '</button></h5>';
    $h .= '<div class="d-flex flex-wrap gap-1 justify-content-end">';
    foreach ($badges as [$text, $colour]) {
        $h .= '<span class="badge bg-' . htmlspecialchars($colour, ENT_QUOTES)
            . ($colour === 'warning' || $colour === 'info' ? ' text-dark' : '') . '">' . htmlspecialchars($text) . '</span>';
    }
    $h .= '</div></div>';
    $h .= '<div id="' . $body_id . '" class="collapse' . ($open ? ' show' : '') . '"'
        . ($open ? '' : ' data-remember-collapse="moop.dashboard.' . htmlspecialchars($id, ENT_QUOTES) . '"') . '>';
    $h .= '<div class="card-body">' . $body . '</div></div></div>';
    return $h;
}

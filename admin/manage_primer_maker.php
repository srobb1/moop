<?php
/**
 * MANAGE PRIMER MAKER — Admin Controller
 *
 * The cards of Primer Maker's "What are you making?" step, for THIS site: switch the built-in cards
 * on or off, choose the one selected when the page opens, set the order, and add the site's own
 * cards (name and description in each language, product size, primer length / Tm / GC, and a tag
 * the card selects). Built for cuttingclass (Sofia, 2026-11-06): no qPCR, plus a card with the
 * WISH/RNAi protocol's settings. Logic and validation: lib/primer/PrimerPresets.php.
 *
 * Saves 'primer_maker' in config/config_editable.json. A site that never saves keeps the shipped
 * page. CSRF is verified in admin_init.php on every POST. POST-redirect-GET.
 */
include_once __DIR__ . '/admin_init.php';
include_once __DIR__ . '/../includes/layout.php';
include_once __DIR__ . '/../lib/primer/Primer3Design.php';
include_once __DIR__ . '/../lib/primer/PrimerTails.php';
include_once __DIR__ . '/../lib/primer/PrimerPresets.php';

$config_dir  = $config->getPath('root_path') . '/' . $config->getString('site') . '/config';
$config_file = "$config_dir/config_editable.json";
$file_write_error = getFileWriteError($config_file);

$settings = PrimerPresets::settings($config->getArray('primer_maker'));
$langs    = $config->languageCodes();
$tails    = PrimerTails::catalogue($config->getArray('primer_tails'));

$flash_and_back = function (string $type, string $msg, string $query = '') {
    $_SESSION['primer_flash'] = ['type' => $type, 'msg' => $msg];
    header('Location: manage_primer_maker.php' . $query);
    exit;
};
$save = function (array $s) use ($config, $config_dir, $flash_and_back) {
    $r = $config->saveEditableConfig(['primer_maker' => $s], $config_dir);
    if (empty($r['success'])) {
        $flash_and_back('danger', 'Not saved: ' . htmlspecialchars($r['message'] ?? 'unknown error'));
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($file_write_error) $flash_and_back('danger', 'Not saved: the settings file is read-only (see below).');
    $action = (string)($_POST['_action'] ?? '');
    $all    = PrimerPresets::all($settings);
    $id     = (string)($_POST['id'] ?? '');

    if ($action === 'save_cards') {
        // The form lists every card; the ticked ones stay on. A POST that lost its fields would
        // read as "all off" -- refused, the same rule as Manage Tools.
        $listed = array_map('strval', (array)($_POST['listed'] ?? []));
        if (array_diff(array_keys($all), $listed)) {
            $flash_and_back('danger', 'Not saved: the request did not come from this form. Nothing was changed.');
        }
        $on = array_map('strval', (array)($_POST['on'] ?? []));
        if (!array_intersect(array_keys($all), $on)) {
            $flash_and_back('danger', 'Not saved: keep at least one card on, or Primer Maker has nothing to offer.');
        }
        $default = (string)($_POST['default'] ?? '');
        if ($default !== '' && !in_array($default, $on, true)) {
            $flash_and_back('danger', 'Not saved: the card selected when the page opens must be one that is on.');
        }
        $settings['cards_off'] = array_values(array_diff(array_keys($all), $on));
        $settings['default']   = isset($all[$default]) ? $default : '';
        $save($settings);
        $flash_and_back('success', 'Saved.');
    }

    if ($action === 'move' && isset($all[$id])) {
        $order = array_keys($all);
        $i = array_search($id, $order, true);
        $j = ($_POST['dir'] ?? '') === 'up' ? $i - 1 : $i + 1;
        if ($j >= 0 && $j < count($order)) {
            [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
            $settings['order'] = $order;
            $save($settings);
        }
        header('Location: manage_primer_maker.php');
        exit;
    }

    if ($action === 'save_card') {
        [$card, $errors] = PrimerPresets::validateCard($_POST, $langs, array_keys($tails));
        if ($errors) {
            $_SESSION['primer_card_form'] = $_POST;
            $flash_and_back('danger', 'Not saved:<ul class="mb-0">' . implode('', array_map(
                fn($e) => '<li>' . htmlspecialchars($e) . '</li>', $errors)) . '</ul>',
                $id !== '' ? '?edit=' . urlencode($id) . '#card-form' : '?new=1#card-form');
        }
        $ids = array_column($settings['custom'], 'id');
        if ($id !== '' && in_array($id, $ids, true)) {
            $card['id'] = $id;
            $settings['custom'][array_search($id, $ids, true)] = $card;
            $msg = 'Saved the card “' . htmlspecialchars($card['label']['en']) . '”.';
        } else {
            $card = ['id' => PrimerPresets::newId($card['label']['en'], array_merge($ids, array_keys(PrimerPresets::BUILTIN)))] + $card;
            $settings['custom'][] = $card;
            $msg = 'Added the card “' . htmlspecialchars($card['label']['en']) . '”. It is on, at the end of the list.';
        }
        $save($settings);
        $flash_and_back('success', $msg);
    }

    if ($action === 'delete_card' && PrimerPresets::isCustomId($id)) {
        $settings['custom']    = array_values(array_filter($settings['custom'], fn($c) => $c['id'] !== $id));
        $settings['order']     = array_values(array_diff($settings['order'], [$id]));
        $settings['cards_off'] = array_values(array_diff($settings['cards_off'], [$id]));
        if ($settings['default'] === $id) $settings['default'] = '';
        $save($settings);
        $flash_and_back('success', 'Deleted the card.');
    }

    $flash_and_back('danger', 'Not saved: the request did not come from this page. Nothing was changed.');
}

// ------------------------------------------------------------------- the card form
// Filled from: a rejected save (what was typed), ?edit=<custom id>, ?copy=<any id>, or empty (?new=1).
$form = null;
$form_id = '';
$all = PrimerPresets::all($settings);
$field_values = function (array $params): array {
    $v = ['size_range' => (string)($params['PRIMER_PRODUCT_SIZE_RANGE'] ?? '')];
    foreach (PrimerPresets::CARD_FIELDS as $f) {
        $tag = Primer3Design::OPTIONS[$f]['tag'];
        $v[$f] = isset($params[$tag]) ? rtrim(rtrim(number_format((float)$params[$tag], 1, '.', ''), '0'), '.') : '';
    }
    return $v;
};
if (isset($_SESSION['primer_card_form'])) {
    $form = $_SESSION['primer_card_form'];
    $form_id = (string)($form['id'] ?? '');
    unset($_SESSION['primer_card_form']);
} elseif (isset($_GET['edit'])) {
    foreach ($settings['custom'] as $c) {
        if ($c['id'] === $_GET['edit']) {
            $form = $c + $field_values(PrimerPresets::cardParams($c));
            $form_id = $c['id'];
        }
    }
} elseif (isset($_GET['copy']) && isset($all[$_GET['copy']])) {
    $src = $all[$_GET['copy']];
    $raw = null;
    foreach ($settings['custom'] as $c) if ($c['id'] === $_GET['copy']) $raw = $c;
    $form = $field_values($src['params']) + [
        'label' => $raw ? array_map(fn($t) => "$t (copy)", (array)$raw['label']) : ['en' => $src['label'] . ' (copy)'],
        'blurb' => $raw ? (array)($raw['blurb'] ?? []) : ['en' => $src['blurb']],
        'tail'  => $src['tail'],
    ];
} elseif (isset($_GET['new'])) {
    $form = [];
}

$flash = $_SESSION['primer_flash'] ?? null;
unset($_SESSION['primer_flash']);

// The badges a visitor sees on each card, from the same merge Primer Maker does.
$summary = function (array $params): string {
    $e = array_merge(Primer3Design::DEFAULTS, $params);
    $n = fn($x) => rtrim(rtrim(number_format((float)$x, 1, '.', ''), '0'), '.');
    return 'Product ' . str_replace('-', '–', (string)$e['PRIMER_PRODUCT_SIZE_RANGE']) . ' bp · '
         . 'Tm ' . $n($e['PRIMER_MIN_TM']) . '–' . $n($e['PRIMER_MAX_TM']) . ' °C (best ' . $n($e['PRIMER_OPT_TM']) . ') · '
         . 'GC ' . $n($e['PRIMER_MIN_GC']) . '–' . $n($e['PRIMER_MAX_GC']) . '% · '
         . 'Length ' . $n($e['PRIMER_MIN_SIZE']) . '–' . $n($e['PRIMER_MAX_SIZE']) . ' nt';
};

$data = [
    'site'             => $config->getString('site'),
    'cards'            => $all,
    'settings'         => $settings,
    'summary'          => $summary,
    'tails'            => $tails,
    'langs'            => $langs,
    'form'             => $form,
    'form_id'          => $form_id,
    'defaults'         => $field_values(Primer3Design::DEFAULTS),
    'option_specs'     => Primer3Design::OPTIONS,
    'tool_on'          => $config->isToolEnabled('primer_maker'),
    'flash'            => $flash,
    'file_write_error' => $file_write_error,
    'config_file'      => $config_file,
];

echo render_display_page(__DIR__ . '/pages/manage_primer_maker.php', $data, 'Primer Maker');

<?php
/**
 * Editing the main navigation bar (cuttingclass step 3). The bar has ONE definition -- the
 * site's 'main_menu' (ConfigManager::mainMenu(), drawn by includes/toolbar.php) -- and two
 * places that edit it: Admin -> Manage Menu (everything) and a content page's "Show in menu"
 * choice (Manage Pages). Both go through these functions, so placing a page from its editor and
 * arranging the menu can never disagree: there is no second copy of where a page sits.
 *
 * Drop-downs are referred to by their KEY, never by position -- positions change as the menu is
 * reordered; a key does not.
 */

/** Where a page sits in the menu: 'none', 'top', or 'dd:<key>' (its first appearance). */
function moop_menu_page_place(array $menu, string $slug): string {
    foreach ($menu as $item) {
        if (($item['type'] ?? '') === 'link' && ($item['to']['page'] ?? null) === $slug) return 'top';
        if (($item['type'] ?? '') === 'dropdown') {
            foreach ((array) ($item['items'] ?? []) as $child) {
                if (($child['to']['page'] ?? null) === $slug) return 'dd:' . ($item['key'] ?? '');
            }
        }
    }
    return 'none';
}

/** The drop-downs a page can go under: [key => English label]. */
function moop_menu_dropdowns(array $menu): array {
    $out = [];
    foreach ($menu as $item) {
        if (($item['type'] ?? '') === 'dropdown') $out[(string) $item['key']] = (string) ($item['label']['en'] ?? $item['key']);
    }
    return $out;
}

/** The menu with every link to this page taken out. */
function moop_menu_without_page(array $menu, string $slug): array {
    $out = [];
    foreach ($menu as $item) {
        if (($item['type'] ?? '') === 'link' && ($item['to']['page'] ?? null) === $slug) continue;
        if (($item['type'] ?? '') === 'dropdown') {
            $item['items'] = array_values(array_filter((array) ($item['items'] ?? []),
                fn($c) => ($c['to']['page'] ?? null) !== $slug));
        }
        $out[] = $item;
    }
    return $out;
}

/**
 * Put a page where its editor says: 'none' takes it out; 'top' or 'dd:<key>' keeps it where it
 * already is if it is already there (so its place within the menu is not lost on every save),
 * otherwise moves it to the END of the menu bar or of that drop-down. The link's label follows
 * the page title, in every language the page has one.
 */
function moop_menu_set_page(array $menu, string $slug, string $where, array $titles): array {
    $label = array_filter(array_map('strval', $titles), fn($t) => trim($t) !== '');
    $here  = moop_menu_page_place($menu, $slug);
    if ($where === $here && $where !== 'none') {
        // Same place: refresh the label only.
        foreach ($menu as &$item) {
            if (($item['type'] ?? '') === 'link' && ($item['to']['page'] ?? null) === $slug) $item['label'] = $label;
            if (($item['type'] ?? '') === 'dropdown') {
                foreach ($item['items'] as &$child) {
                    if (($child['to']['page'] ?? null) === $slug) $child['label'] = $label;
                }
                unset($child);
            }
        }
        unset($item);
        return $menu;
    }
    $menu = moop_menu_without_page($menu, $slug);
    $link = ['type' => 'link', 'label' => $label, 'to' => ['page' => $slug]];
    if ($where === 'top') {
        $menu[] = $link;
    } elseif (strpos($where, 'dd:') === 0) {
        $key = substr($where, 3);
        foreach ($menu as &$item) {
            if (($item['type'] ?? '') === 'dropdown' && ($item['key'] ?? '') === $key) { $item['items'][] = $link; break; }
        }
        unset($item);
    }
    return $menu;
}

/**
 * Save a menu: unchanged from the default is stored as EMPTY, so a site that changes nothing keeps
 * following the default as it evolves. Returns saveEditableConfig()'s ['success', 'message'].
 */
function moop_menu_save(array $menu): array {
    $config = ConfigManager::getInstance();
    // An emptied label (a Spanish box typed in, then cleared) is no label: drop it, so the menu
    // compares equal to the default when it is the default.
    $tidy = function (array $item) {
        if (isset($item['label']) && is_array($item['label'])) {
            $item['label'] = array_filter($item['label'], fn($t) => trim((string) $t) !== '');
        }
        return $item;
    };
    $menu = array_map(function ($item) use ($tidy) {
        $item = $tidy($item);
        if (($item['type'] ?? '') === 'dropdown') $item['items'] = array_map($tidy, (array) ($item['items'] ?? []));
        return $item;
    }, $menu);
    $value  = $menu == ConfigManager::defaultMainMenu() ? [] : array_values($menu);
    $dir    = $config->getPath('root_path') . '/' . $config->getString('site') . '/config';
    return $config->saveEditableConfig(['main_menu' => $value], $dir);
}

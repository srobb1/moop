<?php
/**
 * Primer Maker's "What are you making?" cards: the four built in, plus the ones a site adds.
 *
 * A CARD is a named starting point -- a few Primer3 numbers, and optionally a 5' tag it selects.
 * The built-in cards live here, in code, and a site can only switch them on or off or reorder them:
 * their numbers stay as shipped, so an improvement here reaches every site. A site that wants other
 * numbers copies a card into one of its own (Sofia, 2026-11-06: cuttingclass hides qPCR and adds a
 * "WISH / RNAi probe" card with the protocol's settings).
 *
 * Saved per site in config_editable.json under 'primer_maker', edited on Admin -> Primer Maker
 * (admin/manage_primer_maker.php):
 *   cards_off  [card ids]     built-in or custom cards not shown
 *   default    card id        the card selected when the page opens ('' = the first shown)
 *   order      [card ids]     display order; ids not listed follow, built-ins first
 *   custom     [card, ...]    the site's own cards, see CARD_FIELDS
 * A site that never saved the key gets exactly the shipped page: the four cards, in this order,
 * Standard PCR selected. (simrbase; proven by scripts/render_compare.php.)
 *
 * RT-PCR is not a setting: on an assembly that is transcripts only, a junction card is never shown,
 * because its exon junctions are unknown and it could only fail. That is decided per request
 * (forSite()'s $is_transcripts), so a site with both kinds of assembly keeps it where it works.
 */
class PrimerPresets
{
    /**
     * The shipped cards. Labels and blurbs are English and go through the translation catalog where
     * they are printed. THE BLURB SAYS WHY YOU WOULD PICK IT; the numbers are rendered as badges from
     * 'params' merged over Primer3Design::DEFAULTS, so they are never written out twice.
     */
    const BUILTIN = [
        'standard' => [
            'label'  => 'Standard PCR',
            // 200-900 (was 100-400, 2026-11-03): a gel band is easiest to see between a few hundred bp
            // and 1 kb; 100 bp runs near the primer-dimer smear. Also covers RNAi templates (with the
            // T7 tag), so there is no separate RNAi preset.
            'blurb'  => 'General-purpose primers — cloning, screening, checking a band on a gel. '
                      . 'For an RNAi template, add the T7 promoter tag below.',
            'params' => ['PRIMER_PRODUCT_SIZE_RANGE' => '200-900'],
        ],
        'qpcr' => [
            'label'  => 'qPCR',
            'blurb'  => 'Short products, which amplify efficiently enough to be measured in real time.',
            'params' => [
                'PRIMER_PRODUCT_SIZE_RANGE' => '70-150',
                'PRIMER_OPT_TM'             => 60.0,
                'PRIMER_MIN_TM'             => 58.0,
                'PRIMER_MAX_TM'             => 62.0,
            ],
        ],
        'rtpcr' => [
            'label'  => 'RT-PCR (spans an exon junction)',
            // "or pick a transcript below" was confusing (user, 2026-08-07): the transcript picker only
            // exists when you arrived from a gene page whose gene has several isoforms.
            'blurb'  => 'Forces a primer across an exon junction, so the pair cannot amplify '
                      . 'contaminating genomic DNA. Needs to know where the junctions are — arrive '
                      . 'from a gene page and they are looked up for you.',
            // 150-600 (2026-11-03): a clear gel band, kept below Standard because reverse
            // transcription covers long stretches less reliably, especially from partly degraded RNA.
            'params' => ['PRIMER_PRODUCT_SIZE_RANGE' => '150-600'],
        ],
        'sequencing' => [
            'label'  => 'Sequencing',
            'blurb'  => 'Longer products, for reading right through a region in one go.',
            'params' => [
                'PRIMER_PRODUCT_SIZE_RANGE' => '400-900',
                'PRIMER_MIN_TM'             => 55.0,
                'PRIMER_MAX_TM'             => 65.0,
            ],
        ],
    ];

    /** Cards that must place a primer across an exon junction (unusable on a transcript assembly). */
    const JUNCTION_CARDS = ['rtpcr'];

    /**
     * The numbers a custom card may set: form field => Primer3Design::OPTIONS key. Blank = the
     * shipped default (Primer3Design::DEFAULTS). GC clamp, salt correction etc. are left to
     * "More options" on purpose: a card is a starting point, not every knob.
     */
    const CARD_FIELDS = ['size_min', 'size_opt', 'size_max', 'tm_min', 'tm_opt', 'tm_max', 'gc_min', 'gc_max'];

    /** Custom card ids carry this prefix, so one can never shadow a built-in card. */
    const CUSTOM_PREFIX = 'c_';

    /** The saved settings, read defensively: any missing or malformed part means "as shipped". */
    public static function settings($saved): array
    {
        $saved = is_array($saved) ? $saved : [];
        $ids = fn($v) => is_array($v) ? array_values(array_unique(array_map('strval', $v))) : [];
        $custom = [];
        foreach ((is_array($saved['custom'] ?? null) ? $saved['custom'] : []) as $card) {
            if (is_array($card) && self::isCustomId((string)($card['id'] ?? ''))) $custom[] = $card;
        }
        return [
            'cards_off' => $ids($saved['cards_off'] ?? []),
            'default'   => (string)($saved['default'] ?? ''),
            'order'     => $ids($saved['order'] ?? []),
            'custom'    => $custom,
        ];
    }

    public static function isCustomId(string $id): bool
    {
        return (bool)preg_match('/^' . self::CUSTOM_PREFIX . '[a-z0-9_]{1,40}$/', $id);
    }

    /** A new custom id from a label, unique among $taken. */
    public static function newId(string $label, array $taken): string
    {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($label)), '_');
        $slug = substr($slug !== '' ? $slug : 'card', 0, 30);
        $id = self::CUSTOM_PREFIX . $slug;
        for ($n = 2; in_array($id, $taken, true); $n++) $id = self::CUSTOM_PREFIX . $slug . '_' . $n;
        return $id;
    }

    /**
     * Every card the site knows -- shown or not -- in display order. Each entry:
     *   label, blurb   text to print (custom: already in $lang; built-in: English, for t())
     *   params         Primer3 tags this card sets
     *   tail           tag id it selects, or ''
     *   custom         true for a site card
     *   off            true if switched off
     */
    public static function all($saved, string $lang = 'en'): array
    {
        $s = self::settings($saved);
        $cards = [];
        foreach (self::BUILTIN as $id => $c) {
            $cards[$id] = $c + ['tail' => '', 'custom' => false];
        }
        foreach ($s['custom'] as $c) {
            $cards[$c['id']] = [
                'label'  => self::text($c['label'] ?? null, $lang),
                'blurb'  => self::text($c['blurb'] ?? null, $lang),
                'params' => self::cardParams($c),
                'tail'   => (string)($c['tail'] ?? ''),
                'custom' => true,
            ];
        }
        $ordered = [];
        foreach ($s['order'] as $id) {
            if (isset($cards[$id])) $ordered[$id] = $cards[$id];
        }
        $ordered += $cards;
        foreach ($ordered as $id => &$c) $c['off'] = in_array($id, $s['cards_off'], true);
        return $ordered;
    }

    /**
     * The cards a visitor sees here: switched-on, and no junction card on a transcript assembly.
     * Never empty -- if a site switched everything off, Standard PCR is shown rather than a form
     * with nothing to choose.
     */
    public static function forSite($saved, string $lang = 'en', bool $is_transcripts = false): array
    {
        $shown = [];
        foreach (self::all($saved, $lang) as $id => $c) {
            if ($c['off']) continue;
            if ($is_transcripts && in_array($id, self::JUNCTION_CARDS, true)) continue;
            unset($c['off']);
            $shown[$id] = $c;
        }
        if (!$shown) {
            $shown['standard'] = self::BUILTIN['standard'] + ['tail' => '', 'custom' => false];
        }
        return $shown;
    }

    /**
     * Which card is selected: the one asked for (a link or a posted form) if it is shown, else the
     * site's default if shown, else the first. As shipped that is Standard PCR, as before.
     */
    public static function choose(array $shown, string $requested, $saved): string
    {
        if ($requested !== '' && isset($shown[$requested])) return $requested;
        $default = self::settings($saved)['default'];
        if ($default !== '' && isset($shown[$default])) return $default;
        return (string)array_key_first($shown);
    }

    /** One language's text: that language, else English, else the first given. */
    public static function text($value, string $lang): string
    {
        if (is_string($value)) return $value;
        if (!is_array($value)) return '';
        foreach ([$lang, 'en'] as $l) {
            if (isset($value[$l]) && trim((string)$value[$l]) !== '') return (string)$value[$l];
        }
        foreach ($value as $v) {
            if (trim((string)$v) !== '') return (string)$v;
        }
        return '';
    }

    /** A custom card's saved numbers as Primer3 tags (blank fields set nothing). */
    public static function cardParams(array $card): array
    {
        $params = [];
        $range = (string)($card['size_range'] ?? '');
        if ($range !== '') $params['PRIMER_PRODUCT_SIZE_RANGE'] = $range;
        foreach (self::CARD_FIELDS as $field) {
            $v = $card[$field] ?? null;
            if ($v === null || $v === '') continue;
            $spec = Primer3Design::OPTIONS[$field];
            $params[$spec['tag']] = $spec['type'] === 'int' ? (int)$v : (float)$v;
        }
        return $params;
    }

    /**
     * Check and tidy a card from the admin form. Returns [card, errors]. The bounds are
     * Primer3Design::OPTIONS -- the same ones Primer Maker's own form enforces -- so a card can never
     * hold a number the tool would refuse. min <= opt <= max is checked on the values the card will
     * really run with (its own, or the shipped default where left blank).
     *
     * @param array  $in         label[lang], blurb[lang], size_range, CARD_FIELDS..., tail
     * @param array  $langs      the site's language codes; English is required
     * @param array  $tail_ids   tag ids that exist (PrimerTails::catalogue)
     */
    public static function validateCard(array $in, array $langs, array $tail_ids): array
    {
        $errors = [];
        $card = ['label' => [], 'blurb' => []];
        foreach (array_unique(array_merge(['en'], $langs)) as $l) {
            $label = trim((string)($in['label'][$l] ?? ''));
            $blurb = trim((string)($in['blurb'][$l] ?? ''));
            if (mb_strlen($label) > 80)  $errors[] = "The name ($l) is longer than 80 characters.";
            if (mb_strlen($blurb) > 400) $errors[] = "The description ($l) is longer than 400 characters.";
            if ($label !== '') $card['label'][$l] = $label;
            if ($blurb !== '') $card['blurb'][$l] = $blurb;
        }
        if (!isset($card['label']['en'])) $errors[] = 'The card needs a name in English.';

        $range = preg_replace('/\s+/', '', (string)($in['size_range'] ?? ''));
        if ($range === '') {
            $errors[] = 'The card needs a product size, as low-high (e.g. 1000-2500).';
        } elseif (!preg_match('/^(\d+)-(\d+)$/', $range, $m) || (int)$m[1] < 1 || (int)$m[1] >= (int)$m[2]) {
            $errors[] = 'Product size must be low-high with low below high, e.g. 1000-2500.';
        } elseif ((int)$m[2] > 20000) {
            $errors[] = 'Product size above 20,000 bp is not something PCR will make.';
        } else {
            $card['size_range'] = $range;
        }

        foreach (self::CARD_FIELDS as $field) {
            $raw = trim((string)($in[$field] ?? ''));
            if ($raw === '') continue;
            $spec = Primer3Design::OPTIONS[$field];
            if (!is_numeric($raw)) { $errors[] = $spec['label'] . ' must be a number.'; continue; }
            $v = $spec['type'] === 'int' ? (int)round((float)$raw) : round((float)$raw, 1);
            if ($v < $spec['min'] || $v > $spec['max']) {
                $errors[] = $spec['label'] . " must be between {$spec['min']} and {$spec['max']}.";
                continue;
            }
            $card[$field] = $v;
        }
        $eff = array_merge(Primer3Design::DEFAULTS, self::cardParams($card));
        $tag = fn($f) => $eff[Primer3Design::OPTIONS[$f]['tag']] ?? null;
        foreach (['size' => 'Primer length', 'tm' => 'Tm'] as $p => $name) {
            [$lo, $opt, $hi] = [$tag("{$p}_min"), $tag("{$p}_opt"), $tag("{$p}_max")];
            if ($lo !== null && $opt !== null && $hi !== null && !($lo <= $opt && $opt <= $hi)) {
                $errors[] = "$name must be minimum ≤ optimum ≤ maximum (it would run as $lo / $opt / $hi).";
            }
        }
        [$glo, $ghi] = [$tag('gc_min'), $tag('gc_max')];
        if ($glo !== null && $ghi !== null && $glo > $ghi) {
            $errors[] = "GC content minimum must not be above the maximum (it would run as $glo / $ghi).";
        }

        $tail = trim((string)($in['tail'] ?? ''));
        if ($tail !== '' && $tail !== 'none') {
            if (!in_array($tail, $tail_ids, true)) $errors[] = 'That tag is not one Primer Maker offers.';
            else $card['tail'] = $tail;
        }
        return [$card, $errors];
    }
}

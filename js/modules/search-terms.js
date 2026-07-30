/**
 * search-terms.js — ONE definition of "is this search input usable?"
 *
 * Loaded globally from includes/head-resources.php, like csrf.js, so every search entry
 * point tests the same rule. It used to be `keywords.length < 3` copied into two files,
 * with a third file applying `length >= 3` per term for highlighting — three expressions
 * of one idea, none of which agreed.
 *
 * ---------------------------------------------------------------------------
 * THE RULE, AND WHY IT IS PER-TERM
 *
 * Terms are AND-ed into a prefix query (see buildFtsMatchExpr in database_queries.php), so
 * a short term ALONGSIDE others is harmless -- the other terms constrain it. A short term
 * ALONE is not. Measured on Myotis_myotis:
 *
 *   "1"*                                      1,855,918 rows   <- alone: useless
 *   "histone"* AND "deacetylase"*                 9,258 rows   <- short term dropped
 *   "histone"* AND "deacetylase"* AND "1"*        4,254 rows   <- short term KEPT
 *
 * So keeping the short term is BETTER when accompanied: it halves the result set and the
 * top hits become correctly HDAC1-specific instead of every HDAC undifferentiated. MOOP
 * used to drop short terms; that was hurting precision, and it no longer does.
 *
 * ---------------------------------------------------------------------------
 * A NOTE ON THE TOKENIZER, because it constrains highlighting
 *
 * 'porter unicode61' is PURE SUFFIX-STRIPPING -- no dictionary, no irregular forms.
 * Verified against the tokenizer itself:
 *
 *   bind / binds / binding  -> all three match each other
 *   bound                   -> matches ONLY bound (does NOT reach bind)
 *   ran                     -> only ran     (does not reach run)
 *   mice                    -> only mice    (does not reach mouse)
 *   proteins                -> protein, proteins
 *
 * The useful consequence: every collapse porter performs PRESERVES A COMMON PREFIX. That
 * is what makes prefix-shortening a complete strategy for highlighting a stem match --
 * there is no pair porter unifies that shares no prefix, so trimming the typed term from
 * the right will always reach the matched form. A JS re-implementation of porter would buy
 * nothing and would have to stay in sync with SQLite's forever.
 * ---------------------------------------------------------------------------
 *
 * The old gate tested TOTAL INPUT LENGTH >= 3, which got both halves wrong. It blocked
 * real two-character gene symbols outright -- the search did not run at all, so the user
 * got no results AND no explanation:
 *
 *   AR (androgen receptor) · C2 C3 C5 C6 C7 C9 (complement) · F2 F3 F5 F7 F8 (coagulation
 *   factors, F8 = haemophilia A) · CS · PC · SI · CP · FH
 *
 * ...while `histone deacetylase 1` passed only because the whole string is 21 characters.
 *
 * One character really is different: `a` matches 1,164,973 rows against a 2,500 cap, so
 * the user receives 2,500 arbitrary rows. An honest refusal beats that.
 *
 * Hence: REQUIRE AT LEAST ONE TERM OF 2+ CHARACTERS. `F8` passes. `1` and `a` do not.
 * `histone deacetylase 1` passes on `histone`, and the `1` is still sent.
 *
 * Mirrored server-side by moop_search_input_is_usable() -- the JS copy is a courtesy that
 * gives a fast, specific message; the PHP copy is the one that actually protects the
 * database, because a hand-made request bypasses this file entirely.
 * ---------------------------------------------------------------------------
 */

/** A term shorter than this cannot stand on its own. */
const MOOP_MIN_TERM_LENGTH = 2;

/**
 * Split input the same way the FTS expression builder does: on whitespace, keeping only
 * terms that contain a letter or a digit. Quoted input is ONE phrase, not a term list.
 */
function moopSearchTerms(input) {
    const trimmed = String(input || '').trim();
    if (trimmed === '') return [];

    // "zinc finger" is a single phrase query; its length is the phrase's length.
    if (/^".*"$/.test(trimmed)) {
        const phrase = trimmed.slice(1, -1).trim();
        return phrase === '' ? [] : [phrase];
    }

    return trimmed.split(/\s+/).filter(t => /[\p{L}\p{N}]/u.test(t));
}

/**
 * True when the input can be searched: at least one term long enough to stand alone.
 * A short term is fine as long as SOMETHING in the query is selective.
 */
function moopSearchInputIsUsable(input) {
    return moopSearchTerms(input).some(t => t.length >= MOOP_MIN_TERM_LENGTH);
}

/** The message to show when it is not usable. Kept here so all pages word it identically. */
function moopSearchInputHint() {
    return 'Enter at least ' + MOOP_MIN_TERM_LENGTH + ' characters in one word — '
         + 'single letters match too much to be useful. Short words are fine '
         + 'alongside longer ones, e.g. "histone deacetylase 1".';
}

/**
 * Terms worth highlighting in results. Same threshold as the gate, deliberately: if a
 * 2-character search is allowed to run, its match must be highlighted, or the results
 * table renders rows with nothing marked and reads as broken.
 */
function moopHighlightableTerms(input) {
    return moopSearchTerms(input).filter(t => t.length >= MOOP_MIN_TERM_LENGTH);
}

/**
 * Find what to highlight for one term in one piece of text.
 *
 * Returns { text, exact } where `exact` says whether the user's own word was found, or
 * null when this term is not the reason this cell matched.
 *
 * WHY THIS IS NEEDED AT ALL. FTS5 matched on the STEM, but the results table highlights by
 * searching for the literal string typed. Search `proteins`, match a record saying
 * `protein`, and the literal search finds nothing -- so the row arrives with nothing
 * marked and the user cannot tell why it is there.
 *
 * The obvious fix, FTS5's own highlight(), IS NOT AVAILABLE TO US: our index is contentless
 * (content='' -- see the FTS DDL), so the %_content shadow table that highlight() re-reads
 * does not exist and the function returns NULL. That was a deliberate trade; _content was
 * 38-49% of every database and dropping it is most of the 66 GB -> 32.7 GB reduction that
 * got the search working set inside the page cache. This function is the bill for it.
 *
 * Trimming the typed term from the right is COMPLETE for this tokenizer, not a heuristic:
 * porter only strips suffixes, so any two words it unifies share a prefix (see the
 * tokenizer note above). A null result therefore means the term genuinely is not in THIS
 * cell -- e.g. `F8` matched on feature_name while the description reads "coagulation
 * factor VIII" -- and marking nothing is the correct answer, not a failure.
 */
function moopTermHighlight(text, term, exactFoundInRow) {
    if (!text || !term) return null;
    const hay = String(text);
    const lower = hay.toLowerCase();

    // The literal term the user typed, wherever it appears.
    if (lower.indexOf(term.toLowerCase()) !== -1) {
        return { text: term, exact: true };
    }

    // The exact term was found SOMEWHERE ELSE in this row, so this row is explained and a
    // shortened guess here adds nothing but noise. Searching HDAC used to mark `HD` inside
    // "PHD finger protein 12" in the Description column while the Annotation Description
    // legitimately matched `HDAC` -- and because Description comes first, the wrong,
    // shorter mark is the one the user sees first.
    if (exactFoundInRow) return null;

    for (let n = term.length - 1; n >= MOOP_MIN_TERM_LENGTH; n--) {
        const probe = term.slice(0, n);
        const at = lower.indexOf(probe.toLowerCase());
        if (at === -1) continue;
        // A shortened probe must land at a WORD START. Porter only strips suffixes, so a
        // stem and its inflection share a prefix FROM THE BEGINNING OF THE WORD: `protein`
        // opens `proteins`. A mid-word hit is never that relationship -- `HD` inside `PHD`
        // is a coincidence of letters, not a stem. Without this test the trimming loop will
        // eventually find almost any short prefix somewhere in a long description.
        if (at > 0 && /[A-Za-z0-9]/.test(hay[at - 1])) {
            // Try later positions of the same probe before giving up on this length.
            let next = lower.indexOf(probe.toLowerCase(), at + 1);
            let ok = false;
            while (next !== -1) {
                if (!/[A-Za-z0-9]/.test(hay[next - 1])) { ok = true; break; }
                next = lower.indexOf(probe.toLowerCase(), next + 1);
            }
            if (!ok) continue;
        }
        return { text: probe, exact: false };
    }
    return null;
}

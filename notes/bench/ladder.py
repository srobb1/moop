#!/usr/bin/env python3
"""Does the pre-FTS graded ranking ladder beat bm25 -- without bm25's I/O cost?

HISTORY. Before d691848 the ORDER BY was a six-level CASE that distinguished WHOLE-WORD
matches from substring matches, and ranked by WHERE the match landed (gene name >
feature description > annotation). It was implemented with a PHP REGEXP callback invoked
per row, which is why it could not survive an 85-database fan-out. Switching MATCHING to
FTS5 was correct. But the ladder was flattened to two substring LIKE tiers in the same
commit, and bm25 was brought in to fill the gap -- at 60% of all cold search I/O.

This restores the ladder in PURE SQL (no callback, no REGEXP) evaluated only on the
pooled rows, and asks whether it ranks as well as bm25 or better.

ARMS -- pool held constant so this measures RANKING, not selection:
  A  today      name LIKE, annotation LIKE, has-name, bm25
  B  restored   the 1-6 ladder exactly as it was pre-FTS
  C  improved   the ladder, plus graded whole-word/word-start/substring tiers for
                annotation_description, which the original lumped into one level --
                this IS an annotation search, so that column deserves the same grading

METRICS. 100/100 cannot see what matters here: it asks "does the substring appear",
which is what the LIKE tiers already test, so it agrees with whatever it measures. It
scored a clean 100/100 on a pool that had silently dropped every ProtNLM annotation.
So we report, for the top 100:
  whole-word   rows where the term appears as a WHOLE WORD, not inside another word
  sources      distinct annotation sources represented (diversity)
  ProtNLM      rows from the human-readable protein-name source
"""
import os, re, sys, sqlite3, time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from fts_split import ORGDB, POOL, evict, read_bytes

F = "feature_annotation_search"
CAP = 2500

# Emulate \b in pure SQL: turn punctuation into spaces, pad with spaces, then LIKE.
# Hyphen included on purpose -- \b treats it as a boundary, so "helicase-like" must
# count as a whole-word hit for "helicase".
PUNCT = [",", ".", "-", "/", "(", ")", ";", ":", "[", "]", "'", '"']


def norm(col):
    e = f"lower(COALESCE({col},''))"
    for p in PUNCT:
        # A literal apostrophe inside a SQL string is written as two apostrophes;
        # emitting it raw closes the string and produces a syntax error.
        lit = "''''" if p == "'" else f"'{p}'"
        e = f"replace({e},{lit},' ')"
    return f"' ' || {e} || ' '"


def word(col):   # whole word
    return f"{norm(col)} LIKE :w"


def start(col):  # word start
    return f"{norm(col)} LIKE :s"


def sub(col):    # anywhere
    return f"lower(COALESCE({col},'')) LIKE :nm"


LADDERS = {
    "A today": ("(f.feature_name LIKE :nm) DESC, "
                "(a.annotation_description LIKE :nm) DESC, "
                "(COALESCE(f.feature_name,'') <> '') DESC, pool.r,"),
    "B restored": (
        "CASE"
        f" WHEN {word('f.feature_name')} THEN 1"
        f" WHEN {start('f.feature_name')} THEN 2"
        f" WHEN lower(COALESCE(f.feature_description,'')) LIKE :pre THEN 3"
        f" WHEN {start('f.feature_description')} THEN 4"
        f" WHEN {sub('f.feature_description')} THEN 5"
        f" WHEN {word('a.annotation_description')} THEN 6"
        " ELSE 7 END,"),
    "C improved": (
        "CASE"
        f" WHEN {word('f.feature_name')} THEN 1"
        f" WHEN {start('f.feature_name')} THEN 2"
        f" WHEN {word('a.annotation_description')} THEN 3"
        f" WHEN lower(COALESCE(f.feature_description,'')) LIKE :pre THEN 4"
        f" WHEN {start('a.annotation_description')} THEN 5"
        f" WHEN {start('f.feature_description')} THEN 6"
        f" WHEN {sub('a.annotation_description')} THEN 7"
        f" WHEN {sub('f.feature_description')} THEN 8"
        " ELSE 9 END,"
        " (COALESCE(f.feature_name,'') <> '') DESC,"),
}


def run(term, arm, pool_mode="bm25"):
    if pool_mode == "bm25":
        cte = (f"WITH pool AS (SELECT rowid AS rid, bm25({F},10.0,5.0,2.0,3.0) AS r "
               f"FROM {F} WHERE {F} MATCH :t ORDER BY r LIMIT {POOL})")
    else:
        cte = (f"WITH pool AS (SELECT rowid AS rid, 0 AS r "
               f"FROM {F} WHERE {F} MATCH :t ORDER BY rowid LIMIT {POOL})")
    sql = cte + f"""
        SELECT f.feature_uniquename, f.feature_name, a.annotation_description,
               ans.annotation_source_name
        FROM pool
        JOIN feature_annotation fa ON fa.feature_annotation_id = pool.rid
        JOIN feature f ON f.feature_id = fa.feature_id
        JOIN annotation a ON a.annotation_id = fa.annotation_id
        JOIN annotation_source ans ON ans.annotation_source_id = a.annotation_source_id
        ORDER BY {LADDERS[arm]} f.feature_uniquename
        LIMIT {CAP}"""
    p = {"t": term, "nm": f"%{term}%", "w": f"% {term} %",
         "s": f"% {term}%", "pre": f"{term}%"}
    evict(ORGDB)
    b0, t0 = read_bytes(), time.perf_counter()
    c = sqlite3.connect(f"file:{ORGDB}?mode=ro", uri=True)
    rows = c.execute(sql, p).fetchall()
    c.close()
    return rows, time.perf_counter() - t0, (read_bytes() - b0) / 1048576


def score(rows, term):
    top = rows[:100]
    wb = re.compile(rf"\b{re.escape(term)}\b", re.I)
    whole = sum(1 for r in top if wb.search(r[1] or "") or wb.search(r[2] or ""))
    subs = sum(1 for r in top if term.lower() in ((r[1] or "") + (r[2] or "")).lower())
    srcs = {r[3] for r in top}
    return whole, subs, len(srcs), sum(1 for r in top if r[3] == "ProtNLM")


if __name__ == "__main__":
    terms = sys.argv[1:] or ["piwi", "pax", "helicase", "ubiquitin", "kinase", "binding"]
    print(f"\n  RANKING ARMS — same bm25 pool, so this compares ORDER BY only")
    print(f"  top 100: whole-word hits / substring hits / distinct sources / ProtNLM rows\n")
    print(f"  {'term':11} {'arm':12} {'whole':>6} {'subst':>6} {'srcs':>5} {'ProtNLM':>8} {'MB':>7} {'s':>6}")
    for term in terms:
        print()
        for arm in LADDERS:
            rows, t, mb = run(term, arm)
            w, s, n, p = score(rows, term)
            print(f"  {term:11} {arm:12} {w:6d} {s:6d} {n:5d} {p:8d} {mb:7.1f} {t:6.2f}")

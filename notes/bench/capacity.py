#!/usr/bin/env python3
"""How many people can use MOOP at once? Drive real tool requests concurrently and watch the box.

Standard library only (same philosophy as the rest of the repo), so it runs anywhere with
python3 -- on the server itself, or from another machine such as cerebro.

WHAT IT DOES
  Each SCENARIO is one thing a person does, sent exactly as the browser sends it (the form
  fields were recorded from the real pages, not reconstructed from the PHP):
    blastp_nv        protein vs Nematostella NV2 proteins            (public)
    blastn_nv        transcript vs NV2 transcripts                   (public)
    tblastn_nv_gen   protein vs the Nematostella genome (~270 Mb)    (public)
    blastn_bat_gen   transcript vs the Myotis myotis genome (~2 Gb)  (needs in-range access)
    tblastn_bat_gen  protein vs the Myotis myotis genome  -- the heaviest common request
    tblastn_big_gen  protein vs the largest genome (wallaby, ~3.4 Gb) -- the worst case
    primer_maker     design 5 primer pairs on a transcript (primer3)
    primer_blast     check one pair against the NV2 genome
    search_one       annotation search, one organism
    search_bats      annotation search across every bat, fanned out 5-wide like the page does
    name_one         gene name/ID search (PAX6, TP53, ...), one organism -- the name tier
    name_bats        gene name/ID search across every bat
    gene_page        one gene page (always the same small Nematostella gene: a warm baseline)
    gene_page_any    a gene page picked from earlier search results -- many organisms, cold rows
  The bat scenarios need data a PUBLIC visitor cannot see; they are skipped (and say so) when
  the probe at start-up finds the site will not show them to this client.

COMMANDS
  capacity.py list
  capacity.py ladder SCENARIO [--levels 1,2,4,6,8,10] [--rounds 2]
      For each level N, N simulated people press the button at the same instant; repeated
      --rounds times. Reports each level's median and slowest wait, errors, and (on the
      server) peak CPU, run queue and number of BLAST processes.
  capacity.py burst [--users 8] [--ramp 60] [--loops 2]
      The realistic case: people arrive over --ramp seconds and each does a session --
      search, open a gene, BLAST, design primers -- with think time between steps.
  capacity.py mixed [--viewers 4] [--searchers 4] [--duration 60]
      Do gene pages stay fast while other people search? First --viewers people open gene
      pages (picked from real search results) on their own for --duration/2 seconds, the
      baseline; then the same viewers carry on while --searchers people run cross-bat
      searches back to back. Reports gene-page wait before vs during.
  capacity.py evict
      (server only) drop every organism database and BLAST index from the page cache, so
      the next run measures COLD. Verified with mincore, not assumed.

  --base URL   site to test (default http://172.16.2.52/moop)
  --out DIR    where CSV/JSON results go (default ./capacity_results)

!! SAFETY -- THIS CAN TAKE THE SITE DOWN (it did, 2026-10-23)
  A genome tblastn holds 1.2 GB (bat) to 2.9 GB (wallaby) of RAM. Eight wallaby tblastns at
  once exhausted the 15 GB box; the kernel OOM-killed one BLAST, and because php-fpm runs
  with systemd's default OOMPolicy=stop + Restart=no, systemd then STOPPED ALL OF PHP-FPM.
  The site stayed down until `sudo systemctl start php-fpm`. So: the heavy genome scenarios
  refuse more than MAX_SAFE_HEAVY people at once unless you pass --allow-oom, and you
  should only pass it once php-fpm has OOMPolicy=continue (notes/CAPACITY_TEST_2026-10-23.md).

READING THE RESULTS
  Waits grow while CPU sits near 100% with a run queue above the core count -> CPU-bound:
  more cores, or cap concurrent BLASTs. Waits grow while CPU is idle and refaults climb ->
  the cache cannot hold the working set: more RAM. Errors (502/504) with neither -> a
  configuration limit (nginx's 60 s wait, php-fpm workers), which costs nothing to fix.

The server-side sampler only runs when this script runs ON the server (it reads /proc).
From cerebro you get the user-facing numbers only -- which is the point of running there:
they include the network, as a real user's do.
"""
import argparse, csv, http.cookiejar, json, os, re, statistics, sys, threading, time
import urllib.parse, urllib.request
from concurrent.futures import ThreadPoolExecutor

HERE = os.path.dirname(os.path.abspath(__file__))
QUERIES = json.load(open(os.path.join(HERE, "capacity_queries.json")))

NV = ("Nematostella_vectensis", "GCA_033964005.1", "NV2")
MM = ("Myotis_myotis", "GCF_014108235.1", "RS_100")
BIG = ("Notamacropus_eugenii", "Tammar_Female_v7_Final_Haploid_plus_Y", "T2T_helixer")   # largest genome, ~3.4 Gb
BAT_GROUP = "Bats"
SEARCH_NAMES = ["PAX6", "TP53", "BRCA2", "SOX2", "NOTCH1", "WNT5A", "GAPDH", "MYC", "HOXA1", "ACTB"]
SEARCH_TERMS = ["kinase", "transporter", "helicase", "zinc finger", "receptor", "ribosomal",
                "ubiquitin", "collagen", "homeobox", "dehydrogenase"]
HEAVY = {"tblastn_bat_gen", "tblastn_big_gen"}   # genome tblastn: 1.2-2.9 GB RAM each
MAX_SAFE_HEAVY = 4                               # 4 x 2.9 GB fits in 15 GB beside everything else
TIMEOUT = 900   # longer than any server limit, so the SERVER's timeout is what we observe


def fasta(rec):
    name, seq = rec
    return f">{name}\n{seq}\n"


# ---------------------------------------------------------------------------------------- client

class Client:
    """One simulated person: their own cookies (session), so the site treats them as one visitor."""

    def __init__(self, base):
        self.base = base.rstrip("/")
        self.jar = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
        self._csrf = None

    def req(self, path, data=None, params=None):
        url = self.base + path + ("?" + urllib.parse.urlencode(params) if params else "")
        body = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
        t = time.perf_counter()
        try:
            with self.op.open(urllib.request.Request(url, data=body), timeout=TIMEOUT) as r:
                text = r.read().decode("utf-8", "replace")
                return r.status, text, time.perf_counter() - t
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", "replace"), time.perf_counter() - t
        except Exception as e:                     # timeout, connection reset ...
            return 0, f"{type(e).__name__}: {e}", time.perf_counter() - t

    def csrf(self):
        if self._csrf is None:
            _, html, _ = self.req("/tools/primer_maker.php")
            m = re.search(r'name="csrf-token" content="([^"]+)"', html)
            self._csrf = m.group(1) if m else ""
        return self._csrf


# ------------------------------------------------------------------------------------ scenarios
# Each returns (ok, seconds, detail). ok means the page did the work, not just answered 200:
# a 200 carrying "no access" or an error alert is a failure.

def _blast(c, src, seq_type, program, rec):
    s = "|".join(src)
    st, html, dt = c.req("/tools/blast.php", {
        "query": fasta(rec), "blast_program": program, "selected_source": s,
        "blast_db": f"{s}|{seq_type}", "organism": src[0], "assembly": src[1],
        # The form's own defaults, sent by every real submission (recorded from the page).
        "evalue": "1e-3", "max_results": "50", "filter_seq": "on", "strand": "both",
        **({"matrix": "BLOSUM62"} if program in ("blastp", "blastx", "tblastn") else {})})
    if st != 200:
        return False, dt, f"HTTP {st}"
    m = re.search(r"([0-9,]+) hits found", html)
    if m:
        return True, dt, f"{m.group(1)} hits"
    if re.search(r"No hits", html, re.I):
        return True, dt, "0 hits"
    err = re.search(r"(do not have access|not found|does not belong)[^<]{0,80}", html)
    return False, dt, err.group(0) if err else "no result on page"


def _primer_blast(c):
    p = QUERIES["primers"]
    st, html, dt = c.req("/tools/primer_blast.php", {
        "csrf_token": c.csrf(), "primers": f">p1_F\n{p['fwd']}\n>p1_R\n{p['rev']}\n",
        "selected_source": "|".join(NV), "search_mode": "genome",
        "max_mismatch": "2", "max_product": "10000"})
    bad = re.search(r'alert-danger py-2[^>]*>(.{0,300}?)</div>', html, re.S)
    if st != 200 or bad:
        return False, dt, f"HTTP {st}" if st != 200 else re.sub(r"<[^>]+>|\s+", " ", bad.group(1))[:100]
    return True, dt, "searched"


def _primer_maker(c):
    st, html, dt = c.req("/tools/primer_maker.php", {
        "csrf_token": c.csrf(), "sequence": QUERIES["nv_transcript"][1], "primer_type": "standard",
        "num_return": "5", "salt_corr": "1", "tail": "none", "seq_type": "transcript"})
    m = re.search(r"(\d+) primer pairs?", html)
    return (st == 200 and bool(m)), dt, (f"{m.group(1)} pairs" if m else f"HTTP {st}, no pairs")


def _search(c, organisms, term, width=5):
    """The search page's fan-out: one request per organism, at most `width` in flight."""
    t = time.perf_counter()
    errors, hits = [], 0

    def one(org):
        st, body, _ = c.req("/tools/annotation_search_ajax.php",
                            params={"search_keywords": term, "organism": org, "quoted": "0"})
        if st != 200:
            return org, None, f"HTTP {st}"
        try:
            results = json.loads(body).get("results") or []
        except ValueError:
            return org, None, "not JSON"
        _remember_genes(results)
        return org, len(results), None

    with ThreadPoolExecutor(max_workers=width) as ex:
        for org, n, err in ex.map(one, organisms):
            if err:
                errors.append(f"{org}: {err}")
            else:
                hits += n
    dt = time.perf_counter() - t
    return (not errors), dt, (f"{hits} results, {len(organisms)} organisms"
                              + (f"; {len(errors)} failed ({errors[0]})" if errors else ""))


GENE_POOL, _pool_lock = [], threading.Lock()


def _remember_genes(results, keep=5):
    """Keep a few hits from each search, so gene pages are ones people would actually open."""
    import random
    with _pool_lock:
        for r in random.sample(results, min(keep, len(results))):
            g = (r.get("organism"), r.get("genome_accession"), r.get("gene_set"), r.get("feature_uniquename"))
            if all(g) and len(GENE_POOL) < 3000:
                GENE_POOL.append(g)


def _gene_page_any(c):
    import random
    with _pool_lock:
        g = random.choice(GENE_POOL) if GENE_POOL else None
    if g is None:
        return _gene_page(c)
    st, html, dt = c.req("/tools/parent.php", params={
        "organism": g[0], "assembly": g[1], "gene_set": g[2], "uniquename": g[3]})
    ok = st == 200 and g[3] in html
    return ok, dt, f"HTTP {st}" if ok else f"HTTP {st} {g[0]} {g[3]}"


def _gene_page(c):
    st, html, dt = c.req("/tools/parent.php", params={
        "organism": NV[0], "assembly": NV[1], "gene_set": NV[2], "uniquename": "NV2g025931000.1"})
    ok = st == 200 and "NV2g025931000.1" in html and "not found" not in html.lower()[:5000]
    return ok, dt, f"HTTP {st}"


_term_i = 0
_term_lock = threading.Lock()


def next_name():
    global _term_i
    with _term_lock:
        _term_i += 1
        return SEARCH_NAMES[_term_i % len(SEARCH_NAMES)]


def next_term():
    """Different people search for different things -- the same term every time is all-warm."""
    global _term_i
    with _term_lock:
        _term_i += 1
        return SEARCH_TERMS[_term_i % len(SEARCH_TERMS)]


BATS = []   # filled by probe()

SCENARIOS = {
    "blastp_nv":       (lambda c: _blast(c, NV, "protein", "blastp", QUERIES["nv_protein"]), False),
    "blastn_nv":       (lambda c: _blast(c, NV, "transcript", "blastn", QUERIES["nv_transcript"]), False),
    "tblastn_nv_gen":  (lambda c: _blast(c, NV, "genome", "tblastn", QUERIES["nv_protein"]), False),
    "blastn_bat_gen":  (lambda c: _blast(c, MM, "genome", "blastn", QUERIES["mm_transcript"]), True),
    "tblastn_bat_gen": (lambda c: _blast(c, MM, "genome", "tblastn", QUERIES["mm_protein"]), True),
    "tblastn_big_gen": (lambda c: _blast(c, BIG, "genome", "tblastn", QUERIES["mm_protein"]), True),
    "primer_maker":    (_primer_maker, False),
    "primer_blast":    (_primer_blast, False),
    "search_one":      (lambda c: _search(c, [NV[0]], next_term()), False),
    "search_bats":     (lambda c: _search(c, BATS, next_term()), True),
    "name_one":        (lambda c: _search(c, [NV[0]], next_name()), False),
    "name_bats":       (lambda c: _search(c, BATS, next_name()), True),
    "gene_page":       (_gene_page, False),
    "gene_page_any":   (_gene_page_any, False),
}


def probe(base):
    """Can this client see the in-range data? Decides which scenarios can run."""
    c = Client(base)
    st, html, _ = c.req("/tools/blast.php", params={"organism": MM[0]})
    in_range = st == 200 and "|".join(MM) in html
    if in_range:
        groups_file = "/var/www/html/moop/metadata/organism_assembly_groups.json"
        if os.path.exists(groups_file):
            entries = json.load(open(groups_file))
            BATS[:] = sorted({e["organism"] for e in entries if BAT_GROUP in (e.get("groups") or [])})
        else:   # off the server: the 49 bats are listed on the BLAST page's source radios
            BATS[:] = sorted(set(re.findall(r'value="([A-Z][a-z]+_[a-z]+)\|[^"|]+\|[^"]+"', html)) - {NV[0]})
    return in_range


# -------------------------------------------------------------------------------------- sampler

ON_SERVER = os.path.exists("/proc/stat") and os.path.exists("/var/www/html/moop/organisms")


class Sampler(threading.Thread):
    """Once a second: CPU busy %, runnable tasks, memory, page-cache refaults, BLAST processes."""

    def __init__(self):
        super().__init__(daemon=True)
        self.rows, self.stop_ev = [], threading.Event()

    @staticmethod
    def _cpu():
        f = open("/proc/stat").readline().split()[1:]
        v = list(map(int, f))
        idle = v[3] + v[4]
        return sum(v), idle

    @staticmethod
    def _stat_field(name):
        for line in open("/proc/stat"):
            if line.startswith(name):
                return int(line.split()[1])
        return 0

    @staticmethod
    def _vm(key):
        for line in open("/proc/vmstat"):
            if line.startswith(key + " "):
                return int(line.split()[1])
        return 0

    @staticmethod
    def _mem(key):
        for line in open("/proc/meminfo"):
            if line.startswith(key + ":"):
                return int(line.split()[1]) / 1024 ** 2
        return 0.0

    @staticmethod
    def _procs(names):
        n = 0
        for pid in os.listdir("/proc"):
            if pid.isdigit():
                try:
                    if open(f"/proc/{pid}/comm").read().strip() in names:
                        n += 1
                except OSError:
                    pass
        return n

    def run(self):
        tot0, idle0 = self._cpu()
        ref0 = self._vm("workingset_refault_file")
        while not self.stop_ev.wait(1.0):
            tot, idle = self._cpu()
            ref = self._vm("workingset_refault_file")
            self.rows.append({
                "t": time.time(),
                "cpu_pct": round(100 * (1 - (idle - idle0) / max(1, tot - tot0)), 1),
                "runq": self._stat_field("procs_running") - 1,          # minus this sampler
                "blocked": self._stat_field("procs_blocked"),
                "mem_avail_gb": round(self._mem("MemAvailable"), 2),
                "cached_gb": round(self._mem("Cached"), 2),
                "refaults": ref - ref0,
                "blast_procs": self._procs({"blastn", "blastp", "blastx", "tblastn", "tblastx"}),
                "fpm_procs": self._procs({"php-fpm"}),
            })
            tot0, idle0, ref0 = tot, idle, ref

    def window(self, t0, t1):
        rows = [r for r in self.rows if t0 <= r["t"] <= t1 + 1]
        if not rows:
            return {}
        return {"cpu_peak": max(r["cpu_pct"] for r in rows),
                "cpu_mean": round(statistics.mean(r["cpu_pct"] for r in rows), 1),
                "runq_peak": max(r["runq"] for r in rows),
                "blast_peak": max(r["blast_procs"] for r in rows),
                "refaults": sum(r["refaults"] for r in rows),
                "mem_avail_min_gb": min(r["mem_avail_gb"] for r in rows)}


# ------------------------------------------------------------------------------------- commands

def ladder(a, sampler):
    fn, needs_range = SCENARIOS[a.scenario]
    levels = [int(x) for x in a.levels.split(",")]
    out = []
    print(f"\n{a.scenario}: {a.rounds} round(s) per level")
    print(f"{'users':>5} {'median':>8} {'slowest':>8} {'errors':>6}   server during the level")
    for n in levels:
        times, errs, details = [], 0, set()
        t0 = time.time()
        for _ in range(a.rounds):
            barrier = threading.Barrier(n)
            clients = [Client(a.base) for _ in range(n)]

            def go(c):
                barrier.wait()               # everyone presses the button at once
                return fn(c)

            with ThreadPoolExecutor(max_workers=n) as ex:
                for ok, dt, detail in ex.map(go, clients):
                    times.append(dt)
                    errs += 0 if ok else 1
                    details.add(detail if not ok else re.sub(r"\d+", "N", detail))
            time.sleep(a.pause)
        t1 = time.time()
        srv = sampler.window(t0, t1) if sampler else {}
        row = {"scenario": a.scenario, "users": n, "requests": len(times),
               "median_s": round(statistics.median(times), 2), "max_s": round(max(times), 2),
               "errors": errs, "detail": "; ".join(sorted(details))[:200], **srv}
        out.append(row)
        srv_txt = (f"cpu peak {srv['cpu_peak']:.0f}% mean {srv['cpu_mean']:.0f}%, run queue {srv['runq_peak']}, "
                   f"BLAST procs {srv['blast_peak']}, refaults {srv['refaults']}") if srv else ""
        print(f"{n:>5} {row['median_s']:>7.2f}s {row['max_s']:>7.2f}s {errs:>6}   {srv_txt}"
              + (f"   !! {row['detail']}" if errs else ""))
        sys.stdout.flush()
    return out


def burst(a, sampler, in_range):
    """People arrive over the ramp and each does a realistic session with think time."""
    import random
    rnd = random.Random(42)
    steps = ["search_bats" if in_range else "search_one", "gene_page",
             "tblastn_bat_gen" if in_range else "tblastn_nv_gen", "blastp_nv", "primer_maker", "primer_blast"]
    results, lock = [], threading.Lock()

    def person(i):
        time.sleep(a.ramp * i / max(1, a.users - 1))
        c = Client(a.base)
        for _ in range(a.loops):
            for step in steps:
                ok, dt, detail = SCENARIOS[step][0](c)
                with lock:
                    results.append({"user": i, "step": step, "ok": ok, "s": round(dt, 2),
                                    "detail": detail, "t": time.time()})
                time.sleep(rnd.uniform(a.think_min, a.think_max))

    t0 = time.time()
    with ThreadPoolExecutor(max_workers=a.users) as ex:
        list(ex.map(person, range(a.users)))
    t1 = time.time()

    print(f"\nburst: {a.users} people over {a.ramp}s, {a.loops} session(s) each, "
          f"think {a.think_min}-{a.think_max}s  ({t1 - t0:.0f}s total)")
    print(f"{'step':<16} {'n':>3} {'median':>8} {'slowest':>8} {'errors':>6}")
    summary = []
    for step in steps:
        rows = [r for r in results if r["step"] == step]
        if not rows:
            continue
        ts = [r["s"] for r in rows]
        bad = [r for r in rows if not r["ok"]]
        summary.append({"step": step, "n": len(rows), "median_s": round(statistics.median(ts), 2),
                        "max_s": max(ts), "errors": len(bad)})
        print(f"{step:<16} {len(rows):>3} {statistics.median(ts):>7.2f}s {max(ts):>7.2f}s {len(bad):>6}"
              + (f"   !! {bad[0]['detail']}" if bad else ""))
    if sampler:
        w = sampler.window(t0, t1)
        print(f"server: cpu peak {w['cpu_peak']:.0f}% mean {w['cpu_mean']:.0f}%, run queue peak {w['runq_peak']}, "
              f"BLAST procs peak {w['blast_peak']}, refaults {w['refaults']}, min MemAvailable {w['mem_avail_min_gb']} GB")
    return {"summary": summary, "requests": results}


def mixed(a, sampler, in_range):
    """Gene-page waits alone, then while other people run cross-organism searches."""
    import random
    orgs = BATS if in_range else [NV[0]]
    print(f"priming the gene pool from searches over {len(orgs)} organism(s)...")
    c0 = Client(a.base)
    for term in SEARCH_NAMES[:3] + SEARCH_TERMS[:2]:
        _search(c0, orgs, term)
    print(f"  {len(GENE_POOL)} genes from {len({g[0] for g in GENE_POOL})} organisms")

    results, lock = [], threading.Lock()
    phase = {"name": "alone"}
    stop = threading.Event()
    searching = threading.Event()

    def viewer(i):
        rnd = random.Random(i)
        c = Client(a.base)
        while not stop.is_set():
            ok, dt, detail = _gene_page_any(c)
            with lock:
                results.append({"phase": phase["name"], "kind": "gene_page", "ok": ok, "s": dt, "detail": detail})
            time.sleep(rnd.uniform(0.5, 2.0))

    def searcher(i):
        c = Client(a.base)
        searching.wait()
        n = 0
        while not stop.is_set():
            term = (SEARCH_NAMES if n % 2 else SEARCH_TERMS)[(i + n) % 10]
            ok, dt, detail = _search(c, orgs, term)
            with lock:
                results.append({"phase": "during", "kind": "search", "ok": ok, "s": dt, "detail": detail})
            n += 1

    threads = [threading.Thread(target=viewer, args=(i,), daemon=True) for i in range(a.viewers)]
    threads += [threading.Thread(target=searcher, args=(i,), daemon=True) for i in range(a.searchers)]
    for t in threads:
        t.start()
    t0 = time.time()
    time.sleep(a.duration / 2)
    phase["name"] = "during"
    t1 = time.time()
    searching.set()
    time.sleep(a.duration)
    stop.set()
    for t in threads:
        t.join(timeout=120)
    t2 = time.time()

    def line(label, rows, t_a, t_b):
        if not rows:
            return
        ts = sorted(r["s"] for r in rows)
        bad = [r for r in rows if not r["ok"]]
        p95 = ts[min(len(ts) - 1, int(0.95 * len(ts)))]
        srv = sampler.window(t_a, t_b) if sampler else {}
        print(f"{label:<34} {len(ts):>4} {statistics.median(ts):>7.2f}s {p95:>7.2f}s {max(ts):>7.2f}s {len(bad):>5}"
              + (f"   cpu peak {srv['cpu_peak']:.0f}% mean {srv['cpu_mean']:.0f}%, refaults {srv['refaults']}" if srv else "")
              + (f"   !! {bad[0]['detail']}" if bad else ""))

    print(f"\nmixed: {a.viewers} people opening gene pages; then {a.searchers} more searching "
          f"{len(orgs)} organisms back to back")
    print(f"{'':<34} {'n':>4} {'median':>8} {'p95':>8} {'slowest':>8} {'errs':>5}")
    line("gene pages, alone", [r for r in results if r["phase"] == "alone" and r["kind"] == "gene_page"], t0, t1)
    line(f"gene pages, during {a.searchers} searchers", [r for r in results if r["phase"] == "during" and r["kind"] == "gene_page"], t1, t2)
    line(f"searches ({len(orgs)} organisms each)", [r for r in results if r["kind"] == "search"], t1, t2)
    return {"requests": results}


def evict_all():
    sys.path.insert(0, HERE)
    from cache import evict, resident
    root = "/var/www/html/moop/organisms"
    files = []
    for dp, _, fns in os.walk(root, followlinks=False):
        for fn in fns:
            if fn == "organism.sqlite" or re.search(r"\.(n|p)(sq|hr|in|db|os|ot|tf|to|og)$", fn):
                files.append(os.path.join(dp, fn))
    left = 0
    for f in files:
        try:
            evict(f)
            r, _ = resident(f)
            left += r
        except OSError:
            pass
    print(f"evicted {len(files)} files; {left * os.sysconf('SC_PAGESIZE') / 1024**2:.0f} MB still resident (verified)")


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("command", choices=["list", "ladder", "burst", "mixed", "evict"])
    ap.add_argument("scenario", nargs="?")
    ap.add_argument("--base", default="http://172.16.2.52/moop")
    ap.add_argument("--out", default="capacity_results")
    ap.add_argument("--levels", default="1,2,4,6,8,10")
    ap.add_argument("--rounds", type=int, default=2)
    ap.add_argument("--pause", type=float, default=2.0, help="seconds between rounds")
    ap.add_argument("--users", type=int, default=8)
    ap.add_argument("--ramp", type=float, default=60)
    ap.add_argument("--loops", type=int, default=1)
    ap.add_argument("--think-min", type=float, default=5)
    ap.add_argument("--think-max", type=float, default=20)
    ap.add_argument("--viewers", type=int, default=4)
    ap.add_argument("--searchers", type=int, default=4)
    ap.add_argument("--duration", type=float, default=60)
    ap.add_argument("--allow-oom", action="store_true", help="lift the heavy-BLAST concurrency guard (see SAFETY)")
    a = ap.parse_args()

    if a.command == "list":
        for k in SCENARIOS:
            print(k)
        return
    if a.command == "evict":
        if not ON_SERVER:
            sys.exit("evict only works on the server")
        evict_all()
        return

    in_range = probe(a.base)
    print(f"client sees in-range data: {in_range}" + ("" if in_range else " -- bat scenarios skipped")
          + (f" ({len(BATS)} bats)" if in_range else ""))
    sampler = Sampler() if ON_SERVER else None
    if sampler:
        sampler.start()
    os.makedirs(a.out, exist_ok=True)
    stamp = time.strftime("%Y%m%d-%H%M%S")

    if a.command == "ladder":
        if a.scenario not in SCENARIOS:
            sys.exit(f"unknown scenario; one of: {', '.join(SCENARIOS)}")
        if SCENARIOS[a.scenario][1] and not in_range:
            sys.exit(f"{a.scenario} needs in-range access, which this client does not have")
        top = max(int(x) for x in a.levels.split(","))
        if a.scenario in HEAVY and top > MAX_SAFE_HEAVY and not a.allow_oom:
            sys.exit(f"{a.scenario} at {top} people can exhaust RAM and stop php-fpm (see SAFETY in --help). "
                     f"Use --levels up to {MAX_SAFE_HEAVY}, or --allow-oom once php-fpm has OOMPolicy=continue.")
        rows = ladder(a, sampler)
        path = os.path.join(a.out, f"ladder-{a.scenario}-{stamp}.csv")
        with open(path, "w", newline="") as fh:
            w = csv.DictWriter(fh, fieldnames=list(rows[0]))
            w.writeheader()
            w.writerows(rows)
    elif a.command == "mixed":
        res = mixed(a, sampler, in_range)
        path = os.path.join(a.out, f"mixed-{stamp}.json")
        json.dump(res, open(path, "w"), indent=1)
    else:
        if a.users > MAX_SAFE_HEAVY * 2 and not a.allow_oom:
            # each burst session includes one heavy (bat) tblastn; arrivals are spread, so
            # 2x the guard is the most that stays clear of memory exhaustion in practice
            sys.exit(f"burst with more than {MAX_SAFE_HEAVY * 2} people can exhaust RAM (see SAFETY); pass --allow-oom to override")
        res = burst(a, sampler, in_range)
        path = os.path.join(a.out, f"burst-{stamp}.json")
        json.dump(res, open(path, "w"), indent=1)
    if sampler:
        sampler.stop_ev.set()
        json.dump(sampler.rows, open(os.path.join(a.out, f"server-{a.command}-{stamp}.json"), "w"))
    print(f"results: {path}")


if __name__ == "__main__":
    main()

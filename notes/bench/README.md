# Cold-search benchmark harness (2026-07-28)

Kept because every cold measurement has to be re-done after the reload, and because
the previous round's numbers did not reproduce — eviction was assumed, not verified.

| script | what it answers |
|---|---|
| `cache.py` | evict a file from page cache and PROVE it with mincore. Everything else depends on this. |
| `bench.py` | cold + warm timing of MOOP's real annotation-search query |
| `working_set.py` | bytes actually faulted in by one cold query (mincore delta) — the number the RAM ask rests on |
| `prerank.py` | index-first ranking vs the general query shape |
| `prerank_correct.py` | does pre-ranking change what the user sees, at the 2500 cap |
| `disk_latency.py` | random 4K read latency per volume. ⚠️ **Do not trust it:** its `evict()` is advisory, so it measured page cache, wrong by ~60× (`notes/archive/IT_REQUEST_RAM_INCREASE.md` §5). Use `disk_random_read.py` (O_DIRECT) |

Usage: `python3 cache.py evict <file>`, then the others. Paths are hardcoded to
Nematostella; edit the `DB` constant for a reloaded organism.

RULES, learned the hard way:
- ALWAYS verify eviction (`cache.py` prints residency before/after). A cold number
  taken without verifying is not reproducible.
- Time cold AND warm back to back. Investigating warms the cache and hides the effect.
- Benchmark on the SAME volume as production. /tmp is on sda (flash); the data is on
  sdb. Measuring in /tmp answers a question nobody asked.

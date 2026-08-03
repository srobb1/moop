# Third-party code and attribution

## locBLAST — with thanks

MOOP's BLAST result viewer was **inspired by locBLAST** (Ashok Rajaram, GPL-3.0,
https://github.com/AshokHub/locBLAST, bioRxiv 556225). It was our reference for turning
BLAST+ XML into something a biologist can read — the three-line alignment block, the
bit-score colour key, the per-HSP coverage view. If MOOP is written up, locBLAST belongs
in the citations.

**No locBLAST code is used.** An earlier version of `formatBlastAlignment()` was derived
from its `fmtprint()`. It was rewritten from scratch on 2026-08-03; the replacement lives
in `lib/blast_results_visualizer.php`, with its own tests in
`tests/blast_alignment_tests.php`. MOOP is MIT licensed — see `LICENSE` at the repository
root.

One thing that is shared but is not locBLAST's to license: `getHspColorClass()` uses the
score bands ≤40 / ≤50 / ≤80 / ≤200 / >200 in black / blue / green / purple / red. That is
**NCBI's own published BLAST colour key**, which locBLAST was itself reproducing.

## References

- locBLAST: https://github.com/AshokHub/locBLAST
- NCBI BLAST+: https://blast.ncbi.nlm.nih.gov/
- MIT License (MOOP): https://opensource.org/licenses/MIT

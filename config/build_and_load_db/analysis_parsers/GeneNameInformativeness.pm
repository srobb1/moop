package GeneNameInformativeness;
use strict;
use warnings;
use Exporter 'import';
our @EXPORT_OK = qw(is_informative_name is_placeholder_symbol);

# Shared "is this gene's own RefSeq/Ensembl name worth keeping" test, used by
# get_names_from_gff.pl (native names) to decide when to fall back to homology
# naming (assign_gene_names.pl), and safe to reuse on a homology CANDIDATE too --
# an RBBH hit that itself resolves to "predicted gene, 12345" is not an improvement.
#
# Patterns below are drawn from real RefSeq/Ensembl/FlyBase/WormBase/ZFIN/SGD
# annotation sampled 2026-09 across RefSeq (mammals, fish, Nematostella,
# Drosophila, Bradyrhizobium) and Ensembl (human, mouse, chicken, xenopus, medaka,
# anole, pogona, astyanax + cavefish, lamprey, C. elegans, yeast, E. coli).
# See the conversation this was drafted in for per-species counts -- this list is
# a starting point, expected to grow as more gene sets are reviewed.

# Substring/prefix matches against the DESCRIPTION. Deliberately narrow in a few
# places where real informative text collides with an obvious word:
#   - "putative protein" is exact-only. "putative ABC transporter ATP-binding
#     protein YadG" (E. coli) and "putative ankyrin repeat protein RF_0381"
#     (Amphimedon) are real annotation; only the bare phrase is a placeholder.
#   - "unknown function" (not "protein of unknown function") because yeast SGD
#     wraps it in a whole family of phrasings ("gene of unknown function",
#     "eisosome with unknown function", ...), each inside a long free-text essay.
my @UNINFORMATIVE_DESC_RE = (
    qr/uncharacterized\s+protein/i,
    qr/uncharacterized\s+LOC\d+/i,
    qr/hypothetical\s+protein/i,
    qr/predicted\s+protein/i,
    qr/unnamed\s+protein\s+product/i,
    qr/unknown\s+protein/i,
    qr/unknown\s+function/i,
    qr/predicted\s+gene,?\s*\d+/i,                # mouse Gm##### / MGI
    qr/\bnovel\s+(protein|gene|transcript)\b/i,   # Ensembl, cross-species
    qr/dubious\s+open\s+reading\s+frame/i,        # yeast SGD
    qr/unlikely\s+to\s+encode\s+a\s+functional\s+protein/i,
    qr/^putative\s+protein$/i,
    qr/^(?:si|zgc|wu):/i,                         # zebrafish clone-based, propagates via orthology projection into other fish
);

# Symbol-only placeholder shapes. NEVER disqualify a gene on these alone --
# plenty of RefSeq genes carry a LOC symbol with a perfectly good description
# (LOC102416810 -> "E3 ubiquitin-protein ligase HUWE1"). They only matter
# combined with a description that also failed the checks above, or with no
# description at all.
my @PLACEHOLDER_SYMBOL_RE = (
    qr/^LOC\d+$/i,
    qr/^CG\d+$/i,
    qr/^CR\d+$/i,
    qr/^Gm\d+$/i,
    qr/^(?:si|zgc|wu):/i,
);

# is_informative_name($id, $symbol, $description) -> 1 or 0
#
# $id is the gene's own identifier, used only to catch a description that just
# echoes the symbol/id back with a generic noun tacked on ("si:ch211-132g1.3
# precursor") -- not to gate on symbol shape (see PLACEHOLDER_SYMBOL_RE above).
sub is_informative_name {
    my ($id, $symbol, $desc) = @_;
    $id     //= '';
    $symbol //= '';
    $desc   //= '';

    # A leading quality flag is a caveat on a real name, not an unknown gene:
    # "LOW QUALITY PROTEIN: titin" -- judge the remainder.
    (my $test = $desc) =~ s/^\s*LOW QUALITY PROTEIN:\s*//i;

    # Isoform/variant suffixes ride along on both real and boilerplate names
    # ("acetyl-CoA carboxylase 2 isoform X1" vs "uncharacterized protein isoform
    # A") -- strip before judging what's left.
    $test =~ s/,?\s*(?:isoform\s+\S+|transcript\s+variant\s+\S+)\s*$//i;
    $test =~ s/^\s+|\s+$//g;

    return 0 if $test eq '';

    for my $re (@UNINFORMATIVE_DESC_RE) {
        return 0 if $test =~ $re;
    }

    # Description that's just the symbol/id echoed back with a generic noun --
    # circular, not information ("si:ch211-132g1.3 precursor").
    for my $echo (grep { length } ($symbol, $id)) {
        return 0 if $test =~ /^\Q$echo\E\s*(?:precursor|protein|gene\s+product)?$/i;
    }

    return 1;
}

# is_placeholder_symbol($symbol) -> 1 or 0 -- exposed for callers that want to
# log/report placeholder-symbol prevalence; not used to gate informativeness.
sub is_placeholder_symbol {
    my ($symbol) = @_;
    return 0 unless defined $symbol && length $symbol;
    for my $re (@PLACEHOLDER_SYMBOL_RE) {
        return 1 if $symbol =~ $re;
    }
    return 0;
}

1;

#!/usr/bin/perl
use strict;
use warnings;
use Getopt::Long;
use FindBin;
use lib "$FindBin::Bin";
use GeneNamingV2 qw(clean_name split_symbol is_placeholder_symbol is_informative_hit
                    add_like_to_description add_like_to_symbol load_hgnc hgnc_record);
use OmaHogOrthologs qw(read_hog_orthologs parse_oma_header read_id_map target_ids);

# Gene naming v2: a name for every gene and, separately, its closest human gene.
# Design: notes/NAMING_V2_PLAN.md.
#
#   assign_gene_names_v2.pl --isoforms isoforms.tsv --protein-fasta protein.aa.fa \
#       --hgnc-dir moop/hgnc [--oma-dir OMA_v2/<org>/<asm>/<gs> --oma-code CODE] \
#       [--mmseqs-dir <analysis>/rbh_mmseq] [--diamond-dir <analysis>/diamond] [--ref-db REF_DB] \
#       [--compara-dir moop/ensembl_compara] [--uniprot-dir moop/uniprot] \
#       [--taxonomy-dir moop/ncbi_taxonomy] [--native native_geneNames.tsv] \
#       [--interproscan interproscan_results.tsv[.gz] --interpro-entries moop/interpro/entry.list \
#        --panther-hmm-lengths moop/panther/hmm_lengths.tsv] \
#       [--human-curated-gene-names curated.moop.tsv ...] \
#       [--closest-species 'tag=Nvec|species=Nematostella vectensis|label=sea anemone|oma_code=NEMVE|hits=FILE|use_for_names=0|same_species=0' ...] \
#       --out-names geneNames.tsv --out-dir DIR
#
# Closest human gene, strongest first (the tier is the Score of the moop table):
#   1 OMA pairwise ortholog to HUMAN
#   2 OMA HOG co-ortholog with HUMAN (only when parameters.drw has a fixed SpeciesTree)
#   3 MMseqs2 reciprocal best hit to Ensembl human (NORMAL filter)
#   4 via another species: OMA ortholog in a reference species -> its OMA HUMAN ortholog, or
#     MMseqs2 reciprocal best hit -> Ensembl Compara human ortholog (same Ensembl release)
#   5 DIAMOND best hit to a human protein (Ensembl human or a Swiss-Prot HUMAN entry, NORMAL)
#   6 DIAMOND Swiss-Prot hit in another species -> its Ensembl gene -> Ensembl Compara
#   7 DIAMOND Swiss-Prot hit in another species -> its PANTHER subfamily -> the human
#     Swiss-Prot genes in that subfamily
# Similarity hits must report coverage (NORMAL: E <= 1e-10, both coverages >= 50%); DIAMOND
# output without coverage columns is not used. Several human genes (a 1:many or many:many
# family), or tier 7 (a PANTHER subfamily), are reported as ONE family entry, never as a
# member picked by score. Notes/GENE_NAMING_METHODS.md has the full method.
#
# Closest gene in another species (--closest-species, from geneset_config.yaml): OMA ortholog to
# oma_code (1:1, many:1, 1:many, many:many, in that order; several genes = a family), else the
# best hit in its hits file.
#
# Names -- a plain name only from an orthology call, "-like" for full-length similarity, a
# family name when the evidence stops at the family, else no name:
#   1 human-curated name, as given (never checked)
#   2 native name if informative; or the closest-species entry with use_for_names (its OMA 1:1
#     or many:1 ortholog, else its hits file; "-like (label)" unless same_species)
#   3 OMA human ortholog (tier 1-2): 1:1 and many:1 "SYM: name" (every many:1 copy the same);
#     a family "<HGNC group> family member", or -- no shared group -- straight to step 6
#   4 transposable element: a TE Pfam domain -> "<class> transposase domain-containing protein"
#     (before 5 and 6, which would name it after a gene domesticated from such an element)
#   5 best full-length human hit (FULL: both coverages >= 80%), reciprocal or not: "SYM-like:
#     name-like"; symbol only from HGNC. Other species never name a gene.
#   6 PANTHER family whose match covers >= 80% of the family model: "<family> family member",
#     with InterPro's curated name when the family is in InterPro, else PANTHER's (if informative)
#   7 InterPro domain or repeat: "<domain> domain-containing protein"
#   - None (the transcript id stays the name)
# The step is the Score of the Gene Name Source table: the order the steps are tried.
#
# Output geneNames.tsv: ID MAINID GroupId Desc Note (one row per id in isoforms.tsv or, with
# --native, per id in the native file). In --out-dir, per species (human always, then
# each --closest-species; <tag> lowercased): closest_<tag>.tsv, the same rows as geneNames.tsv
# with ID GroupId and four columns whose header names ARE the GFF attributes
# (addClosestToGFF.pl): closestHGNC closestHumanSym closestHumanDesc closestHumanEvidence, or
# closest<Tag>Id closest<Tag>Sym closest<Tag>Desc closest<Tag>Evidence; and
# closest_<tag>[.ensembl|.family].moop.tsv, annotation type "Closest Gene", a row for the gene
# and every isoform. And gene_name_source.<kind>.moop.tsv, annotation type "Gene Name Source":
# for every named gene, what its name came from (accession), the name and the rule
# (description), and the naming step (score) -- one file per kind of accession link.
# And naming_decisions.tsv, for people to read: one row per gene -- the name, its step and
# reason, the key scores whatever the cutoffs, every step's own result, the closest human; a "#"
# header with the run, the programs and data, every cutoff and the abbreviations.

# Per-gene-set inputs come from geneset_config.yaml through scripts/geneset_config.pl.
#
# LAYOUT -- constants and shared state at file level, ALL the work in main(), called on the
# LAST line of this file. A file-level "my %X = (...)" is assigned only when execution reaches
# its line; with the work at the top of the file, a table defined further down was still EMPTY
# when used, and Perl says nothing (no error under strict, no warning). That silently broke
# this script three times on 2026-09-25 (OMA pair ranks, the Closest Gene type, InterProScan
# E-value analyses). With main() last, every file-level assignment has run before any work
# starts, wherever it sits. tests/check_perl_file_scope.pl fails CI if the trap comes back.

# ---- filters (percent; see the plan)
# A hit must report coverage to be used at all: an E-value alone says two proteins share
# something (often one domain), not that they are the same kind of protein.
#   NORMAL -- evidence for the closest human gene
#   FULL   -- a name: the whole of both proteins aligns
my %NORMAL = (evalue => 1e-10, qcov => 50, tcov => 50);
my %FULL   = (evalue => 1e-10, qcov => 80, tcov => 80);
# A PANTHER family names a gene only when the protein holds most of the family's model -- the
# same "full length" bar as FULL, measured on the model alone: a protein that also carries other
# domains is still a member. Below it the match is usually one shared domain (a SET domain
# matching the KMT5A family at 38% of its model), which the InterPro domain step names honestly.
my $FAMILY_MODEL_COVERAGE = 80;
# Similarity to a human gene at all (any coverage): the E-value a hit needs to count as support
# for an orthology name, or as the "best human gene" a -like name must agree with.
my $HIT_MAX_EVALUE = 1e-5;
# A -like name picks one human gene only when no OTHER human gene scores within this fraction of
# its bitscore; closer than that the paralogs are a tie (UBE2D2 vs UBE2D4) -- see like_name.
my $LIKE_TIE = 0.95;
# An HGNC gene group names a family ("X family member") only when it is a family by descent. HGNC
# groups are also made by a shared domain ("EF-hand domain containing") or a function ("CD
# molecules", "BAF complex subunits"), and "family member" would claim common descent those do not
# have. Coherence = the fraction of the group's human genes (those with a Swiss-Prot PANTHER family)
# in the group's most common PANTHER family: families by descent score 0.6-1.0 (Tubulin beta 1.00,
# Tetraspanin 0.94, Cathepsins 0.73, HSP70 0.65), domain and function groups 0.06-0.21 (CD
# molecules 0.07, EF-hand 0.09, Sushi 0.16). The threshold sits in that gap.
my $HGNC_GROUP_MIN_COHERENCE = 0.6;
# A PANTHER family names a co-ortholog family or a paralog tie only when the gene is a whole member:
# its own match covers at least this much of the family's model, OR it has a full-length (FULL) hit
# to one of the human members. Model coverage from the InterProScan TSV (protein residues over the
# model's length) underestimates short and compact members -- the TSV has no model coordinates --
# so a full-length human hit also counts (ACBP: 35% of the model, 99%/100% to DBI).
my $FAMILY_NAME_MIN_OWN_COVERAGE = 50;
# InterPro's curated name for a PANTHER family is used before PANTHER's own ("TRIM45/56/19-like", not
# "BONUS, ISOFORM C-RELATED") -- unless it describes a function or a process rather than naming a
# family ("Complement & Cell Adhesion Regulators", "Cerebellin Synaptic Organizer", "Bacterial
# Antiviral Defense Nuclease", "Synovial Proliferation Regulator" for serum amyloid A): such names
# carry roles known from other organisms, often vertebrates. Then PANTHER's own name is used, if
# informative ("Cerebellin-related", "Collagen alpha", "Serum amyloid A").
my $FUNCTION_WORDS = qr/\b(?:Regulators?|Regulatory|Organi[sz]ers?|Organi[sz]ation|Assembly|Signal(?:l)?ing|Immunity|Immune|Development(?:al)?|Defen[cs]e|Roles?|Pathways?|Perception|Multifunctional|Diverse|Barrier|Stress|Proliferation|Biosynthetic|Modification|Apoptosis|Clearance|Associated)\b/;

# ---- closest gene in a --closest-species species
my %OMA_RANK = ('1:1' => 0, 'many:1' => 1, '1:many' => 2, 'many:many' => 3);
# the one database annotation type for every closest-gene table; the sources tell them apart
my $CLOSEST_TYPE = 'Closest Gene';
# InterProScan member databases whose score column is an E-value (the others report a match
# score, or nothing) -- used to pick a gene's best InterPro domain
my %EVALUE_ANALYSIS = map { $_ => 1 } qw(Pfam SMART CDD NCBIfam PRINTS PIRSF SFLD Gene3D SUPERFAMILY FunFam);
# Repeat-built PANTHER families: when repeat units (InterPro Repeat entries, and the C2H2 zinc
# finger, which InterPro types as a Domain) cover this much of a gene's PANTHER family match,
# model coverage says nothing -- any C2H2 protein fills a "KRAB AND ZINC FINGER" model -- and the
# gene is named for its repeat ("WD40 repeat-containing protein") instead of the family.
my $REPEAT_FAMILY_FRACTION = 0.25;
my %REPEAT_LIKE_ENTRY = map { $_ => 1 } qw(IPR013087);   # Zinc finger C2H2-type
# Transposable elements: Pfam families of the catalytic or signature domain of each TE class
# (checked against Pfam as shipped with InterProScan 5.78). Left out on purpose: DNA-binding
# helper domains (HTH_Tnp_4, CENP-B HTH) and reverse transcriptase alone (TERT has one).
# A gene with one of these (any match InterProScan reports, i.e. past Pfam's threshold) is named as a TE protein unless OMA gives it
# a human ortholog -- except when OMA's "ortholog" is shared by >= $TE_MIN_COPIES copies here
# (a TE family, next to a human gene domesticated from one: HARBI1, ZBED1, ZMYM1).
my $TE_MIN_COPIES = 5;
my %TE_PFAM = (
  PF13359 => ['PIF/Harbinger', 'DNA transposon', 'PIF/Harbinger transposase'],
  PF13358 => ['Tc1/mariner',   'DNA transposon', 'Tc1/mariner transposase'],
  PF01359 => ['Tc1/mariner',   'DNA transposon', 'Tc1/mariner transposase'],
  PF03184 => ['pogo (Tc1/mariner)', 'DNA transposon', 'pogo transposase'],
  PF13843 => ['piggyBac',      'DNA transposon', 'piggyBac transposase'],
  PF05699 => ['hAT',           'DNA transposon', 'hAT transposase dimerisation'],
  PF10551 => ['Mutator',       'DNA transposon', 'Mutator transposase'],
  PF20700 => ['Mutator',       'DNA transposon', 'Mutator transposase'],
  PF02992 => ['En/Spm (CACTA)', 'DNA transposon', 'En/Spm transposase'],
  PF14214 => ['Helitron',      'rolling-circle transposon', 'Helitron helicase'],
  PF05380 => ['Bel/Pao',       'LTR retrotransposon', 'Bel/Pao retrotransposon RNase H'],
  PF00665 => ['LTR retrotransposon', 'retrotransposon', 'retrotransposon integrase'],
  PF24764 => ['LTR retrotransposon', 'retrotransposon', 'retrotransposon integrase'],
);
# Gene Name Source: why each gene has its name. One database source (and file) per kind of
# accession, because a source has one accession link.
my $NAME_SOURCE_TYPE = 'Gene Name Source';
my %NAME_SOURCE = (
  hgnc       => ['Gene name source: HGNC gene', 'https://www.genenames.org',
                 'https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/'],
  hgnc_group => ['Gene name source: HGNC gene group', 'https://www.genenames.org',
                 'https://www.genenames.org/data/genegroup/#!/group/'],
  ensembl    => ['Gene name source: Ensembl gene', 'https://www.ensembl.org',
                 'https://www.ensembl.org/Homo_sapiens/Gene/Summary?g='],
  panther    => ['Gene name source: PANTHER family', 'https://www.ebi.ac.uk/interpro/',
                 'https://www.ebi.ac.uk/interpro/entry/panther/'],
  ncbi       => ['Gene name source: naming species', 'https://www.ncbi.nlm.nih.gov',
                 'https://www.ncbi.nlm.nih.gov/search/all/?term='],
  curated    => ['Gene name source: human-curated', '', ''],
  native     => ["Gene name source: the gene set's own name", '', ''],
  nolink     => ['Gene name source: human gene without an id', '', ''],
  interpro   => ['Gene name source: InterPro domain', 'https://www.ebi.ac.uk/interpro/',
                 'https://www.ebi.ac.uk/interpro/entry/InterPro/'],
  pfam       => ['Gene name source: transposable element domain (Pfam)', 'https://www.ebi.ac.uk/interpro/',
                 'https://www.ebi.ac.uk/interpro/entry/pfam/'],
);
# closest human: OMA pair types, and Ensembl Compara's for the via-another-species links
my %LINK_TYPE_RANK = (%OMA_RANK, one2one => 0, one2many => 2, many2many => 3);

my %COMMON_NAME = (
  MOUSE => 'mouse', DROME => 'fly', LOTGI => 'limpet', CAPTE => 'annelid (Capitella)',
  BRAFL => 'amphioxus', CALMI => 'elephant shark', LEPOC => 'spotted gar', NEMVE => 'sea anemone',
  MONBE => 'choanoflagellate',
  anolis_carolinensis => 'anole lizard', astyanax_mexicanus => 'cavefish',
  astyanax_mexicanus_pachon => 'cavefish (Pachon)', caenorhabditis_elegans => 'worm',
  danio_rerio => 'zebrafish', drosophila_melanogaster => 'fly', gallus_gallus => 'chicken',
  gallus_gallus_gca000002315v5 => 'chicken (GRCg6a)', mus_musculus => 'mouse',
  oryzias_latipes => 'medaka', petromyzon_marinus => 'lamprey', pogona_vitticeps => 'bearded dragon',
  saccharomyces_cerevisiae => 'yeast', xenopus_tropicalis => 'frog', escherichia_coli => 'E. coli',
);

# ---- shared state: declared here, filled by main() (declarations only -- see LAYOUT above)
my %opt;
my @command_line;     # the command as given, for the decision table's header
my (@closest_species, $naming_species);
my %stats;
my (%group_of, %members, %curated_selected);   # isoform groups
my (%gene_of_protein, %query_length);
my $hgnc;
my %human_links;      # group -> [ link ]   link = {tier, human => [records], type, evidence, bits, id, hit}
my %hits;             # group -> [ naming candidates from similarity ]
my %human_hit;        # group -> human key -> { best => hit, best_full => hit, rbh => 0|1 }: every human hit
                      # with E <= $HIT_MAX_EVALUE, any coverage (record_human_hit)
my %gene_panther;     # group -> { PANTHER family => 1 }: every PANTHER match, any coverage
my %human_panther;    # HGNC id -> { PANTHER family => 1 } (Swiss-Prot human entries)
my $human_searched = 0;   # a similarity search against human proteins was read (else "no hit" means nothing)
my %unsupported_oma;      # group -> { humans, type }: an OMA human ortholog set aside (oma_supported)
my %conflicting_oma;      # group -> { humans, type, best }: an OMA name withheld, both checks against it (oma_conflicts)
my @pending_compara;  # links through another species' Ensembl gene, resolved in one Compara pass
my %reference_fasta_cache;
my (%panther, %domain, %curated, %transposon);
my %closest;          # group -> { tier, human => [records], evidence, id }
my %claimed_human;    # human key -> { group => 1 }: the co-orthologs a many:1 name is shared by
my %name;             # group -> { desc, note, selected, origin }
my %candidates;       # group -> naming step -> its candidate: a name, or { why } (collect_candidates)
my %decision;         # group -> { step (0: none), reached => [steps tried], passed => { step => why } }
my %panther_label;    # PANTHER family -> InterPro's Family name when integrated, else PANTHER's cleaned name
my %gene_family_coverage;   # group -> PANTHER family -> the gene's best model coverage (%, any isoform)

# ============================================================== main
sub main {
  %opt = ('human-curated-gene-names' => []);
  @command_line = ($0, @ARGV);
  GetOptions(\%opt, 'isoforms=s', 'protein-fasta=s', 'protein2gene=s', 'hgnc-dir=s',
             'oma-dir=s', 'oma-code=s', 'mmseqs-dir=s', 'diamond-dir=s', 'ref-db=s',
             'compara-dir=s', 'uniprot-dir=s', 'taxonomy-dir=s', 'native=s', 'oma-id-map=s',
             'human-curated-gene-names=s@', 'closest-species=s@',
             'interproscan=s', 'interpro-entries=s', 'panther-hmm-lengths=s', 'out-names=s', 'out-dir=s')
    or die "bad options\n";
  foreach my $required (qw(isoforms protein-fasta hgnc-dir out-names out-dir)) {
    die "--$required is required\n" unless defined $opt{$required};
  }

  # --closest-species: one species each, key=value fields joined by |
  foreach my $spec (@{$opt{'closest-species'} // []}) {
    my %species;
    foreach my $field (split /\|/, $spec) {
      my ($key, $value) = split /=/, $field, 2;
      $species{$key} = $value;
    }
    foreach my $key (qw(tag species)) {
      die "--closest-species needs $key: $spec\n" unless defined $species{$key} and $species{$key} ne '';
    }
    die "--closest-species tag must be letters/digits, not 'human': $spec\n"
      if $species{tag} !~ /^[A-Za-z][A-Za-z0-9]*$/ or lc $species{tag} eq 'human';
    die "--closest-species needs oma_code or hits: $spec\n" unless $species{oma_code} or $species{hits};
    $species{$_} = $species{$_} ? 1 : 0 foreach qw(use_for_names same_species);
    die "--closest-species needs a label unless same_species: $spec\n" unless $species{same_species} or $species{label};
    push @closest_species, \%species;
  }
  my @naming_species = grep { $_->{use_for_names} } @closest_species;
  die "only one --closest-species may have use_for_names\n" if @naming_species > 1;
  $naming_species = $naming_species[0];

  # ============================================================== genes and ids
  read_isoforms($opt{isoforms});
  %gene_of_protein = read_protein2gene($opt{protein2gene});
  %query_length = fasta_lengths($opt{'protein-fasta'});

  $hgnc = load_hgnc("$opt{'hgnc-dir'}/hgnc_complete_set.txt", "$opt{'hgnc-dir'}/withdrawn.txt");

  # ============================================================== evidence
  if (defined $opt{'oma-dir'}) {
    collect_oma();
  }
  if (defined $opt{'mmseqs-dir'}) {
    collect_mmseqs();
  }
  if (defined $opt{'diamond-dir'}) {
    collect_diamond();
    link_swissprot_hits() if defined $opt{'uniprot-dir'};
  }
  collect_closest_species($_) foreach @closest_species;
  resolve_compara();
  name_species();
  read_human_panther() if defined $opt{'uniprot-dir'};
  if (defined $opt{interproscan}) {
    foreach my $needed (qw(interpro-entries panther-hmm-lengths)) {
      die "--interproscan needs --$needed (update_reference_data.sh makes it)\n" unless defined $opt{$needed};
    }
    my $entries = read_interpro_entries($opt{'interpro-entries'});
    %panther = read_panther_families($opt{interproscan}, $entries, $opt{'panther-hmm-lengths'});
    %domain  = read_interpro_domains($opt{interproscan}, $entries);
  }
  foreach my $curated_file (@{$opt{'human-curated-gene-names'}}) {
    read_curated($curated_file, \%curated);
  }

  # ============================================================== decide

  foreach my $group (keys %members) {
    $closest{$group} = choose_closest_human($group);
  }

  # many:1 co-orthologs: genes whose closest human is that ONE gene by OMA (tier 1-2). Counted
  # from the final picks, so a copy that ended up in a family is not counted.
  foreach my $group (keys %closest) {
    my $closest = $closest{$group} or next;
    next if $closest->{family} or $closest->{tier} > 2;
    $claimed_human{$closest->{human}[0]{key}}{$group} = 1;
  }

  foreach my $group (keys %members) {
    $candidates{$group} = collect_candidates($group);
    $name{$group} = tagged(set_aside_note($group, choose_name($group)));
  }

  # ============================================================== write
  write_outputs();
  write_decisions("$opt{'out-dir'}/naming_decisions.tsv");
  foreach my $key (sort keys %stats) {
    warn sprintf("%-40s %d\n", $key, $stats{$key});
  }
}

# ##############################################################################
# input readers

sub read_isoforms {
  my ($file) = @_;
  open my $fh, '<', $file or die "cant open isoforms file $file $!\n";
  while (my $line = <$fh>) {
    chomp $line;
    next if $line !~ /\S/;
    my ($ids, $selected, $group) = split /\t/, $line;
    $group = $line unless defined $group;
    foreach my $id (split /;/, $ids) {
      $group_of{$id} = $group;
      push @{$members{$group}}, $id;
    }
    $curated_selected{$group} = $selected if defined $selected and $selected ne 'None' and $selected ne '';
  }
  close $fh;
}

sub read_protein2gene {
  my ($file) = @_;
  my %map;
  return %map unless defined $file and -e $file;
  open my $fh, '<', $file or die "cant open $file $!\n";
  while (my $line = <$fh>) {
    chomp $line;
    my ($protein, $gene) = split /\t/, $line;
    $map{$protein} = $gene if defined $gene;
  }
  close $fh;
  return %map;
}

sub fasta_lengths {
  my ($file) = @_;
  my (%length, $id);
  my $open = $file =~ /\.gz$/ ? "gzip -dc '$file' |" : "< $file";
  open my $fh, $open or die "cant open fasta $file $!\n";
  while (my $line = <$fh>) {
    if ($line =~ /^>(\S+)/) {
      $id = $1;
      $length{$id} = 0;
    } elsif (defined $id) {
      $line =~ s/\s//g;
      $length{$id} += length $line;
    }
  }
  close $fh;
  return %length;
}

# group for an id seen in some analysis: exact, without mmseqs ":pep", without ORF ".pN",
# or through protein2gene when the gene id is a group
sub group_for {
  my ($id) = @_;
  return undef unless defined $id;
  foreach my $candidate ($id, strip_suffixes($id)) {
    return $group_of{$candidate} if exists $group_of{$candidate};
  }
  my $plain = strip_suffixes($id);
  if (exists $gene_of_protein{$plain} and exists $members{$gene_of_protein{$plain}}) {
    return $gene_of_protein{$plain};
  }
  return undef;
}

sub strip_suffixes {
  my ($id) = @_;
  $id =~ s/:pep$//;
  $id =~ s/\.p\d+$//i;
  return $id;
}

sub query_length_of {
  my ($id) = @_;
  return $query_length{$id} // $query_length{strip_suffixes($id)};
}

# ##############################################################################
# human records

# key a human gene by HGNC id when there is one, else by Ensembl gene
sub human_record {
  my (%keys) = @_;
  # Ensembl states the HGNC gene in its description ("... [Source:HGNC Symbol;Acc:HGNC:9455]"),
  # also for genes on alternate haplotypes and patches, whose own ENSG id HGNC does not list
  # (ENSG00000274382 is PROP1 on HSCHR5_3_CTG5). Without this they had no HGNC record at all.
  if (!$keys{hgnc_id} and ($keys{description} // '') =~ /\bAcc:(HGNC:\d+)/) {
    $keys{hgnc_id} = $1;
  }
  my $record = hgnc_record($hgnc, %keys);
  if ($record) {
    return { key => $record->{hgnc_id}, hgnc_id => $record->{hgnc_id}, symbol => $record->{symbol},
             name => $record->{name}, gene_group => $record->{gene_group}, gene_group_id => $record->{gene_group_id} };
  }
  my $ensembl_gene = $keys{ensembl_gene} // '';
  $ensembl_gene =~ s/\.\d+$//;
  my $description = clean_name($keys{description} // '');
  return undef if $ensembl_gene eq '' and $description eq '';
  return { key => ($ensembl_gene ne '' ? $ensembl_gene : "desc:$description"), hgnc_id => '',
           symbol => $ensembl_gene, name => $description, gene_group => '' };
}

sub human_from_oma_header {
  my ($header) = @_;
  my $parsed = parse_oma_header($header);
  return human_record(hgnc_id => $parsed->{hgnc_id}, ensembl_gene => $parsed->{gene_id},
                      uniprot => $parsed->{uniprot}, description => $parsed->{description});
}

sub add_link {
  my ($group, %link) = @_;
  push @{$human_links{$group}}, \%link;
}

# ##############################################################################
# OMA: pairwise (tier 1), HOGs (tier 2), reference-species chains (tier 4)

# OMA ids of the target -> (gene set protein id, group) pairs. Normally the OMA id is the gene
# set's own id; for a gene set that IS a reference genome (--oma-id-map, run through the
# template's reference run) it is the reference's id (NEMVE000123), mapped by identical sequence.
my $oma_id_map;
sub own_ids_and_groups {
  my ($oma_target_id) = @_;
  $oma_id_map = read_id_map($opt{'oma-id-map'}) if defined $opt{'oma-id-map'} and !$oma_id_map;
  my @found;
  foreach my $own_id (target_ids($oma_id_map, $oma_target_id)) {
    my $group = group_for($own_id);
    push @found, [$own_id, $group] if defined $group;
  }
  return @found;
}

sub collect_oma {
  my $output = "$opt{'oma-dir'}/Output";
  my $code = $opt{'oma-code'} or die "--oma-code is required with --oma-dir\n";
  die "no $output/PairwiseOrthologs\n" unless -d "$output/PairwiseOrthologs";

  # tier 1
  my $direct = read_oma_pairs($output, $code, 'HUMAN');
  foreach my $oma_target_id (keys %$direct) {
    foreach my $own (own_ids_and_groups($oma_target_id)) {
      my ($target_id, $group) = @$own;
      foreach my $pair (@{$direct->{$oma_target_id}}) {
        my $human = human_from_oma_header($pair->{partner_header}) or next;
        add_link($group, tier => 1, human => [$human], type => $pair->{type}, id => $target_id,
                 evidence => "OMA ortholog ($pair->{type})", hit => $pair->{partner_id});
      }
    }
  }

  # tier 2, only with a fixed species tree
  my $parameters = "$opt{'oma-dir'}/parameters.drw";
  my $fixed_tree = 0;
  if (open my $parameters_fh, '<', $parameters) {
    while (my $line = <$parameters_fh>) {
      $fixed_tree = 1 if $line =~ /^\s*SpeciesTree\s*:=\s*'\(/;
    }
    close $parameters_fh;
  }
  if ($fixed_tree and -s "$output/HierarchicalGroups.orthoxml") {
    my $hogs = read_hog_orthologs("$output/HierarchicalGroups.orthoxml", $code);
    my $human_pairs = $hogs->{pairs}{HUMAN} // {};
    foreach my $target_gene (keys %$human_pairs) {
      foreach my $own (own_ids_and_groups($hogs->{genes}{$target_gene}{prot_id})) {
        my ($target_id, $group) = @$own;
        foreach my $human_gene (keys %{$human_pairs->{$target_gene}}) {
          my $human = human_from_oma_header($hogs->{genes}{$human_gene}{header}) or next;
          my $type = $hogs->{type}{HUMAN}{$target_gene}{$human_gene};
          add_link($group, tier => 2, human => [$human], type => $type, id => $target_id,
                   evidence => "OMA HOG co-ortholog ($type)", hit => $hogs->{genes}{$human_gene}{prot_id});
        }
      }
    }
  } else {
    $stats{'note: HOG tier skipped (no fixed SpeciesTree)'} = 1;
  }

  # tier 4 via reference species: target -> REF ortholog -> REF's HUMAN ortholog
  opendir my $dir_handle, "$output/PairwiseOrthologs" or die "cant read $output/PairwiseOrthologs\n";
  my %references;
  foreach my $file (readdir $dir_handle) {
    next unless $file =~ /^([A-Za-z0-9]+)-([A-Za-z0-9]+)\.txt$/;
    my ($left, $right) = ($1, $2);
    $references{$right} = 1 if $left eq $code and $right ne 'HUMAN';
    $references{$left}  = 1 if $right eq $code and $left ne 'HUMAN';
  }
  closedir $dir_handle;
  foreach my $reference (sort keys %references) {
    my $to_reference = read_oma_pairs($output, $code, $reference);
    my $reference_to_human = read_oma_pairs($output, $reference, 'HUMAN');
    foreach my $oma_target_id (keys %$to_reference) {
      foreach my $own (own_ids_and_groups($oma_target_id)) {
        my ($target_id, $group) = @$own;
        foreach my $first (@{$to_reference->{$oma_target_id}}) {
          foreach my $second (@{$reference_to_human->{$first->{partner_id}} // []}) {
            my $human = human_from_oma_header($second->{partner_header}) or next;
            my $common = $COMMON_NAME{$reference} // $reference;
            add_link($group, tier => 4, human => [$human], type => $second->{type}, id => $target_id,
                     evidence => "via $common ortholog (OMA $first->{type}) > OMA ortholog ($second->{type})",
                     hit => $second->{partner_id});
          }
        }
      }
    }
  }
}

# pairs between species A and B from PairwiseOrthologs/A-B.txt or B-A.txt, keyed by A's
# protein id; type oriented A:B
sub read_oma_pairs {
  my ($output, $species_a, $species_b) = @_;
  my %pairs;
  my $forward = "$output/PairwiseOrthologs/$species_a-$species_b.txt";
  my $reverse = "$output/PairwiseOrthologs/$species_b-$species_a.txt";
  my ($file, $a_first);
  if (-e $forward) {
    ($file, $a_first) = ($forward, 1);
  } elsif (-e $reverse) {
    ($file, $a_first) = ($reverse, 0);
  } else {
    return \%pairs;
  }
  open my $fh, '<', $file or die "cant open $file $!\n";
  while (my $line = <$fh>) {
    next if $line =~ /^#/;
    chomp $line;
    my ($number_1, $number_2, $header_1, $header_2, $type) = split /\t/, $line;
    my ($a_header, $b_header) = $a_first ? ($header_1, $header_2) : ($header_2, $header_1);
    unless ($a_first) {
      my ($left, $right) = split /:/, $type;
      $type = "$right:$left";
    }
    my ($a_id) = $a_header =~ /^(\S+)/;
    my ($b_id) = $b_header =~ /^(\S+)/;
    push @{$pairs{$a_id}}, { partner_id => $b_id, partner_header => $b_header, type => $type };
  }
  close $fh;
  return \%pairs;
}

# ##############################################################################
# MMseqs2 reciprocal best hits (tier 3 human, tier 4 via Compara, naming candidates)

sub collect_mmseqs {
  my $base = $opt{'mmseqs-dir'};
  opendir my $dir_handle, $base or die "cant read $base\n";
  my @species_dirs;
  foreach my $entry (sort readdir $dir_handle) {
    push @species_dirs, $entry if $entry =~ /^ENS_/ and -s "$base/$entry/rbh_mmseq_results.tsv";
  }
  closedir $dir_handle;
  foreach my $species_dir (@species_dirs) {
    (my $species = $species_dir) =~ s/^ENS_//;
    my $release = read_release("$base/$species_dir/db_version.txt");
    my $reference = reference_proteome($species_dir);
    next unless $reference;
    $human_searched = 1 if $species eq 'homo_sapiens';

    open my $fh, '<', "$base/$species_dir/rbh_mmseq_results.tsv" or die "cant open mmseqs results $!\n";
    while (my $line = <$fh>) {
      chomp $line;
      next if $line =~ /^query\t/ or $line !~ /\S/;
      my ($query, $target, $pident, $alnlen, $mismatch, $gapopen,
          $qstart, $qend, $tstart, $tend, $evalue, $bits) = split /\t/, $line;
      my $group = group_for($query) or next;
      my $query_len  = query_length_of($query);
      my $target_len = $reference->{$target}{length};
      next unless $query_len and $target_len;
      my %hit = (
        evalue => $evalue, bits => $bits, pident => $pident * 100,
        qcov => 100 * ($qend - $qstart + 1) / $query_len,
        tcov => 100 * ($tend - $tstart + 1) / $target_len,
      );
      my $info = $reference->{$target};
      my $common = $COMMON_NAME{$species} // $species;
      my $human = $species eq 'homo_sapiens'
        ? human_record(hgnc_id => $info->{hgnc_id}, ensembl_gene => $info->{gene}, description => $info->{description}) : undef;
      record_human_hit($group, $human, { %hit, id => $query, hit => $target, reciprocal => 1, tool => 'MMseqs2',
                                                source => 'MMseqs2_RBH_Homo_sapiens', type => 'RBBH_Homolog' }) if $human;
      next unless passes(\%hit, \%NORMAL);

      if ($species eq 'homo_sapiens') {
        next unless $human;
        add_link($group, tier => 3, human => [$human], type => '1:1', id => $query, bits => $bits,
                 evidence => 'reciprocal best hit (MMseqs2)', hit => $target);
        push @{$hits{$group}}, { %hit, source => 'MMseqs2_RBH_Homo_sapiens', type => 'RBBH_Homolog',
                                 id => $query, hit => $target, human => $human, reciprocal => 1,
                                 symbol => $human->{symbol}, description => $human->{name}, species => 'human' };
      } else {
        push @{$hits{$group}}, { %hit, source => "MMseqs2_RBH_$species", type => 'RBBH_Homolog',
                                 id => $query, hit => $target, reciprocal => 1, species => $common,
                                 symbol => $info->{symbol}, description => $info->{description} };
        push @pending_compara, { group => $group, release => $release, genes => [ $info->{gene} ],
                                 tier => 4, id => $query, hit => $target, bits => $bits, evalue => $evalue,
                                 via => "via $common reciprocal best hit (MMseqs2)" };
      }
    }
    close $fh;
  }
}

sub read_release {
  my ($file) = @_;
  open my $fh, '<', $file or return '';
  my $line = <$fh> // '';
  close $fh;
  # "release-113" or "ENS_<species><TAB>release-113"; main Ensembl only -- Ensembl Genomes
  # ("release-61_bacteria_...") has no human Compara
  my ($release) = $line =~ /(?:^|\s)release-(\d+)\s*$/;
  return $release // '';
}

# protein id -> { gene, symbol, description, length } from REF_DB/ENS_<species>/current
sub reference_proteome {
  my ($species_dir) = @_;
  return $reference_fasta_cache{$species_dir} if exists $reference_fasta_cache{$species_dir};
  my $ref_db = $opt{'ref-db'} or die "--ref-db is required for MMseqs2/DIAMOND hits\n";
  my @fastas = glob "$ref_db/$species_dir/current/*.pep.all.fa.gz";
  if (!@fastas) {
    warn "WARNING: no reference proteome for $species_dir under $ref_db\n";
    $reference_fasta_cache{$species_dir} = undef;
    return undef;
  }
  my (%proteins, $id);
  open my $fh, "gzip -dc '$fastas[0]' |" or die "cant read $fastas[0]\n";
  while (my $line = <$fh>) {
    if ($line =~ /^>(\S+)/) {
      $id = $1;
      my ($gene)   = $line =~ /\bgene:(\S+)/;
      my ($symbol) = $line =~ /\bgene_symbol:(\S+)/;
      my ($desc)   = $line =~ /\bdescription:(.+?)\s*$/;
      my ($hgnc_id) = $line =~ /\bAcc:(HGNC:\d+)/;   # read before clean_name drops the [Source:...] part
      $proteins{$id} = { gene => $gene // $id, symbol => $symbol // '', description => clean_name($desc // ''),
                         hgnc_id => $hgnc_id // '', length => 0 };
    } elsif (defined $id) {
      $line =~ s/\s//g;
      $proteins{$id}{length} += length $line;
    }
  }
  close $fh;
  $reference_fasta_cache{$species_dir} = \%proteins;
  return \%proteins;
}

# Links through another species' Ensembl gene (MMseqs2 hits, Swiss-Prot cross-references):
# one pass over each Compara release file, keeping only the genes asked for.
# A link without a release uses the newest release downloaded.
my %compara_releases_used;
sub resolve_compara {
  return unless @pending_compara and defined $opt{'compara-dir'};
  my @available;
  foreach my $release_dir (glob "$opt{'compara-dir'}/release-*") {
    push @available, $1 if $release_dir =~ /release-(\d+)$/ and -s "$release_dir/homo_sapiens.orthologs.tsv.gz";
  }
  @available = sort { $a <=> $b } @available;
  return unless @available;

  my %wanted;   # release -> gene -> 1
  foreach my $pending (@pending_compara) {
    my $release = $pending->{release} ne '' ? $pending->{release} : $available[-1];
    $pending->{release} = $release;
    foreach my $gene (@{$pending->{genes}}) {
      $wanted{$release}{$gene} = 1;
    }
  }
  my %orthologs;   # release -> gene -> [ { human_gene, type } ]
  foreach my $release (sort keys %wanted) {
    my $file = "$opt{'compara-dir'}/release-$release/homo_sapiens.orthologs.tsv.gz";
    if (!-s $file) {
      $stats{"warning: no Compara release $release (run update_reference_data.sh)"} = 1;
      next;
    }
    $compara_releases_used{$release} = 1;
    open my $fh, "gzip -dc '$file' |" or die "cant read $file\n";
    <$fh>;
    while (my $line = <$fh>) {
      my ($human_gene, $human_protein, $human_species, $identity, $type, $other_gene) = split /\t/, $line, 7;
      next unless $wanted{$release}{$other_gene};
      (my $short_type = $type) =~ s/^ortholog_//;
      push @{$orthologs{$release}{$other_gene}}, { human_gene => $human_gene, type => $short_type };
    }
    close $fh;
  }
  foreach my $pending (@pending_compara) {
    foreach my $gene (@{$pending->{genes}}) {
      foreach my $ortholog (@{$orthologs{$pending->{release}}{$gene} // []}) {
        my $human = human_record(ensembl_gene => $ortholog->{human_gene}) or next;
        add_link($pending->{group}, tier => $pending->{tier}, human => [$human], type => $ortholog->{type},
                 id => $pending->{id}, bits => $pending->{bits}, evalue => $pending->{evalue}, hit => $pending->{hit},
                 evidence => "$pending->{via} > Ensembl Compara ($ortholog->{type})");
      }
    }
  }
}

# Non-human Swiss-Prot hits: Ensembl gene -> Compara (tier 6, resolved with the rest), or
# PANTHER subfamily -> the human Swiss-Prot genes in it (tier 7). Also records each hit's taxon.
sub link_swissprot_hits {
  my $file = "$opt{'uniprot-dir'}/sprot_xrefs.tsv.gz";
  if (!-s $file) {
    $stats{'warning: no uniprot/sprot_xrefs.tsv.gz (run update_reference_data.sh)'} = 1;
    return;
  }
  my %wanted;
  foreach my $group (keys %hits) {
    foreach my $hit (@{$hits{$group}}) {
      $wanted{$hit->{accession}} = 1 if defined $hit->{accession};
    }
  }
  my (%xref, %human_in_subfamily);
  open my $fh, "gzip -dc '$file' |" or die "cant read $file\n";
  <$fh>;
  while (my $line = <$fh>) {
    chomp $line;
    my ($accession, $taxid, $gene_name, $hgnc_ids, $genes, $proteins, $panther, $secondary) = split /\t/, $line, -1;
    if ($taxid eq '9606') {
      my ($hgnc_id) = split /;/, $hgnc_ids;
      foreach my $family (split /;/, $panther) {
        $human_in_subfamily{$family}{$hgnc_id} = 1 if $family =~ /:SF/ and defined $hgnc_id and $hgnc_id ne '';
      }
    }
    # a hit may carry an older accession since merged into this entry
    my @hit_accessions;
    foreach my $candidate ($accession, split /;/, $secondary // '') {
      push @hit_accessions, $candidate if $wanted{$candidate};
    }
    next unless @hit_accessions;
    my @subfamilies;
    foreach my $family (split /;/, $panther) {
      push @subfamilies, $family if $family =~ /:SF/;
    }
    foreach my $hit_accession (@hit_accessions) {
      $xref{$hit_accession} = { taxid => $taxid, genes => [ split /;/, $genes ], subfamilies => \@subfamilies };
    }
  }
  close $fh;

  foreach my $group (keys %hits) {
    foreach my $hit (@{$hits{$group}}) {
      next unless defined $hit->{accession} and !$hit->{human};
      my $cross = $xref{$hit->{accession}} or next;
      $hit->{taxid} = $cross->{taxid};
      my $what = "$hit->{species_scientific} Swiss-Prot hit" . ($hit->{symbol} ne '' ? " $hit->{symbol}" : '');
      if (@{$cross->{genes}}) {
        push @pending_compara, { group => $group, release => '', genes => $cross->{genes}, tier => 6,
                                 id => $hit->{id}, hit => $hit->{hit}, bits => $hit->{bits}, evalue => $hit->{evalue}, via => "via $what" };
      }
      foreach my $subfamily (@{$cross->{subfamilies}}) {
        my @hgnc_ids = sort keys %{$human_in_subfamily{$subfamily} // {}};
        next unless @hgnc_ids;
        my @humans;
        foreach my $hgnc_id (@hgnc_ids) {
          my $human = human_record(hgnc_id => $hgnc_id);
          push @humans, $human if $human;
        }
        next unless @humans;
        add_link($group, tier => 7, human => \@humans, type => 'PANTHER subfamily', id => $hit->{id},
                 bits => $hit->{bits}, evalue => $hit->{evalue}, hit => $hit->{hit},
                 evidence => "via $what > PANTHER subfamily $subfamily");
      }
    }
  }
}

# PANTHER families of human genes, from their Swiss-Prot entries: an orthology name is supported
# when the gene's own PANTHER family (InterProScan) is one of its human gene's (oma_support)
sub read_human_panther {
  my $file = "$opt{'uniprot-dir'}/sprot_xrefs.tsv.gz";
  return unless -s $file;
  open my $fh, "gzip -dc '$file' |" or die "cant read $file\n";
  <$fh>;
  while (my $line = <$fh>) {
    chomp $line;
    my ($accession, $taxid, $gene_name, $hgnc_ids, $genes, $proteins, $panther) = split /\t/, $line, -1;
    next unless $taxid eq '9606' and $hgnc_ids ne '' and $panther ne '';
    foreach my $family (split /;/, $panther) {
      $family =~ s/:SF\d+$//;
      $human_panther{$_}{$family} = 1 foreach split /;/, $hgnc_ids;
    }
  }
  close $fh;
}

# common names for the species of Swiss-Prot hits ("turkey"), from NCBI genbank common names
sub name_species {
  my %wanted;
  foreach my $group (keys %hits) {
    foreach my $hit (@{$hits{$group}}) {
      $wanted{$hit->{taxid}} = 1 if defined $hit->{taxid};
    }
  }
  my %common;
  if (%wanted and defined $opt{'taxonomy-dir'} and open my $fh, '<', "$opt{'taxonomy-dir'}/names.dmp") {
    while (my $line = <$fh>) {
      next unless $line =~ /genbank common name/;
      my ($taxid, $name) = split /\t\|\t/, $line;
      $common{$taxid} = $name if $wanted{$taxid};
    }
    close $fh;
  }
  foreach my $group (keys %hits) {
    foreach my $hit (@{$hits{$group}}) {
      next unless defined $hit->{taxid};
      $hit->{species} = $common{$hit->{taxid}} // $hit->{species_scientific};
    }
  }
}

# ##############################################################################
# DIAMOND best hits (Swiss-Prot and Ensembl species)

sub collect_diamond {
  my $base = $opt{'diamond-dir'};
  opendir my $dir_handle, $base or die "cant read $base\n";
  my @dbs;
  foreach my $entry (sort readdir $dir_handle) {
    push @dbs, $entry if -e "$base/$entry/diamond_results.tsv" or -e "$base/$entry/diamond_results.tsv.gz";
  }
  closedir $dir_handle;
  foreach my $db (@dbs) {
    my $file = -e "$base/$db/diamond_results.tsv" ? "$base/$db/diamond_results.tsv" : "$base/$db/diamond_results.tsv.gz";
    my $open = $file =~ /\.gz$/ ? "gzip -dc '$file' |" : "< $file";
    open my $fh, $open or die "cant read $file\n";
    my $has_coverage = 0;
    while (my $line = <$fh>) {
      chomp $line;
      if ($line =~ /^qseqid\t/) {
        $has_coverage = $line =~ /\tscovhsp/ ? 1 : 0;
        next;
      }
      my @fields = split /\t/, $line;
      my ($query, $subject, $title, $evalue) = @fields[0 .. 3];
      my $group = group_for($query) or next;
      my %hit = (evalue => $evalue);
      if (@fields >= 17) {
        @hit{qw(pident bits qcov tcov)} = ($fields[4], $fields[12], $fields[15], $fields[16]);
        $has_coverage = 1;
      }
      next unless @fields >= 17;   # no coverage, no bitscore: not used at all (see the note below)
      my $candidate = diamond_candidate($db, $subject, $title);
      next unless $candidate;
      record_human_hit($group, $candidate->{human}, { %hit, id => $query, hit => $subject, reciprocal => 0,
                                                     tool => 'DIAMOND', label => $candidate->{label},
                                                     source => $candidate->{source}, type => $candidate->{type} }) if $candidate->{human};
      next unless passes(\%hit, \%NORMAL);
      push @{$hits{$group}}, { %hit, %$candidate, id => $query, hit => $subject, reciprocal => 0 };
      if ($candidate->{human}) {
        add_link($group, tier => 5, human => [$candidate->{human}], type => 'best hit', id => $query,
                 bits => $hit{bits}, evalue => $hit{evalue}, evidence => "best BLAST hit ($candidate->{label})", hit => $subject);
      }
    }
    close $fh;
    $stats{"note: DIAMOND $db has no coverage columns: NOT USED (needs the 17-column output)"} = 1 unless $has_coverage;
    $human_searched = 1 if $has_coverage and ($db eq 'ENS_homo_sapiens' or $db =~ /sprot/i);
  }
}

sub diamond_candidate {
  my ($db, $subject, $title) = @_;
  if ($db =~ /sprot/i) {
    # sp|Q6AZB8|HARB1_DANRE Putative nuclease HARBI1 OS=Danio rerio OX=7955 GN=harbi1 PE=2 SV=1
    my ($accession) = $subject =~ /^sp\|([^|]+)\|/;
    my ($description) = $title =~ /^\S+\s+(.*?)\s+OS=/;
    my ($organism) = $title =~ /\bOS=(.*?)\s+OX=/;
    my ($symbol) = $title =~ /\bGN=(\S+)/;
    my ($taxid) = $title =~ /\bOX=(\d+)/;
    my $candidate = { source => 'UniProtKB/Swiss-Prot', type => 'Homologs', species => $organism // '',
                      species_scientific => $organism // '', taxid => $taxid, accession => $accession,
                      symbol => $symbol // '', description => clean_name($description // ''),
                      label => 'Swiss-Prot' };
    if (defined $organism and $organism eq 'Homo sapiens' and defined $accession) {
      $candidate->{human} = human_record(uniprot => [$accession], description => $description);
      $candidate->{symbol} = $candidate->{human}{symbol} if $candidate->{human} and $candidate->{human}{hgnc_id};
    }
    return $candidate;
  }
  if ($db =~ /^ENS_(.+)$/) {
    my $species = $1;
    my ($gene)   = $title =~ /\bgene:(\S+)/;
    my ($symbol) = $title =~ /\bgene_symbol:(\S+)/;
    my ($desc)   = $title =~ /\bdescription:(.+?)\s*$/;
    my $candidate = { source => "Ensembl_$species", type => 'Homologs', species => $COMMON_NAME{$species} // $species,
                      symbol => $symbol // '', description => clean_name($desc // ''),
                      label => "Ensembl $species" };
    if ($species eq 'homo_sapiens') {
      $candidate->{human} = human_record(ensembl_gene => $gene, description => $desc);
      $candidate->{label} = 'Ensembl human';
    }
    return $candidate;
  }
  return undef;
}

# E-value and both coverages; a hit that does not report coverage fails
sub passes {
  my ($hit, $filter) = @_;
  return 0 unless defined $hit->{evalue} and $hit->{evalue} <= $filter->{evalue};
  foreach my $measure (qw(qcov tcov)) {
    return 0 unless defined $hit->{$measure} and $hit->{$measure} >= $filter->{$measure};
  }
  return 1;
}

# Every hit to a human gene with E <= $HIT_MAX_EVALUE, whatever its coverage, kept per HUMAN GENE
# (HGNC id, else Ensembl gene: isoforms of one gene are one entry): its best-scoring hit, its
# best-scoring full-length (FULL) hit, and whether any hit was a reciprocal best hit. This is what
# says which human gene a protein is most similar to -- compared by gene id and bitscore, never
# by name text -- for -like names (like_name) and for the support of orthology names (oma_support).
sub record_human_hit {
  my ($group, $human, $hit) = @_;
  return unless defined $hit->{evalue} and $hit->{evalue} <= $HIT_MAX_EVALUE and defined $hit->{bits};
  my $entry = $human_hit{$group}{$human->{key}} //= { human => $human, rbh => 0 };
  $entry->{rbh} = 1 if $hit->{reciprocal};
  $entry->{best} = $hit if better_hit($hit, $entry->{best});
  $entry->{best_full} = $hit if passes($hit, \%FULL) and better_hit($hit, $entry->{best_full});
}

# higher bitscore, then lower E-value, then ids -- so the choice never depends on read order
sub better_hit {
  my ($new, $old) = @_;
  return 1 unless $old;
  return ($new->{bits} <=> $old->{bits} or $old->{evalue} <=> $new->{evalue}
          or $old->{hit} cmp $new->{hit} or $old->{id} cmp $new->{id}) > 0 ? 1 : 0;
}

# a group's human genes by their best hit, strongest first: [ key, entry ]
sub ranked_human_hits {
  my ($group) = @_;
  my $genes = $human_hit{$group} or return ();
  return map { [$_, $genes->{$_}] }
         sort { better_hit($genes->{$a}{best}, $genes->{$b}{best}) ? -1 : better_hit($genes->{$b}{best}, $genes->{$a}{best}) ? 1 : $a cmp $b }
         keys %$genes;
}

# ##############################################################################
# PANTHER and human-curated names (moop TSV: id accession description score)

# PANTHER families from the gene set's InterProScan TSV, per gene: the best family whose match
# covers at least $FAMILY_MODEL_COVERAGE% of the family's model. Coverage = the protein residues
# inside the family's match regions (merged), over the model length (--panther-hmm-lengths,
# from PANTHER's HMM file; the TSV does not report model positions). Best = lowest E-value,
# then family, then protein id. InterPro's curated name comes along when the family is
# integrated into an InterPro Family entry.
sub read_panther_families {
  my ($results, $entries, $lengths_file) = @_;
  my %model_length;
  open my $lengths_fh, '<', $lengths_file or die "cant open $lengths_file $!\n";
  while (my $line = <$lengths_fh>) {
    next if $line =~ /^#/;
    chomp $line;
    my ($family, $length) = split /\t/, $line;
    $model_length{$family} = $length if defined $length and $length =~ /^\d+$/ and $length > 0;
  }
  close $lengths_fh;
  die "no model lengths in $lengths_file\n" unless %model_length;

  my %match;   # "protein\tfamily" -> { id, family, description, interpro, evalue, regions => [[start, end]] }
  my %repeat;  # protein -> repeat entry name -> [[start, end]]: repeat units, for repeat-built families
  my $open = $results =~ /\.gz$/ ? "gzip -dc '$results' |" : "< $results";
  open my $fh, $open or die "cant read $results\n";
  while (my $line = <$fh>) {
    chomp $line;
    my ($id, undef, undef, $analysis, $family, $description, $start, $end, $score, undef, undef, $interpro) = split /\t/, $line;
    next unless defined $end;
    if (defined $interpro and $entries->{$interpro} and $analysis ne 'ProSitePatterns'
        and ($entries->{$interpro}{type} eq 'Repeat' or $REPEAT_LIKE_ENTRY{$interpro})) {
      push @{$repeat{$id}{$entries->{$interpro}{name}}}, [$start, $end];
    }
    next unless $analysis eq 'PANTHER';
    my $key = "$id\t$family";
    my $evalue = defined $score && $score =~ /^[0-9.eE+-]+$/ ? $score : 1;
    my $match = $match{$key} //= { id => $id, family => $family, description => $description // '',
                                   interpro => $interpro, evalue => $evalue, regions => [] };
    $match->{evalue} = $evalue if $evalue < $match->{evalue};
    push @{$match->{regions}}, [$start, $end];
  }
  close $fh;

  my (%best, $no_length);
  foreach my $match (values %match) {
    my $group = group_for($match->{id}) or next;
    # every family matched, any coverage: what an orthology name's human gene is checked against
    (my $family_only = $match->{family}) =~ s/:SF\d+$//;
    $gene_panther{$group}{$family_only} = 1;
    # the family's label, for naming a co-ortholog family by it (shared_panther_family)
    my $family_entry = defined $match->{interpro} ? $entries->{$match->{interpro}} : undef;
    $panther_label{$family_only} //= ($family_entry and $family_entry->{type} eq 'Family'
                                      and usable_interpro_family_name($family_entry->{name}, $match->{description}))
                                   ? $family_entry->{name} : panther_name($match->{description});
    my $length = $model_length{$match->{family}};
    if (!$length) {
      $no_length++;
      next;
    }
    my $coverage = int(100 * aligned_residues($match->{regions}) / $length);
    $coverage = 100 if $coverage > 100;
    $gene_family_coverage{$group}{$family_only} = $coverage if $coverage > ($gene_family_coverage{$group}{$family_only} // -1);
    if ($coverage < $FAMILY_MODEL_COVERAGE) {
      $stats{'PANTHER match: below model coverage'}++;
      next;
    }
    $match->{model_coverage} = $coverage;
    # repeat units inside the family match: the repeat covering most of it, and how much
    my %in_match = map { $_ => 1 } map { $_->[0] .. $_->[1] } @{$match->{regions}};
    foreach my $repeat_name (sort keys %{$repeat{$match->{id}} // {}}) {
      my %covered = map { $_ => 1 } grep { $in_match{$_} } map { $_->[0] .. $_->[1] } @{$repeat{$match->{id}}{$repeat_name}};
      my $fraction = (scalar keys %covered) / (scalar keys %in_match);
      if ($fraction > ($match->{repeat_fraction} // 0)) {
        @{$match}{qw(repeat_name repeat_fraction)} = ($repeat_name, $fraction);
      }
    }
    my $entry = defined $match->{interpro} ? $entries->{$match->{interpro}} : undef;
    if ($entry and $entry->{type} eq 'Family') {
      if (usable_interpro_family_name($entry->{name}, $match->{description})) {
        $match->{interpro_name} = $entry->{name};
      } else {
        $match->{interpro_set_aside} = $entry->{name};
      }
    }
    my $current = $best{$group};
    if (!$current or $match->{evalue} < $current->{evalue}
        or ($match->{evalue} == $current->{evalue}
            and ($match->{family} cmp $current->{family} or $match->{id} cmp $current->{id}) < 0)) {
      $best{$group} = $match;
    }
  }
  # every family missing: the lengths are from another PANTHER release than InterProScan used
  warn "WARNING: $no_length PANTHER matches have no model length in $lengths_file (a different PANTHER release?)\n"
    if $no_length;
  return %best;
}

# InterPro's Family name is used for a PANTHER family unless it describes a function or process
# ($FUNCTION_WORDS) and PANTHER's own name is informative -- then PANTHER's is used instead
sub usable_interpro_family_name {
  my ($interpro_name, $panther_description) = @_;
  return 1 unless $interpro_name =~ $FUNCTION_WORDS;
  my $panther_own = panther_name($panther_description // '');
  return 0 if is_informative_hit('', $panther_own, 'x');
  return 1;
}

# residues covered by a list of [start, end] regions, overlaps counted once
sub aligned_residues {
  my ($regions) = @_;
  my ($total, $covered_to) = (0, 0);
  foreach my $region (sort { $a->[0] <=> $b->[0] } @$regions) {
    my ($start, $end) = @$region;
    $start = $covered_to + 1 if $start <= $covered_to;
    $total += $end - $start + 1 if $end >= $start;
    $covered_to = $end if $end > $covered_to;
  }
  return $total;
}

# InterPro's entry.list: accession -> { type (Domain, Repeat, Family, ...), name }
sub read_interpro_entries {
  my ($file) = @_;
  my %entry;
  open my $fh, '<', $file or die "cant open $file $!\n";
  while (my $line = <$fh>) {
    chomp $line;
    my ($accession, $type, $name) = split /\t/, $line;
    next unless defined $name and $accession =~ /^IPR\d+$/;
    $entry{$accession} = { type => $type, name => $name };
  }
  close $fh;
  return \%entry;
}

# InterProScan TSV (the gene set's own results) + InterPro's entry.list: per gene, the best match
# to an InterPro Domain or Repeat entry, leaving out "unknown function" entries (DUF, UPF,
# uncharacterised). Best = lowest E-value among member databases that report one (Pfam, SMART,
# CDD, ...), then entries without an E-value (PROSITE profiles report a score), then accession.
# Every match InterProScan reports has already passed its member database's own curated
# threshold (Pfam's per-family gathering thresholds, SMART, CDD, PROSITE profiles). No E-value
# floor is added on top: an E-value depends on domain length, so a single floor removes short
# domains (zinc fingers, repeats, the Hedgehog signalling domain) however real -- which is why
# Pfam itself uses per-family bit-score thresholds. PROSITE patterns are not used at all: a
# short regular expression with no score or threshold, which unrelated proteins match by chance.
# (With the InterProScan JSON, a match covering too little of its domain model can be rejected.)
sub read_interpro_domains {
  my ($results, $entries) = @_;
  my %entry;
  foreach my $accession (keys %$entries) {
    my ($type, $name) = @{$entries->{$accession}}{qw(type name)};
    next unless $type eq 'Domain' or $type eq 'Repeat';
    next if $name =~ /unknown function|\bDUF\d|\bUPF\d{4}|uncharacteri[sz]ed/i;   # UPF0160, not UPF1/UPF3 (real genes)
    $entry{$accession} = $entries->{$accession};
  }
  my %best;
  my $open = $results =~ /\.gz$/ ? "gzip -dc '$results' |" : "< $results";
  open my $fh, $open or die "cant read $results\n";
  while (my $line = <$fh>) {
    chomp $line;
    my @fields = split /\t/, $line;
    my ($id, $analysis, $signature, $score, $interpro) = @fields[0, 3, 4, 8, 11];
    if ($analysis eq 'Pfam' and defined $signature and $TE_PFAM{$signature =~ s/\.\d+$//r}) {
      my $te_group = group_for($id);
      my $te_evalue = defined $score && $score =~ /^[0-9.eE+-]+$/ ? $score : undef;
      if (defined $te_group and defined $te_evalue) {   # past Pfam's own threshold, as for domains
        my $current = $transposon{$te_group};
        if (!$current or $te_evalue < $current->{evalue}
            or ($te_evalue == $current->{evalue} and ($signature cmp $current->{signature} or $id cmp $current->{id}) < 0)) {
          $transposon{$te_group} = { id => $id, signature => $signature =~ s/\.\d+$//r, evalue => $te_evalue,
                                     pfam_name => $fields[5] // '' };
        }
      }
    }
    next unless defined $interpro and exists $entry{$interpro};
    next if $analysis eq 'ProSitePatterns';
    my $group = group_for($id) or next;
    my $evalue = $EVALUE_ANALYSIS{$analysis} && defined $score && $score =~ /^[0-9.eE+-]+$/ ? $score : undef;
    my $candidate = { id => $id, entry => $interpro, %{$entry{$interpro}}, analysis => $analysis,
                      signature => $signature // '', evalue => $evalue };
    my $current = $best{$group};
    if (!$current or better_domain($candidate, $current)) {
      $best{$group} = $candidate;
    }
  }
  close $fh;
  return %best;
}

sub better_domain {
  my ($new, $old) = @_;
  return 1 if defined $new->{evalue} and !defined $old->{evalue};
  return 0 if !defined $new->{evalue} and defined $old->{evalue};
  if (defined $new->{evalue} and $new->{evalue} != $old->{evalue}) {
    return $new->{evalue} < $old->{evalue} ? 1 : 0;
  }
  return ($new->{entry} cmp $old->{entry} or $new->{id} cmp $old->{id}) < 0 ? 1 : 0;
}

sub read_curated {
  my ($file, $curated) = @_;
  open my $fh, '<', $file or die "cant open human-curated names $file $!\n";
  my $source = $file;
  while (my $line = <$fh>) {
    if ($line =~ /^## Annotation Source:\s*(.+?)\s*$/) {
      $source = $1;
    }
    next if $line =~ /^#/;
    chomp $line;
    my ($id, $accession, $description, $score) = split /\t/, $line;
    my $group = group_for($id) or next;
    next if exists $curated->{$group};
    $curated->{$group} = { id => $id, hit => $accession, description => $description // '',
                            score => $score // '-', source => $source };
  }
  close $fh;
}

# ##############################################################################
# closest human gene

# human gene key -> best bitscore of this gene's similarity hits (MMseqs2 RBH, DIAMOND) to it
sub human_support {
  my ($group) = @_;
  my %support;
  foreach my $hit (@{$hits{$group} // []}) {
    next unless $hit->{human} and defined $hit->{bits};
    my $key = $hit->{human}{key};
    $support{$key} = $hit->{bits} if !defined $support{$key} or $hit->{bits} > $support{$key};
  }
  return \%support;
}

sub choose_closest_human {
  my ($group) = @_;
  my @links = @{$human_links{$group} // []};
  return undef unless @links;
  my $best_tier = 99;
  foreach my $link (@links) {
    $best_tier = $link->{tier} if $link->{tier} < $best_tier;
  }
  my @tier_links;
  foreach my $link (@links) {
    push @tier_links, $link if $link->{tier} == $best_tier;
  }
  my $support = human_support($group);
  @tier_links = order_links($best_tier, $support, @tier_links);
  # tiers 1-2: every co-ortholog, in that order; later tiers: the single best link
  @tier_links = ($tier_links[0]) if $best_tier > 2;
  my (%seen, @humans);
  foreach my $link (@tier_links) {
    foreach my $human (@{$link->{human}}) {
      next if $seen{$human->{key}}++;
      push @humans, $human;
    }
  }
  my $closest = { tier => $best_tier, human => \@humans, evidence => $tier_links[0]{evidence},
                  type => $tier_links[0]{type}, id => $tier_links[0]{id}, hit => $tier_links[0]{hit} };
  # OMA's pairwise file can pair a gene 1:1 with ONE human copy of a vertebrate duplication
  # (HDAC1 of HDAC1/HDAC2) while OMA's own HOGs, which follow the species tree, make it
  # co-ortholog of every copy. Then the HOG is the more complete call: the family, not one copy.
  if ($best_tier == 1 and @humans == 1) {
    my (%hog_seen, @hog_humans);
    foreach my $link (sort { ($a->{human}[0]{key} // '') cmp ($b->{human}[0]{key} // '') } grep { $_->{tier} == 2 } @links) {
      foreach my $human (@{$link->{human}}) {
        push @hog_humans, $human unless $hog_seen{$human->{key}}++;
      }
    }
    if (@hog_humans > 1 and $hog_seen{$humans[0]{key}}) {
      $stats{'closest human: pairwise 1 gene, HOG several (family)'}++;
      $closest = { %$closest, human => \@hog_humans, hog_family => 1, pairwise_human => $humans[0],
                   evidence => "OMA ortholog ($closest->{type}) of " . human_label($humans[0]) . "; OMA HOG co-ortholog of " . scalar(@hog_humans) . " human genes" };
      @humans = @hog_humans;
    }
  }
  # OMA is precise but not infallible: repetitive and compositionally biased proteins, and hidden
  # paralogy (each lineage lost a different copy of an old duplication) give OMA pairs that no
  # other evidence backs. An OMA human ortholog is used only when the gene is also similar to it
  # (any human hit to that gene, E <= $HIT_MAX_EVALUE) or shares its PANTHER family; otherwise it
  # is set aside -- for the closest gene and the name alike -- and the next evidence decides.
  # The pair stays in the database's OMA ortholog tables.
  if ($best_tier <= 2 and !oma_supported($group, \@humans)) {
    $stats{'OMA human ortholog set aside: no homology support'}++;
    $unsupported_oma{$group} = { humans => [@humans], type => $closest->{type} };
    $human_links{$group} = [ grep { $_->{tier} > 2 } @links ];
    my $next = choose_closest_human($group) or return undef;
    return { %$next, evidence => "$next->{evidence}; OMA ortholog " . join('/', map { human_label($_) } @humans)
                                 . " not used (no similarity or PANTHER family supports it)" };
  }
  # One human gene: that gene. Several (a 1:many or many:many family), or a PANTHER
  # subfamily (tier 7, family-level evidence even with one human member): the family, never
  # one member picked by score -- a gene that predates a duplication is equally related to
  # every copy, and the best-scoring copy is only the slowest-evolving one.
  return $closest if @humans == 1 and $best_tier != 7;
  my $size = @humans;
  $stats{'closest human: family'}++;
  return { %$closest, family_size => $size, family => 1,
           evidence => "$closest->{evidence}, family of $size" };
}

# Order links so the result never depends on hash order (the links arrive in whatever order
# the OMA/hit files were walked through hashes). Tiers 1-2 are OMA and carry no score: the pair
# type first (1:1 > many:1 > 1:many > many:many; Compara's one2one > one2many > many2many
# likewise), then how strongly this gene's own MMseqs2/DIAMOND hits agree (best bitscore to
# that same human gene). Later tiers: their own bitscore first, then the same two. Then a human
# gene with an HGNC record over one without (it has a symbol). Ids last, only for exact ties.
sub order_links {
  my ($tier, $support, @links) = @_;
  my %agreement;
  foreach my $link (@links) {
    my $best = 0;
    foreach my $human (@{$link->{human}}) {
      my $bits = $support->{$human->{key}} // 0;
      $best = $bits if $bits > $best;
    }
    $agreement{$link} = $best;
  }
  my $by_type      = sub { ($LINK_TYPE_RANK{$_[0]{type} // ''} // 9) <=> ($LINK_TYPE_RANK{$_[1]{type} // ''} // 9) };
  my $by_agreement = sub { $agreement{$_[1]} <=> $agreement{$_[0]} };
  my $has_hgnc     = sub { ($_[0]{human}[0]{hgnc_id} // '') ne '' ? 1 : 0 };
  my $by_hgnc      = sub { $has_hgnc->($_[1]) <=> $has_hgnc->($_[0]) };
  my $by_ids       = sub { (($_[0]{human}[0]{key} // '') cmp ($_[1]{human}[0]{key} // ''))
                             or (($_[0]{id} // '') cmp ($_[1]{id} // ''))
                             or (($_[0]{hit} // '') cmp ($_[1]{hit} // '')) };
  if ($tier <= 2) {
    return sort { $by_type->($a, $b) or $by_agreement->($a, $b) or $by_hgnc->($a, $b) or $by_ids->($a, $b) } @links;
  }
  # E-value next: DIAMOND without its coverage columns reports no bitscore, and ranking those
  # hits by id instead picked a closest gene that disagreed with the E-value-ranked name
  return sort { (($b->{bits} // 0) <=> ($a->{bits} // 0)) or (($a->{evalue} // 1e9) <=> ($b->{evalue} // 1e9))
                or $by_agreement->($a, $b)
                or $by_type->($a, $b) or $by_hgnc->($a, $b) or $by_ids->($a, $b) } @links;
}

# the smallest HGNC gene group every member shares that is a family by descent (coherence, see
# $HGNC_GROUP_MIN_COHERENCE: "Integrin alpha subunits", not "CD molecules"), or nothing
sub shared_hgnc_group {
  my ($humans, $all) = @_;   # $all: every shared group, coherent or not (for provenance text)
  my %groups_seen;
  foreach my $human (@$humans) {
    my %own_groups;
    foreach my $gene_group (split /\|/, $human->{gene_group} // '') {
      $own_groups{$gene_group} = 1 if $gene_group ne '';
    }
    $groups_seen{$_}++ foreach keys %own_groups;
  }
  my @shared = grep { $groups_seen{$_} == scalar @$humans } keys %groups_seen;
  @shared = sort { hgnc_group_size($a) <=> hgnc_group_size($b) or $a cmp $b } @shared;
  return $shared[0] if $all;
  foreach my $gene_group (@shared) {
    return $gene_group if hgnc_group_coherence($gene_group) >= $HGNC_GROUP_MIN_COHERENCE;
  }
  $stats{'HGNC group not a family by descent (PANTHER coherence), not used'}++ if @shared;
  return undef;
}

# How much an HGNC group is a family by descent: the fraction of its human genes that have a
# Swiss-Prot PANTHER family which are in the group's most common one (see
# $HGNC_GROUP_MIN_COHERENCE). A group with fewer than two such genes, or no Swiss-Prot data at
# all, cannot be judged and counts as coherent (1).
my %group_coherence;
sub hgnc_group_coherence {
  my ($gene_group) = @_;
  if (!%group_coherence) {
    my (%judged, %in_family);
    foreach my $record (values %{$hgnc->{by_id}}) {
      my $families = $human_panther{$record->{hgnc_id}} or next;
      foreach my $name (split /\|/, $record->{gene_group} // '') {
        next if $name eq '';
        $judged{$name}++;
        foreach my $family (keys %$families) {
          $in_family{$name}{$family}++;
        }
      }
    }
    foreach my $name (keys %judged) {
      my ($largest) = sort { $b <=> $a } values %{$in_family{$name}};
      $group_coherence{$name} = $judged{$name} < 2 ? 1 : $largest / $judged{$name};
    }
    $group_coherence{''} = 1;   # filled once, even when there is no Swiss-Prot data
  }
  return $group_coherence{$gene_group} // 1;
}

# A PANTHER family every human member is in (Swiss-Prot) AND the gene itself matches, as a whole
# member (see $FAMILY_NAME_MIN_OWN_COVERAGE), with an informative label -- what a co-ortholog
# family or a paralog tie is named for when no HGNC group they share is a family by descent.
# Several: by family id.
sub shared_panther_family {
  my ($group, $humans) = @_;
  my %count;
  foreach my $human (@$humans) {
    return undef unless $human->{hgnc_id};
    foreach my $family (keys %{$human_panther{$human->{hgnc_id}} // {}}) {
      $count{$family}++;
    }
  }
  my $full_length = grep { my $human = $_; ($human_hit{$group}{$human->{key}} // {})->{best_full} } @$humans;
  my @shared = sort grep { $count{$_} == @$humans and $gene_panther{$group}{$_}
                           and ($full_length or ($gene_family_coverage{$group}{$_} // 0) >= $FAMILY_NAME_MIN_OWN_COVERAGE)
                           and defined $panther_label{$_} and is_informative_hit('', $panther_label{$_}, $_) } keys %count;
  $stats{'PANTHER family shared, but the gene is not a whole member (< 50% of the model, no full-length hit): not used'}++
    if !@shared and grep { $count{$_} == @$humans and $gene_panther{$group}{$_} } keys %count;
  return $shared[0];
}

# a family as ONE closest entry: symbol column "<HGNC group> family" when the
# members share one, else "A/B-family" for up to 3 members -- "(N genes)" added when some
# members have no symbol, so "SCYGR2-family" never reads as one gene -- else "family of N genes"
sub family_entry {
  my ($size, $symbols, $descriptions, $group_name) = @_;
  my @symbols = grep { $_ ne '' } @$symbols;
  my $label = defined $group_name ? ($group_name =~ /family$/i ? $group_name : "$group_name family")
            : (@symbols and $size <= 3) ? join('/', @symbols) . '-family' . (@symbols < $size ? " ($size genes)" : '')
            : "family of $size genes";
  my %seen;
  my @descriptions = grep { $_ ne '' and !$seen{$_}++ } @$descriptions;
  my $description = join(' / ', @descriptions[0 .. ($#descriptions < 2 ? $#descriptions : 2)]);
  $description .= " ... ($size genes)" if $size > 3;
  return { id => '', symbol => $label, description => $description };
}

my (%species_oma, %species_hit);   # tag -> group -> [ OMA pairs ] / best hit in the hits file
my %species_hit_source;            # tag -> the hits file's Annotation Source, else its file name

# a name from the naming species: plain for another annotation of this same species,
# otherwise "-like (label)", as for any other species' hit
sub naming_species_text {
  my ($symbol, $description) = @_;
  return ($symbol ne '' ? "$symbol: $description" : $description) if $naming_species->{same_species};
  my $like_symbol = $symbol ne '' ? add_like_to_symbol($symbol) : '';
  my $like_description = add_like_to_description($description ne '' ? $description : $symbol)
                       . " ($naming_species->{label})";
  return $like_symbol ne '' ? "$like_symbol: $like_description" : $like_description;
}

# naming step 2: the use_for_names species' OMA 1:1 / many:1 ortholog, else its hits file
sub naming_species_name {
  my ($group) = @_;
  return not_named('no naming species') unless $naming_species;
  my $tag = $naming_species->{tag};
  my $code = $naming_species->{oma_code} // '';
  foreach my $candidate (@{$species_oma{$tag}{$group} // []}) {
    next unless $candidate->{type} eq '1:1' or $candidate->{type} eq 'many:1';
    my $parsed = $candidate->{parsed};
    my $symbol = is_placeholder_symbol($parsed->{gene_id}) ? '' : $parsed->{gene_id};
    my $description = clean_name($parsed->{description});
    next unless is_informative_hit($symbol, $description, $candidate->{hit});
    $stats{"name: $tag (OMA $candidate->{type})"}++;
    my $partner = $parsed->{protein_ids}[0] // $candidate->{hit};
    my $rule = $naming_species->{same_species}
      ? "Same gene in another annotation of this species ($naming_species->{species} $partner; OMA, $candidate->{type})"
      : "Ortholog of $naming_species->{species} $partner (OMA, $candidate->{type}); named after it, marked -like";
    return { origin => { kind => 'ncbi', accession => $partner, step => 2, rule => $rule },
             desc => naming_species_text($symbol, $description),
             tag => [($naming_species->{same_species} ? 'SRC' : 'ISO'), relationship_tag($candidate->{type}), lc $tag],
             selected => selected_id($group, $candidate->{id}),
             note => "OMA_pairwise_$code|Orthologs|" . strip_suffixes($candidate->{id}) . "|$candidate->{hit}|$candidate->{type}" };
  }
  if (my $hit = $species_hit{$tag}{$group}) {
    my ($symbol, $description) = split_symbol($hit->{description});
    $symbol = '' if is_placeholder_symbol($symbol);
    $description = clean_name($description);
    if (is_informative_hit($symbol, $description, $hit->{hit})) {
      $stats{"name: $tag hits file"}++;
      my $type = $naming_species->{same_species} ? 'Same_species' : 'Naming_species';
      my $rule = $naming_species->{same_species}
        ? "Same gene in another annotation of this species ($naming_species->{species} $hit->{hit}; best hit in $hit->{source}, E=" . e_value($hit->{evalue}) . ")"
        : "Best hit $naming_species->{species} $hit->{hit} ($hit->{source}, E=" . e_value($hit->{evalue}) . "); named after it, marked -like";
      return { origin => { kind => 'ncbi', accession => $hit->{hit}, step => 2, rule => $rule },
               desc => naming_species_text($symbol, $description),
               tag => [($naming_species->{same_species} ? 'SRC' : 'ISS'), lc $tag],
               selected => selected_id($group, $hit->{id}),
               note => "$hit->{source}|$type|" . strip_suffixes($hit->{id}) . "|$hit->{hit}|$hit->{score}" };
    }
  }
  return not_named((@{$species_oma{$tag}{$group} // []} or $species_hit{$tag}{$group})
                   ? "no $naming_species->{species} OMA 1:1 or many:1 ortholog or best hit with an informative name"
                   : "no $naming_species->{species} OMA ortholog or hit");
}

# closest gene in a --closest-species species, informative or not, by the same rule as the
# closest human gene: the best-ranked OMA relationship, a family of several genes reported as
# the family; no OMA relationship at all: the best hit in the hits file
sub choose_closest_species {
  my ($species, $group) = @_;
  my $tag = $species->{tag};
  my $hit = $species_hit{$tag}{$group};
  my @pairs = @{$species_oma{$tag}{$group} // []};
  if (@pairs) {
    my $type = $pairs[0]{type};
    my (%seen, @genes);
    foreach my $pair (@pairs) {
      last if $pair->{type} ne $type;
      next if $seen{$pair->{hit}}++;
      my $parsed = $pair->{parsed};
      push @genes, { id => $parsed->{protein_ids}[0] // $pair->{hit}, oma_id => $pair->{hit},
                     symbol => $parsed->{gene_id} // '', description => clean_name($parsed->{description} // '') };
    }
    return { rank => 1, genes => \@genes, evidence => "OMA ortholog ($type)" } if @genes == 1;

    # a family: reported as the family (see choose_closest_human)
    my $size = @genes;
    $stats{"closest $tag: family"}++;
    return { rank => 1, genes => [ family_entry($size, [map { $_->{symbol} } @genes], [map { $_->{description} } @genes]) ],
             evidence => "OMA ortholog ($type), family of $size" };
  }
  if ($hit) {
    my ($symbol, $description) = split_symbol($hit->{description});
    return { rank => 2, genes => [ { id => $hit->{hit}, symbol => $symbol // '', description => clean_name($description // '') } ],
             evidence => "hits file ($hit->{source})" };
  }
  return undef;
}

# OMA pairs with oma_code (from the same --oma-dir run), best rank first; the hits file
# (moop TSV: id accession "SYMBOL: description" score), best E-value per gene
sub collect_closest_species {
  my ($species) = @_;
  my $tag = $species->{tag};
  if ($species->{oma_code} and defined $opt{'oma-dir'} and defined $opt{'oma-code'}) {
    my $pairs = read_oma_pairs("$opt{'oma-dir'}/Output", $opt{'oma-code'}, $species->{oma_code});
    foreach my $oma_target_id (keys %$pairs) {
      foreach my $own (own_ids_and_groups($oma_target_id)) {
        my ($target_id, $group) = @$own;
        foreach my $pair (@{$pairs->{$oma_target_id}}) {
          next unless exists $OMA_RANK{$pair->{type}};
          push @{$species_oma{$tag}{$group}}, { id => $target_id, hit => $pair->{partner_id}, type => $pair->{type},
                                                parsed => parse_oma_header($pair->{partner_header}) };
        }
      }
    }
    foreach my $group (keys %{$species_oma{$tag} // {}}) {
      @{$species_oma{$tag}{$group}} = sort { $OMA_RANK{$a->{type}} <=> $OMA_RANK{$b->{type}} or $a->{hit} cmp $b->{hit} }
                                      @{$species_oma{$tag}{$group}};
    }
  }
  if ($species->{hits}) {
    open my $fh, '<', $species->{hits} or die "cant open $species->{hits} $!\n";
    my $source = $species->{hits};
    while (my $line = <$fh>) {
      if ($line =~ /^## Annotation Source:\s*(.+?)\s*$/) {
        ($source = $1) =~ s/\s+/_/g;
      }
      next if $line =~ /^#/;
      chomp $line;
      my ($id, $accession, $description, $score) = split /\t/, $line;
      my $group = group_for($id) or next;
      my $evalue = ($score // '') =~ /^[0-9.eE+-]+$/ ? $score : 1;
      if (!exists $species_hit{$tag}{$group} or $evalue < $species_hit{$tag}{$group}{evalue}) {
        $species_hit{$tag}{$group} = { id => $id, hit => $accession, description => $description // '',
                                       score => $score // '-', evalue => $evalue, source => $source };
      }
    }
    close $fh;
    ($species_hit_source{$tag} = $source) =~ s{.*/}{};
  }
}

# ##############################################################################
# naming: collect every step's candidate, then decide
#
# Every naming step is evaluated for every gene (collect_candidates). Each step returns a
# candidate: the name it would give, or not_named(why) -- why it gives none. choose_name then
# takes the first step, in order, that gives a name, with the few rules that tie steps together
# (decide_name). The decision table prints these same candidates, so it cannot disagree with
# the names. A step's counters (%stats) are kept with its candidate and added only when the
# decision reached that step, so stats.txt counts what happened, not everything evaluated.

# [ step, what it is, the function giving its candidate ] -- the order the steps are tried
my @NAMING_STEPS = (
  [1, 'human-curated name',    \&curated_name],
  [2, 'naming species',        \&naming_species_name],
  [3, 'OMA human ortholog',    \&oma_name],
  [4, 'transposable element',  \&te_name],
  [5, 'full-length human hit', \&like_name],
  [6, 'PANTHER family',        \&panther_family_name],
  [7, 'InterPro domain',       \&domain_name],
);

# a candidate that gives no name, and why
sub not_named {
  my ($why, %extra) = @_;
  return { why => $why, %extra };
}

sub is_named {
  my ($candidate) = @_;
  return ($candidate and defined $candidate->{desc}) ? 1 : 0;
}

sub collect_candidates {
  my ($group) = @_;
  my %candidate;
  foreach my $naming_step (@NAMING_STEPS) {
    my ($step, undef, $code) = @$naming_step;
    my %saved = %stats;
    %stats = ();
    my $result = $code->($group) // not_named('no evidence');
    $result->{counts} = { %stats };
    %stats = %saved;
    $candidate{$step} = $result;
  }
  return \%candidate;
}

# the name: the first step that gives one, in order, with the rules that tie steps together.
# The decision (the step chosen, the steps reached, why a step was passed over) is kept in
# %decision for the decision table.
sub choose_name {
  my ($group) = @_;
  my $candidate = $candidates{$group};
  my (@reached, %passed);
  my $chosen = decide_name($group, $candidate, \@reached, \%passed);
  foreach my $step (@reached) {
    foreach my $counter (keys %{$candidate->{$step}{counts}}) {
      $stats{$counter} += $candidate->{$step}{counts}{$counter};
    }
  }
  $decision{$group} = { step => $chosen, reached => \@reached, passed => \%passed };
  return $candidate->{$chosen} if $chosen;
  $stats{'name: none'}++;
  return { desc => 'None', selected => selected_id($group, undef), note => 'none|none|none|none|-' };
}

# returns the chosen step (0: none); fills @$reached (steps tried, in order) and %$passed (a
# step passed over by a rule here, not by its own evidence)
sub decide_name {
  my ($group, $candidate, $reached, $passed) = @_;
  my $try = sub {
    my ($step) = @_;
    push @$reached, $step;
    return is_named($candidate->{$step});
  };

  # a human-curated name is kept as is (never checked); then the naming species
  foreach my $step (1, 2) {
    return $step if $try->($step);
  }

  # a transposon family: many OMA many:1 copies of one human gene, each with a TE domain, are
  # named as transposable elements, not as orthologs (te_name)
  if ($candidate->{4}{oma_override}) {
    $passed->{3} = "passed over: $TE_MIN_COPIES or more copies in this genome pair with the same human gene and carry a transposable-element domain -- a transposon family";
    return 4 if $try->(4);
  }

  # OMA orthology to human. A family OMA could not name is not handed to step 5 either: the best
  # BLAST hit would just be picking one member by score again.
  my $oma_family = 0;
  my $closest = $closest{$group};
  if ($closest and $closest->{tier} <= 2) {
    return 3 if $try->(3);
    $conflicting_oma{$group} = $candidate->{3}{conflict} if $candidate->{3}{conflict};
    $oma_family = $closest->{family} ? 1 : 0;
  } else {
    $try->(3);   # tried: no OMA human ortholog (or one set aside, omaX)
  }

  # a transposable-element protein: named for its TE class before similarity or family names,
  # which would give it the name of a human gene domesticated from such an element
  return 4 if $try->(4);

  if ($oma_family) {
    $passed->{5} = 'skipped: OMA makes it co-ortholog of several human genes (step 3) that could not name it; a best hit would pick one of them by score';
  } elsif ($try->(5)) {
    return 5;
  }
  foreach my $step (6, 7) {
    return $step if $try->($step);
  }
  return 0;
}

# naming step 1: a human-curated name (a person named these genes, e.g. Chamaeleo's Apollo
# file), kept as is: deliberately NOT subject to is_informative_hit, unlike every step below
sub curated_name {
  my ($group) = @_;
  my $curated = $curated{$group} or return not_named('no human-curated name');
  $stats{'name: human-curated'}++;
  (my $source = $curated->{source}) =~ s/\s+/_/g;
  return { desc => $curated->{description}, selected => selected_id($group, $curated->{id}), tag => ['TAS'],
           note => "$source|Curated|$curated->{id}|$curated->{hit}|$curated->{score}",
           origin => { kind => 'curated', accession => $curated->{hit}, step => 1, rule => "Named by a curator ($curated->{source})" } };
}

# naming step 3: OMA orthology to human (closest human tier 1-2)
sub oma_name {
  my ($group) = @_;
  my $closest = $closest{$group};
  if (!$closest or $closest->{tier} > 2) {
    return not_named($unsupported_oma{$group}
                     ? 'OMA human ortholog set aside (omaX): no similarity hit or PANTHER family supports it'
                     : 'no OMA human ortholog');
  }
  my $named = ortholog_name($group, $closest);
  return $named unless is_named($named);
  $stats{"name: OMA " . ($closest->{family} ? 'family' : $closest->{type})}++;
  return $named;
}

# naming step 4: a transposable-element Pfam domain. Also before step 3 when OMA pairs the gene
# many:1 with one human gene together with $TE_MIN_COPIES or more copies (oma_override).
sub te_name {
  my ($group) = @_;
  return not_named('no transposable-element Pfam domain') unless $transposon{$group};
  my $closest = $closest{$group};
  my $override = ($closest and $closest->{tier} <= 2 and !$closest->{family}
                  and scalar(keys %{$claimed_human{$closest->{human}[0]{key}} // {}}) >= $TE_MIN_COPIES) ? 1 : 0;
  my $named = transposon_name($group, $override ? $closest : undef);
  $named->{oma_override} = 1 if $override;
  return $named;
}

# naming step 6: PANTHER family (only matches covering most of the family's model;
# read_panther_families). InterPro's curated name for the family when InterPro has taken it in,
# else PANTHER's own.
sub panther_family_name {
  my ($group) = @_;
  my $family = $panther{$group};
  if (!$family) {
    my $coverage = $gene_family_coverage{$group} // {};
    my ($best) = sort { $coverage->{$b} <=> $coverage->{$a} or $a cmp $b } keys %$coverage;
    return not_named(defined $best
                     ? "best PANTHER match $best covers $coverage->{$best}% of the family model (needs $FAMILY_MODEL_COVERAGE%)"
                     : 'no PANTHER match');
  }
  if (($family->{repeat_fraction} // 0) >= $REPEAT_FAMILY_FRACTION) {
    $stats{'name: repeat (PANTHER family built of repeats)'}++;
    return { desc => domain_description($family->{repeat_name}), selected => selected_id($group, $family->{id}), tag => ['ISM', 'rpt'],
             note => "PANTHER|Gene_Families|$family->{id}|$family->{family}|$family->{evalue}",
             origin => { kind => 'panther', accession => $family->{family}, step => 6,
                         rule => "Its PANTHER family $family->{family} (\"$family->{description}\") match is "
                               . sprintf('%.0f%%', 100 * $family->{repeat_fraction}) . " repeat units ($family->{repeat_name}), "
                               . "which any protein with such repeats fills; named for the repeat, not the family" } };
  }
  my ($label, $named_by);
  if (defined $family->{interpro_name} and is_informative_hit('', $family->{interpro_name}, $family->{interpro})) {
    ($label, $named_by) = ($family->{interpro_name}, "InterPro $family->{interpro} \"$family->{interpro_name}\"");
  } elsif (is_informative_hit('', panther_name($family->{description}), $family->{family})) {
    my $why = defined $family->{interpro_set_aside}
      ? "InterPro's name \"$family->{interpro_set_aside}\" describes a function, not the family" : 'not in InterPro';
    ($label, $named_by) = (panther_name($family->{description}), "\"$family->{description}\", $why");
  }
  return not_named("PANTHER family $family->{family} (\"$family->{description}\") has no informative name") unless defined $label;
  $stats{'name: PANTHER family' . (defined $family->{interpro_name} && $label eq $family->{interpro_name} ? ' (InterPro name)' : ' (PANTHER name)')}++;
  return { desc => family_member($label), selected => selected_id($group, $family->{id}), tag => ['ISM', 'pthr'],
           note => "PANTHER|Gene_Families|$family->{id}|$family->{family}|$family->{evalue}",
           origin => { kind => 'panther', accession => $family->{family}, step => 6,
                       rule => "Member of PANTHER family $family->{family} ($named_by): "
                             . "$family->{model_coverage}% of the family model aligned, E=" . e_value($family->{evalue}) . " (InterProScan)" } };
}

# naming step 7: the gene's best InterPro domain or repeat: "X domain-containing protein"
# (UniProt's convention for a protein known only by a domain) -- claims the domain, not a gene
# identity
sub domain_name {
  my ($group) = @_;
  my $domain = $domain{$group} or return not_named('no InterPro domain or repeat');
  return not_named("InterPro $domain->{entry} \"$domain->{name}\" is not an informative name")
    unless is_informative_hit('', $domain->{name}, $domain->{entry});
  my $description = domain_description($domain->{name});
  $stats{"name: InterPro $domain->{type}"}++;
  my $signature = $domain->{analysis} . ($domain->{signature} ne '' ? " $domain->{signature}" : '')
                . (defined $domain->{evalue} ? ", E=" . e_value($domain->{evalue}) : '');
  # what the gene lacks, said truthfully: it may well have a human homolog, just not one
  # that could name it (no ortholog, no full-length hit, no family)
  my ($best) = ranked_human_hits($group);
  my $partial = '';
  if ($best) {
    my $label = human_label($best->[1]{human});
    my $numbers = sprintf('%.0f%% of this protein, %.0f%% of %s', $best->[1]{best}{qcov}, $best->[1]{best}{tcov}, $label)
                . ", E=" . e_value($best->[1]{best}{evalue});
    # a full-length homolog that still did not name it: one of a family of co-orthologs, a
    # paralog tie, or a gene whose name is uninformative
    $partial = $best->[1]{best_full}
      ? "; similar to human $label along its length ($numbers), but that gene's name could not be used "
        . "(a family of co-orthologs, a paralog tie, or an uninformative name)"
      : "; similar to human $label over part of its length only ($numbers)";
  }
  return { desc => $description, selected => selected_id($group, $domain->{id}), tag => ['ISM', 'ipr', ($best ? 'sim~' : ())],
           note => "InterPro|Domains|$domain->{id}|$domain->{entry}|" . ($domain->{evalue} // '-'),
           origin => { kind => 'interpro', accession => $domain->{entry}, step => 7,
                       rule => "Contains InterPro " . lc($domain->{type}) . " $domain->{entry} \"$domain->{name}\" ($signature); "
                             . "no ortholog, full-length homolog or family to name it by$partial" } };
}

# a name given after an unsupported OMA ortholog was set aside says so: "omaX" in its tag, and the
# pair in its provenance
sub set_aside_note {
  my ($group, $named) = @_;
  if (my $conflict = $conflicting_oma{$group}) {
    return $named unless $named->{tag} and $named->{origin};
    my $humans = join('/', map { human_label($_) } @{$conflict->{humans}});
    my $best = $conflict->{best} ? human_label($conflict->{best}) : 'another gene';
    return { %$named, tag => [@{$named->{tag}}, 'omaC'],
             origin => { %{$named->{origin}}, rule => $named->{origin}{rule} . "; OMA pairs it with human $humans ($conflict->{type}), "
                                                     . "but its best human similarity hit is $best and its PANTHER family differs, so $humans does not name it" } };
  }
  my $set_aside = $unsupported_oma{$group} or return $named;
  return $named unless $named->{tag} and $named->{origin};
  my $humans = join('/', map { human_label($_) } @{$set_aside->{humans}});
  return { %$named, tag => [@{$named->{tag}}, 'omaX'],
           origin => { %{$named->{origin}}, rule => $named->{origin}{rule} . "; OMA pairs it with human $humans ($set_aside->{type}), "
                                                   . "but no similarity hit or PANTHER family supports that pair, so it does not name the gene" } };
}

# an OMA human ortholog (or co-ortholog set) is supported when the gene has a similarity hit to one
# of those human genes or shares a PANTHER family with them; without a human search, nothing can
# be checked and OMA stands
sub oma_supported {
  my ($group, $humans) = @_;
  return 1 unless $human_searched;
  foreach my $human (@$humans) {
    return 1 if $human_hit{$group}{$human->{key}};
  }
  my %human_families;
  foreach my $human (@$humans) {
    $human_families{$_} = 1 foreach keys %{$human_panther{$human->{hgnc_id} // ''} // {}};
  }
  return (grep { $human_families{$_} } keys %{$gene_panther{$group} // {}}) ? 1 : 0;
}

# An OMA name is withheld when both independent checks go against it: the gene's best human
# similarity hit is ANOTHER gene (sim~) AND its PANTHER family differs from the named gene's
# (pthrC). Each alone is common and weak -- a close paralog can outscore the ortholog, and PANTHER
# families are split and renamed -- but together they are the signature of hidden paralogy
# (each lineage kept a different copy of an old duplication) or of an OMA pair made through a
# shared repeat or domain (Congeria: APOH x18, selectins, matrilins through Sushi / vWA domains).
# The next step names the gene; the tag carries omaC and the provenance says why. The closest
# human gene is left as OMA called it. Returns the conflict (decide_name keeps it in
# %conflicting_oma when the decision reaches step 3), else undef.
sub oma_conflicts {
  my ($group, $humans, $closest, $flags) = @_;
  my %flag = map { my $flag = $_; ($flag => 1) } @$flags;
  return undef unless $flag{'sim~'} and $flag{'pthrC'};
  my ($best) = ranked_human_hits($group);
  $stats{'OMA name withheld: best hit another gene and a different PANTHER family'}++;
  my $humans_text = join('/', map { my $human = $_; human_label($human) } @$humans);
  return { humans => [@$humans], type => $closest->{type}, best => $best ? $best->[1]{human} : undef,
           why => "withheld (omaC): OMA pairs it with human $humans_text ($closest->{type}), but its best human hit is "
                . ($best ? human_label($best->[1]{human}) : 'another gene') . " and its PANTHER family differs" };
}

# the evidence tag every name ends with, GO-style: " [ISO|1to1|sim+|pthr+]" (no colon inside --
# downstream, the text before a name's first colon is its symbol). The full reasoning is in the
# Gene Name Source table; the tag is the short form a reader sees next to the name.
#   ISO  orthology (OMA)          1to1, Nto1 (N copies share the human gene), mto1, fam (co-orthologs)
#   ISS  similarity (-like)        rbh (reciprocal best hit) or bh; tie-rbh / tie-grp: a paralog tie resolved
#   ISM  sequence model            pthr (PANTHER family), ipr (InterPro domain)
#   TAS  human-curated name;  SRC  the gene set's own name, or another annotation of the same species
# Support marks, one meaning each: + agrees, ~ partly (similar, not the best), C contradicts (the
# evidence points elsewhere), - no evidence, X excluded (set aside).
# support of an orthology name: sim+ its human gene is the best human similarity hit, sim~ a hit but
# not the best, sim- no hit; pthr+ / pthrC same / conflicting PANTHER family; hog OMA's HOG agrees.
# sim~ on an ISM name: the gene has a human homolog, but only a partial one.
# omaX: an OMA pair nothing supports was set aside; omaC: an OMA name was withheld because the best
# human hit is another gene AND the PANTHER family differs (oma_conflicts).
sub tagged {
  my ($named) = @_;
  return $named unless $named->{tag} and $named->{desc} ne 'None';
  return { %$named, desc => "$named->{desc} [" . join('|', @{$named->{tag}}) . "]" };
}

# OMA relationship as the tag writes it (no colon)
sub relationship_tag {
  my ($type, $copies) = @_;
  return '1to1' if $type eq '1:1';
  if ($type eq 'many:1') {
    return defined $copies && $copies > 1 ? "${copies}to1" : 'mto1';
  }
  return 'fam';
}

# How well an orthology name's human gene(s) are backed by homology evidence that OMA did not
# use: this gene's own similarity hits (is the named gene its best human hit?) and its PANTHER
# family (InterProScan) against the human gene's (Swiss-Prot). OMA is never overruled -- the name
# stays -- but a reader is told when nothing else supports it. Returns (tag flags, provenance text).
sub oma_support {
  my ($group, $humans, $closest) = @_;
  my %named = map { $_->{key} => 1 } @$humans;
  my $one = @$humans == 1;
  my $label = $one ? human_label($humans->[0]) : 'these genes';
  my (@flags, @said);
  my $similar = 0;
  if ($human_searched) {
    my @ranked = ranked_human_hits($group);
    if (@ranked and $named{$ranked[0][0]}) {
      push @flags, 'sim+';
      push @said, ($one ? $label : 'one of ' . $label) . ' is its best human similarity hit';
      $similar = 1;
    } elsif (grep { $named{$_->[0]} } @ranked) {
      push @flags, 'sim~';
      push @said, "similar to " . ($one ? $label : 'one of ' . $label) . ", but its best human hit is " . human_label($ranked[0][1]{human});
      $similar = 1;
    } else {
      push @flags, 'sim-';
      push @said, "no similarity hit to " . ($one ? $label : 'any of ' . $label) . " (E <= " . e_value($HIT_MAX_EVALUE) . ")";
    }
  }
  my %gene_families = %{$gene_panther{$group} // {}};
  my %human_families;
  foreach my $human (@$humans) {
    $human_families{$_} = 1 foreach keys %{$human_panther{$human->{hgnc_id} // ''} // {}};
  }
  my $same_family = 0;
  if (%gene_families and %human_families) {
    my @shared = sort grep { $human_families{$_} } keys %gene_families;
    if (@shared) {
      push @flags, 'pthr+';
      push @said, "same PANTHER family ($shared[0])";
      $same_family = 1;
    } else {
      push @flags, 'pthrC';
      push @said, "a different PANTHER family (" . join('/', sort keys %gene_families) . "; human: " . join('/', sort keys %human_families) . ")";
    }
  }
  my $hog = $closest->{tier} == 2 || $closest->{hog_family}
         || grep { $_->{tier} == 2 and $named{$_->{human}[0]{key} // ''} } @{$human_links{$group} // []};
  push @flags, 'hog' if $hog;
  my $text = @said ? '; ' . join('; ', @said) : '';
  return (\@flags, $text);
}

# an InterPro domain or repeat name as a protein name, UniProt's convention: "Zinc finger, RING-type"
# -> "Zinc finger RING-type domain-containing protein" (comma + space dropped; "1,2-lyase" kept),
# "WD40 repeat" -> "WD40 repeat-containing protein"
sub domain_description {
  my ($name) = @_;
  $name =~ s/,\s+/ /g;
  $name =~ s/\s+/ /g;
  return $name =~ /(?:domain|repeats?)(?:\s+\d+)?$/i ? "$name-containing protein" : "$name domain-containing protein";
}

# a transposable-element protein, named for its TE class ("PIF/Harbinger transposase
# domain-containing protein"); with $closest, OMA had paired it with one human gene together with
# >= $TE_MIN_COPIES copies in this genome, which is a TE family, not an ortholog
sub transposon_name {
  my ($group, $closest) = @_;
  my $te = $transposon{$group};
  my ($class, $kind, $domain) = @{$TE_PFAM{$te->{signature}}};
  $stats{'name: transposable element' . ($closest ? ' (instead of an OMA many:1 name)' : '')}++;
  my $rule = "Contains Pfam $te->{signature} (\"$te->{pfam_name}\", E=" . e_value($te->{evalue}) . "), the $domain domain of $class ${kind}s; "
           . "named as a transposable-element protein";
  if ($closest) {
    my $copies = scalar keys %{$claimed_human{$closest->{human}[0]{key}}};
    $rule .= "; OMA pairs it with human " . human_label($closest->{human}[0]) . " ($closest->{type}) together with "
           . ($copies - 1) . " other copies in this genome -- a transposon family, not one ortholog";
  }
  return { desc => domain_description($domain), selected => selected_id($group, $te->{id}), tag => ['ISM', 'te'],
           note => "Pfam|Transposable_element|$te->{id}|$te->{signature}|$te->{evalue}",
           origin => { kind => 'pfam', accession => $te->{signature}, step => 4, rule => $rule } };
}

sub ortholog_name {
  my ($group, $closest) = @_;
  my @humans = @{$closest->{human}};
  my $source = $closest->{tier} == 1 ? 'OMA_pairwise_HUMAN' : 'OMA_HOG_HUMAN';
  my $note_tail = strip_suffixes($closest->{id}) . "|$closest->{hit}|$closest->{type}";
  my $selected = selected_id($group, $closest->{id});
  my $method = $closest->{tier} == 1 ? 'OMA' : 'OMA HOG';

  if (!$closest->{family}) {
    my $human = $humans[0];
    return not_named("the name of human " . human_label($human) . " is not informative")
      unless is_informative_hit($human->{symbol}, $human->{name}, $human->{key});
    my $symbol = $human->{hgnc_id} ? $human->{symbol} : '';
    my $description = $human->{name};
    # many:1 (a duplication in this lineage): every copy is an ortholog of the human gene and
    # carries its name; how many copies share it is in the tag and the provenance
    my $copies = scalar keys %{$claimed_human{$human->{key}} // {}};
    my ($flags, $support) = oma_support($group, [$human], $closest);
    my $conflict = oma_conflicts($group, [$human], $closest, $flags);
    return not_named($conflict->{why}, conflict => $conflict) if $conflict;
    my $rule = ($closest->{tier} == 1 ? 'Ortholog' : 'Co-ortholog') . " of human " . human_label($human)
             . " ($method, $closest->{type})" . ($copies > 1 ? ", one of $copies copies in this genome" : '') . $support;
    if (my $te = $transposon{$group}) {
      push @$flags, 'te';
      $rule .= "; it carries a $TE_PFAM{$te->{signature}}[2] domain (Pfam $te->{signature}), as do human genes domesticated from transposons";
    }
    return { desc => ($symbol ne '' ? "$symbol: $description" : $description), selected => $selected,
             tag => ['ISO', relationship_tag($closest->{type}, $copies), @$flags],
             note => "$source|Orthologs|$note_tail", origin => human_origin($human, $rule, 3) };
  }

  # a family: named after the most specific HGNC gene group they all share, or not named here
  # at all -- never after a member picked by score or by spelling
  # No symbol: the symbol is what users search as the gene's identity, and a family has none.
  my $shared = shared_hgnc_group(\@humans);
  if (!defined $shared) {
    # no HGNC group they share is a family by descent: the PANTHER family they all belong to, and
    # the gene matches, names them; else step 6
    my $family = shared_panther_family($group, \@humans)
      or return not_named("co-ortholog of " . scalar(@humans) . " human genes that share no HGNC group that is a family by descent, "
                          . "and no PANTHER family the gene matches as a whole member");
    my ($flags, $support) = oma_support($group, \@humans, $closest);
    my $conflict = oma_conflicts($group, \@humans, $closest, $flags);
    return not_named($conflict->{why}, conflict => $conflict) if $conflict;
    my $scattered = shared_hgnc_group(\@humans, 'all');
    my $not_group = defined $scattered
      ? sprintf('; the HGNC group they share ("%s") is not a family by descent (PANTHER coherence %.2f)', $scattered, hgnc_group_coherence($scattered)) : '';
    $stats{'name: OMA family, named for its PANTHER family'}++;
    return { desc => family_member($panther_label{$family}), selected => $selected, note => "$source|Orthologs|$note_tail",
             tag => ['ISO', 'fam', @$flags],
             origin => { kind => 'panther', accession => $family, step => 3,
                         rule => "Co-ortholog of " . scalar(@humans) . " human genes ($method, $closest->{type}), all in PANTHER family $family "
                               . "(\"$panther_label{$family}\"), which it matches too$not_group$support" } };
  }
  my ($flags, $support) = oma_support($group, \@humans, $closest);
  my $conflict = oma_conflicts($group, \@humans, $closest, $flags);
  return not_named($conflict->{why}, conflict => $conflict) if $conflict;
  my $why = $closest->{hog_family}
    ? "OMA pairs it $closest->{type} with human " . human_label($closest->{pairwise_human}) . ", but OMA's HOG makes it co-ortholog of "
      . scalar(@humans) . " human genes in the HGNC group \"$shared\" (copies duplicated on the human side after the two lineages split); named for the group"
    : "Co-ortholog of " . scalar(@humans) . " human genes in the HGNC group \"$shared\" ($method, $closest->{type}); no single ortholog";
  return { desc => family_member($shared), selected => $selected, note => "$source|Orthologs|$note_tail",
           tag => ['ISO', 'fam', @$flags],
           origin => { kind => 'hgnc_group', accession => hgnc_group_id(\@humans, $shared), step => 3, rule => "$why$support" } };
}

# a human gene in provenance text: its HGNC symbol, else its Ensembl id or description
sub human_label {
  my ($human) = @_;
  return $human->{hgnc_id} ne '' ? $human->{symbol} : $human->{key} =~ /^ENSG/ ? $human->{key} : "\"$human->{name}\"";
}

# an E-value as provenance shows it: 2e-95, 0
sub e_value {
  my ($evalue) = @_;
  return '?' unless defined $evalue and $evalue =~ /^[0-9.eE+-]+$/;
  return $evalue == 0 ? '0' : sprintf('%.0e', $evalue);
}

# the provenance of a name taken from one human gene: its HGNC record, else its Ensembl gene
sub human_origin {
  my ($human, $rule, $step) = @_;
  return $human->{hgnc_id} ne '' ? { kind => 'hgnc', accession => $human->{hgnc_id}, rule => $rule, step => $step }
       : $human->{key} =~ /^ENSG/ ? { kind => 'ensembl', accession => $human->{key}, rule => $rule, step => $step }
       : { kind => 'nolink', accession => $human->{name}, rule => $rule, step => $step };
}

# HGNC's id for one of the members' group names (names and ids are parallel "|" lists)
sub hgnc_group_id {
  my ($humans, $group_name) = @_;
  foreach my $human (@$humans) {
    my @names = split /\|/, $human->{gene_group} // '';
    my @ids = split /\|/, $human->{gene_group_id} // '';
    foreach my $index (0 .. $#names) {
      return $ids[$index] if $names[$index] eq $group_name and defined $ids[$index] and $ids[$index] ne '';
    }
  }
  return $group_name;
}

# "Integrin alpha subunits" -> "Integrin alpha subunits family member";
# "Tubulin beta family" -> "Tubulin beta family member"
sub family_member {
  my ($family) = @_;
  # a colon would be read downstream as "SYMBOL: description" (updateGFF.pl takes what precedes
  # the first colon as the symbol): "DUMPY: SHORTER THAN WILD-TYPE" -> "DUMPY - SHORTER ..."
  (my $name = $family) =~ s/\s*:\s*/ - /g;
  $name =~ s/\s+/ /g;   # stray double spaces in PANTHER / HGNC names
  $name =~ s/^ | $//g;
  # already "... family member" ("SOLUTE CARRIER FAMILY 22 MEMBER"); ending in a family
  # ("Solute carrier family 5", "Glycosyl transferase family 10", "... superfamily") -> "member";
  # else "X family member" -- "family" earlier in the name does not count ("GPCR family 3,
  # GABA-B receptor family member", not "... receptor member")
  return $name if $name =~ /\bfamily\s+member$/i;
  return $name =~ /family(?:\s+[A-Za-z]?\d+[A-Za-z]?)?$/i ? "$name member" : "$name family member";
}

# PANTHER's own family names are written from one member's UniProt entry; tidy what reads as
# that entry rather than as a family: "BONUS, ISOFORM C-RELATED" -> "BONUS-RELATED",
# "...-RELATED-RELATED", "APICAL ENDOSOMAL GLYCOPROTEIN PRECURSOR." Clone and locus ids
# ("LD39211P", "AGAP001331-PA-RELATED") are left for is_informative_hit to reject.
sub panther_name {
  my ($name) = @_;
  $name =~ s/,\s*ISOFORM\s+[A-Z0-9_]+\b//gi;
  $name =~ s/(?:-RELATED)+/-RELATED/gi;
  $name =~ s/\s*\.$//;
  $name =~ s/\s+PRECURSOR$//i;
  $name =~ s/\s+/ /g;
  $name =~ s/^ | $//g;
  return sentence_case($name);
}

# PANTHER writes its family names in capitals ("SHORT-CHAIN DEHYDROGENASE/REDUCTASE FAMILY 9C").
# Sentence case, word by word, keeping acronyms: a word HGNC's approved names use takes HGNC's most
# frequent spelling of it ("dehydrogenase", "zinc", "GTPase", "CoA", "tRNA", but "SET", "RNA");
# a word HGNC does not use is lowered when it has at least 6 letters (METALLOPROTEASE, PERMEASE)
# and kept as an acronym when shorter (NACHT, DOMON, RECQ). Words with digits (9C, E2) stay. The
# first letter is a capital. Names with any lower-case letter are left alone.
my %hgnc_spelling;   # upper-case word -> HGNC's most frequent spelling of it
sub sentence_case {
  my ($name) = @_;
  return $name if $name =~ /[a-z]/ or $name !~ /[A-Z]/;
  if (!%hgnc_spelling) {
    my %count;
    foreach my $record (values %{$hgnc->{by_id}}) {
      foreach my $word (split /[^A-Za-z]+/, $record->{name} // '') {
        $count{uc $word}{$word}++ if length $word >= 2;
      }
    }
    foreach my $upper (keys %count) {
      # most frequent spelling; a tie goes to the one with fewer capitals, then alphabetically
      my ($spelling) = sort { $count{$upper}{$b} <=> $count{$upper}{$a} or ($a =~ tr/A-Z//) <=> ($b =~ tr/A-Z//) or $a cmp $b }
                       keys %{$count{$upper}};
      $hgnc_spelling{$upper} = $spelling;
    }
    $hgnc_spelling{''} = '';   # filled once
  }
  my @parts = split /([A-Za-z]+)/, $name;
  foreach my $part (@parts) {
    next unless $part =~ /^[A-Za-z]{2,}$/;
    if (exists $hgnc_spelling{uc $part}) {
      $part = $hgnc_spelling{uc $part};
    } elsif (length $part >= 6) {
      $part = lc $part;
    }
  }
  my $cased = join('', @parts);
  return ucfirst $cased;
}

my %group_size_cache;
sub hgnc_group_size {
  my ($gene_group) = @_;
  if (!%group_size_cache) {
    foreach my $record (values %{$hgnc->{by_id}}) {
      foreach my $name (split /\|/, $record->{gene_group}) {
        $group_size_cache{$name}++ if $name ne '';
      }
    }
  }
  return $group_size_cache{$gene_group} // 1e9;
}

# Naming step 5: "-like" from similarity to ONE human gene along the whole length. Hits to other
# species never name a gene: a transferred name may be a lineage-specific paralog ("member 4a" in
# fish), which we cannot tell and should not copy. Human genes are compared by gene id (HGNC,
# else Ensembl gene) and bitscore, never by name text, over every human hit (record_human_hit):
#   - the human gene the protein is MOST similar to (any coverage) must itself have a full-length
#     (FULL) hit -- a weaker full-length hit to another gene never names it (a WD40 protein that
#     hits WDR90 at 562 bits over part of WDR90 is not "CFAP52-like" from a 119-bit full hit);
#   - if another human gene scores within $LIKE_TIE of it, the paralogs are a tie: a reciprocal best
#     hit to exactly one of them (with a full-length hit) decides; else their shared HGNC group
#     names the gene; else no -like name.
sub like_name {
  my ($group) = @_;
  my @ranked = grep { $_->[1]{best}{evalue} <= $FULL{evalue} } ranked_human_hits($group);
  return not_named($human_searched ? 'no human hit with E <= ' . e_value($FULL{evalue}) : 'no search against human proteins') unless @ranked;
  my ($top_key, $top) = @{$ranked[0]};
  if (!$top->{best_full}) {
    $stats{'no -like name: best human gene not full-length'}++ if grep { $_->[1]{best_full} } @ranked;
    return not_named(sprintf('best human hit %s is not full-length: %.0f%% of this protein, %.0f%% of %s (needs %d%% of both)',
                             human_label($top->{human}), $top->{best}{qcov}, $top->{best}{tcov}, human_label($top->{human}), $FULL{qcov}));
  }
  my @tied = grep { $_->[1]{best}{bits} >= $LIKE_TIE * $top->{best}{bits} } @ranked[1 .. $#ranked];
  my ($chosen, $tie_flag, @tie_genes) = ($top, '');
  if (@tied) {
    @tie_genes = map { $_->[1]{human} } ($ranked[0], @tied);
    my @reciprocal = grep { $_->[1]{rbh} and $_->[1]{best_full} } ($ranked[0], @tied);
    if (@reciprocal == 1) {
      ($chosen, $tie_flag) = ($reciprocal[0][1], 'tie-rbh');
    } else {
      my $shared = (grep { !$_->{hgnc_id} } @tie_genes) ? undef : shared_hgnc_group(\@tie_genes);
      my $family = defined $shared ? undef : shared_panther_family($group, \@tie_genes);
      if (defined $family) {
        $stats{'name: full-length human hit, paralog tie (PANTHER family)'}++;
        my $hit = $top->{best_full};
        my $members = join(', ', map { human_label($_) } @tie_genes);
        return { desc => family_member($panther_label{$family}), selected => selected_id($group, $hit->{id}),
                 tag => ['ISS', ($top->{rbh} ? 'rbh' : 'bh'), 'tie-grp'],
                 note => "$hit->{source}|$hit->{type}|" . strip_suffixes($hit->{id}) . "|$hit->{hit}|$hit->{evalue}",
                 origin => { kind => 'panther', accession => $family, step => 5,
                             rule => "Similar along its length to human genes of PANTHER family $family (\"$panther_label{$family}\") that score within "
                                   . sprintf('%.0f%%', 100 * (1 - $LIKE_TIE)) . " of each other ($members); no one of them is closest, "
                                   . "no single reciprocal best hit decides, and they share no HGNC group that is a family by descent; named for the PANTHER family" } };
      }
      if (!defined $shared) {
        $stats{'no -like name: paralog tie, no reciprocal hit or shared HGNC group'}++;
        return not_named('paralog tie: ' . join(', ', map { my $human = $_; human_label($human) } @tie_genes)
                         . ' score within ' . sprintf('%.0f%%', 100 * (1 - $LIKE_TIE)) . ' of each other; no single reciprocal best hit, '
                         . 'and no shared HGNC group or PANTHER family names them');
      }
      $stats{'name: full-length human hit, paralog tie (HGNC group)'}++;
      my $hit = $top->{best_full};
      my $members = join(', ', map { human_label($_) } @tie_genes);
      return { desc => family_member($shared), selected => selected_id($group, $hit->{id}), tag => ['ISS', ($top->{rbh} ? 'rbh' : 'bh'), 'tie-grp'],
               note => "$hit->{source}|$hit->{type}|" . strip_suffixes($hit->{id}) . "|$hit->{hit}|$hit->{evalue}",
               origin => { kind => 'hgnc_group', accession => hgnc_group_id(\@tie_genes, $shared), step => 5,
                           rule => "Similar along its length to human genes of the HGNC group \"$shared\" that score within "
                                 . sprintf('%.0f%%', 100 * (1 - $LIKE_TIE)) . " of each other ($members); no one of them is closest, "
                                 . "and no single reciprocal best hit decides; named for the group" } };
    }
  }
  my $human = $chosen->{human};
  return not_named("the name of human " . human_label($human) . " is not informative")
    unless is_informative_hit($human->{symbol}, $human->{name}, $chosen->{best_full}{hit});
  return like_text($group, $chosen, $tie_flag, \@tie_genes);
}

# similarity is not orthology, reciprocal or not: always "-like". The symbol is the human
# gene's HGNC symbol, or none -- never borrowed from another gene.
sub like_text {
  my ($group, $entry, $tie_flag, $tie_genes) = @_;
  my $human = $entry->{human};
  my $hit = $entry->{best_full};
  my $symbol = $human->{hgnc_id} ? $human->{symbol} : '';
  my $description = $human->{name} ne '' ? $human->{name} : $symbol;
  $stats{'name: full-length human hit (-like)' . ($entry->{rbh} ? ', reciprocal' : '') . ($tie_flag ? ", $tie_flag" : '')}++;
  my $like_symbol = $symbol ne '' ? add_like_to_symbol($symbol) : '';
  my $like_description = add_like_to_description($description);
  my $label = human_label($human);
  my $rule = sprintf('Similar to human %s along its length: %s, %.0f%% of this protein and %.0f%% of %s aligned, E=%s (%s)',
                     $label, ($entry->{rbh} ? 'reciprocal best hit' : 'best hit'), $hit->{qcov}, $hit->{tcov},
                     $label, e_value($hit->{evalue}), $hit->{tool});
  if ($tie_flag) {
    $rule .= "; " . join(', ', map { human_label($_) } @$tie_genes) . " score within " . sprintf('%.0f%%', 100 * (1 - $LIKE_TIE))
           . " of each other, and only $label is a reciprocal best hit";
  }
  return { desc => ($like_symbol ne '' ? "$like_symbol: $like_description" : $like_description),
           selected => selected_id($group, $hit->{id}), tag => ['ISS', ($entry->{rbh} ? 'rbh' : 'bh'), ($tie_flag ? $tie_flag : ())],
           note => "$hit->{source}|$hit->{type}|" . strip_suffixes($hit->{id}) . "|$hit->{hit}|$hit->{evalue}",
           origin => human_origin($human, $rule, 5) };
}

sub selected_id {
  my ($group, $evidence_id) = @_;
  return $curated_selected{$group} if exists $curated_selected{$group};
  if (defined $evidence_id) {
    foreach my $member (@{$members{$group}}) {
      return $member if $member eq $evidence_id or $member eq strip_suffixes($evidence_id);
    }
  }
  return $members{$group}[0];
}

# ##############################################################################
# output

# every geneNames.tsv row: [ id, main, GroupId column, desc, note, group, origin ]
my @name_rows;

sub write_outputs {
  if (defined $opt{native}) {
    collect_native_rows();
  } else {
    foreach my $group (sort keys %members) {
      my $named = $name{$group};
      foreach my $id (sort @{$members{$group}}) {
        my $main = $id eq $named->{selected} ? 'SELF' : $named->{selected};
        push @name_rows, [$id, $main, $group, $named->{desc}, $named->{note}, $group, $named->{origin}];
      }
    }
  }
  open my $names_fh, '>', $opt{'out-names'} or die "cant write $opt{'out-names'} $!\n";
  print $names_fh join("\t", qw(ID MAINID GroupId Desc Note)), "\n";
  foreach my $row (@name_rows) {
    print $names_fh join("\t", @{$row}[0 .. 4]), "\n";
  }
  close $names_fh;

  # human: its closest pick, as HGNC records
  my %human_closest;
  foreach my $group (keys %closest) {
    my $closest = $closest{$group} or next;
    my @genes = $closest->{family}
      ? (family_entry($closest->{family_size}, [map { $_->{hgnc_id} ? $_->{symbol} : '' } @{$closest->{human}}],
                      [map { $_->{name} } @{$closest->{human}}], shared_hgnc_group($closest->{human})))
      : map { { id => ($_->{hgnc_id} ne '' ? $_->{hgnc_id} : $_->{key} =~ /^ENSG/ ? $_->{key} : ''),
                symbol => ($_->{hgnc_id} ne '' ? $_->{symbol} : ''), description => $_->{name} } } @{$closest->{human}};
    $human_closest{$group} = { rank => $closest->{tier}, genes => \@genes, evidence => $closest->{evidence} };
    $stats{"closest human: tier $closest->{tier}"}++;
  }
  my $version = reference_versions();
  write_name_source($version);
  write_closest('human', [qw(closestHGNC closestHumanSym closestHumanDesc closestHumanEvidence)], \%human_closest, [
    { file => '', source => 'Closest human gene (HGNC)', version => $version, url => 'https://www.genenames.org',
      accession_url => 'https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/', match => sub { $_[0]{id} =~ /^HGNC:/ } },
    { file => '.ensembl', source => 'Closest human gene (Ensembl, no HGNC record)', version => $version,
      url => 'https://www.ensembl.org', accession_url => 'https://www.ensembl.org/Homo_sapiens/Gene/Summary?g=',
      match => sub { $_[0]{id} ne '' } },
    { file => '.family', source => 'Closest human gene family', version => $version, url => 'https://www.genenames.org',
      accession_url => '', match => sub { 1 } },
  ]);

  foreach my $species (@closest_species) {
    my $tag = $species->{tag};
    my %species_closest;
    foreach my $group (keys %members) {
      my $closest = choose_closest_species($species, $group) or next;
      $species_closest{$group} = $closest;
      $stats{"closest $tag: " . ($closest->{rank} == 1 ? 'OMA' : 'hits file')}++;
    }
    my @sources;
    push @sources, "OMA $opt{'oma-code'}-$species->{oma_code}" if $species->{oma_code} and defined $opt{'oma-code'};
    push @sources, $species_hit_source{$tag} if defined $species_hit_source{$tag};
    my $species_version = join('; ', @sources);
    write_closest(lc $tag, [map { "closest$tag$_" } qw(Id Sym Desc Evidence)], \%species_closest, [
      { file => '', source => "Closest $species->{species} gene", version => $species_version,
        url => 'https://www.ncbi.nlm.nih.gov', accession_url => 'https://www.ncbi.nlm.nih.gov/search/all/?term=',
        match => sub { $_[0]{id} ne '' } },
      { file => '.family', source => "Closest $species->{species} gene family", version => $species_version,
        url => 'https://www.ncbi.nlm.nih.gov', accession_url => '', match => sub { 1 } },
    ]);
  }
}

sub open_closest_moop {
  my ($file, $source, $type) = @_;
  $type //= $CLOSEST_TYPE;
  open my $fh, '>', $file or die "cant write $file $!\n";
  print $fh "## Annotation Source: $source->{source}
## Annotation Source Version: $source->{version}
## Annotation Source URL: $source->{url}
## Annotation Accession URL: $source->{accession_url}
## Annotation Type: $type
## Annotation Creation Date: " . `date '+%Y-%m-%d'`;
  print $fh join("\t", '## Gene', 'Accession', 'Accession_Description', 'Score'), "\n";
  return $fh;
}

# gene_name_source.<kind>.moop.tsv: a row for every named id and its gene. Accession = what the
# name came from; description = why, in words (the name itself is its own column on the site);
# score = the naming step.
sub write_name_source {
  my ($version) = @_;
  my (%fh, %done);
  foreach my $row (@name_rows) {
    my $origin = $row->[6] or next;
    my $kind = $origin->{kind};
    my $meta = $NAME_SOURCE{$kind} or die "unknown name source kind $kind\n";
    my $fh = $fh{$kind} //= open_closest_moop("$opt{'out-dir'}/gene_name_source.$kind.moop.tsv",
      { source => $meta->[0], version => $version, url => $meta->[1], accession_url => $meta->[2] }, $NAME_SOURCE_TYPE);
    foreach my $feature ($row->[0], $row->[2]) {   # the id, and its gene
      next if !defined $feature or $done{$feature}++;
      print $fh join("\t", $feature, $origin->{accession}, $origin->{rule}, $origin->{step}), "\n";
    }
  }
  close $_ foreach values %fh;
}

# closest_<file_tag>.tsv (geneNames.tsv rows, columns named by the GFF attributes) and one
# closest_<file_tag><source file>.moop.tsv per source that has rows (gene and every isoform)
sub write_closest {
  my ($file_tag, $columns, $closest_of, $sources) = @_;
  my $base = "$opt{'out-dir'}/closest_$file_tag";
  open my $fh, '>', "$base.tsv" or die "cant write $base.tsv $!\n";
  print $fh join("\t", 'ID', 'GroupId', @$columns), "\n";
  foreach my $row (@name_rows) {
    my $closest = defined $row->[5] ? $closest_of->{$row->[5]} : undef;
    my (@ids, @symbols, @names);
    foreach my $gene (@{$closest ? $closest->{genes} : []}) {
      push @ids, $gene->{id};
      push @symbols, $gene->{symbol};
      (my $name = $gene->{description}) =~ s/,/%2C/g;   # commas separate multiple genes
      push @names, $name;
    }
    print $fh join("\t", $row->[0], $row->[2], join(',', @ids), join(',', @symbols), join(',', @names),
                   $closest ? $closest->{evidence} : ''), "\n";
  }
  close $fh;

  # one database file per source: each source has one accession link (a family has none),
  # and every source shares the one annotation type, so the site shows and filters them together
  my %moop_fh;
  foreach my $group (sort keys %members) {
    my $closest = $closest_of->{$group} or next;
    # the gene and every isoform carry the same final pick
    my %features = ($group => 1);
    foreach my $member (@{$members{$group}}) {
      $features{$member} = 1;
    }
    foreach my $gene (@{$closest->{genes}}) {
      my ($source) = grep { $_->{match}->($gene) } @$sources;
      my $fh = $moop_fh{$source->{file}} //= open_closest_moop("$base$source->{file}.moop.tsv", $source);
      my $label = $gene->{symbol} ne '' && $gene->{description} ne '' ? "$gene->{symbol}: $gene->{description}"
                : ($gene->{description} || $gene->{symbol});
      foreach my $feature (sort keys %features) {
        print $fh join("\t", $feature, ($gene->{id} ne '' ? $gene->{id} : $gene->{symbol}),
                       "$label [$closest->{evidence}]", $closest->{rank}), "\n";
      }
    }
  }
  close $_ foreach values %moop_fh;
}

# native RefSeq/Ensembl names are kept as provided unless uninformative
sub collect_native_rows {
  my (%native_rows, %native_group_informative);
  open my $fh, '<', $opt{native} or die "cant open $opt{native} $!\n";
  while (my $line = <$fh>) {
    chomp $line;
    next if $line =~ /^ID\t/;
    my ($id, $main, $native_group, $desc, $note) = split /\t/, $line;
    push @{$native_rows{$native_group}}, [$id, $main, $native_group, $desc // '', $note // ''];
    if ($main eq 'SELF') {
      my ($symbol, $description) = split_symbol($desc // '');
      $native_group_informative{$native_group} = is_informative_hit($symbol, $description, $id);
    }
  }
  close $fh;
  foreach my $native_group (sort keys %native_rows) {
    my $group;
    foreach my $row (@{$native_rows{$native_group}}) {
      $group = group_for($row->[0]);
      last if defined $group;
    }
    my $keep_native = $native_group_informative{$native_group} // 1;
    $stats{$keep_native ? 'native name kept' : 'native name replaced (uninformative)'}++;
    foreach my $row (@{$native_rows{$native_group}}) {
      my ($id, $main, $native_group_id, $desc, $note) = @$row;
      my $origin = $desc ne '' && $desc ne 'None'
        ? { kind => 'native', accession => $native_group_id, step => 2, rule => "Name from this gene set's own annotation" } : undef;
      if (!$keep_native and defined $group) {
        ($desc, $note, $origin) = ($name{$group}{desc}, $name{$group}{note}, $name{$group}{origin});
      } elsif ($origin) {
        $desc = tagged({ desc => $desc, tag => ['SRC'] })->{desc};   # the source's own text, as it is, plus the tag
      }
      push @name_rows, [$id, $main, $native_group_id, $desc, $note, $group, $origin];
    }
  }
}

sub reference_versions {
  my @parts;
  foreach my $file ("$opt{'hgnc-dir'}/VERSION.txt") {
    if (open my $fh, '<', $file) {
      my $line = <$fh> // '';
      close $fh;
      my ($date) = $line =~ /(\d{4}-\d{2}-\d{2})/;
      push @parts, "HGNC $date" if $date;
    }
  }
  if (defined $opt{'oma-dir'} and open my $readme_fh, '<', "$opt{'oma-dir'}/README.exportedAllAll") {
    while (my $line = <$readme_fh>) {
      push @parts, "OMA $1" if $line =~ /^OMA template:\s*(\S+)/;
    }
    close $readme_fh;
  }
  push @parts, 'Ensembl Compara ' . join('/', sort keys %compara_releases_used) if %compara_releases_used;
  if (defined $opt{'uniprot-dir'} and open my $uniprot_fh, '<', "$opt{'uniprot-dir'}/VERSION.txt") {
    my $line = <$uniprot_fh> // '';
    close $uniprot_fh;
    push @parts, "UniProt $1" if $line =~ /UniProt release ([0-9_]+)/;
  }
  return @parts ? join('; ', @parts) : 'unknown';
}

# ##############################################################################
# decision table: naming_decisions.tsv, for people to read -- one row per gene: the name, the
# step that gave it and why, every step's own result (named / not used and why / not reached),
# the key scores whatever their cutoffs, and the closest human gene. A "#" header records the
# run, every cutoff, the abbreviations, and the programs and data it read.

# a file's modification date, for the header
sub file_date {
  my ($file) = @_;
  my $mtime = (stat $file)[9] or return 'missing';
  my @time = localtime $mtime;
  return sprintf('%04d-%02d-%02d', $time[5] + 1900, $time[4] + 1, $time[3]);
}

# the first line of a small version file, tabs as spaces
sub first_line {
  my ($file) = @_;
  open my $fh, '<', $file or return '';
  my $line = <$fh> // '';
  close $fh;
  chomp $line;
  $line =~ s/\t/ /g;
  return $line;
}

# the programs and data this run read, one "#" line each
sub input_lines {
  my @lines;
  push @lines, "gene set: $opt{isoforms} (" . file_date($opt{isoforms}) . "); proteins $opt{'protein-fasta'} (" . file_date($opt{'protein-fasta'}) . ")";
  if (defined $opt{'oma-dir'}) {
    push @lines, "OMA: $opt{'oma-dir'} (all-vs-all and orthologs, " . file_date("$opt{'oma-dir'}/Output") . "), this genome's OMA code "
               . ($opt{'oma-code'} // '?');
  }
  foreach my $search (['mmseqs-dir', 'MMseqs2 reciprocal best hits', 'rbh_mmseq_results.tsv', 'rbh_mmseq_version.txt'],
                      ['diamond-dir', 'DIAMOND blastp', 'diamond_results.tsv', 'diamond_version.txt']) {
    my ($option, $label, $results, $version_file) = @$search;
    my $base = $opt{$option} // next;
    opendir my $dir_handle, $base or next;
    foreach my $entry (sort readdir $dir_handle) {
      my ($file) = grep { my $candidate = $_; -e $candidate } ("$base/$entry/$results", "$base/$entry/$results.gz");
      next unless $file;
      my $database = first_line("$base/$entry/db_version.txt") || $entry;
      my $version = first_line("$base/$entry/$version_file");
      push @lines, "$label vs $database: " . ($version ne '' ? "$version; " : '') . "$file (" . file_date($file) . ")";
    }
    closedir $dir_handle;
  }
  if (defined $opt{interproscan}) {
    (my $dir = $opt{interproscan}) =~ s{/[^/]+$}{};
    my $version = first_line("$dir/interproscan_version.txt");
    push @lines, "InterProScan " . ($version ne '' ? "$version: " : '') . "$opt{interproscan} (" . file_date($opt{interproscan}) . ")";
    push @lines, "InterPro entries: $opt{'interpro-entries'} (" . file_date($opt{'interpro-entries'}) . "); "
               . "PANTHER model lengths: $opt{'panther-hmm-lengths'} (" . file_date($opt{'panther-hmm-lengths'}) . ")";
  }
  push @lines, "HGNC: " . (first_line("$opt{'hgnc-dir'}/VERSION.txt") || "$opt{'hgnc-dir'}");
  push @lines, "UniProt: " . first_line("$opt{'uniprot-dir'}/VERSION.txt") if defined $opt{'uniprot-dir'};
  push @lines, "Ensembl Compara: $opt{'compara-dir'}, release(s) used " . (join('/', sort keys %compara_releases_used) || 'none')
    if defined $opt{'compara-dir'};
  push @lines, "NCBI taxonomy: $opt{'taxonomy-dir'}" if defined $opt{'taxonomy-dir'};
  push @lines, "native names: $opt{native} (" . file_date($opt{native}) . ")" if defined $opt{native};
  foreach my $file (@{$opt{'human-curated-gene-names'}}) {
    push @lines, "human-curated names: $file (" . file_date($file) . ")";
  }
  foreach my $species (@closest_species) {
    push @lines, "closest species $species->{tag} ($species->{species}): "
               . join(', ', ($species->{oma_code} ? "OMA code $species->{oma_code}" : ()),
                            ($species->{hits} ? "hits $species->{hits} (" . file_date($species->{hits}) . ")" : ()))
               . ($species->{use_for_names} ? '; names genes (step 2)' : '');
  }
  return @lines;
}

sub decision_header {
  my $commit = `git -C '$FindBin::Bin' log -1 --format='%h %cs' 2>/dev/null` // '';
  chomp $commit;
  my $dirty = `git -C '$FindBin::Bin' status --porcelain -- '$FindBin::Script' 2>/dev/null` // '';
  $commit .= ' (with uncommitted changes)' if $dirty ne '';
  my @header = (
    'Gene naming decisions: one row per gene. For people to read; the names themselves are in geneNames.tsv,',
    'and the database tables (Gene Name Source, Closest Gene) come from the same decisions.',
    '',
    'RUN',
    '  date: ' . `date '+%Y-%m-%d %H:%M'` =~ s/\n//r,
    "  script: $FindBin::Bin/$FindBin::Script" . ($commit ne '' ? " (git $commit)" : ''),
    '  command: ' . join(' ', @command_line),
    '',
    'PROGRAMS AND DATA',
    (map { my $line = $_; "  $line" } input_lines()),
    '',
    'NAMING STEPS (tried in this order; the first that gives a name names the gene)',
    '  1 human-curated name, as given',
    '  2 naming species (closest species with use_for_names): its OMA 1:1 / many:1 ortholog, else its best hit;',
    '    or, with --native, the gene set\'s own name when informative (then Step says "2 native name")',
    '  3 OMA human ortholog (closest human tier 1-2): 1:1 / many:1 -> the gene\'s name; co-orthologs -> their HGNC group',
    '    (if a family by descent) or PANTHER family; withheld when omaC',
    "  4 transposable element (a TE Pfam domain); before step 3 when >= $TE_MIN_COPIES copies share one OMA human gene",
    '  5 full-length human hit -> "SYM-like"; skipped after an OMA co-ortholog family step 3 could not name',
    '  6 PANTHER family -> "<family> family member"',
    '  7 InterPro domain or repeat -> "<domain> domain-containing protein"',
    '  - none',
    '',
    'CUTOFFS',
    "  full-length hit (names, step 5): E <= " . e_value($FULL{evalue}) . ", >= $FULL{qcov}% of this protein and >= $FULL{tcov}% of the other aligned",
    "  normal hit (closest human, tiers 3-7): E <= " . e_value($NORMAL{evalue}) . ", >= $NORMAL{qcov}% of both proteins",
    "  any hit (support of an OMA name, best human gene): E <= " . e_value($HIT_MAX_EVALUE) . ", any coverage",
    "  paralog tie: another human gene scoring within " . sprintf('%.0f%%', 100 * (1 - $LIKE_TIE)) . " of the best bitscore",
    "  PANTHER family name (step 6): the match covers >= $FAMILY_MODEL_COVERAGE% of the family model",
    "  PANTHER family for co-orthologs / a tie: the gene's own match >= $FAMILY_NAME_MIN_OWN_COVERAGE% of the model, or a full-length hit to a member",
    "  HGNC group as a family: PANTHER coherence >= $HGNC_GROUP_MIN_COHERENCE (share of the group's human genes in its main PANTHER family)",
    "  repeat-built PANTHER family: repeat units >= " . sprintf('%.0f%%', 100 * $REPEAT_FAMILY_FRACTION) . " of the match -> named for the repeat",
    "  transposon family: >= $TE_MIN_COPIES copies sharing one OMA human gene (chosen by testing; arbitrary)",
    '',
    'ABBREVIATIONS (the tag at the end of a name)',
    '  evidence: ISO orthology (OMA); ISS similarity (-like); ISM sequence model (PANTHER, InterPro, Pfam);',
    '            TAS human-curated; SRC the gene set\'s own name or another annotation of this species',
    '  relationship: 1to1; Nto1 (N copies here share the human gene); mto1; fam (co-ortholog of several human genes)',
    '  similarity: rbh reciprocal best hit; bh best hit; tie-rbh / tie-grp a paralog tie resolved by a reciprocal hit / a family',
    '  model: pthr PANTHER family; ipr InterPro domain; rpt repeat; te transposable element',
    '  support marks: + agrees, ~ partly (similar, not the best), C contradicts, - no evidence, X excluded',
    '    sim+ / sim~ / sim- the named human gene is the best human hit / a hit but not the best / not a hit',
    '    pthr+ / pthrC same / different PANTHER family as the named human gene; hog OMA\'s HOG agrees',
    '    omaX an OMA pair nothing supports was set aside; omaC an OMA name withheld (best hit another gene AND PANTHER family differs)',
    '  closest human tiers: 1 OMA pairwise; 2 OMA HOG; 3 MMseqs2 RBH; 4 via another species\' ortholog; 5 DIAMOND best hit;',
    '    6 Swiss-Prot hit -> Ensembl Compara; 7 Swiss-Prot hit -> PANTHER subfamily',
    '',
    'COLUMNS',
    '  ID: the transcript whose evidence named the gene; GroupId: the gene',
    '  Name: as in geneNames.tsv; Step: the step that named it; Reason: why, in full',
    '  Native_name / Pipeline_name (--native only): the gene set\'s own name, and the name the steps give without it',
    '  Best_human_hit ...: the gene\'s best hit to a human gene (E <= ' . e_value($HIT_MAX_EVALUE) . ', any coverage), whatever the cutoffs:',
    '    coverage of this protein / of the human protein (%), E-value, bitscore, rbh or bh, full-length yes/no;',
    '    Second_human_hit: the next human gene and its bitscore as % of the best (a paralog close behind)',
    '  PANTHER_best / PANTHER_model_cov: the gene\'s best-covered PANTHER family and how much of the model it covers (%)',
    '  S1 ... S7: each step\'s own result --',
    '    NAMED: this step named the gene',
    '    not used: the step was tried and gives no name (why)',
    '    passed over / skipped: a rule set the step aside (why)',
    '    not reached: an earlier step named the gene; what this step would have said follows',
    '  Closest_human: the closest human gene (tier: gene, evidence), as in closest_human.tsv',
  );
  return join('', map { my $line = $_; "# $line\n" } @header);
}

# one step's cell: its status, then what it found. $native: the gene set's own name was kept
# (--native), so no step named the gene -- step 2 stands for the native name.
sub step_cell {
  my ($group, $step, $native) = @_;
  my $candidate = $candidates{$group}{$step};
  my $decision = $decision{$group};
  my %reached = map { my $number = $_; ($number => 1) } @{$decision->{reached}};
  my $found = is_named($candidate)
    ? $candidate->{desc} . ($candidate->{tag} ? ' [' . join('|', @{$candidate->{tag}}) . ']' : '')
      . ($candidate->{origin} ? " -- $candidate->{origin}{rule}" : '')
    : $candidate->{why};
  if ($native) {
    return "NAMED: the gene set's own name (--native), informative so kept" if $step == 2;
    return "not reached; the pipeline's pick without the native name: $found" if $decision->{step} == $step;
    return is_named($candidate) ? "not reached; would name: $found" : "not reached; $found";
  }
  return "NAMED: $found" if $decision->{step} == $step;
  if (my $passed = $decision->{passed}{$step}) {
    return is_named($candidate) ? "$passed; would name: $found" : "$passed; $found";
  }
  return "not used: $found" if $reached{$step};
  return is_named($candidate) ? "not reached; would name: $found" : "not reached; $found";
}

sub write_decisions {
  my ($file) = @_;
  open my $fh, '>', $file or die "cant write $file $!\n";
  print $fh decision_header();
  my %step_label = map { my $naming_step = $_; ($naming_step->[0] => $naming_step->[1]) } @NAMING_STEPS;
  print $fh join("\t", qw(ID GroupId Name Step Reason Native_name Pipeline_name Best_human_hit Best_hit_qcov Best_hit_tcov Best_hit_evalue
                          Best_hit_bits Best_hit_kind Best_hit_full_length Second_human_hit PANTHER_best PANTHER_model_cov),
                 (map { my $naming_step = $_; "S$naming_step->[0]_" . ($naming_step->[1] =~ s/[^A-Za-z0-9]+/_/gr) } @NAMING_STEPS),
                 'Closest_human'), "\n";
  # --native: the gene set's own informative names replace the decision (collect_native_rows)
  my %native_kept;
  foreach my $row (@name_rows) {
    next unless defined $row->[5] and $row->[1] eq 'SELF' and $row->[6] and $row->[6]{kind} eq 'native';
    $native_kept{$row->[5]} //= { desc => $row->[3], selected => $row->[0], origin => $row->[6] };
  }
  foreach my $group (sort keys %members) {
    my $native = $native_kept{$group};
    my $named = $native // $name{$group};
    my $step = $decision{$group}{step};
    my @ranked = ranked_human_hits($group);
    my @best = ('') x 8;
    if (@ranked) {
      my $top = $ranked[0][1];
      @best = (human_label($top->{human}), sprintf('%.0f', $top->{best}{qcov} // 0), sprintf('%.0f', $top->{best}{tcov} // 0),
               e_value($top->{best}{evalue}), sprintf('%.0f', $top->{best}{bits}), ($top->{rbh} ? 'rbh' : 'bh'),
               ($top->{best_full} ? 'yes' : 'no'),
               (@ranked > 1 ? sprintf('%s (%.0f%%)', human_label($ranked[1][1]{human}), 100 * $ranked[1][1]{best}{bits} / $top->{best}{bits}) : ''));
    }
    my $coverage = $gene_family_coverage{$group} // {};
    my ($family) = sort { $coverage->{$b} <=> $coverage->{$a} or $a cmp $b } keys %$coverage;
    my $family_text = defined $family ? $family . (defined $panther_label{$family} ? " \"$panther_label{$family}\"" : '') : '';
    my $closest = $closest{$group};
    my $closest_text = $closest
      ? "tier $closest->{tier}: " . ($closest->{family} ? join('/', map { my $human = $_; human_label($human) } @{$closest->{human}})
                                                       : human_label($closest->{human}[0])) . " ($closest->{evidence})"
      : '';
    my @cells = ($named->{selected}, $group, $named->{desc},
                 ($native ? '2 native name' : $step ? "$step $step_label{$step}" : 'none'),
                 ($named->{origin} ? $named->{origin}{rule} : 'no step gave a name'),
                 ($native ? $native->{desc} : ''),
                 ($native ? $name{$group}{desc} . ' (' . ($step ? "step $step" : 'no step') . ')' : ''),
                 @best, $family_text, (defined $family ? $coverage->{$family} : ''),
                 (map { my $naming_step = $_; step_cell($group, $naming_step->[0], $native ? 1 : 0) } @NAMING_STEPS),
                 $closest_text);
    print $fh join("\t", map { my $cell = $_ // ''; $cell =~ s/[\t\n]/ /g; $cell } @cells), "\n";
  }
  close $fh;
}

# LAST LINE: run only now, when every file-level assignment above has been made (see LAYOUT)
main();

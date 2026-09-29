package GeneNamingV2;
use strict;
use warnings;
use Exporter 'import';

our @EXPORT_OK = qw(
  clean_name split_symbol is_placeholder_symbol is_uninformative_description
  is_informative_hit add_like_to_description add_like_to_symbol
  load_hgnc hgnc_record
);

# Name handling for gene naming v2 (see notes/NAMING_V2_PLAN.md):
# cleaning hit names, deciding whether a hit is informative, adding "-like", and the
# HGNC table used to turn any human id into the current symbol and approved name.
#
# Every placeholder pattern was checked against HGNC approved symbols (Sep 2026) so no
# real human gene is flagged; keep it that way when adding patterns.

# ---------------------------------------------------------------------------
# cleaning

# Removes source decorations, not meaning: Swiss-Prot "OS=... OX=... GN=... PE=... SV=...",
# "[Source:...]", "LOW QUALITY PROTEIN:", isoform / transcript variant suffixes,
# "(Fragment)", ", partial", trailing "precursor", and the ";" sub-name lists some
# references use ("A domain-containing protein;B domain-containing protein" -> first one).
sub clean_name {
  my ($text) = @_;
  return '' unless defined $text;
  my $name = $text;
  $name =~ s/%2C/,/g;
  $name =~ s/\s+OS=.*$//;
  $name =~ s/\s*\[Source:[^\]]*\]//g;
  $name =~ s/^\s*LOW QUALITY PROTEIN:\s*//i;
  $name =~ s/,?\s+isoform\s+\S+\s*$//i;
  $name =~ s/,?\s+transcript\s+variant\s+\S+\s*$//i;
  $name =~ s/\s*\(Fragments?\)//gi;
  $name =~ s/,\s*partial\s*$//i;
  $name =~ s/\s+precursor\s*$//i;
  $name =~ s/;.*$//;
  $name =~ s/\s+/ /g;
  $name =~ s/^\s+|\s+$//g;
  return $name;
}

# "SYMBOL: description" -> (SYMBOL, description); the symbol has no spaces.
sub split_symbol {
  my ($text) = @_;
  $text //= '';
  if ($text =~ /^(\S+):\s+(.+)$/) {
    return ($1, $2);
  }
  return ('', $text);
}

# ---------------------------------------------------------------------------
# informative or not

my @PLACEHOLDER_SYMBOL_RE = (
  qr/^LOC\d+$/,                          # RefSeq, no symbol
  qr/^CG\d+$/,                           # fly computed gene
  qr/^CR\d{4,}$/,                        # fly non-coding (not human CR1/CR2)
  qr/^Gm\d+$/,                           # mouse predicted gene
  qr/^\d+[A-Z]\d+Rik$/,                  # mouse RIKEN clone
  qr/^(?:si|zgc|wu|im|sb):/,             # zebrafish clone-based
  qr/^[A-Z0-9]+\.\d+[a-z]?$/,            # C. elegans sequence name (F54D5.1)
  qr/^Y[A-P][LR]\d{3}[WC](?:-[A-Z])?$/,  # yeast systematic ORF name
  qr/^ENS[A-Z]*[PGT]\d{11}(?:\.\d+)?$/,  # Ensembl id used as a symbol
  qr/^[A-Z]{2}_\d+(?:\.\d+)?$/,          # RefSeq accession used as a symbol
);

my @UNINFORMATIVE_DESCRIPTION_RE = (
  qr/uncharacteri[sz]ed\s+(?:protein|LOC\d+)/i,
  qr/hypothetical\s+protein/i,
  qr/predicted\s+protein/i,
  qr/unnamed\s+protein\s+product/i,
  qr/unknown\s+(?:protein|function)/i,
  qr/predicted\s+gene,?\s*\d+/i,
  qr/\bnovel\s+(?:protein|gene|transcript)\b/i,
  qr/dubious\s+open\s+reading\s+frame/i,
  qr/unlikely\s+to\s+encode\s+a\s+functional\s+protein/i,
  qr/^putative\s+protein$/i,
  qr/^protein$/i,
  # "protein <locus id>": PANTHER families named after another species' unnamed locus
  # ("PROTEIN CBG26694", "PROTEIN CBG12054-RELATED", "PROTEIN CBR-CLEC-78", "PROTEIN FAM167A").
  # Narrow on purpose: "protein S100-A1" is a real name. No HGNC approved name matches.
  qr/^protein\s+(?:[A-Za-z]{2,4}\d{4,}[A-Za-z]?(?:-related)?|cbr-\S+|fam\d+[a-z]?(?:-related)?)$/i,
  qr/^eg:\S+(?:\s+protein(?:-related)?)?$/i,   # fly clone names ("EG:114D9.1 PROTEIN-RELATED")
  qr/^(?:si|zgc|wu):/i,
  # ... and "<locus id> protein", the other word order ("RGD1565685 PROTEIN", "SLR1189 PROTEIN",
  # rice/Arabidopsis loci "OS10G0105400 PROTEIN-RELATED", "AT1G01010 PROTEIN")
  qr/^(?:[A-Za-z]{2,4}\d{4,}[A-Za-z]?|[A-Za-z]{2}\d{1,2}g\d{5,})\s+protein(?:-related)?$/i,
  qr/^[A-Z]{2,3}\d{4,}p\d*(?:-related)?$/i,           # fly cDNA clones ("LD39211P", "GEO02494P1", "FI19922P1-RELATED"); not KIAA1143P1
  qr/^[A-Z]{2,6}\d{5,}-p[A-Z](?:-related)?$/i,        # mosquito etc. locus ("AGAP001331-PA-RELATED")
  # mouse clone- and EST-based names ("cDNA sequence BC048562", "RIKEN cDNA 1110002E22 gene",
  # "expressed sequence AI413582", "DNA segment, Chr 1, ERATO Doi 1"), human cDNA clone labels
  # ("cDNA FLJ12345 fis") and Celera mouse ids ("MCG131172, isoform CRA_a"). No HGNC approved name
  # matches (checked September 2026).
  qr/^(?:cDNA\s+sequence|expressed\s+sequence)\s+\S+(?:[\s-]+related)?$/i,
  qr/^RIKEN\s+cDNA\s+\S+(?:\s+gene)?(?:[\s-]+related)?$/i,
  qr/^DNA\s+segment,\s+Chr\b/i,
  qr/\bcDNA\s+FLJ\d+/i,
  qr/^MCG\d+(?:,\s*isoform\s+\S+)?(?:[\s-]+related)?$/i,
  qr/\(AFU_orthologue\s/i,                              # Aspergillus locus labels ("... putative (AFU_orthologue AFUA_1G17690)")
  qr/^(?:expressed|unnamed)\s+(?:protein|product)$/i,
  qr/^transmembrane\s+protein$/i,
  # a DUF/UPF id and nothing else that identifies a gene ("UNCHARACTERIZED DUF1308", "DUF4605
  # DOMAIN-CONTAINING PROTEIN", "UPF0462 PROTEIN"); NOT "UPF0565 protein C2orf69 homolog" (it names
  # a gene) and not UPF1/UPF3 (genes). Checked against HGNC and RefSeq names.
  qr/^(?:uncharacteri[sz]ed\s+)?(?:protein\s+)?(?:DUF\d+|UPF\d{4})(?:[\s-]+(?:domain-containing|domain|family|protein|member|related))*$/i,
);

sub is_placeholder_symbol {
  my ($symbol) = @_;
  return 1 unless defined $symbol and length $symbol;
  foreach my $pattern (@PLACEHOLDER_SYMBOL_RE) {
    return 1 if $symbol =~ $pattern;
  }
  return 0;
}

sub is_uninformative_description {
  my ($description, @ids) = @_;
  my $test = clean_name($description);
  return 1 if $test eq '' or $test eq 'None' or $test eq 'none' or $test !~ /[A-Za-z0-9]/;   # "-", "."
  foreach my $pattern (@UNINFORMATIVE_DESCRIPTION_RE) {
    return 1 if $test =~ $pattern;
  }
  # a description that only repeats an id or symbol ("CG12345", "F13H8.2 protein")
  foreach my $id (@ids) {
    next unless defined $id and length $id;
    return 1 if $test =~ /^\Q$id\E(?:\s+(?:protein|gene\s+product|precursor))?$/i;
  }
  return 1 if is_placeholder_symbol($test) and $test !~ /\s/;
  return 0;
}

# A hit can name a gene if it has an informative description, or failing that a real
# (non-placeholder) symbol.
sub is_informative_hit {
  my ($symbol, $description, $hit_id) = @_;
  return 1 unless is_uninformative_description($description, $symbol, $hit_id);
  return 0;
}

# ---------------------------------------------------------------------------
# "-like"

sub add_like_to_description {
  my ($description) = @_;
  my $name = clean_name($description);
  # already a similarity name: "...-like", "...-like protein", "...-like protein 2"
  return $name if $name =~ /-like(?:\s+protein)?(?:\s+\d+[A-Za-z]?)?$/i;
  # qualifiers read better after "-like" is removed than with it glued on
  $name =~ s/,\s*(?:mitochondrial|chloroplastic|cytoplasmic|nuclear|peroxisomal)\s*$//i;
  # a trailing bracketed part stays last: "BCL2-like 12-like (proline rich)"
  if ($name =~ /^(.*\S)\s+(\([^()]*\))$/) {
    return "$1-like $2";
  }
  return "$name-like";
}

sub add_like_to_symbol {
  my ($symbol) = @_;
  return $symbol if $symbol =~ /-like$/i;
  return "$symbol-like";
}

# ---------------------------------------------------------------------------
# HGNC

# load_hgnc(hgnc_complete_set.txt, [withdrawn.txt]) -> {
#   by_id => { 'HGNC:20773' => { hgnc_id, symbol, name, gene_group, gene_group_id, ensembl_gene_id, uniprot_ids => [...] } },
#   by_ensembl_gene => { ENSG... => record }, by_uniprot => { P12345 => record },
#   by_symbol => { SYMBOL => record }, by_previous_symbol => { OLD => [records] },
#   replaced_by => { 'HGNC:old' => 'HGNC:new' }   (merged/withdrawn ids)
# }
sub load_hgnc {
  my ($complete_file, $withdrawn_file) = @_;
  my %table = (by_id => {}, by_ensembl_gene => {}, by_uniprot => {}, by_symbol => {},
               by_previous_symbol => {}, replaced_by => {});

  open my $fh, '<', $complete_file or die "cant open $complete_file $!\n";
  my $header = <$fh>;
  chomp $header;
  my @columns = split /\t/, $header;
  my %index;
  foreach my $column_number (0 .. $#columns) {
    $index{$columns[$column_number]} = $column_number;
  }
  foreach my $needed (qw(hgnc_id symbol name gene_group gene_group_id ensembl_gene_id uniprot_ids prev_symbol)) {
    die "column $needed missing from $complete_file\n" unless defined $index{$needed};
  }
  while (my $line = <$fh>) {
    chomp $line;
    my @fields = split /\t/, $line, -1;
    my $record = {
      hgnc_id         => $fields[$index{hgnc_id}],
      symbol          => $fields[$index{symbol}],
      name            => $fields[$index{name}],
      gene_group      => $fields[$index{gene_group}] // '',
      gene_group_id   => $fields[$index{gene_group_id}] // '',   # parallel to gene_group, "|"-separated
      ensembl_gene_id => $fields[$index{ensembl_gene_id}] // '',
      uniprot_ids     => [ split /\|/, ($fields[$index{uniprot_ids}] // '') ],
    };
    $record->{gene_group} =~ s/^"|"$//g;
    $record->{gene_group_id} =~ s/^"|"$//g;
    $table{by_id}{$record->{hgnc_id}} = $record;
    $table{by_symbol}{$record->{symbol}} = $record;
    $table{by_ensembl_gene}{$record->{ensembl_gene_id}} = $record if $record->{ensembl_gene_id} ne '';
    foreach my $uniprot_id (@{$record->{uniprot_ids}}) {
      $table{by_uniprot}{$uniprot_id} = $record;
    }
    my $previous = $fields[$index{prev_symbol}] // '';
    $previous =~ s/"//g;
    foreach my $old_symbol (split /\|/, $previous) {
      push @{$table{by_previous_symbol}{$old_symbol}}, $record;
    }
  }
  close $fh;

  if (defined $withdrawn_file and -e $withdrawn_file) {
    open my $withdrawn_fh, '<', $withdrawn_file or die "cant open $withdrawn_file $!\n";
    <$withdrawn_fh>;
    while (my $line = <$withdrawn_fh>) {
      chomp $line;
      my ($old_id, $status, $old_symbol, $merged_into) = split /\t/, $line;
      next unless defined $merged_into and $merged_into =~ /(HGNC:\d+)\|[^|]*\|Approved/;
      $table{replaced_by}{$old_id} = $1;
    }
    close $withdrawn_fh;
  }
  return \%table;
}

# hgnc_record($table, hgnc_id => ..., ensembl_gene => ..., uniprot => [...])
# First match wins in that order; a withdrawn HGNC id is followed to its replacement.
sub hgnc_record {
  my ($table, %keys) = @_;
  if (defined $keys{hgnc_id} and length $keys{hgnc_id}) {
    my $id = $keys{hgnc_id};
    $id = $table->{replaced_by}{$id} if !exists $table->{by_id}{$id} and exists $table->{replaced_by}{$id};
    return $table->{by_id}{$id} if exists $table->{by_id}{$id};
  }
  if (defined $keys{ensembl_gene} and length $keys{ensembl_gene}) {
    (my $gene = $keys{ensembl_gene}) =~ s/\.\d+$//;
    return $table->{by_ensembl_gene}{$gene} if exists $table->{by_ensembl_gene}{$gene};
  }
  foreach my $uniprot_id (@{$keys{uniprot} // []}) {
    return $table->{by_uniprot}{$uniprot_id} if exists $table->{by_uniprot}{$uniprot_id};
  }
  return undef;
}

1;

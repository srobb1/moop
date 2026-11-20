package GenomeName;
use strict;
use warnings;
use Exporter 'import';
use File::Spec;
use File::Basename qw(basename dirname);
our @EXPORT_OK = qw(genome_name_from_metadata);

# The assembly's NAME for "## Genome Name:" -> genome.genome_name (2026-11-20).
#
# Before this, the parsers wrote simrbase-prefix there: the gene-ID prefix, not a name.
# Assemblies of one organism that share a prefix became indistinguishable on the web
# (Smed dd_v6 / A2h1 / A2h2 all "SMED", both lampreys "PMZ").
# See notes/ASSEMBLY_NAME_IS_ID_PREFIX_FROM_WEB_2026-11-20.md.
#
# Order:
#   1. genome-name: from metadata.yaml (whole value, so a name may contain spaces), unless empty or NA.
#   2. the assembly directory name (GENOMES/<org>/<assembly>/<geneset>/metadata.yaml),
#      but only when it differs from genome-accession.
#   3. empty. The web shows the accession alone when the name is empty; writing the
#      accession here would print it twice ("GCA_x (GCA_x)").
# simrbase-prefix is never used: it stays the gene-ID prefix.

sub genome_name_from_metadata {
    my ($metadata_path) = @_;
    my ($name, $accession) = ('', '');
    open my $META, '<', $metadata_path or die "Can't open $metadata_path: $!\n";
    while (my $line = <$META>) {
        $line =~ s/\r?\n\z//;
        if    ($line =~ /^genome-name:\s*(.*?)\s*$/)      { $name      = $1 }
        elsif ($line =~ /^genome-accession:\s*(.*?)\s*$/) { $accession = $1 }
    }
    close $META;
    $name      =~ s/^(["'])(.*)\1$/$2/;
    $accession =~ s/^(["'])(.*)\1$/$2/;
    return $name if $name ne '' and $name ne 'NA';

    my $full = File::Spec->rel2abs($metadata_path);    # no symlink resolving: the path the build used
    my $assembly_dir = basename(dirname(dirname($full)));
    return $assembly_dir if $assembly_dir ne '' and $assembly_dir ne $accession;
    return '';
}

1;

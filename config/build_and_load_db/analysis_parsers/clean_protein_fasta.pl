#!/usr/bin/perl
use strict;
use warnings;

## Filter a protein FASTA for internal stop codons / gap characters (* or .).
##   - Strip a single terminal * or . (last char of sequence, right before next > or EOF)
##   - Remove the entire record if any * or . remain after that strip
##   - * or . in a header line are ignored

my ($header, @seq_lines);

sub emit {
    return unless defined $header;
    my $seq = join('', @seq_lines);
    $seq =~ s/[*.]\z//;        # strip single terminal stop/gap
    print "$header\n$seq\n" unless $seq =~ /[*.]/;
}

while (<>) {
    chomp;
    if (/^>/) {
        emit();
        $header = $_;
        @seq_lines = ();
    } else {
        push @seq_lines, $_;
    }
}
emit();

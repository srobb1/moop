#!/usr/bin/env python3
# ejr: 2023-07-17 (RosettaStone v2); --name added for the MOOP port
# This script counts the number of overlapping nucleotides for each gene in a BED file
# input is from bedtools intersect -wo, which has both beds

import os
import re
import argparse

def main():

    # get command line arguments
    args = get_args()

    # set name for the "transcriptome" column: --name, else the file name minus .overlap.bed
    transcriptome = args.name if args.name else re.sub(r"\.overlap\.bed$", "", os.path.basename(args.bed_file))
    max_overlap  = get_max_overlap(args.bed_file)
    exon_overlap = get_exon_overlap(args.bed_file)
    # print header
    print("gene_id\ttarget_id\ttranscriptome\tmax_overlap\texon_overlap")

    # print results
    for gene in max_overlap:
        for transcript in max_overlap[gene]:
            if gene in exon_overlap:
                if transcript in exon_overlap[gene]:
                    print(gene + "\t" + transcript + "\t" + transcriptome + "\t" +str(max_overlap[gene][transcript]) + "\t" + str(exon_overlap[gene][transcript]))
                else:
                    print(gene + "\t" + transcript + "\t" + transcriptome + "\t" + str(max_overlap[gene][transcript]) + "\t0")
            else:
                print(gene + "\t" + transcript + "\t" + transcriptome + "\t" + str(max_overlap[gene][transcript]) + "\t0")


    return(0)

# get length of longest overlap for each transcript to gene
def get_max_overlap(bed_file):
    # create dictionary to store max overlap
    max_overlap = {}

    # example bed file line
    with open(bed_file, "r") as bed:
        for line in bed:
            fields = line.strip().split("\t")

            # get schmedS3 gene id
            if (fields[11] == "cDNA_match_part"):
                gene = fields[8]
                gene = re.sub(".*gene_id \"(.+?)\".*", "\\1", gene)
                # get target id
                target = fields[17]
                target = re.sub(".*Target=(.+?) .*", "\\1", target)
                # get overlap length
                overlap_length = int(fields[18])  

                # if gene is already in dictionary, check to see if overlap is longer than max
                # if it is longer, replace max
                if gene in max_overlap:
                    if target in max_overlap[gene]:
                        if (overlap_length > max_overlap[gene][target]):
                            max_overlap[gene][target] = overlap_length
                    else:
                        max_overlap[gene][target] = overlap_length

                else:
                    max_overlap[gene] = {}
                    max_overlap[gene][target] = overlap_length

    return(max_overlap)

# number of exons with identical boundaries for each gene-transcript pair
def get_exon_overlap(bed_file):
    exon_overlap = {}

    # example bed file line
    with open(bed_file, "r") as bed:
        for line in bed:
            fields = line.strip().split("\t")

            # get schmedS3 gene id
            if (fields[11] == "cDNA_match_part"):
                gene = fields[8]
                gene = re.sub(".*gene_id \"(.+?)\".*", "\\1", gene)
                # get target id
                target = fields[17]
                target = re.sub(".*Target=(.+?) .*", "\\1", target)
                # get A start and stop
                a_start = int(fields[3])
                a_stop = int(fields[4])
                # get B start and stop
                b_start = int(fields[12])
                b_stop = int(fields[13])

                if(a_start == b_start and a_stop == b_stop):
                    # if gene is already in dictionary, check to see if overlap is longer than max
                    # if it is longer, replace max
                    if gene in exon_overlap:
                        if target in exon_overlap[gene]:
                            exon_overlap[gene][target] = exon_overlap[gene][target] + 1
                        else:
                            exon_overlap[gene][target] = 1
                    else:
                        exon_overlap[gene] = {}
                        exon_overlap[gene][target] = 1

    return(exon_overlap)


def get_args():
    parser = argparse.ArgumentParser(description="Count the number of overlapping nucleotides for each gene in a BED file")
    parser.add_argument("bed_file", type=str, help="Path to BED file")
    parser.add_argument("--name", type=str, default=None,
                        help="value for the transcriptome column (default: file name minus .overlap.bed)")
    return(parser.parse_args())

# Run the script
if __name__ == "__main__":
    main()


    #chr1_h1 ONThybrid       exon    285091  288185  .       +       .       transcript_id "h1SMcT0000006.1"; gene_id "h1SMcG0000006"; gene_name "h1SMcG0000006";    chr1_h1 est2genome      cDNA_match_part 285374  287020  1305    +       .       ID=match5455.p1;Parent=match5455;Target=SMED30005795 1 1653     1647

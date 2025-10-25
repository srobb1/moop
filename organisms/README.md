# Single organism data organization

## this `organisms` directrory has one sub-directory for each organism
- Each sub-directory is named for each organism with it's genus and species and optional subclassification. 
- the genus and species and sub-classification are separated with underscores
- Examples: Anoura_caudifer, Pantera_pardus_fusca

## Each organism specific sub-directory 
  - contains a database file that houses gene, transcript, and annotation information
  - contains subdirectories for different genome assemblies
    - each genome assembly directory contains FASTA files  

```text
Anoura_caudifer [genus_species]
|--> genes.sqlite
|--> GCA_004027475.1_AnoCau_v1_BIUU_genomic [genome_uniquename]
     |--> transcript.nt.fa [transcript/mRNA fasta]
     |--> protein.aa.fa [peptides/protein fasta]
     |--> cds.nt.fa [coding nucleotide fasta]
     |--> genome.nt.fa [genome fasta]
```

public.txt contains all the organisms and assemblies that are available to the public
`for i in `ls  -1` ; do for j in `ls -d $i/*` ; do BASE=`basename $j` ; echo  "$i"$'\t'"$BASE" >> public.txt ; done ; done`
```
Anoura_caudifer	GCA_004027475.1_AnoCau_v1_BIUU_genomic
Anoura_caudifer	assembly_v1
Lasiurus_cinereus	GCA_011751065.1
Lasiurus_cinereus	assembly_v1
```




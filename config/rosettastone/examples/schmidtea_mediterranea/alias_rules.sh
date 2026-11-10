# Alias rules for Schmidtea mediterranea (RosettaStone v2, ejr 2024). Sourced by step 05
# (ALIAS_RULES); see scripts/05_make_aliases.sh for the helpers: rule, heads, $IN, $AL.
# Each rule parses one set's own FASTA header style. SMU_TO_DD4 and NCBIPROT_TO_NCBINUCL are
# keys in this organism's config. Sources named "ncbi" are renamed by ALIAS_SOURCE_RENAME.

Q=$QUERY_DIR
P=${PROBE_DIR:-/nonexistent}
NCBI=$Q/smed_ncbi_20240424.nt

# NCBI accessions without the version: AB123456 = AB123456.1
if rule ncbi_accession_no_version "$NCBI"; then
    heads | perl -p -e 's/>(.+?)\.(\d+) (.+)/$1\t$1.$2\tncbi\tno_version/' | sort -u >> "$AL/rules/ncbi_accession_no_version.txt"
fi

# EST clone names
if rule ncbi_ests_to_names "$NCBI"; then
    { heads | grep "Schmidtea mediterranea clone" \
        | perl -p -e 's/>(.+?) .*Schmidtea mediterranea clone (.+?) .*/$2\t$1\tncbi\test_names/' | sort -u
      heads | grep -v "TPA_exp:" | grep -v "TPA_inf:" | grep -v "TSA:" \
        | perl -p -e 's/>(.+?) (.+?) .*/$2\t$1\tncbi\test_names/' \
        | grep -v UNVERIFIED | grep -v Schmidtea | grep -v Sequence | sort -u
    } >> "$AL/rules/ncbi_ests_to_names.txt"
fi

# EST names without "(T3)"
if rule ncbi_ests_no_t3 "$NCBI"; then
    heads | grep "(T3)" | perl -p -e 's/>(.+?) .*?(\S+)\(T3\) .*/$2\t$1\tncbi\tno_t3/' | sort -u >> "$AL/rules/ncbi_ests_no_t3.txt"
fi

# PlanMine transcripts without the transcriptome prefix: be_Smed_v2_isotig1 -> isotig1
if rule planmine_drop_transcriptome "$Q/be_Smed_v2.nt" "$Q/bo_Smed_v1.nt" "$Q/ka_Smed_v1.nt" "$Q/to_Smed_v2.nt" "$Q/uc_Smed_v2.nt"; then
    heads | perl -p -e 's/>(.+?_Smed_.+?)_(.+) .+/$2\t$1_$2\t$1\tdrop_transcriptome_name/' | sort -u >> "$AL/rules/planmine_drop_transcriptome.txt"
fi

# dd_Smed_v6 truncated (as used in PlanMine/PLAAS): dd_Smed_v6_1_0_1 -> dd_Smed_v6_1_0
if rule ddv6_truncate "$Q/dd_Smed_v6.nt"; then
    heads | perl -p -e 's/>(dd_Smed_v6_\d+_\d+)(_\d+) .*/$1\t$1$2\tdd_Smed_v6\ttruncate/' | sort -u >> "$AL/rules/ddv6_truncate.txt"
fi

# dd_Smed_v4 short names: dd_Smed_v4_2920_0_1 -> dd_2920
if rule ddv4_truncate "$Q/dd_Smed_v4.nt"; then
    heads | perl -p -e 's/>dd_Smed_v4_(\d+)_(\d+)_(\d+) .*/dd_$1\tdd_Smed_v4_$1_$2_$3\tdd_Smed_v4\ttruncate/' | sort -u >> "$AL/rules/ddv4_truncate.txt"
fi

# dd_Smed_v4 names cited with a _0_1 suffix the file does not have
if rule ddv4_rename "$Q/dd_Smed_v4.nt"; then
    heads | perl -p -e 's/>(dd_Smed_v4_)(\d+)(_\d+_\d+) .*/dd_$2_0_1\t$1$2$3\tdd_Smed_v4\tddv4_rename/' | sort -u >> "$AL/rules/ddv4_rename.txt"
fi

# Muenster contigs: mu_Smed_v1_11049_1_1 -> tr5_11049
if rule muenster_rename "$Q/mu_Smed_v1.nt"; then
    heads | perl -p -e 's/>(mu_Smed_v1_)(\d+)(_.*) .*/tr5_$2\t$1$2$3\tmu_Smed_v1\trename/' | sort -u >> "$AL/rules/muenster_rename.txt"
fi

# Oxford transcripts: ox_Smed_v2_123 -> OX_Smed_1.0.123
if rule ox_rename "$Q/ox_Smed_v2.nt"; then
    heads | perl -p -e 's/>ox_Smed_v2_(\d+) .*/OX_Smed_1.0.$1\tox_Smed_v2_$1\tox_Smed_v2\trename/' | sort -u >> "$AL/rules/ox_rename.txt"
fi

# GPL14150 gene models: SMED_00001_V2_1r -> SMED_00001_V2 (v2 built this but never used it)
if rule GPL14150_rename "$Q/GPL14150_gene_models.nt"; then
    heads | perl -p -e 's/>(SMED_.+?_V2)(_\d+.*) .*/$1\t$1$2\tGPL14150_gene_models\trename/' | grep -v ">" | sort -u >> "$AL/rules/GPL14150_rename.txt"
fi

# Microarray probes: Contig1000_SE3__192 -> Contig1000_SE3
if rule GPL10652_truncate "$P/GPL10652.nt"; then
    heads | perl -p -e 's/>(.+?)(__\d+)/$1\t$1$2\tGPL10652\ttruncate/' | sort -u >> "$AL/rules/GPL10652_truncate.txt"
fi
# SMED_31964_V2_1 -> SMED_31964_V2
if rule GPL14150_truncate "$P/GPL14150.nt"; then
    heads | perl -p -e 's/>(SMED_.+?_V2)(_\d+)/$1\t$1$2\tGPL14150\ttruncate/' | grep -v ">" | sort -u >> "$AL/rules/GPL14150_truncate.txt"
fi
# Contig1645_SE3, Contig1645_338_372 -> Contig1645
if rule GPL15192_truncate "$P/GPL15192.nt"; then
    heads | perl -p -e 's/>(Contig\d+)(.*)/$1\t$1$2\tGPL15192\ttruncate/' | grep -v ">" | sort -u >> "$AL/rules/GPL15192_truncate.txt"
fi

# SMU IDs for dd_Smed_v4 transcripts (dd_Smed_v4_10001_0_1<TAB>SMU15031515)
if rule SMU "${SMU_TO_DD4:-}"; then
    perl -p -e 's/(.+)\t(.+)/$2\t$1\tdd_Smed_v4\tSMU/' "${IN[@]}" | sort -u >> "$AL/rules/SMU.txt"
fi

# NCBI protein accessions -> nucleotide accession, with and without ".1"
smed_ncbi_prot() {   # smed_ncbi_prot SUFFIX TYPE
    grep -v nuc_id "${IN[@]}" | perl -p -e "s/(.+)\t(.+)\t(.+)/\$1$1\t\$3\tncbi\t$2/" \
        | perl -p -e 's/embl //' | perl -p -e 's/UniProtKB:locus (.+?),.+\t/$1\t/' \
        | perl -p -e 's/REFSEQ: //' | sort -u
}
if rule ncbi_prot "${NCBIPROT_TO_NCBINUCL:-}"; then smed_ncbi_prot "" ncbi_prot >> "$AL/rules/ncbi_prot.txt"; fi
if rule ncbi_prot_dot1 "${NCBIPROT_TO_NCBINUCL:-}"; then smed_ncbi_prot ".1" ncbi_prot_add_dot1 >> "$AL/rules/ncbi_prot_dot1.txt"; fi

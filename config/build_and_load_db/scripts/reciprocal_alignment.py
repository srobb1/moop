#!/usr/bin/env python3
# last modified: 2026-09-25
# 2026-09-25: six columns appended to the output (bitscore, coverage, lengths, next genes)
#             for gene naming; the original six columns are unchanged.
#
# PROPOSED VERSION for the annotation pipeline -- not run by this repo. The pipeline runs
#   /n/projects/sm2699/SBG_v4/accessory_scripts/RBH/reciprocal_alignment.py
# (via sbatch_scripts/rbh_eross.sbatch, in src/rbh_eross/rbbh_venv). This file is that
# script with the change applied; tested 2026-09-25 in that venv with diamond/2.1.13: the
# first six output columns are byte-identical to the current script's.

"""
Reciprocal DIAMOND alignment script.

This script performs reciprocal DIAMOND alignments between two FASTA files
and calculates reciprocal scores based on ranking in the reverse alignment.
Optionally uses protein2gene mapping files to rank by gene instead of transcripts.
"""

import argparse
import subprocess
import tempfile
import os
import sys
from pathlib import Path
import pandas as pd
from collections import defaultdict

SCRIPT_VERSION = "2026-09-25"
# DIAMOND columns, in --outfmt order
DIAMOND_COLUMNS = ['qseqid', 'sseqid', 'evalue', 'bitscore', 'qlen', 'slen', 'qcovhsp', 'scovhsp']
# how many other target genes to report after the best one
N_NEXT_GENES = 5


def run_diamond_blastp(query_file, database_file, output_file, num_threads=4):
    """Run DIAMOND blastp alignment."""
    try:
        # Create DIAMOND database
        db_file = f"{database_file}.dmnd"
        print(f"Creating DIAMOND database from {database_file}...")
        subprocess.run([
            "diamond", "makedb", 
            "--in", database_file, 
            "--db", db_file
        ], check=True, capture_output=True)
        
        # Run DIAMOND blastp
        print(f"Running DIAMOND alignment: {query_file} vs {database_file}...")
        subprocess.run([
            "diamond", "blastp",
            "--query", query_file,
            "--db", db_file,
            "--out", output_file,
            "--outfmt", "6", *DIAMOND_COLUMNS,
            "--threads", str(num_threads),
            "--max-target-seqs", "100",  # Keep more hits for better reciprocal analysis
            "--evalue", ".001"
        ], check=True, capture_output=False)
        
        # Clean up database files
        for ext in [".dmnd"]:
            db_path = f"{database_file}{ext}"
            if os.path.exists(db_path):
                os.remove(db_path)
                
    except subprocess.CalledProcessError as e:
        print(f"Error running DIAMOND: {e}")
        sys.exit(1)


def load_protein2gene_mapping(mapping_file):
    """Load protein to gene mapping from file."""
    mapping = {}
    try:
        with open(mapping_file, 'r') as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith('#'):
                    parts = line.split('\t')
                    if len(parts) >= 2:
                        protein_id = parts[0]
                        gene_id = parts[1]
                        mapping[protein_id] = gene_id
    except FileNotFoundError:
        print(f"Warning: Mapping file {mapping_file} not found")
    return mapping


def get_best_hits(alignment_file, protein2gene_map=None):
    """Parse alignment results and get best hits for each query."""
    best_hits = {}
    
    try:
        # Read alignment results
        df = pd.read_csv(alignment_file, sep='\t', header=None, names=DIAMOND_COLUMNS)
        
        if df.empty:
            return best_hits
            
        # Sort by query and bitscore (descending)
        df = df.sort_values(['qseqid', 'bitscore'], ascending=[True, False])
        
        # Group by query and get best hit
        for query, group in df.groupby('qseqid'):
            best_hit = group.iloc[0]
            
            # Store both original target and mapped gene ID
            original_target = best_hit['sseqid']
            mapped_target = original_target
            if protein2gene_map and original_target in protein2gene_map:
                mapped_target = protein2gene_map[original_target]
            
            # The next best DIFFERENT target genes, with each gene's best bitscore, so a
            # caller can tell a clear best hit from one with a near-equal paralog. Isoforms
            # of the best hit's own gene are skipped (by gene id when mapping is provided).
            next_genes = []
            seen = {mapped_target}
            for _, row in group.iloc[1:].iterrows():
                gene = protein2gene_map.get(row['sseqid'], row['sseqid']) if protein2gene_map else row['sseqid']
                if gene in seen:
                    continue
                seen.add(gene)
                next_genes.append(f"{gene}:{row['bitscore']}")
                if len(next_genes) == N_NEXT_GENES:
                    break

            best_hits[query] = {
                'target': mapped_target,  # This will be gene ID if mapping provided
                'original_target': original_target,  # Always the protein ID
                'evalue': best_hit['evalue'],
                'bitscore': best_hit['bitscore'],
                'qlen': best_hit['qlen'],
                'slen': best_hit['slen'],
                'qcovhsp': best_hit['qcovhsp'],
                'scovhsp': best_hit['scovhsp'],
                'next_genes': ';'.join(next_genes)
            }
    
    except Exception as e:
        print(f"Error parsing alignment file {alignment_file}: {e}")
    
    return best_hits


def calculate_reciprocal_scores(forward_hits, reverse_alignment_file, 
                               query_to_gene_map=None, target_to_gene_map=None):
    """Calculate reciprocal scores based on reverse alignment rankings."""
    reciprocal_scores = {}
    
    try:
        # Read reverse alignment results
        print("  Loading reverse alignment file...")
        df = pd.read_csv(reverse_alignment_file, sep='\t', header=None, names=DIAMOND_COLUMNS)
        
        if df.empty:
            # If no reverse hits, assign high scores to all
            for query in forward_hits:
                reciprocal_scores[query] = 999
            return reciprocal_scores
        
        print(f"  Processing {len(df)} reverse alignment hits...")
        
        # Sort by query and bitscore (descending)
        df = df.sort_values(['qseqid', 'bitscore'], ascending=[True, False])
        
        # Pre-group by query for efficient lookup - this is the key optimization
        print("  Grouping reverse hits by query...")
        reverse_grouped = df.groupby('qseqid')
        
        # Pre-compute gene mappings if provided to avoid repeated lookups
        if target_to_gene_map:
            df['hit_gene'] = df['sseqid'].map(target_to_gene_map).fillna(df['sseqid'])
        else:
            df['hit_gene'] = df['sseqid']
        
        # For each forward hit, find its rank in the reverse alignment
        total_queries = len(forward_hits)
        processed = 0
        
        for original_query, hit_info in forward_hits.items():
            processed += 1
            if processed % 1000 == 0:
                print(f"  Processed {processed}/{total_queries} queries...")
            
            target = hit_info['original_target']  # Use original target for reverse lookup
            reciprocal_scores[original_query] = 999  # Default high score
            
            # Get the gene ID for the original query if mapping is provided
            query_gene = original_query
            if query_to_gene_map and original_query in query_to_gene_map:
                query_gene = query_to_gene_map[original_query]
            
            # Find reverse alignment results for this target using pre-grouped data
            if target in reverse_grouped.groups:
                target_alignments = reverse_grouped.get_group(target)
                
                rank = 1
                seen_genes = set()
                
                # Iterate through the already-sorted alignments
                for _, row in target_alignments.iterrows():
                    hit_gene = row['hit_gene']
                    
                    # Skip if we've already seen this gene (for isoform handling)
                    if target_to_gene_map and hit_gene in seen_genes:
                        continue
                    
                    if target_to_gene_map:
                        seen_genes.add(hit_gene)
                    
                    # Check if this hit matches our original query
                    if (target_to_gene_map and hit_gene == query_gene) or \
                       (not target_to_gene_map and row['sseqid'] == original_query):
                        reciprocal_scores[original_query] = rank
                        break
                    
                    rank += 1
    
    except Exception as e:
        print(f"Error calculating reciprocal scores: {e}")
        # Set default scores for all queries
        for query in forward_hits:
            reciprocal_scores[query] = 999
    
    return reciprocal_scores


def main():
    parser = argparse.ArgumentParser(description='Perform reciprocal DIAMOND alignment')
    parser.add_argument('fasta1', help='First FASTA file (query)')
    parser.add_argument('fasta2', help='Second FASTA file (database)')
    parser.add_argument('--protein2gene1', help='Protein to gene mapping for fasta1')
    parser.add_argument('--protein2gene2', help='Protein to gene mapping for fasta2')
    parser.add_argument('--output', '-o', default='reciprocal_alignment.tsv',
                       help='Output file (default: reciprocal_alignment.tsv)')
    parser.add_argument('--threads', '-t', type=int, default=4,
                       help='Number of threads for DIAMOND (default: 4)')
    parser.add_argument('--keep-temp', action='store_true',
                       help='Keep temporary alignment files')
    
    args = parser.parse_args()
    print(f"reciprocal_alignment.py version {SCRIPT_VERSION}")
    
    # Check if input files exist
    for file_path in [args.fasta1, args.fasta2]:
        if not os.path.exists(file_path):
            print(f"Error: File {file_path} does not exist")
            sys.exit(1)
    
    # Load protein2gene mappings if provided
    protein2gene1 = {}
    protein2gene2 = {}
    
    if args.protein2gene1:
        protein2gene1 = load_protein2gene_mapping(args.protein2gene1)
        print(f"Loaded {len(protein2gene1)} protein-to-gene mappings from {args.protein2gene1}")
    
    if args.protein2gene2:
        protein2gene2 = load_protein2gene_mapping(args.protein2gene2)
        print(f"Loaded {len(protein2gene2)} protein-to-gene mappings from {args.protein2gene2}")
    
    # Create temporary files for alignment results
    with tempfile.NamedTemporaryFile(mode='w', suffix='.tsv', delete=False) as forward_temp:
        forward_alignment_file = forward_temp.name
    
    with tempfile.NamedTemporaryFile(mode='w', suffix='.tsv', delete=False) as reverse_temp:
        reverse_alignment_file = reverse_temp.name
    
    try:
        # Run forward alignment (fasta1 vs fasta2)
        print("Running forward alignment...")
        run_diamond_blastp(args.fasta1, args.fasta2, forward_alignment_file, args.threads)
        
        # Run reverse alignment (fasta2 vs fasta1)
        print("Running reverse alignment...")
        run_diamond_blastp(args.fasta2, args.fasta1, reverse_alignment_file, args.threads)
        
        # Get best hits from forward alignment
        print("Processing forward alignment results...")
        forward_hits = get_best_hits(forward_alignment_file, protein2gene2)
        
        # Calculate reciprocal scores
        print("Calculating reciprocal scores...")
        reciprocal_scores = calculate_reciprocal_scores(
            forward_hits, reverse_alignment_file, protein2gene1, protein2gene1)
        
        # Write output
        print(f"Writing results to {args.output}...")
        with open(args.output, 'w') as out:
            # Create header based on whether gene mappings are provided
            header = ["query", "best_hit", "evalue", "reciprocal_score"]
            if protein2gene1:
                header.append("query_gene")
            if protein2gene2:
                header.append("hit_gene")
            # appended columns: readers of the original six are unaffected
            header += ["bitscore", "query_cov", "hit_cov", "query_len", "hit_len", "next_hit_genes"]
            out.write("\t".join(header) + "\n")
            
            for query, hit_info in forward_hits.items():
                reciprocal_score = reciprocal_scores.get(query, 999)
                
                # Build output row - always use original_target (protein ID) for best_hit column
                row = [query, hit_info['original_target'], str(hit_info['evalue']), str(reciprocal_score)]
                
                # Add gene IDs if mappings are provided
                if protein2gene1:
                    query_gene = protein2gene1.get(query, query)
                    row.append(query_gene)
                
                if protein2gene2:
                    # Use the mapped gene ID if available, otherwise the original target
                    hit_gene = protein2gene2.get(hit_info['original_target'], hit_info['original_target'])
                    row.append(hit_gene)

                row += [str(hit_info['bitscore']), str(hit_info['qcovhsp']), str(hit_info['scovhsp']),
                        str(hit_info['qlen']), str(hit_info['slen']), hit_info['next_genes']]
                out.write("\t".join(row) + "\n")
        
        print(f"Analysis complete. Results written to {args.output}")
        print(f"Total queries processed: {len(forward_hits)}")
        
        # Count mutual best hits (reciprocal score = 1)
        mutual_best_hits = sum(1 for score in reciprocal_scores.values() if score == 1)
        print(f"Mutual best hits: {mutual_best_hits}")
        
    finally:
        # Clean up temporary files unless requested to keep them
        if not args.keep_temp:
            for temp_file in [forward_alignment_file, reverse_alignment_file]:
                if os.path.exists(temp_file):
                    os.remove(temp_file)
        else:
            print(f"Temporary files kept:")
            print(f"  Forward alignment: {forward_alignment_file}")
            print(f"  Reverse alignment: {reverse_alignment_file}")


if __name__ == "__main__":
    main()

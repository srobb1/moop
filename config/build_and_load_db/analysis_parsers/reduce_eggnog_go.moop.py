#!/usr/bin/env python3

import sys
from goatools.obo_parser import GODag

import os
import subprocess


print("DO THIS: conda activate  goatools")
def usage():
    print(f"Usage: {sys.argv[0]} <input_file>")
    print("Please provide a tab-delimited file with at least two columns:")
    print("Example format:")
    print("GeneID\tGO_term_ID \tDescription\tNamespace")
    print("GeneID and GO_term_ID are required")
    sys.exit(1)

# Step: remove terms that are parents of others
#def get_most_specific_terms(input_terms, go_dag, term_to_children):
#    return {
#        term for term in input_terms
#        if term in go_dag
#        and not go_dag[term].is_obsolete
#        and not any(
#            child in input_terms
#            for child in term_to_children.get(term, set())
#        )
#    }

# Checks if any of the child terms of the current term are also present in the input.
def get_most_specific_terms(input_terms, go_dag, term_to_children):
  good_terms = set();
  # skip it if term is obsolete
  for term in input_terms:
    if term not in go_dag:
      continue
    if go_dag[term].is_obsolete:
      continue
    children_of_this_term = term_to_children.get(term, set())
    # if any child of this term is also in the input list of term, it is a parent, 
    # don't add it to the set
    if any(child in input_terms for child in children_of_this_term):
      continue  # it's a parent so skip it
    good_terms.add(term)
  return good_terms
    

def main():
  #found_terms = sys.argv[1]

  if len(sys.argv) < 2:
    usage()

  found_terms_file = sys.argv[1]

  if not os.path.isfile(found_terms_file):
    print(f"Error: File '{found_terms_file}' does not exist or is not a file.")
    usage()

    print(f"Processing file: {found_terms_file}")

  # Load the GO hierarchy (slow step, once)
  if not os.path.exists("go-basic.obo"):
    # Download go.obo
    # http://geneontology.org/ontology/go-basic.obo
    subprocess.run(["curl", "-OL", "http://geneontology.org/ontology/go-basic.obo"], check=True)
  go_dag = GODag("go-basic.obo",optional_attrs=['def'])


  # Precompute all children for each term (speed boost)
  term_to_children = {
        term: go_dag[term].get_all_children()
        for term in go_dag
        if not go_dag[term].is_obsolete
  }


  gene2go = {}
  file_comments = ''
  with open(found_terms_file, 'r') as f:
        for line in f:
            if line.startswith("#"):
                file_comments += line
                continue
            cols = line.strip().split("\t")
            if len(cols) < 2:
                continue
            transcript_id = cols[0]
            go_term = cols[1]

            #gene2go.setdefault(transcript_id, set()).add(go_term)
            if transcript_id not in gene2go:
              gene2go[transcript_id] = set()
            gene2go[transcript_id].add(go_term)

  with open("EggNOG2GO.eggnog.reduced.moop.tsv", "w") as reduced_go_out:
    print(file_comments.strip(),file=reduced_go_out)
    for gene_id, go_terms in gene2go.items():
        specific_terms = get_most_specific_terms(go_terms, go_dag, term_to_children)
        for keep in specific_terms:
          go_term = go_dag.get(keep)
          #name = go_term.name 
          #desc = go_term.defn
          namespace = go_term.namespace
          term_info = go_term.name + ": " + go_term.defn
          print(gene_id, keep, term_info, namespace, sep="\t", file=reduced_go_out)

if __name__ == "__main__":
    main()


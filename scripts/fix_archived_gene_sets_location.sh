#!/bin/bash
# One-off cleanup: moves the CCA3-geneset archive (created by an early version of
# archiveGeneSet() that put archives inside organisms/, where every top-level dir
# is treated as an organism) to the corrected location outside organisms/.
set -e

sudo mkdir -p /var/www/html/moop/archived_gene_sets/Chamaeleo_calyptratus/CCA3
sudo mv /var/www/html/moop/organisms/_archived_gene_sets/Chamaeleo_calyptratus/CCA3/CCA3-geneset_20260703_172426 /var/www/html/moop/archived_gene_sets/Chamaeleo_calyptratus/CCA3/
sudo rm -rf /var/www/html/moop/organisms/_archived_gene_sets
sudo chown -R apache:apache /var/www/html/moop/archived_gene_sets

echo "Done."

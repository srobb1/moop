<?php
/**
 * JBrowse gene set prep — shared by jbrowse_register_assembly and jbrowse_register_gene_set.
 */

/**
 * Prepare one gene set's GFF for JBrowse.
 *
 * What it does:
 *   1. Sort + bgzip + tabix into data/genomes/{org}/{asm}/{gene_set}/
 *   2. Create/update gene track JSON in jbrowse2-configs/tracks/
 *   3. Add track ID to assembly JSON primaryGeneTracks
 *   4. Generate feature_coords.tsv (for BLAST linkouts)
 *
 * @param bool $force  Re-build even if bgzip/tbi/track JSON already exist.
 * @return bool  False only if source GFF is absent or empty (genuinely nothing to do).
 */
function prepareGeneSetForJBrowse(
    string $organism,
    string $assembly,
    string $gene_set,
    ConfigManager $config,
    array &$log,
    bool $force = false
): bool {
    $organisms_dir = $config->getPath('organism_data');
    $site_path     = $config->getPath('site_path');
    $site          = $config->getString('site', 'moop');
    $metadata_path = $config->getPath('metadata_path');

    $source_gff    = "$organisms_dir/$organism/$assembly/$gene_set/genomic.gff";
    $genomes_dir   = "$site_path/data/genomes/$organism/$assembly/$gene_set";
    $target_gff    = "$genomes_dir/annotations.gff3";
    $gz_file       = "$genomes_dir/annotations.gff3.gz";
    $tbi_file      = "$gz_file.tbi";
    $csi_file      = "$gz_file.csi";
    $index_type    = 'TBI';   // may be updated to 'CSI' below
    $index_file    = $tbi_file;
    $assembly_name = "{$organism}_{$assembly}";
    $track_id      = "{$assembly_name}_{$gene_set}_genes";
    $track_name    = "Genes ($gene_set)";
    $tracks_dir    = "$metadata_path/jbrowse2-configs/tracks/$organism/$assembly/gff";
    $track_file    = "$tracks_dir/{$gene_set}_genes.json";
    $assembly_json = "$metadata_path/jbrowse2-configs/assemblies/{$organism}_{$assembly}.json";
    $uri_base      = "/$site/data/genomes/$organism/$assembly/$gene_set";

    if (!file_exists($source_gff)) {
        $log[] = "$gene_set: no genomic.gff found — skipped";
        return false;
    }
    if (filesize($source_gff) === 0) {
        $log[] = "$gene_set: genomic.gff is empty — skipped";
        return false;
    }
    $log[] = "$gene_set: source GFF found (" . number_format(filesize($source_gff)) . " bytes)";

    // ── Create gene_set directory ────────────────────────────────────────────
    if (!is_dir($genomes_dir) && !mkdir($genomes_dir, 0755, true)) {
        $log[] = "$gene_set: ERROR — could not create $genomes_dir";
        return false;
    }

    // ── Symlink annotations.gff3 ─────────────────────────────────────────────
    if (!is_link($target_gff) && !file_exists($target_gff)) {
        symlink($source_gff, $target_gff);
        $log[] = "$gene_set: symlinked annotations.gff3";
    } else {
        $log[] = "$gene_set: annotations.gff3 symlink OK";
    }

    // ── bgzip + tabix ────────────────────────────────────────────────────────
    if ($force || !file_exists($gz_file)) {
        foreach ([$tbi_file, $csi_file, $gz_file] as $f) {
            if (file_exists($f)) unlink($f);
        }
        $rc = 1; $out = [];
        $sort_cmd = '(grep "^#" ' . escapeshellarg($target_gff)
                  . '; grep -v "^#" ' . escapeshellarg($target_gff)
                  . ' | sort -t"$(printf \'\\t\')" -k1,1 -k4,4n) | bgzip > ' . escapeshellarg($gz_file);
        exec('/bin/bash -c ' . escapeshellarg($sort_cmd), $out, $rc);

        if ($rc !== 0 || !file_exists($gz_file)) {
            $jb = findJBrowseCliGS();
            if ($jb) {
                $sort_cmd = escapeshellarg($jb) . ' sort-gff ' . escapeshellarg($target_gff)
                          . ' | bgzip > ' . escapeshellarg($gz_file);
                exec('/bin/bash -c ' . escapeshellarg($sort_cmd), $out, $rc);
                $log[] = $rc === 0 ? "$gene_set: bgzip via jbrowse sort-gff OK" : "$gene_set: jbrowse sort-gff also failed";
            }
        }

        if ($rc === 0 && file_exists($gz_file)) {
            $log[] = "$gene_set: bgzip OK (" . number_format(filesize($gz_file)) . " bytes)";
        } else {
            $log[] = "$gene_set: WARNING — bgzip failed: " . implode(' ', $out);
        }
    } else {
        $log[] = "$gene_set: compressed GFF already exists";
    }

    if (file_exists($gz_file)) {
        $need_index = $force || (!file_exists($tbi_file) && !file_exists($csi_file));
        if ($need_index) {
            if (file_exists($tbi_file)) unlink($tbi_file);
            if (file_exists($csi_file)) unlink($csi_file);
            $out = []; $rc = 1;
            exec('tabix -p gff ' . escapeshellarg($gz_file) . ' 2>&1', $out, $rc);
            if ($rc !== 0) {
                $err = implode(' ', $out);
                // tabix fails with a "coordinate limit" message when chromosomes exceed 512 Mb; CSI handles those
                if (stripos($err, 'coordinate') !== false || stripos($err, 'CSI') !== false) {
                    $out = []; $rc = 1;
                    exec('tabix -C -p gff ' . escapeshellarg($gz_file) . ' 2>&1', $out, $rc);
                    $index_type = 'CSI';
                    $index_file = $csi_file;
                    $log[] = ($rc === 0)
                        ? "$gene_set: tabix CSI OK (" . number_format(filesize($csi_file)) . " bytes) — chromosomes exceed TBI limit"
                        : "$gene_set: WARNING — tabix CSI also failed: " . implode(' ', $out);
                } else {
                    $log[] = "$gene_set: WARNING — tabix failed: $err";
                }
            } else {
                $log[] = "$gene_set: tabix TBI OK (" . number_format(filesize($tbi_file)) . " bytes)";
            }
        } else {
            if (file_exists($csi_file)) {
                $index_type = 'CSI';
                $index_file = $csi_file;
            }
            $log[] = "$gene_set: tabix index already exists ($index_type)";
        }
    }

    // ── Gene track JSON ──────────────────────────────────────────────────────
    if (!is_dir($tracks_dir)) mkdir($tracks_dir, 0755, true);

    if (($force || !file_exists($track_file)) && file_exists($gz_file)) {
        $track = [
            'trackId'       => $track_id,
            'name'          => $track_name,
            'assemblyNames' => [$assembly_name],
            'category'      => ['Gene Models'],
            'type'          => 'FeatureTrack',
            'adapter'       => [
                'type'          => 'Gff3TabixAdapter',
                'gffGzLocation' => [
                    'uri'          => "$uri_base/annotations.gff3.gz",
                    'locationType' => 'UriLocation',
                ],
                'index'         => ($index_type === 'CSI')
                    ? [
                        'indexType' => 'CSI',
                        'location'  => [
                            'uri'          => "$uri_base/annotations.gff3.gz.csi",
                            'locationType' => 'UriLocation',
                        ],
                    ]
                    : [
                        'location' => [
                            'uri'          => "$uri_base/annotations.gff3.gz.tbi",
                            'locationType' => 'UriLocation',
                        ],
                    ],
            ],
            'displays' => [
                [
                    'type'               => 'LinearGeneAnnotationsDisplay',
                    'displayId'          => "$track_id-LinearGeneAnnotationsDisplay",
                    'filterFeatureTypes' => ['gene', 'pseudogene'],
                ],
            ],
            'metadata' => [
                'management_track_id'   => $track_id,
                'gene_set'              => $gene_set,
                'description'           => "Gene models ($gene_set)",
                'access_level'          => 'PUBLIC',
                'is_primary_gene_track' => true,
                'is_remote'             => false,
                'file_path'             => "$genomes_dir/annotations.gff3.gz",
                'added_date'            => date('c'),
            ],
        ];
        if (file_put_contents($track_file, json_encode($track, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false) {
            $log[] = "$gene_set: created gene track JSON ($track_id)";
        } else {
            $log[] = "$gene_set: WARNING — could not write track JSON";
        }
    } else {
        $log[] = "$gene_set: gene track JSON already exists";
    }

    // ── Update assembly JSON primaryGeneTracks ───────────────────────────────
    if (file_exists($assembly_json)) {
        $asm_data = json_decode(file_get_contents($assembly_json), true) ?: [];
        $primary  = $asm_data['primaryGeneTracks'] ?? [];
        if (!in_array($track_id, $primary, true)) {
            $primary[] = $track_id;
            $asm_data['primaryGeneTracks'] = $primary;
            file_put_contents($assembly_json, json_encode($asm_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $log[] = "$gene_set: added $track_id to assembly primaryGeneTracks";
        }
    }

    // ── Feature coords index (for BLAST linkouts + MOOPmart) ─────────────────
    $gene_set_path = "$organisms_dir/$organism/$assembly/$gene_set";
    $tsv_path      = "$gene_set_path/feature_coords.tsv";
    $gff_mtime     = filemtime("$gene_set_path/genomic.gff") ?: 0;
    $tsv_mtime     = file_exists($tsv_path) ? filemtime($tsv_path) : 0;
    if ($tsv_mtime >= $gff_mtime && $tsv_mtime > 0) {
        $log[] = "$gene_set: feature_coords.tsv is up to date — skipped";
    } elseif (generateFeatureCoordsIndex($gene_set_path)) {
        $log[] = "$gene_set: generated feature_coords.tsv";
    }

    return true;
}

/**
 * Run jbrowse text-index for a specific gene set and update its track JSON.
 * Trix files go in jbrowse2/{organism}/{assembly}/{gene_set}/trix/.
 *
 * @return array ['success'=>bool, 'error'=>string?, ...]
 */
function buildGeneSetTextIndex(
    string $organism,
    string $assembly,
    string $gene_set,
    string $attributes,
    ConfigManager $config
): array {
    $site_path     = $config->getPath('site_path');
    $site          = $config->getString('site', 'moop');
    $metadata_path = $config->getPath('metadata_path');

    $jbrowse = findJBrowseCliGS();
    if (!$jbrowse) {
        return [
            'success' => false,
            'error'   => 'jbrowse CLI not found — install Node.js then: npm install -g @jbrowse/cli',
            'no_cli'  => true,
        ];
    }

    $gz_file     = "$site_path/data/genomes/$organism/$assembly/$gene_set/annotations.gff3.gz";
    $trix_parent = "$site_path/jbrowse2/$organism/$assembly/$gene_set";
    $trix_dir    = "$trix_parent/trix";

    if (!file_exists($gz_file)) {
        return ['success' => false, 'error' => "GFF not prepped: $gz_file — register the gene set first"];
    }

    if (!is_dir($trix_parent) && !mkdir($trix_parent, 0755, true)) {
        return ['success' => false, 'error' => "Could not create trix directory: $trix_parent"];
    }

    // Find the gene set's track JSON
    $tracks_dir = "$metadata_path/jbrowse2-configs/tracks/$organism/$assembly/gff";
    $track_file = "$tracks_dir/{$gene_set}_genes.json";
    if (!file_exists($track_file)) {
        return ['success' => false, 'error' => "Gene track JSON not found: $track_file — register the gene set first"];
    }
    $track_def = json_decode(file_get_contents($track_file), true);
    if (!$track_def) {
        return ['success' => false, 'error' => "Could not parse gene track JSON"];
    }
    $track_id = $track_def['trackId'] ?? null;
    if (!$track_id) {
        return ['success' => false, 'error' => "Gene track JSON has no trackId"];
    }

    $cmd = escapeshellarg($jbrowse) . ' text-index'
         . ' --file '       . escapeshellarg($gz_file)
         . ' --fileId '     . escapeshellarg($track_id)
         . ' --out '        . escapeshellarg($trix_parent)
         . ' --attributes ' . escapeshellarg($attributes)
         . ' --force 2>&1';

    $out = []; exec($cmd, $out, $rc);

    if ($rc !== 0) {
        return ['success' => false, 'error' => "exit $rc: " . implode(' ', $out)];
    }

    $gz_basename = basename($gz_file);
    $ix_file     = "$trix_dir/{$gz_basename}.ix";
    if (!file_exists($ix_file)) {
        return ['success' => false, 'error' => 'text-index ran but .ix file was not created'];
    }

    $asm_name = $track_def['assemblyNames'][0] ?? "{$organism}_{$assembly}";
    $base     = "/$site/jbrowse2/$organism/$assembly/$gene_set/trix/$gz_basename";
    $track_def['textSearching'] = [
        'textSearchAdapter' => [
            'type'                => 'TrixTextSearchAdapter',
            'textSearchAdapterId' => "$track_id-index",
            'ixFilePath'          => ['uri' => "$base.ix",        'locationType' => 'UriLocation'],
            'ixxFilePath'         => ['uri' => "$base.ixx",       'locationType' => 'UriLocation'],
            'metaFilePath'        => ['uri' => "{$base}_meta.json", 'locationType' => 'UriLocation'],
            'assemblyNames'       => [$asm_name],
        ],
    ];
    $track_def['metadata']['text_index_attributes'] = $attributes;
    $track_def['metadata']['text_index_date']       = gmdate('Y-m-d\TH:i:s\Z');
    file_put_contents($track_file, json_encode($track_def, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return ['success' => true];
}

/**
 * Build feature_coords.tsv from a sorted GFF3 file.
 *
 * Streams the file one line at a time. Because the GFF is coordinate-sorted
 * (by our bgzip prep step), all children of a gene appear consecutively after
 * the gene line. When a new root feature is encountered the previous gene's
 * batch is flushed to disk — so only one gene family is in memory at a time.
 *
 * Output format: hit_id\tgene_id\tchr\tstart\tend\tstrand
 */
function generateFeatureCoordsIndex(string $assembly_path): bool {
    $gff_file = $assembly_path . '/genomic.gff';
    $tsv_file = $assembly_path . '/feature_coords.tsv';

    if (!file_exists($gff_file) || filesize($gff_file) === 0) {
        return false;
    }

    $skip_types = ['region', 'chromosome', 'contig', 'scaffold', 'supercontig', 'biological_region'];

    $fh = fopen($gff_file, 'r');
    if (!$fh) return false;
    $out = fopen($tsv_file, 'w');
    if (!$out) { fclose($fh); return false; }

    $gene    = null;   // current root: ['id','chr','start','end','strand']
    $pending = [];     // feature rows for current gene family
    $seen    = [];     // dedup within current gene family

    $flush = function() use ($out, &$gene, &$pending, &$seen): void {
        if (!$gene) return;
        $suffix = "\t{$gene['id']}\t{$gene['chr']}\t{$gene['start']}\t{$gene['end']}\t{$gene['strand']}\n";
        foreach ($pending as [$id, $type, $name]) {
            if (!isset($seen[$id])) {
                fwrite($out, $id . $suffix);
                $seen[$id] = true;
            }
            $bare = preg_replace('/^(?:rna|cds|gene|id)-/', '', $id);
            if ($bare !== $id && !isset($seen[$bare])) {
                fwrite($out, $bare . $suffix);
                $seen[$bare] = true;
            }
            if ($type === 'cds' && $name !== null && $name !== $id && $name !== $bare && !isset($seen[$name])) {
                fwrite($out, $name . $suffix);
                $seen[$name] = true;
            }
        }
        $pending = [];
        $seen    = [];
    };

    while (($line = fgets($fh)) !== false) {
        if ($line[0] === '#') continue;
        $parts = explode("\t", rtrim($line), 9);
        if (count($parts) < 9) continue;
        $type = strtolower($parts[2]);
        if (in_array($type, $skip_types)) continue;

        $id = $parent = $name = null;
        foreach (explode(';', $parts[8]) as $attr) {
            $kv = explode('=', $attr, 2);
            if (count($kv) !== 2) continue;
            switch (trim($kv[0])) {
                case 'ID':     $id     = trim($kv[1]); break;
                case 'Parent': $parent = trim($kv[1]); break;
                case 'Name':   $name   = trim($kv[1]); break;
            }
        }
        if (!$id) continue;

        if (!$parent) {
            $flush();
            $gene = ['id' => $id, 'chr' => $parts[0], 'start' => $parts[3], 'end' => $parts[4], 'strand' => $parts[6]];
        }
        $pending[] = [$id, $type, $name];
    }
    $flush();

    fclose($fh);
    fclose($out);
    return true;
}

function findJBrowseCliGS(): ?string {
    static $cached = null;
    if ($cached !== null) return $cached;

    $candidates = [
        __DIR__ . '/../../tools/jbrowse-cli/jbrowse-run.sh',
        __DIR__ . '/../../tools/jbrowse-cli/bin/jbrowse',
        '/usr/local/bin/jbrowse',
        '/usr/bin/jbrowse',
        (getenv('HOME') ?: '') . '/.npm-global/bin/jbrowse',
        '/root/.npm-global/bin/jbrowse',
        '/usr/local/lib/node_modules/.bin/jbrowse',
        '/usr/lib/node_modules/@jbrowse/cli/bin/run',
    ];
    foreach ($candidates as $path) {
        if ($path && is_executable($path)) {
            $cached = $path;
            return $path;
        }
    }
    $out = [];
    exec('command -v jbrowse 2>/dev/null', $out, $ret);
    $cached = ($ret === 0 && !empty($out[0])) ? trim($out[0]) : false;
    return $cached ?: null;
}

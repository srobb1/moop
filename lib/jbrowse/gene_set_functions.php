<?php
/**
 * JBrowse gene set prep — shared by jbrowse_register_assembly and jbrowse_register_gene_set.
 */

require_once __DIR__ . '/../blast_functions.php';

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
        foreach ([$tbi_file, $gz_file] as $f) {
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
        if ($force || !file_exists($tbi_file)) {
            if (file_exists($tbi_file)) unlink($tbi_file);
            $out = []; $rc = 1;
            $cmd = 'tabix -p gff ' . escapeshellarg($gz_file) . ' 2>&1';
            exec($cmd, $out, $rc);
            $log[] = ($rc === 0)
                ? "$gene_set: tabix OK (" . number_format(filesize($tbi_file)) . " bytes)"
                : "$gene_set: WARNING — tabix failed: " . implode(' ', $out);
        } else {
            $log[] = "$gene_set: tabix index already exists";
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
                'index'         => [
                    'location' => [
                        'uri'          => "$uri_base/annotations.gff3.gz.tbi",
                        'locationType' => 'UriLocation',
                    ],
                ],
            ],
            'displays' => [
                [
                    'type'      => 'LinearBasicDisplay',
                    'displayId' => "$track_id-LinearBasicDisplay",
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
    if (generateFeatureCoordsIndex($gene_set_path)) {
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

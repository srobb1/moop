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

    $source_gff    = "$organisms_dir/$organism/$assembly/$gene_set/" . genes_gff_filename();
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
        $log[] = "$gene_set: no " . genes_gff_filename() . " found — skipped";
        return false;
    }
    if (filesize($source_gff) === 0) {
        $log[] = "$gene_set: " . genes_gff_filename() . " is empty — skipped";
        return false;
    }
    $log[] = "$gene_set: source GFF found (" . number_format(filesize($source_gff)) . " bytes)";

    // ── Create gene_set directory ────────────────────────────────────────────
    if (!is_dir($genomes_dir) && !mkdir($genomes_dir, 0755, true)) {
        $log[] = "$gene_set: ERROR — could not create $genomes_dir";
        return false;
    }

    // ── Symlink annotations.gff3 ─────────────────────────────────────────────
    // Self-healing: recreate if missing, dangling, or pointing at the wrong
    // source (e.g. after the source GFF is renamed). is_link() alone is true
    // for a *broken* link, so we must compare readlink() to the current source.
    if (is_link($target_gff)) {
        if (readlink($target_gff) === $source_gff) {
            $log[] = "$gene_set: annotations.gff3 symlink OK";
        } else {
            unlink($target_gff);
            symlink($source_gff, $target_gff);
            $log[] = "$gene_set: repointed annotations.gff3 symlink";
        }
    } elseif (!file_exists($target_gff)) {
        symlink($source_gff, $target_gff);
        $log[] = "$gene_set: symlinked annotations.gff3";
    } else {
        // A real (non-symlink) file is present — leave it as-is.
        $log[] = "$gene_set: annotations.gff3 present (regular file)";
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
    $gff_mtime     = filemtime("$gene_set_path/" . genes_gff_filename()) ?: 0;
    $tsv_mtime     = file_exists($tsv_path) ? filemtime($tsv_path) : 0;
    if ($tsv_mtime >= $gff_mtime && $tsv_mtime > 0) {
        $log[] = "$gene_set: feature_coords.tsv is up to date — skipped";
    } elseif (generateFeatureCoordsIndex($gene_set_path)) {
        $log[] = "$gene_set: generated feature_coords.tsv";
    }

    return true;
}

/**
 * Archive a gene set: move its source data directory out of the way and strip every
 * derived reference to it (JBrowse track JSON, assembly primaryGeneTracks entry,
 * bgzip/tabix/trix build artifacts, groups.json access entries).
 *
 * Intended for gene sets already confirmed absent from the organism's database — see
 * validateAssemblyDirectories()'s 'orphaned_gene_set_directory' mismatch, surfaced on
 * the admin dashboard. This function does NOT touch organism.sqlite: by definition
 * there's nothing there for this tuple to remove. It is the inverse of
 * prepareGeneSetForJBrowse() for everything that function creates.
 *
 * @return array ['success'=>bool, 'archived_to'=>string?, 'error'=>string?, 'removed'=>string[]]
 */
function archiveGeneSet(string $organism, string $assembly, string $gene_set, ConfigManager $config): array {
    $organisms_dir = $config->getPath('organism_data');
    $site_path     = $config->getPath('site_path');
    $metadata_path = $config->getPath('metadata_path');

    $source_dir = "$organisms_dir/$organism/$assembly/$gene_set";
    if (!is_dir($source_dir)) {
        return ['success' => false, 'error' => "Gene set directory not found: $source_dir"];
    }

    $removed = [];

    // 1. Move the source data directory to a timestamped archive location — never
    //    overwrite a previous archive of the same tuple.
    // IMPORTANT: this must live OUTSIDE $organisms_dir. Every organism-discovery
    // routine in this codebase (getOrganismsWithAssemblies(), fingerprinting, the
    // dashboard's organism count, etc.) treats every top-level directory under
    // organisms/ as an organism — an archive dir placed inside it gets scanned as a
    // phantom "organism" with its own fake assemblies/gene_sets.
    $archive_root = "$site_path/archived_gene_sets/$organism/$assembly";
    if (!is_dir($archive_root) && !@mkdir($archive_root, 0755, true)) {
        return ['success' => false, 'error' => "Could not create archive directory: $archive_root"];
    }
    $archive_dest = "$archive_root/{$gene_set}_" . date('Ymd_His');
    if (!@rename($source_dir, $archive_dest)) {
        return ['success' => false, 'error' => "Could not move $source_dir to $archive_dest"];
    }

    // 2. Remove derived JBrowse build artifacts — regenerated from source data, which
    //    is now archived, so there's nothing left for them to correctly serve.
    $genomes_dir = "$site_path/data/genomes/$organism/$assembly/$gene_set";
    if (is_dir($genomes_dir)) {
        rrmdir($genomes_dir);
        $removed[] = "data/genomes/$organism/$assembly/$gene_set";
    }
    $trix_dir = "$site_path/jbrowse2/$organism/$assembly/$gene_set";
    if (is_dir($trix_dir)) {
        rrmdir($trix_dir);
        $removed[] = "jbrowse2/$organism/$assembly/$gene_set (trix search index)";
    }

    // 3. Remove the gene track JSON and its trackId from the assembly's primaryGeneTracks.
    $track_id   = "{$organism}_{$assembly}_{$gene_set}_genes";
    $track_file = "$metadata_path/jbrowse2-configs/tracks/$organism/$assembly/gff/{$gene_set}_genes.json";
    if (file_exists($track_file)) {
        @unlink($track_file);
        $removed[] = "track JSON ({$gene_set}_genes.json)";
    }
    $assembly_json = "$metadata_path/jbrowse2-configs/assemblies/{$organism}_{$assembly}.json";
    if (file_exists($assembly_json)) {
        $asm_data = json_decode(file_get_contents($assembly_json), true) ?: [];
        $primary  = $asm_data['primaryGeneTracks'] ?? [];
        $filtered = array_values(array_filter($primary, fn($id) => $id !== $track_id));
        if (count($filtered) !== count($primary)) {
            $asm_data['primaryGeneTracks'] = $filtered;
            file_put_contents($assembly_json, json_encode($asm_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $removed[] = "primaryGeneTracks entry";
        }
    }

    // 4. Remove groups.json access entries for this tuple — nothing left to grant
    //    access to.
    $groups_file = "$metadata_path/organism_assembly_groups.json";
    if (file_exists($groups_file)) {
        $groups_data = json_decode(file_get_contents($groups_file), true) ?: [];
        $filtered = array_values(array_filter($groups_data, function ($e) use ($organism, $assembly, $gene_set) {
            return !($e['organism'] === $organism && $e['assembly'] === $assembly && ($e['gene_set'] ?? 'v1') === $gene_set);
        }));
        if (count($filtered) !== count($groups_data)) {
            file_put_contents($groups_file, json_encode($filtered, JSON_PRETTY_PRINT));
            $removed[] = "groups.json entry";
        }
    }

    return ['success' => true, 'archived_to' => $archive_dest, 'removed' => $removed];
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
    $gff_file = $assembly_path . '/' . genes_gff_filename();
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

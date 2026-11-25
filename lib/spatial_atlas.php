<?php
/**
 * Spatial expression atlas -- a 3-D model of an animal (a mesh) with one predicted expression
 * value per mesh vertex per gene. EndoAtlas (He et al. 2023, Nematostella) is the first; any later
 * atlas is another data file, not new code. Plan + formats: notes/SPATIAL_ATLAS_PLAN.md.
 *
 * ONE read-only SQLite file per atlas, in the folder of the gene set whose IDs it uses:
 *     organisms/<organism>/<assembly>/<gene set>/spatial_atlas/<name>.sqlite
 * so retiring or replacing the gene set takes the atlas with it. The file's existence turns the
 * card on; there is no config switch.
 *
 * BUILT from a "bundle" directory (atlas.json, mesh.obj, values.tsv[.gz], ...) by
 * scripts/build_spatial_atlas.php -- CLI only; temp file, checks, rename; a failed build leaves the
 * previous file. What the build cannot use goes on a REVIEW list (stored in meta.build_review and
 * printed), never silently dropped. Pictures are not in the file: see moop_atlas_image_analysis().
 *
 * VALUES ARE ROUNDED for display (meta.value_encoding, default uint8 = 256 levels of each gene's
 * own min..max). The reader decodes whatever the file says, so precision is a rebuild option.
 */

const MOOP_ATLAS_FORMAT    = '1';
const MOOP_ATLAS_ENCODINGS = ['uint8' => 255, 'uint16' => 65535, 'float32' => 0];
const MOOP_ATLAS_REQUIRED  = ['name', 'title', 'organism', 'assembly', 'gene_set', 'citation'];

function moop_atlas_dir(string $organism, string $assembly, string $gene_set): string {
    $clean = fn(string $s) => preg_replace('/[^A-Za-z0-9_.-]/', '', $s);
    return rtrim(ConfigManager::getInstance()->getPath('organism_data'), '/') . '/'
         . $clean($organism) . '/' . $clean($assembly) . '/' . $clean($gene_set) . '/spatial_atlas';
}

function moop_atlas_file(string $organism, string $assembly, string $gene_set, string $name): string {
    return moop_atlas_dir($organism, $assembly, $gene_set) . '/' . preg_replace('/[^a-z0-9_-]/', '', $name) . '.sqlite';
}

/** Access key (lib/access_rules.php kind 'atlas'): atlas:<organism>/<assembly>/<gene set>/<name>. */
function moop_atlas_analysis_key(string $organism, string $assembly, string $gene_set, string $name): string {
    return "atlas:$organism/$assembly/$gene_set/$name";
}

/** May this visitor see this atlas? The gene set must be visible AND the atlas's own rule pass. */
function moop_atlas_visible(string $organism, string $assembly, string $gene_set, string $name): bool {
    foreach ([$organism, $assembly, $gene_set, $name] as $p) if (!moop_plain_name($p)) return false;
    return moop_dataset_visible($organism, $assembly, $gene_set, moop_atlas_analysis_key($organism, $assembly, $gene_set, $name));
}

/**
 * Every atlas of an organism this visitor may see: [['organism','assembly','gene_set','name','file'], ...].
 * An organism without atlases costs one glob.
 */
function moop_atlases_for_organism(string $organism): array {
    if (!moop_plain_name($organism)) return [];
    $base = rtrim(ConfigManager::getInstance()->getPath('organism_data'), '/') . '/' . $organism;
    $out = [];
    foreach (glob("$base/*/*/spatial_atlas/*.sqlite") ?: [] as $f) {
        [$asm, $gs] = array_slice(explode('/', $f), -4, 2);
        $name = basename($f, '.sqlite');
        if (moop_atlas_visible($organism, $asm, $gs, $name)) {
            $out[] = ['organism' => $organism, 'assembly' => $asm, 'gene_set' => $gs, 'name' => $name, 'file' => $f];
        }
    }
    return $out;
}

/** Find an atlas by its name alone (e.g. the Research menu link): its one file, or null if none or several. */
function moop_atlas_by_name(string $name): ?array {
    if (!preg_match('/^[a-z0-9_-]+$/', $name)) return null;
    $root  = rtrim(ConfigManager::getInstance()->getPath('organism_data'), '/');
    $found = glob("$root/*/*/*/spatial_atlas/$name.sqlite") ?: [];
    if (count($found) !== 1) return null;
    [$org, $asm, $gs] = array_slice(explode('/', $found[0]), -5, 3);
    return ['organism' => $org, 'assembly' => $asm, 'gene_set' => $gs, 'name' => $name, 'file' => $found[0]];
}

/**
 * The atlas's ID for a gene known by any of $ids (a gene page's own ID, its transcripts, and what the
 * crosswalk links it to -- so a RefSeq gene page finds the NV2 gene). null = not in the atlas.
 */
function moop_atlas_resolve(PDO $db, array $ids): ?string {
    $row = moop_atlas_gene($db, array_values(array_unique(array_filter(array_map('strval', $ids), 'strlen'))));
    return $row ? $row['gene_id'] : null;
}

/** A gene ID plus everything the crosswalk links it to (and their genes), for moop_atlas_resolve(). */
function moop_atlas_candidate_ids(string $organism, string $gene_id, array $child_ids = []): array {
    require_once __DIR__ . '/crosswalk.php';
    $self = array_merge([$gene_id], $child_ids);
    if (!moop_crosswalk_open($organism)) return $self;
    $refs = [];
    foreach (moop_crosswalk_genes($organism, $self) as $rows) $refs = array_merge($refs, array_column($rows, 'ref_id'));
    $members = array_column(moop_crosswalk_members($organism, array_merge($self, $refs)), 'accession');
    return array_values(array_unique(array_merge($self, $refs, $members, moop_crosswalk_parent_genes($organism, $members))));
}

/** The atlas's own pictures (logo, figure), stored in the file: ['mime','data'] or null. */
function moop_atlas_asset(PDO $db, string $name): ?array {
    $st = $db->prepare('SELECT mime, data FROM asset WHERE name = ?');
    $st->execute([$name]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Atlas genes matching a name or ID (for the gene picker): [['gene_id','name'], ...], names first. */
function moop_atlas_search(PDO $db, string $q, int $limit = 20): array {
    $q = trim($q);
    if ($q === '') return [];
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $st = $db->prepare("SELECT gene_id, name FROM gene
                         WHERE name LIKE :l ESCAPE '\\' OR gene_id LIKE :l ESCAPE '\\'
                         ORDER BY (lower(name) = lower(:q)) DESC, (lower(name) LIKE lower(:p) ESCAPE '\\') DESC,
                                  (name IS NULL), name COLLATE NOCASE, gene_id
                         LIMIT " . max(1, min(100, $limit)));
    $st->execute([':l' => $like, ':q' => $q, ':p' => str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%']);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function moop_atlas_open(string $file): ?PDO {
    if (!is_file($file)) return null;
    try {
        $db = new PDO('sqlite:' . $file, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        return null;
    }
}

/** meta as [key => value]; axis_map/camera/aspect/build_review decoded from JSON. */
function moop_atlas_meta(PDO $db): array {
    $m = $db->query('SELECT key, value FROM meta')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach (['axis_map', 'camera', 'aspect', 'build_review'] as $k) {
        if (isset($m[$k])) $m[$k] = json_decode($m[$k], true);
    }
    return $m;
}

// ---------------------------------------------------------------------------------------------------
// Encoding. uint8/uint16: per gene, b = round((v - vmin) / (vmax - vmin) * levels). float32: as is.
// All little-endian. A gene whose values are all equal stores vmin = vmax and all-zero codes.
// ---------------------------------------------------------------------------------------------------

/** @return array [vmin, vmax, blob] */
function moop_atlas_encode(array $vals, string $encoding): array {
    if (!isset(MOOP_ATLAS_ENCODINGS[$encoding])) throw new InvalidArgumentException("unknown encoding $encoding");
    $min = min($vals);
    $max = max($vals);
    if ($encoding === 'float32') return [$min, $max, pack('g*', ...$vals)];
    $levels = MOOP_ATLAS_ENCODINGS[$encoding];
    $span   = $max - $min;
    $codes  = [];
    foreach ($vals as $v) $codes[] = $span > 0 ? (int) round(($v - $min) / $span * $levels) : 0;
    return [$min, $max, pack($encoding === 'uint8' ? 'C*' : 'v*', ...$codes)];
}

/** Back to numbers (approximate for uint8/uint16: within half a step of the gene's range). */
function moop_atlas_decode(string $blob, float $vmin, float $vmax, string $encoding): array {
    if ($encoding === 'float32') return array_values(unpack('g*', $blob));
    if (!isset(MOOP_ATLAS_ENCODINGS[$encoding])) throw new InvalidArgumentException("unknown encoding $encoding");
    $levels = MOOP_ATLAS_ENCODINGS[$encoding];
    $span   = $vmax - $vmin;
    $out    = [];
    foreach (unpack($encoding === 'uint8' ? 'C*' : 'v*', $blob) as $b) $out[] = $vmin + $b / $levels * $span;
    return $out;
}

// ---------------------------------------------------------------------------------------------------
// Reading
// ---------------------------------------------------------------------------------------------------

/**
 * The atlas row for a gene page: tries each ID in order (the gene's own ID, then its transcripts'
 * -- atlases are delivered with either). null = the gene is not in the study.
 * Returns ['gene_id','name','vmin','vmax','vals'(blob|null)].
 */
function moop_atlas_gene(PDO $db, array $ids): ?array {
    $st = $db->prepare('SELECT gene_id, name, vmin, vmax, vals FROM gene WHERE gene_id = ?');
    foreach ($ids as $id) {
        $st->execute([(string) $id]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) return $row;
    }
    return null;
}

/** ['vertices' => blob, 'faces' => blob, 'overlay' => blob|null] (see the schema in the plan). */
function moop_atlas_mesh(PDO $db): ?array {
    $row = $db->query('SELECT vertices, faces, overlay FROM mesh WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** Most similar genes: [['other_id','r','name'], ...] by rank. */
function moop_atlas_correlated(PDO $db, string $gene_id): array {
    $st = $db->prepare('SELECT c.other_id, c.r, g.name FROM correlated c LEFT JOIN gene g ON g.gene_id = c.other_id
                        WHERE c.gene_id = ? ORDER BY c.rank');
    $st->execute([$gene_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Pictures (in situ etc.) are NOT in the atlas file: they live in the organism's gene images
 * (lib/gene_images.php), and atlas.json "image_analysis" names the analysis whose picture the card
 * shows -- so they are also on the gene page's Images card, and one picture has one home.
 */
function moop_atlas_image_analysis(array $meta): string {
    $a = (string) ($meta['image_analysis'] ?? '');
    return moop_plain_name($a) ? $a : '';
}

/**
 * A gene's own ID followed by its transcripts' IDs, from organism.sqlite, for moop_atlas_gene().
 * Unknown gene: just [$gene_id].
 */
function moop_atlas_lookup_ids(string $organism, string $assembly, string $gene_set, string $gene_id): array {
    $ids = [$gene_id];
    $f = rtrim(ConfigManager::getInstance()->getPath('organism_data'), '/') . '/'
       . preg_replace('/[^A-Za-z0-9_.-]/', '', $organism) . '/organism.sqlite';
    if (!is_file($f)) return $ids;
    try {
        $db = new PDO('sqlite:' . $f, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
        $st = $db->prepare('SELECT c.feature_uniquename FROM feature p
                              JOIN feature c ON c.parent_feature_id = p.feature_id
                              JOIN gene_set gs ON gs.gene_set_id = p.gene_set_id
                              JOIN genome g ON g.genome_id = gs.genome_id
                             WHERE p.feature_uniquename = ? AND gs.gene_set_name = ? AND g.genome_accession = ?
                             ORDER BY c.feature_uniquename');
        $st->execute([$gene_id, $gene_set, $assembly]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $c) $ids[] = $c;
    } catch (PDOException $e) {
        // organism.sqlite unreadable: the gene's own ID is still tried
    }
    return array_values(array_unique($ids));
}

// ---------------------------------------------------------------------------------------------------
// Building (CLI only)
// ---------------------------------------------------------------------------------------------------

/** Every gene and transcript ID of one gene set, as [id => true]. Empty if the gene set is unknown. */
function moop_atlas_gene_set_ids(string $organism, string $assembly, string $gene_set): array {
    $f = rtrim(ConfigManager::getInstance()->getPath('organism_data'), '/') . '/'
       . preg_replace('/[^A-Za-z0-9_.-]/', '', $organism) . '/organism.sqlite';
    if (!is_file($f)) return [];
    $db = new PDO('sqlite:' . $f, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
    $st = $db->prepare('SELECT f.feature_uniquename FROM feature f
                          JOIN gene_set gs ON gs.gene_set_id = f.gene_set_id
                          JOIN genome g ON g.genome_id = gs.genome_id
                         WHERE gs.gene_set_name = ? AND g.genome_accession = ?');
    $st->execute([$gene_set, $assembly]);
    return array_fill_keys($st->fetchAll(PDO::FETCH_COLUMN), true);
}

/** Read a bundle TSV (.gz too): yields [line number, cells]; header checked against $columns. */
function moop_atlas_tsv(string $file, array $columns): Generator {
    $fh = substr($file, -3) === '.gz' ? gzopen($file, 'rb') : fopen($file, 'rb');
    if (!$fh) throw new RuntimeException('cannot read ' . basename($file));
    $gets = substr($file, -3) === '.gz' ? 'gzgets' : 'fgets';
    $hdr  = explode("\t", rtrim((string) $gets($fh), "\r\n"));
    if ($hdr !== $columns) throw new RuntimeException(basename($file) . ': header must be ' . implode(' TAB ', $columns));
    $n = 1;
    while (($line = $gets($fh)) !== false) {
        $n++;
        $line = rtrim($line, "\r\n");
        if ($line === '') continue;
        yield [$n, explode("\t", $line)];
    }
    substr($file, -3) === '.gz' ? gzclose($fh) : fclose($fh);
}

/**
 * Parse an OBJ: vertices [[x,y,z],...] and (if $faces) triangles [[i,j,k],...] 0-based; polygons with
 * more than 3 corners are fanned. Bad face indices go to $bad.
 */
function moop_atlas_read_obj(string $file, bool $faces, array &$bad): array {
    $v = [];
    $f = [];
    $fh = fopen($file, 'rb');
    if (!$fh) throw new RuntimeException('cannot read ' . basename($file));
    $polys = [];
    while (($line = fgets($fh)) !== false) {
        if (strncmp($line, 'v ', 2) === 0) {
            $p = preg_split('/\s+/', trim($line));
            if (count($p) < 4 || !is_numeric($p[1]) || !is_numeric($p[2]) || !is_numeric($p[3])) {
                throw new RuntimeException(basename($file) . ': bad vertex line "' . trim($line) . '"');
            }
            $v[] = [(float) $p[1], (float) $p[2], (float) $p[3]];
        } elseif ($faces && strncmp($line, 'f ', 2) === 0) {
            $polys[] = array_slice(preg_split('/\s+/', trim($line)), 1);
        }
    }
    fclose($fh);
    $n = count($v);
    foreach ($polys as $corners) {
        $idx = [];
        foreach ($corners as $c) {
            $i = (int) explode('/', $c)[0];           // a, a/b, a//c, a/b/c: only the vertex index
            if ($i < 0) $i = $n + $i + 1;              // OBJ negative = relative to the end
            if ($i < 1 || $i > $n) { $bad[] = $c; continue 2; }
            $idx[] = $i - 1;
        }
        for ($k = 1; $k + 1 < count($idx); $k++) $f[] = [$idx[0], $idx[$k], $idx[$k + 1]];
    }
    return [$v, $f];
}

/**
 * Build <gene set>/spatial_atlas/<name>.sqlite from a bundle directory.
 * $known_ids: [id => true] of the gene set (null = look it up; [] = skip the check, e.g. tests).
 * Returns ['ok' => bool, 'error' => string, 'file' => path, 'counts' => [...], 'review' => [kind => [detail => n]]].
 */
function moop_atlas_build(string $bundle, string $encoding = 'uint8', ?string $out_file = null, ?array $known_ids = null): array {
    $bundle = rtrim($bundle, '/');
    $review = [];
    $note = function (string $kind, string $detail) use (&$review) { $review[$kind][$detail] = ($review[$kind][$detail] ?? 0) + 1; };
    $counts = [];
    $tmp = null;
    try {
        if (!isset(MOOP_ATLAS_ENCODINGS[$encoding])) throw new RuntimeException("unknown encoding '$encoding' (uint8, uint16, float32)");
        $m = json_decode((string) @file_get_contents("$bundle/atlas.json"), true);
        if (!is_array($m)) throw new RuntimeException('atlas.json is missing or not JSON');
        foreach (MOOP_ATLAS_REQUIRED as $k) {
            if (!isset($m[$k]) || !is_string($m[$k]) || trim($m[$k]) === '') throw new RuntimeException("atlas.json: '$k' is required");
        }
        if (!preg_match('/^[a-z0-9_-]+$/', $m['name'])) throw new RuntimeException("atlas.json: name must be [a-z0-9_-]");
        if (isset($m['image_analysis']) && !moop_plain_name((string) $m['image_analysis'])) {
            throw new RuntimeException('atlas.json: image_analysis must be a plain name (letters, digits, _ . -)');
        }
        if (isset($m['axis_map']) && (!is_array($m['axis_map']) || count($m['axis_map']) !== 3
                || array_diff($m['axis_map'], ['x', 'y', 'z']) || count(array_unique($m['axis_map'])) !== 3)) {
            throw new RuntimeException('atlas.json: axis_map must be three of "x","y","z", each once');
        }
        $values_file = is_file("$bundle/values.tsv.gz") ? "$bundle/values.tsv.gz" : "$bundle/values.tsv";
        if (!is_file($values_file)) throw new RuntimeException('values.tsv(.gz) is missing');
        if (!is_file("$bundle/mesh.obj")) throw new RuntimeException('mesh.obj is missing');

        $out_file = $out_file ?? moop_atlas_file($m['organism'], $m['assembly'], $m['gene_set'], $m['name']);
        if ($known_ids === null) {
            $known_ids = moop_atlas_gene_set_ids($m['organism'], $m['assembly'], $m['gene_set']);
            if (!$known_ids) throw new RuntimeException("gene set {$m['organism']} / {$m['assembly']} / {$m['gene_set']} has no genes in organism.sqlite");
        }
        $check = $known_ids ? fn(string $id) => isset($known_ids[$id]) : fn(string $id) => true;
        if (!is_dir(dirname($out_file)) && !mkdir(dirname($out_file), 02750, true)) throw new RuntimeException('cannot create ' . dirname($out_file));
        $tmp = $out_file . '.tmp-' . getmypid();
        @unlink($tmp);

        // --- mesh ---
        $bad = [];
        [$verts, $faces] = moop_atlas_read_obj("$bundle/mesh.obj", true, $bad);
        if ($bad) throw new RuntimeException('mesh.obj: ' . count($bad) . ' face corner(s) refer to no vertex, e.g. ' . $bad[0]);
        $nv = count($verts);
        if ($nv === 0 || !$faces) throw new RuntimeException('mesh.obj has no vertices or no faces');
        $overlay = null;
        if (is_file("$bundle/overlay.obj")) {
            [$ov] = moop_atlas_read_obj("$bundle/overlay.obj", false, $bad);
            $overlay = $ov;
        }
        $packv = fn(array $pts) => pack('g*', ...array_merge(...$pts));

        $db = new PDO('sqlite:' . $tmp);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA page_size = 65536; PRAGMA journal_mode = OFF; PRAGMA synchronous = OFF;');
        $db->exec('CREATE TABLE meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
        $db->exec('CREATE TABLE mesh (id INTEGER PRIMARY KEY CHECK (id = 1), vertices BLOB NOT NULL, faces BLOB NOT NULL, overlay BLOB)');
        $db->exec('CREATE TABLE gene (gene_id TEXT PRIMARY KEY, name TEXT, vmin REAL, vmax REAL, vals BLOB)');
        $db->exec('CREATE TABLE correlated (gene_id TEXT NOT NULL, rank INTEGER NOT NULL, other_id TEXT NOT NULL, r REAL NOT NULL,
                                            PRIMARY KEY (gene_id, rank))');
        $db->exec('CREATE TABLE region (vertex INTEGER PRIMARY KEY, region TEXT NOT NULL)');
        // The atlas's own pictures (assets/logo.png, assets/figure.png): shown on its tool page. Gene
        // pictures are NOT here -- they are gene images (moop_atlas_image_analysis()).
        $db->exec('CREATE TABLE asset (name TEXT PRIMARY KEY, mime TEXT NOT NULL, data BLOB NOT NULL)');
        $db->beginTransaction();

        $st = $db->prepare('INSERT INTO mesh (id, vertices, faces, overlay) VALUES (1, ?, ?, ?)');
        $st->bindValue(1, $packv($verts), PDO::PARAM_LOB);
        $st->bindValue(2, pack('V*', ...array_merge(...$faces)), PDO::PARAM_LOB);
        $st->bindValue(3, $overlay ? $packv($overlay) : null, $overlay ? PDO::PARAM_LOB : PDO::PARAM_NULL);
        $st->execute();
        $counts += ['vertex_count' => $nv, 'face_count' => count($faces), 'overlay_count' => $overlay ? count($overlay) : 0];
        unset($verts, $faces, $overlay);

        // --- names (optional): the genes in the study, incl. those with no values ---
        $names = [];
        if (is_file("$bundle/genes.tsv")) {
            foreach (moop_atlas_tsv("$bundle/genes.tsv", ['gene_id', 'name']) as [$ln, $c]) {
                $id = trim($c[0]);
                if (isset($names[$id])) throw new RuntimeException("genes.tsv: $id is listed twice (line $ln)");
                $names[$id] = trim($c[1] ?? '');
            }
        }

        // --- values ---
        $ins = $db->prepare('INSERT INTO gene (gene_id, name, vmin, vmax, vals) VALUES (?, ?, ?, ?, ?)');
        $seen = [];
        $gmin = INF; $gmax = -INF; $with_vals = 0;
        foreach (moop_atlas_tsv($values_file, ['gene_id', 'values']) as [$ln, $c]) {
            $id = trim($c[0]);
            if (isset($seen[$id])) throw new RuntimeException("values: $id is listed twice (line $ln)");
            $seen[$id] = true;
            if (!$check($id)) { $note('ID not in the gene set (skipped)', $id); continue; }
            $vals = explode(',', $c[1] ?? '');
            if (count($vals) !== $nv) { $note("values row does not have $nv numbers (skipped)", "$id (" . count($vals) . ')'); continue; }
            foreach ($vals as $i => $v) {
                if (!is_numeric($v) || !is_finite((float) $v)) { $note('values row has a non-number (skipped)', $id); continue 2; }
                $vals[$i] = (float) $v;
            }
            [$lo, $hi, $blob] = moop_atlas_encode($vals, $encoding);
            $gmin = min($gmin, $lo); $gmax = max($gmax, $hi);
            if ($hi == $lo) $note('all values equal (stored, draws flat)', $id);
            $ins->bindValue(1, $id);
            $ins->bindValue(2, ($names[$id] ?? '') !== '' ? $names[$id] : null, ($names[$id] ?? '') !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $ins->bindValue(3, $lo);
            $ins->bindValue(4, $hi);
            $ins->bindValue(5, $blob, PDO::PARAM_LOB);
            $ins->execute();
            $with_vals++;
        }
        // genes in the study without values: "not expressed in this study"
        $without = 0;
        foreach ($names as $id => $name) {
            if (isset($seen[$id])) continue;
            if (!$check($id)) { $note('ID not in the gene set (skipped)', $id); continue; }
            $ins->execute([$id, $name !== '' ? $name : null, null, null, null]);
            $without++;
        }
        if ($with_vals === 0) throw new RuntimeException('no usable values rows');
        $counts += ['gene_count' => $with_vals + $without, 'genes_with_values' => $with_vals, 'genes_without_values' => $without,
                    'value_min' => $gmin, 'value_max' => $gmax];

        // --- correlated (optional) ---
        $pairs = 0;
        if (is_file("$bundle/correlated.tsv")) {
            $rank = [];
            $st = $db->prepare('INSERT INTO correlated (gene_id, rank, other_id, r) VALUES (?, ?, ?, ?)');
            foreach (moop_atlas_tsv("$bundle/correlated.tsv", ['gene_id', 'other_id', 'r']) as [$ln, $c]) {
                [$a, $b, $r] = [trim($c[0]), trim($c[1] ?? ''), trim($c[2] ?? '')];
                if ($b === '' || !is_numeric($r)) { $note('correlated: bad line (skipped)', "line $ln"); continue; }
                if (!$check($a) || !$check($b)) { $note('correlated: ID not in the gene set (skipped)', $check($a) ? $b : $a); continue; }
                $rank[$a] = ($rank[$a] ?? 0) + 1;
                $st->execute([$a, $rank[$a], $b, (float) $r]);
                $pairs++;
            }
        }
        $counts['correlated_pairs'] = $pairs;

        // --- regions (optional) ---
        $labelled = 0;
        if (is_file("$bundle/regions.tsv")) {
            $st = $db->prepare('INSERT INTO region (vertex, region) VALUES (?, ?)');
            foreach (moop_atlas_tsv("$bundle/regions.tsv", ['vertex', 'region']) as [$ln, $c]) {
                $vx = (int) $c[0];
                if ((string) $vx !== trim($c[0]) || $vx < 1 || $vx > $nv) { $note("regions: vertex not in 1..$nv (skipped)", "line $ln"); continue; }
                $st->execute([$vx - 1, trim($c[1] ?? '')]);
                $labelled++;
            }
        }
        $counts['regions_labelled'] = $labelled;

        // --- assets (optional): assets/<name>.png|jpg, name = logo | figure ---
        $assets = 0;
        $st = $db->prepare('INSERT INTO asset (name, mime, data) VALUES (?, ?, ?)');
        foreach (glob("$bundle/assets/*") ?: [] as $f) {
            if (!preg_match('/^(logo|figure)\.(png|jpe?g)$/i', basename($f), $mm)) { $note('assets: not logo/figure .png/.jpg (skipped)', basename($f)); continue; }
            $info = @getimagesize($f);
            if (!$info || !in_array($info['mime'], ['image/png', 'image/jpeg'], true)) { $note('assets: not a readable PNG/JPEG (skipped)', basename($f)); continue; }
            $st->bindValue(1, strtolower($mm[1]));
            $st->bindValue(2, $info['mime']);
            $st->bindValue(3, file_get_contents($f), PDO::PARAM_LOB);
            $st->execute();
            $assets++;
        }
        $counts['assets'] = $assets;

        // --- meta ---
        $meta = $m;
        foreach (['axis_map', 'camera', 'aspect'] as $k) if (isset($meta[$k])) $meta[$k] = json_encode($meta[$k]);
        $review_counts = array_map('array_sum', $review);
        $meta += $counts;
        $meta['format_version'] = MOOP_ATLAS_FORMAT;
        $meta['value_encoding'] = $encoding;
        $meta['built_at']       = date('c');
        $meta['source_sha256']  = hash_file('sha256', $values_file);
        $meta['build_review']   = json_encode((object) $review_counts);
        $st = $db->prepare('INSERT INTO meta (key, value) VALUES (?, ?)');
        foreach ($meta as $k => $v) {
            if (is_array($v)) $v = json_encode($v);
            $st->execute([$k, (string) $v]);
        }
        $db->commit();
        $ok = $db->query('PRAGMA integrity_check')->fetchColumn();
        $db = null;
        if ($ok !== 'ok') throw new RuntimeException("integrity check: $ok");
        @chmod($tmp, 0640);
        if (!rename($tmp, $out_file)) throw new RuntimeException('cannot move the new file into place');
        return ['ok' => true, 'error' => '', 'file' => $out_file, 'counts' => $counts, 'review' => $review];
    } catch (Throwable $e) {
        $db = null;
        if ($tmp) @unlink($tmp);
        return ['ok' => false, 'error' => $e->getMessage(), 'file' => $out_file, 'counts' => $counts, 'review' => $review];
    }
}

<?php
/**
 * One-off conversion: the EndoAtlas authors' Dash app (He et al. 2023) -> a spatial atlas BUNDLE
 * (the input format of scripts/build_spatial_atlas.php; notes/SPATIAL_ATLAS_PLAN.md).
 *
 *   php scripts/spatial_atlas_from_endoatlas.php <unpacked app dir> <bundle dir>
 *
 * <unpacked app dir> = the folder holding application.py, obj/ and assets/ (unzip
 * endo_atlas_dash_app_20230331.zip somewhere with ~1.5 GB free; /tmp is too small here).
 * Nothing is "fixed" on the way: values are copied as the authors wrote them, names are only
 * split off the "Name | ID" label the app displays. What looks odd is printed for review.
 *
 * What maps to what (read from application.py, 2026-11-18):
 *   obj/prediction_20220130_3d_51markers.db  PREDDICTION(ID, GENENAME, PREDIC)  -> values.tsv.gz, genes.tsv
 *   obj/20220130_id_to_name.json             {id: "Name | id" or "id"}          -> genes.tsv
 *   obj/segmentation grid_no_top_dot.obj     the surface (value n = n-th v line) -> mesh.obj
 *   obj/segmentation grid_grid_only3.obj     the segment grid, drawn as dots     -> overlay.obj
 *   obj/20210120_predicted_sig_top10_correlation_by_bin_51markers.csv            -> correlated.tsv
 *   obj/20220116_vertex_dict.json            {"x_y_z": "segment_position"}       -> regions.tsv
 *   obj/insitu_list.txt + assets/<id>.png  -> the organism's GENE IMAGES (.images/endoatlas/), not the bundle
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
if ($argc !== 3) { fwrite(STDERR, "usage: php {$argv[0]} <unpacked app dir> <bundle dir>\n"); exit(2); }
[$app, $out] = [rtrim($argv[1], '/'), rtrim($argv[2], '/')];
$obj = "$app/obj";
$need = ['prediction_20220130_3d_51markers.db', '20220130_id_to_name.json', 'segmentation grid_no_top_dot.obj',
         'segmentation grid_grid_only3.obj', '20210120_predicted_sig_top10_correlation_by_bin_51markers.csv',
         '20220116_vertex_dict.json', 'insitu_list.txt'];
foreach ($need as $f) if (!is_file("$obj/$f")) { fwrite(STDERR, "missing $obj/$f\n"); exit(1); }
if (!is_dir($out) && !mkdir($out, 02775, true)) { fwrite(STDERR, "cannot create $out\n"); exit(1); }

function fail(string $m): void { fwrite(STDERR, "FAILED: $m\n"); exit(1); }
function label_name(string $label, string $id): string {
    // The app shows "Szt2 | NV2g000006000.1" for a named gene and the bare ID otherwise.
    $label = trim($label);
    if ($label === $id) return '';
    $suffix = ' | ' . $id;
    return substr($label, -strlen($suffix)) === $suffix ? trim(substr($label, 0, -strlen($suffix))) : $label;
}

// --- mesh + overlay: copied byte for byte (the build reads only v and f lines) -------------------
copy("$obj/segmentation grid_no_top_dot.obj", "$out/mesh.obj") || fail('copy mesh');
copy("$obj/segmentation grid_grid_only3.obj", "$out/overlay.obj") || fail('copy overlay');
$verts = [];
foreach (file("$out/mesh.obj") as $line) {
    if (strncmp($line, 'v ', 2) === 0) $verts[] = array_map('floatval', array_slice(preg_split('/\s+/', trim($line)), 1, 3));
}
printf("mesh: %d vertices\n", count($verts));

// --- values + names --------------------------------------------------------------------------------
$names = [];   // id => name ('' = in the study, no name)
$odd   = [];
$json = json_decode(file_get_contents("$obj/20220130_id_to_name.json"), true);
if (!is_array($json)) fail('id_to_name.json is not JSON');
foreach ($json as $id => $label) $names[$id] = label_name((string) $label, (string) $id);

$db = new PDO('sqlite:' . "$obj/prediction_20220130_3d_51markers.db", null, null,
              [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
$gz = gzopen("$out/values.tsv.gz", 'wb6') ?: fail('cannot write values.tsv.gz');
gzwrite($gz, "gene_id\tvalues\n");
$rows = 0;
foreach ($db->query('SELECT ID, GENENAME, PREDIC FROM PREDDICTION ORDER BY ID') as $r) {
    $id = trim($r['ID']);
    $n  = label_name((string) $r['GENENAME'], $id);
    if (isset($names[$id]) && $names[$id] !== $n) $odd[] = "$id: values table says '$n', id_to_name says '{$names[$id]}'";
    $names[$id] = $names[$id] ?? $n;
    gzwrite($gz, $id . "\t" . trim($r['PREDIC']) . "\n");
    $rows++;
}
gzclose($gz);
ksort($names);
$fh = fopen("$out/genes.tsv", 'w');
fwrite($fh, "gene_id\tname\n");
foreach ($names as $id => $n) fwrite($fh, "$id\t$n\n");
fclose($fh);
printf("values: %d genes; genes.tsv: %d genes in the study (%d named)\n",
       $rows, count($names), count(array_filter($names, 'strlen')));

// --- correlated: "Var1","top_genes","top_cor" = gene, similar gene, Pearson r; file order = rank ----
$in  = fopen("$obj/20210120_predicted_sig_top10_correlation_by_bin_51markers.csv", 'r');
$hdr = fgetcsv($in);
if ($hdr !== ['Var1', 'top_genes', 'top_cor']) fail('correlation CSV header is ' . json_encode($hdr));
$fh = fopen("$out/correlated.tsv", 'w');
fwrite($fh, "gene_id\tother_id\tr\n");
$c = 0;
while (($r = fgetcsv($in)) !== false) { if (count($r) === 3) { fwrite($fh, "$r[0]\t$r[1]\t$r[2]\n"); $c++; } }
fclose($fh); fclose($in);
printf("correlated: %d pairs\n", $c);

// --- regions: keys are Python float reprs ("0.0_4.86871_-6.424011") -> match numerically ------------
$key = fn(array $xyz) => implode('_', array_map(fn($x) => sprintf('%.5f', round($x, 5) + 0.0), $xyz));
$region = [];
foreach (json_decode(file_get_contents("$obj/20220116_vertex_dict.json"), true) ?: [] as $k => $lab) {
    $region[$key(array_map('floatval', explode('_', $k)))] = $lab;
}
$fh = fopen("$out/regions.tsv", 'w');
fwrite($fh, "vertex\tregion\n");
$hit = 0;
foreach ($verts as $i => $xyz) {
    if (isset($region[$key($xyz)])) { fwrite($fh, ($i + 1) . "\t" . $region[$key($xyz)] . "\n"); $hit++; }
}
fclose($fh);
printf("regions: %d of %d vertices labelled\n", $hit, count($verts));

// --- in situ images: NOT in the bundle -- they join the organism's gene images ----------------------
// (lib/gene_images.php, docs/ORGANISM_SIDE_FILES.md §2) as analysis "endoatlas", so they show in the
// gene page's Images card like any other in situ, and the atlas card asks that analysis for its picture
// (atlas.json "image_analysis"). Files -> organisms/<Org>/.images/endoatlas/; rows -> .images/images.tsv
// and analyses.tsv, which are written only if they do not exist yet (they are curated files: otherwise
// the rows are written next to them as *.endoatlas.tsv for a person to merge).
$organism = 'Nematostella_vectensis';
$img_root = dirname($out, 6) . "/$organism/.images";      // <organisms>/<Org>/<asm>/<gs>/spatial_atlas/source/<name>
if (!is_dir(dirname($img_root))) fail("cannot find the organism folder $organism next to the bundle");
if (!is_dir("$img_root/endoatlas") && !mkdir("$img_root/endoatlas", 02775, true)) fail("cannot create $img_root/endoatlas");
$pmid     = '37315559';
$citation = 'He S et al. Current Biology 2023';   // PMID 37315559
$rows = [];
foreach (file("$obj/insitu_list.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $id) {
    $id = trim($id);
    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $id)) { $odd[] = "in situ list: odd id '$id'"; continue; }
    if (!is_file("$app/assets/$id.png")) { $odd[] = "in situ list: no assets/$id.png"; continue; }
    copy("$app/assets/$id.png", "$img_root/endoatlas/$id.png") || fail("copy image $id");
    chmod("$img_root/endoatlas/$id.png", 0644);
    $rows[] = implode("\t", ["endoatlas/$id.png", 'in situ', $id, 'GCA_033964005.1', 'NV2', 'endoatlas', '', '', '', $pmid, $citation, 'Gibson lab', '', '']);
}
$img_hdr = "file\tkind\taccession\tassembly\tgene_set\tanalysis\tstage\tterms\tcaption\tpmid\tcitation\tcredit\tlicense\tposter\n";
$an_hdr  = "analysis\ttitle\tdescription\tpmid\tcitation\tkey_image\tkey_caption\tlink_url\tlink_text\n";
$an_row  = implode("\t", ['endoatlas', 'Larval in situ hybridization (He et al. 2023, EndoAtlas)',
                          'Whole-mount in situ hybridization of Nematostella larvae, from the EndoAtlas spatial transcriptomics study.',
                          $pmid, $citation, '', '', '', '']) . "\n";
foreach (['images.tsv' => $img_hdr . implode("\n", $rows) . "\n", 'analyses.tsv' => $an_hdr . $an_row] as $f => $text) {
    $target = is_file("$img_root/$f") ? "$img_root/" . basename($f, '.tsv') . '.endoatlas.tsv' : "$img_root/$f";
    file_put_contents($target, $text) !== false || fail("write $target");
    chmod($target, 0664);
    if ($target !== "$img_root/$f") $odd[] = "$f already exists: merge the rows from $target by hand";
}
printf("images: %d -> %s/endoatlas (then: php scripts/build_gene_images.php %s)\n", count($rows), $img_root, $organism);

// --- manifest -------------------------------------------------------------------------------------
$manifest = [
    'name'        => 'endoatlas',
    'title'       => 'EndoAtlas',
    'description' => 'Predicted 3-D expression in the endomesoderm of the Nematostella larva',
    'organism'    => 'Nematostella_vectensis', 'assembly' => 'GCA_033964005.1', 'gene_set' => 'NV2',
    'citation'    => 'He S, et al. Spatial transcriptomics reveals a cnidarian segment polarity program in Nematostella vectensis. Current Biology 2023.',
    'pmid'        => '37315559',
    'data_url'    => 'https://www.ncbi.nlm.nih.gov/geo/query/acc.cgi?acc=GSE173786',
    'value_label' => 'Expression prediction',
    'image_label'    => 'In situ hybridization',
    'image_analysis' => 'endoatlas',             // gene-images analysis whose picture the atlas card shows
    'axis_map'    => ['z', 'x', 'y'],
    'camera'      => ['eye' => [0, 1, 1.2]],
    'aspect'      => [1, 1, 0.8],
    'colorscale'  => 'Viridis',
    'source'      => 'endo_atlas_dash_app_20230331.zip (authors\' Dash app), converted by scripts/spatial_atlas_from_endoatlas.php on ' . date('Y-m-d'),
];
file_put_contents("$out/atlas.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

foreach (array_slice($odd, 0, 20) as $o) echo "  review: $o\n";
if (count($odd) > 20) printf("  review: ... %d more\n", count($odd) - 20);
echo "bundle written to $out\n";

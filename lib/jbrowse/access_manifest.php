<?php
/**
 * Per-assembly track access manifest.
 *
 * Generates data/tracks/{organism}/{assembly}/access_manifest.json, a static map of
 *   relative-file-path → required access level
 * built from the track-definition JSONs. api/jbrowse2/tracks.php reads this manifest
 * to enforce PER-FILE access, so a low-privilege token can no longer fetch a
 * higher-level file on the same assembly merely by knowing its path.
 *
 * MOOP-side only: this reads metadata/jbrowse2-configs/tracks/ (the track definitions),
 * which the tracks server does NOT have. The tracks server only ever READS the emitted
 * manifest — it never runs this generator. Keep it that way so the tracks-server copy
 * set stays {tracks.php, track_token.php, the manifest files}.
 *
 * See [[plan_jbrowse_auth_headers]] / audit item #17.
 */

require_once __DIR__ . '/track_token.php';   // trackAccessLevelValue()

/**
 * Recursively collect every adapter "uri" string under a node.
 */
function _manifest_collect_uris($node) {
    $uris = [];
    if (is_array($node)) {
        foreach ($node as $k => $v) {
            if ($k === 'uri' && is_string($v)) {
                $uris[] = $v;
            } else {
                $uris = array_merge($uris, _manifest_collect_uris($v));
            }
        }
    }
    return $uris;
}

/**
 * Build the access manifest for one assembly from its track-definition JSONs.
 *
 * A file referenced by more than one track is assigned the MOST RESTRICTIVE level of
 * those tracks (the secure choice: e.g. a bigWig used by both a COLLABORATOR track and
 * a PUBLIC combo track is treated as COLLABORATOR). Index files (.bai, .tbi, .csi …)
 * are separate adapter URIs and are captured with their track's level automatically.
 *
 * @param string $organism
 * @param string $assembly
 * @param string $tracks_config_dir  metadata/jbrowse2-configs/tracks
 * @param array  &$conflicts         out: [relpath => [levels...]] for files seen at >1 level
 * @return array  [ relpath => 'LEVEL', ... ]  (levels canonical-uppercase)
 */
function buildAccessManifest($organism, $assembly, $tracks_config_dir, array &$conflicts = []) {
    $dir     = "$tracks_config_dir/$organism/$assembly";
    $prefix  = "/data/tracks/$organism/$assembly/";
    $levels  = [];   // relpath => highest numeric level seen
    $names   = [];   // relpath => canonical level string for that highest value
    $seen    = [];   // relpath => set of level strings (for conflict reporting)

    foreach (glob("$dir/*/*.json") as $track_file) {
        $def = json_decode(file_get_contents($track_file), true);
        if (!is_array($def)) continue;

        $level_str = strtoupper(trim((string)($def['metadata']['access_level'] ?? 'PUBLIC')));
        if ($level_str === '') $level_str = 'PUBLIC';
        $level_val = trackAccessLevelValue($level_str);

        foreach (_manifest_collect_uris($def['adapter'] ?? []) as $uri) {
            $pos = strpos($uri, $prefix);
            if ($pos === false) continue;                 // external / non-tracks URL — not ours to gate
            $rel = substr($uri, $pos + strlen($prefix));
            if (($q = strpos($rel, '?')) !== false) $rel = substr($rel, 0, $q);
            if ($rel === '') continue;

            $seen[$rel][$level_str] = true;
            if (!isset($levels[$rel]) || $level_val > $levels[$rel]) {
                $levels[$rel] = $level_val;
                $names[$rel]  = $level_str;
            }
        }
    }

    foreach ($seen as $rel => $set) {
        if (count($set) > 1) $conflicts[$rel] = array_keys($set);
    }

    ksort($names);
    return $names;
}

/**
 * Build and write the manifest for one assembly to its data/tracks dir.
 * Creates the directory if needed (on MOOP this may be a staging copy for a
 * remote-hosted assembly; that is harmless — MOOP's tracks.php is not hit for it).
 *
 * @return array [ 'path'=>..., 'file_count'=>int, 'conflicts'=>array, 'written'=>bool ]
 */
function writeAccessManifest($organism, $assembly, $tracks_config_dir, $tracks_data_dir) {
    $conflicts = [];
    $files = buildAccessManifest($organism, $assembly, $tracks_config_dir, $conflicts);

    $out_dir  = "$tracks_data_dir/$organism/$assembly";
    $out_file = "$out_dir/access_manifest.json";

    $payload = [
        'version'   => 1,
        'organism'  => $organism,
        'assembly'  => $assembly,
        'generated' => gmdate('c'),
        'policy'    => 'most-restrictive',
        'files'     => $files,
    ];

    if (!is_dir($out_dir)) {
        @mkdir($out_dir, 0755, true);
    }
    $written = file_put_contents(
        $out_file,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    ) !== false;

    return [
        'path'       => $out_file,
        'file_count' => count($files),
        'conflicts'  => $conflicts,
        'written'    => $written,
    ];
}

/**
 * Regenerate one assembly's access manifest, resolving paths from config.
 *
 * Call this after ANY operation that adds, syncs, or deletes an assembly's tracks, so
 * the manifest never goes stale. Cheap (a local file walk + one write); safe to call
 * often. Paths are derived to match where tracks.php reads: {site_path}/data/tracks.
 *
 * @return array writeAccessManifest() result (path, file_count, conflicts, written)
 */
function refreshAccessManifest($organism, $assembly) {
    $config    = ConfigManager::getInstance();
    $site_path = $config->getPath('site_path');
    return writeAccessManifest(
        $organism,
        $assembly,
        $site_path . '/metadata/jbrowse2-configs/tracks',
        $site_path . '/data/tracks'
    );
}

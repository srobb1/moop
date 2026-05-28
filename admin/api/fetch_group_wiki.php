<?php
include_once __DIR__ . '/../admin_init.php';
include_once __DIR__ . '/../../lib/functions_display.php';

header('Content-Type: application/json');

$group_name      = trim($_POST['group_name'] ?? '');
$wikipedia_topic = trim($_POST['wikipedia_topic'] ?? '');

if (empty($group_name) || empty($wikipedia_topic)) {
    echo json_encode(['success' => false, 'message' => 'group_name and wikipedia_topic are required']);
    exit;
}

$metadata_path     = $config->getPath('metadata_path');
$descriptions_file = "$metadata_path/group_descriptions.json";

if (!is_writable($descriptions_file)) {
    echo json_encode(['success' => false, 'message' => 'group_descriptions.json is not writable']);
    exit;
}

$wiki = getWikipediaTaxonomyData($wikipedia_topic);

if (empty($wiki['description'])) {
    echo json_encode(['success' => false, 'message' => "No Wikipedia article found for \"$wikipedia_topic\""]);
    exit;
}

// Build html_p entry with Wikipedia attribution
$description_html = htmlspecialchars($wiki['description'])
    . '<br><br><small class="text-muted">Source: <a href="' . htmlspecialchars($wiki['wikipedia_url'])
    . '" target="_blank">Wikipedia</a></small>';

// Download image if available
$image_file = '';
if (!empty($wiki['image_url'])) {
    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $group_name) . '.jpg';
    $dl = downloadWikimediaImage($wiki['image_url'], $safe_name);
    if ($dl) {
        // Strip leading /site/images/ prefix — stored relative to images dir (e.g. "wikimedia/Foo.jpg")
        $image_file = preg_replace('#^/' . preg_quote($config->getString('site'), '#') . '/images/#', '', $dl);
    }
}

// Update group_descriptions.json
$data = json_decode(file_get_contents($descriptions_file), true) ?: [];
$found = false;
foreach ($data as &$entry) {
    if ($entry['group_name'] === $group_name) {
        $entry['wikipedia_topic'] = $wikipedia_topic;
        $entry['html_p'] = [['text' => $description_html, 'style' => '', 'class' => '']];
        if ($image_file) {
            $entry['images'] = [['file' => $image_file, 'caption' => '']];
        }
        $found = true;
        break;
    }
}
unset($entry);

if (!$found) {
    echo json_encode(['success' => false, 'message' => "Group \"$group_name\" not found in descriptions file"]);
    exit;
}

if (file_put_contents($descriptions_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to write group_descriptions.json']);
    exit;
}

echo json_encode([
    'success'          => true,
    'description_html' => $description_html,
    'image_file'       => $image_file,
    'wikipedia_url'    => $wiki['wikipedia_url'],
]);

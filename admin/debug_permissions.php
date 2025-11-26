<?php
include_once __DIR__ . '/admin_init.php';

$file = '/data/moop/metadata/group_descriptions.json';
$parent = dirname($file);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Debug Permissions - www-data View</title>
    <?php include_once '../includes/head.php'; ?>
</head>
<body class="bg-light">

<div class="container mt-5">
    <h1>Permission Debug - What www-data Sees</h1>
    
    <table class="table table-bordered">
        <tr>
            <th>Item</th>
            <th>Value</th>
        </tr>
        <tr>
            <td>Current PHP user</td>
            <td><code><?php echo get_current_user() ?: 'N/A'; ?></code></td>
        </tr>
        <tr>
            <td>Current UID</td>
            <td><code><?php echo function_exists('posix_getuid') ? posix_getuid() : 'N/A'; ?></code></td>
        </tr>
        <tr>
            <td>Current user (from POSIX)</td>
            <td><code><?php 
                if (function_exists('posix_getuid')) {
                    $info = posix_getpwuid(posix_getuid());
                    echo $info['name'] ?? 'unknown';
                } else {
                    echo 'N/A';
                }
            ?></code></td>
        </tr>
        <tr>
            <th colspan="2" class="bg-light"><strong>File: <?php echo $file; ?></strong></th>
        </tr>
        <tr>
            <td>File exists</td>
            <td><?php echo file_exists($file) ? '✓ YES' : '✗ NO'; ?></td>
        </tr>
        <tr>
            <td>File readable</td>
            <td><?php echo is_readable($file) ? '✓ YES' : '✗ NO'; ?></td>
        </tr>
        <tr>
            <td>File writable</td>
            <td><?php echo is_writable($file) ? '✓ YES' : '✗ NO'; ?></td>
        </tr>
        <tr>
            <td>File perms (octal)</td>
            <td><code><?php echo substr(sprintf('%o', fileperms($file)), -3); ?></code></td>
        </tr>
        <tr>
            <th colspan="2" class="bg-light"><strong>Parent Directory: <?php echo $parent; ?></strong></th>
        </tr>
        <tr>
            <td>Parent exists</td>
            <td><?php echo file_exists($parent) ? '✓ YES' : '✗ NO'; ?></td>
        </tr>
        <tr>
            <td>Parent readable</td>
            <td><?php echo is_readable($parent) ? '✓ YES' : '✗ NO'; ?></td>
        </tr>
        <tr>
            <td>Parent writable</td>
            <td><?php echo is_writable($parent) ? '✓ YES' : '✗ NO'; ?></td>
        </tr>
        <tr>
            <td>Parent perms (octal)</td>
            <td><code><?php echo substr(sprintf('%o', fileperms($parent)), -3); ?></code></td>
        </tr>
        <tr>
            <th colspan="2" class="bg-light"><strong>Decision Logic</strong></th>
        </tr>
        <tr>
            <td>can_fix logic<br/>(parent writable OR file writable)</td>
            <td>
                <?php 
                    $can_fix = is_writable($parent) || is_writable($file);
                    echo '<code>' . (is_writable($parent) ? 'TRUE' : 'FALSE') . ' || ' . (is_writable($file) ? 'TRUE' : 'FALSE') . ' = ' . ($can_fix ? 'TRUE' : 'FALSE') . '</code>';
                ?>
            </td>
        </tr>
        <tr>
            <td><strong>Button will show?</strong></td>
            <td><strong><?php echo $can_fix ? '✓ YES - Button' : '✗ NO - Manual Instructions'; ?></strong></td>
        </tr>
    </table>

    <div class="alert alert-info mt-4">
        <h5>What to expect:</h5>
        <ul>
            <li>If "File writable" = ✗ NO and "Parent writable" = ✓ YES → Button should show</li>
            <li>If "Parent writable" = ✗ NO → Manual instructions show</li>
        </ul>
    </div>

</div>

<?php include_once '../includes/footer.php'; ?>

</body>
</html>

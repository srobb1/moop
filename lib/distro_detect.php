<?php
/**
 * Linux Distribution Detection
 *
 * Detects whether the host is Debian/Ubuntu or RHEL/CentOS/Rocky/Fedora
 * and returns the appropriate package manager command and package names.
 *
 * Used by setup-check.php and setup.php to show distro-appropriate
 * fix commands and install instructions.
 */

/**
 * Detect Linux distribution family and package manager.
 *
 * @return array{family: string, pkg_cmd: string}
 *   - family: 'debian', 'rhel', or 'unknown'
 *   - pkg_cmd: e.g. 'apt-get install -y' or 'dnf install -y'
 */
function detectDistroFamily() {
    // Check /etc/os-release first (works on all modern distros)
    if (file_exists('/etc/os-release')) {
        $content = file_get_contents('/etc/os-release');

        // ID_LIKE is the most reliable for derivatives (e.g. Rocky says "rhel centos fedora")
        if (preg_match('/^ID_LIKE=(.+)$/m', $content, $m)) {
            $like = strtolower(trim($m[1], '"\''));
            if (strpos($like, 'debian') !== false || strpos($like, 'ubuntu') !== false) {
                return ['family' => 'debian', 'pkg_cmd' => 'apt-get install -y'];
            }
            if (strpos($like, 'rhel') !== false || strpos($like, 'fedora') !== false || strpos($like, 'centos') !== false) {
                $cmd = is_executable('/usr/bin/dnf') ? 'dnf install -y' : 'yum install -y';
                return ['family' => 'rhel', 'pkg_cmd' => $cmd];
            }
        }

        // Fallback to ID for base distros (e.g. ID=fedora has no ID_LIKE)
        if (preg_match('/^ID=(.+)$/m', $content, $m)) {
            $id = strtolower(trim($m[1], '"\''));
            if (in_array($id, ['debian', 'ubuntu', 'linuxmint', 'pop'], true)) {
                return ['family' => 'debian', 'pkg_cmd' => 'apt-get install -y'];
            }
            if (in_array($id, ['rhel', 'centos', 'rocky', 'almalinux', 'fedora', 'ol'], true)) {
                $cmd = is_executable('/usr/bin/dnf') ? 'dnf install -y' : 'yum install -y';
                return ['family' => 'rhel', 'pkg_cmd' => $cmd];
            }
        }
    }

    // Fallback: check for package manager binaries
    if (is_executable('/usr/bin/apt-get') || is_executable('/usr/bin/apt')) {
        return ['family' => 'debian', 'pkg_cmd' => 'apt-get install -y'];
    }
    if (is_executable('/usr/bin/dnf')) {
        return ['family' => 'rhel', 'pkg_cmd' => 'dnf install -y'];
    }
    if (is_executable('/usr/bin/yum')) {
        return ['family' => 'rhel', 'pkg_cmd' => 'yum install -y'];
    }

    return ['family' => 'unknown', 'pkg_cmd' => '(package manager not detected)'];
}

/**
 * Map a Debian package name to its RHEL equivalent.
 *
 * @param string $debianPkg  Package name on Debian/Ubuntu
 * @param string $rhelPkg    Package name on RHEL/CentOS/Rocky
 * @param string $family     'debian', 'rhel', or 'unknown'
 * @return string
 */
function distroPackage($debianPkg, $rhelPkg, $family) {
    return ($family === 'rhel') ? $rhelPkg : $debianPkg;
}

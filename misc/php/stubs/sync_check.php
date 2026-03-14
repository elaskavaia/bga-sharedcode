<?php
/**
 * Compare method signatures between _ide_helper.php and BgaFrameworkStubs.php.
 *
 * Usage: php sync_check.php [path/to/_ide_helper.php] [path/to/BgaFrameworkStubs.php]
 *
 * Defaults:
 *   _ide_helper.php: ../../_ide_helper.php (relative to this script)
 *   BgaFrameworkStubs.php: ./BgaFrameworkStubs.php (relative to this script)
 */

$scriptDir = __DIR__;
$ideHelper = $argv[1] ?? $scriptDir . '/../../../_ide_helper.php';
$stubs = $argv[2] ?? $scriptDir . '/BgaFrameworkStubs.php';

if (!file_exists($ideHelper)) {
    fwrite(STDERR, "IDE helper not found: $ideHelper\n");
    exit(1);
}
if (!file_exists($stubs)) {
    fwrite(STDERR, "Stubs file not found: $stubs\n");
    exit(1);
}

$reflectScript = $scriptDir . '/reflect_signatures.php';
if (!file_exists($reflectScript)) {
    fwrite(STDERR, "reflect_signatures.php not found: $reflectScript\n");
    exit(1);
}

// Prepare _ide_helper.php: strip the exit() line so it can be loaded
$tmpHelper = tempnam(sys_get_temp_dir(), 'ide_helper_');
$content = file_get_contents($ideHelper);
$content = preg_replace('/^\s*exit\s*\(.*?\)\s*;/m', '// exit removed for reflection', $content);
file_put_contents($tmpHelper, $content);

// Run reflection on both files in separate processes
$helperJson = shell_exec("php " . escapeshellarg($reflectScript) . " " . escapeshellarg($tmpHelper) . " 2>&1");
$stubsJson = shell_exec("php " . escapeshellarg($reflectScript) . " " . escapeshellarg($stubs) . " 2>&1");

unlink($tmpHelper);

$helperSigs = json_decode($helperJson, true);
$stubsSigs = json_decode($stubsJson, true);

if ($helperSigs === null) {
    fwrite(STDERR, "Failed to parse IDE helper signatures:\n$helperJson\n");
    exit(1);
}
if ($stubsSigs === null) {
    fwrite(STDERR, "Failed to parse stubs signatures:\n$stubsJson\n");
    exit(1);
}

// Compare
$missingClasses = [];
$missingMethods = [];
$signatureMismatches = [];
foreach ($helperSigs as $class => $methods) {
    if (!isset($stubsSigs[$class])) {
        $missingClasses[] = $class;
        continue;
    }
    foreach ($methods as $method => $sig) {
        if (!isset($stubsSigs[$class][$method])) {
            $missingMethods[] = "$class::$method";
        } else if (normalizeSignature($sig) !== normalizeSignature($stubsSigs[$class][$method])) {
            $signatureMismatches[] = [
                'method' => "$class::$method",
                'helper' => $sig,
                'stubs' => $stubsSigs[$class][$method],
            ];
        }
    }
}

// Output report
$hasIssues = false;

if (!empty($missingClasses)) {
    $hasIssues = true;
    echo "=== MISSING CLASSES (in _ide_helper but not in stubs) ===\n";
    foreach ($missingClasses as $c) echo "  - $c\n";
    echo "\n";
}

if (!empty($missingMethods)) {
    $hasIssues = true;
    echo "=== MISSING METHODS (in _ide_helper but not in stubs) ===\n";
    foreach ($missingMethods as $m) echo "  - $m\n";
    echo "\n";
}

if (!empty($signatureMismatches)) {
    $hasIssues = true;
    echo "=== SIGNATURE MISMATCHES ===\n";
    foreach ($signatureMismatches as $mm) {
        echo "  {$mm['method']}:\n";
        echo "    helper: " . normalizeSignature($mm['helper']) . "\n";
        echo "    stubs:  " . normalizeSignature($mm['stubs']) . "\n";
    }
    echo "\n";
}


if (!$hasIssues) {
    echo "All _ide_helper signatures are present in stubs. No issues found.\n";
}

function normalizeSignature(string $sig): string {
    // Normalize whitespace and remove 'final'/'abstract' modifiers for comparison
    $sig = preg_replace('/\b(final|abstract)\b\s*/', '', $sig);
    $sig = preg_replace('/\s+/', ' ', trim($sig));
    return $sig;
}

<?php
/**
 * Run all xmetadb test suites and print a summary.
 * Usage: php tests/run_all.php
 */

$suites = [
    'test_files.php',
    'test_xmlphp.php',
    'test_sqlite3.php',
    'test_csv.php',
    'test_serialize.php',
    'test_database.php',
    'test_mysql.php',   // last: requires a live MySQL server; may be SKIPped
];

$totalPassed = 0;
$totalFailed = 0;
$skipped     = [];

echo str_repeat('=', 60) . "\n";
echo "  xmetadb test runner\n";
echo str_repeat('=', 60) . "\n\n";

foreach ($suites as $suite) {
    $file = __DIR__ . '/' . $suite;
    echo str_repeat('-', 60) . "\n";
    echo "Running: $suite\n";
    echo str_repeat('-', 60) . "\n";

    // Run each suite in a separate process to isolate static caches
    $output = [];
    $code   = 0;
    exec('php ' . escapeshellarg($file) . ' 2>&1', $output, $code);

    $out = implode("\n", $output);
    echo $out . "\n";

    if (strpos($out, 'SKIP:') !== false) {
        $skipped[] = $suite;
        continue;
    }

    // Parse "X/Y passed" from summary line
    if (preg_match('/(\d+)\/(\d+) passed/', $out, $m)) {
        $totalPassed += (int)$m[1];
        $totalFailed += (int)$m[2] - (int)$m[1];
    } elseif ($code !== 0) {
        // Suite crashed / fatal error
        $totalFailed++;
        echo "  [ERROR] Suite exited with code $code\n";
    }
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "TOTAL: {$totalPassed} passed";
if ($totalFailed > 0) {
    echo ", {$totalFailed} FAILED";
}
if (!empty($skipped)) {
    echo ", " . count($skipped) . " suite(s) skipped: " . implode(', ', $skipped);
}
echo "\n" . str_repeat('=', 60) . "\n";

exit($totalFailed > 0 ? 1 : 0);

<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php benchmarks/generate-fixtures.php <directory> <count>\n");
    exit(2);
}

$directory = rtrim($argv[1], DIRECTORY_SEPARATOR);
$count = max(1, (int) $argv[2]);
if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
    throw new RuntimeException("Unable to create {$directory}");
}

for ($i = 1; $i <= $count; $i++) {
    $file = $directory . DIRECTORY_SEPARATOR . sprintf('Fixture%05d.php', $i);
    file_put_contents($file, "<?php\nnamespace Benchmark;\nfinal class Fixture" . sprintf('%05d', $i) . " {}\n");
}

fwrite(STDOUT, sprintf("Generated %d PHP fixtures in %s\n", $count, $directory));

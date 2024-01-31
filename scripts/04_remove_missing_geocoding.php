<?php
$basePath = dirname(__DIR__);
$missingFh = fopen($basePath . '/data/missing.csv', 'r');
while ($line = fgetcsv($missingFh)) {
    $f = $basePath . '/raw/geocoding/' . $line[3] . '.json';
    if (file_exists($f)) {
        unlink($f);
    }
}

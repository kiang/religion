<?php

$basePath = dirname(__DIR__);
$config = require $basePath . '/config.php';
$conn = new PDO('pgsql:host=localhost;dbname=' . $config['db'], $config['user'], $config['password']);

$pool = [];
$oFh = fopen($basePath . "/data/cunli_lnglat.csv", 'w');
fputcsv($oFh, ['villcode', '經度', '緯度']);
foreach (glob($basePath . '/data/poi/*.json') as $jsonFile) {
    $json = json_decode(file_get_contents($jsonFile), true);
    foreach ($json['features'] as $f) {
        $lnglat = "{$f['properties']['WGS84X']} {$f['properties']['WGS84Y']}";
        if (empty($f['properties']['WGS84X']) || isset($pool[$lnglat])) {
            continue;
        }
        $pool[$lnglat] = true;
        $sql = "SELECT villcode FROM {$config['table']} AS cunli WHERE ST_Intersects('SRID=4326;POINT({$lnglat})'::geometry, cunli.geom)";
        $rs = $conn->query($sql);
        if ($rs) {
            $row = $rs->fetch(PDO::FETCH_ASSOC);
        }
        if (!empty($row['villcode'])) {
            fputcsv($oFh, [$row['villcode'], $f['properties']['WGS84X'], $f['properties']['WGS84Y']]);
        }
    }
}

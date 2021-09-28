<?php
$basePath = dirname(__DIR__);
$reports = [
    '寺廟' => 'https://religion.moi.gov.tw/Report/temple.xml',
    '法人教會' => 'https://religion.moi.gov.tw/Report/church.xml',
    '宗祠基金會' => 'https://religion.moi.gov.tw/Report/Ancestral-F.xml',
    '宗祠' => 'https://religion.moi.gov.tw/Report/Ancestral.xml',
    '基金會' => 'https://religion.moi.gov.tw/Report/Foundation.xml',
];

foreach($reports AS $report) {
    $p = pathinfo($report);
    $targetFile = $basePath . '/raw/' . $p['basename'];
    file_put_contents($targetFile, file_get_contents($report));
}
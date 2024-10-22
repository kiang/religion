<?php
$basePath = dirname(__DIR__);
$reports = [
    '寺廟' => 'https://religion.moi.gov.tw/Report/temple.xml',
    '法人教會' => 'https://religion.moi.gov.tw/Report/church.xml',
    '宗祠基金會' => 'https://religion.moi.gov.tw/Report/Ancestral-F.xml',
    '宗祠' => 'https://religion.moi.gov.tw/Report/Ancestral.xml',
    '基金會' => 'https://religion.moi.gov.tw/Report/Foundation.xml',
];

$currentKey = -1;
$currentTag = '';
$pool = [];
$tags = [
    '編號' => true,
    '寺廟名稱' => true,
    '基金會名稱' => true,
    '教會名稱' => true,
    '宗祠名稱' => true,
    '宗祠基金會名稱' => true,
    '主祀神祇' => true,
    '行政區' => true,
    '地址' => true,
    '教別' => true,
    '登記別' => true,
    '建別' => true,
    '組織型態' => true,
    '電話' => true,
    '負責人' => true,
    '其他' => true,
    'WGS84X' => true,
    'WGS84Y' => true,
];
function startElement($parser, $name, $attrs)
{
    global $currentTag;
    $currentTag = $name;
}

function endElement($parser, $name) {}

function characterData($parser, $data)
{
    global $currentKey, $currentTag, $pool, $tags;
    $data = trim($data);
    if (!empty($data) && isset($tags[$currentTag])) {
        $inTypeTag = false;
        if (false !== strpos($currentTag, '名稱')) {
            ++$currentKey;
            $inTypeTag = true;
        }
        if (!isset($pool[$currentKey])) {
            $pool[$currentKey] = [];
        }
        if (!$inTypeTag) {
            $pool[$currentKey][$currentTag] = $data;
        } else {
            $pool[$currentKey]['類型'] = str_replace('名稱', '', $currentTag);
            $pool[$currentKey]['名稱'] = $data;
        }
    }
}

foreach ($reports as $report) {
    $p = pathinfo($report);
    $targetFile = $basePath . '/raw/' . $p['basename'];
    $c = file_get_contents($report);
    if (empty($c)) {
        continue;
    }
    file_put_contents($targetFile, $c);
    $xml_parser = xml_parser_create();
    xml_set_element_handler($xml_parser, "startElement", "endElement");
    xml_set_character_data_handler($xml_parser, "characterData");
    $fp = fopen($targetFile, "r");
    while ($data = fread($fp, 4096)) {
        if (!xml_parse($xml_parser, $data, feof($fp))) {
            die(sprintf(
                "XML error: %s at line %d",
                xml_error_string(xml_get_error_code($xml_parser)),
                xml_get_current_line_number($xml_parser)
            ));
        }
    }
    xml_parser_free($xml_parser);
    fclose($fp);
}
$dataPath = $basePath . '/data/poi';
if (!file_exists($dataPath)) {
    mkdir($dataPath, 0777, true);
}
$oFh = [];
$idPool = [];
$idFile = $basePath . '/data/id.csv';
if (file_exists($idFile)) {
    $fh = fopen($idFile, 'r');
    while ($line = fgetcsv($fh, 2048)) {
        $idPool[$line[0]] = $line[1];
    }
    fclose($fh);
}
$missingFh = fopen($basePath . '/data/missing.csv', 'w');
fputcsv($missingFh, ['type', 'name', 'city', 'address', 'x', 'y']);
$addressReplace = [];
foreach ($pool as $item) {
    if ($item['類型'] === '寺廟') {
        $idKey = '寺廟' . $item['編號'];
    } else {
        $idKey = $item['類型'] . $item['行政區'] . $item['名稱'];
    }
    if (!isset($idPool[$idKey])) {
        $idPool[$idKey] = file_get_contents('/proc/sys/kernel/random/uuid');
    }

    if (!empty($item['WGS84X']) && $item['WGS84X'] < 123 && $item['WGS84X'] > 118 && $item['WGS84Y'] > 21 && $item['WGS84Y'] < 27) {
        $item['uuid'] = $idPool[$idKey];
        if (!isset($oFh[$item['行政區']])) {
            $oFh[$item['行政區']] = [
                'type' => 'FeatureCollection',
                'features' => [],
            ];
        }
        $oFh[$item['行政區']]['features'][] = [
            'type' => 'Feature',
            'properties' => $item,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [
                    floatval($item['WGS84X']),
                    floatval($item['WGS84Y']),
                ],
            ],
        ];
    } else if (isset($item['地址'])) {
        fputcsv($missingFh, [$item['類型'], $item['名稱'], $item['行政區'], $item['地址'], isset($item['WGS84X']) ? $item['WGS84X'] : 0.0, isset($item['WGS84Y']) ? $item['WGS84Y'] : 0.0]);
    }
}

foreach ($oFh as $city => $fc) {
    file_put_contents($dataPath . '/' . $city . '.json', json_encode($fc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$fh = fopen($idFile, 'w');
foreach ($idPool as $k => $v) {
    fputcsv($fh, [$k, $v]);
}
fclose($fh);

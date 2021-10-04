<?php
$basePath = dirname(__DIR__);
$config = require $basePath . '/config.php';
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
    '寺廟名稱' => true,
    '基金會名稱' => true,
    '教會名稱' => true,
    '宗祠名稱' => true,
    '宗祠基金會名稱' => true,
    '主祀神祇' => true,
    '行政區' => true,
    '地址' => true,
    '教別' => true,
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

function endElement($parser, $name)
{
}

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
    file_put_contents($targetFile, file_get_contents($report));
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
$geocodingPath = $basePath . '/raw/geocoding';
if (!file_exists($geocodingPath)) {
    mkdir($geocodingPath, 0777, true);
}
$dataPath = $basePath . '/data/poi';
if (!file_exists($dataPath)) {
    mkdir($dataPath, 0777, true);
}
$oFh = [];
$missingFh = fopen($basePath . '/data/missing.csv', 'w');
fputcsv($missingFh, ['type', 'name', 'city', 'address', 'x', 'y']);
$addressReplace = [];
foreach ($pool as $item) {
    if (empty($item['WGS84X']) && isset($item['地址'])) {
        $addressToFind = $item['地址'];
        $pos = strpos($addressToFind, '號');
        if(false !== $pos) {
            $addressToFind = substr($addressToFind, 0, $pos) . '號';
        }
        $geocodingFile = $geocodingPath . '/' . $addressToFind . '.json';
        if (!file_exists($geocodingFile)) {
            $apiUrl = $config['tgos']['url'] . '?' . http_build_query([
                'oAPPId' => $config['tgos']['APPID'], //應用程式識別碼(APPId)
                'oAPIKey' => $config['tgos']['APIKey'], // 應用程式介接驗證碼(APIKey)
                'oAddress' => $addressToFind, //所要查詢的門牌位置
                'oSRS' => 'EPSG:4326', //回傳的坐標系統
                'oFuzzyType' => '2', //模糊比對的代碼
                'oResultDataType' => 'JSON', //回傳的資料格式
                'oFuzzyBuffer' => '0', //模糊比對回傳門牌號的許可誤差範圍
                'oIsOnlyFullMatch' => 'false', //是否只進行完全比對
                'oIsLockCounty' => 'true', //是否鎖定縣市
                'oIsLockTown' => 'false', //是否鎖定鄉鎮市區
                'oIsLockVillage' => 'false', //是否鎖定村里
                'oIsLockRoadSection' => 'false', //是否鎖定路段
                'oIsLockLane' => 'false', //是否鎖定巷
                'oIsLockAlley' => 'false', //是否鎖定弄
                'oIsLockArea' => 'false', //是否鎖定地區
                'oIsSameNumber_SubNumber' => 'true', //號之、之號是否視為相同
                'oCanIgnoreVillage' => 'true', //找不時是否可忽略村里
                'oCanIgnoreNeighborhood' => 'true', //找不時是否可忽略鄰
                'oReturnMaxCount' => '0', //如為多筆時，限制回傳最大筆數
            ]);
            $content = file_get_contents($apiUrl);
            $pos = strpos($content, '{');
            $posEnd = strrpos($content, '}') + 1;
            $resultline = substr($content, $pos, $posEnd - $pos);
            if (strlen($resultline) > 10) {
                file_put_contents($geocodingFile, substr($content, $pos, $posEnd - $pos));
            }
        }
        if (file_exists($geocodingFile)) {
            $json = json_decode(file_get_contents($geocodingFile), true);
            if (!empty($json['AddressList'][0]['X'])) {
                $item['WGS84X'] = $json['AddressList'][0]['X'];
                $item['WGS84Y'] = $json['AddressList'][0]['Y'];
            }
        }
    }

    if (!empty($item['WGS84X']) && $item['WGS84X'] < 123 && $item['WGS84X'] > 118 && $item['WGS84Y'] > 21 && $item['WGS84Y'] < 27) {
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

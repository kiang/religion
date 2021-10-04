<?php
$basePath = dirname(__DIR__);

$currentKey = -1;
$currentTag = '';
$pool = [];
$tags = [
    '寺廟名稱' => true,
    '基金會名稱' => true,
    '教會名稱' => true,
    '宗祠名稱' => true,
    '宗祠基金會名稱' => true,
    '行政區' => true,
    '地址' => true,
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

foreach(glob($basePath . '/raw/*.xml') AS $xmlFile) {
    $xml_parser = xml_parser_create();
    xml_set_element_handler($xml_parser, "startElement", "endElement");
    xml_set_character_data_handler($xml_parser, "characterData");
    $fp = fopen($xmlFile, "r");
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

$count = [];
$header = ['行政區' => '行政區'];
foreach($pool AS $item) {
    if(!isset($item['行政區'])) {
        continue;
    }
    if(!isset($count[$item['行政區']])) {
        $count[$item['行政區']] = [];
    }
    if(!isset($count[$item['行政區']][$item['類型']])) {
        $count[$item['行政區']][$item['類型']] = 0;
    }
    if(!isset($header[$item['類型']])) {
        $header[$item['類型']] = $item['類型'];
    }
    ++$count[$item['行政區']][$item['類型']];
}

$fh = fopen($basePath . '/data/count.csv', 'w');
fputcsv($fh, $header);
array_shift($header);
foreach($count AS $city => $data) {
    $line = [$city];
    foreach($header AS $key) {
        if(!isset($data[$key])) {
            $data[$key] = 0;
        }
        $line[] = $data[$key];
    }
    fputcsv($fh, $line);
}
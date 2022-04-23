<?php
$basePath = dirname(__DIR__);

$currentKey = -1;
$currentTag = '';
$pool = [];
$tags = [
    '主祀神祇' => true,
];

$count = [];
$countTotal = 0;

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
    global $currentKey, $currentTag, $pool, $tags, $count, $countTotal;
    $data = trim($data);
    if (!empty($data) && isset($tags[$currentTag])) {
        if(!isset($count[$data])) {
            $count[$data] = 0;
        }
        ++$count[$data];
        ++$countTotal;
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

arsort($count);
echo $countTotal;
print_r($count);
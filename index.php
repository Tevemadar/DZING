<?php

ob_start("ob_gzhandler");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: *");
if (filter_input(INPUT_SERVER, "REQUEST_METHOD") === "OPTIONS")
    die();

$url = filter_input(INPUT_SERVER, "QUERY_STRING");
$url = substr($url, 0, 6) . "/" . substr($url, 6);
$dzi_pos = strrpos($url, ".dzi");
$dziless_url = substr($url, 0, $dzi_pos);
$dzi_xml = simplexml_load_file($dziless_url . ".dzi");
$TileSize = intval($dzi_xml["TileSize"]);
$Overlap = intval($dzi_xml["Overlap"]);
$Format = (string) $dzi_xml["Format"];
$Width = intval($dzi_xml->Size["Width"]);
$Height = intval($dzi_xml->Size["Height"]);
if (substr($url, strlen($url) - 5) === "/info") {
    $w = $Width;
    $h = $Height;
    $level = 0;
    while ($w > 1 || $h > 1) {
        $level++;
        $w = ($w + 1) >> 1;
        $h = ($h + 1) >> 1;
    }
    $scales = [];
    $resolution = 1;
    while ($Width > 1 || $Height > 1) {
        $scales[] = [
            "key" => (string) $level,
            "size" => [$Width, $Height, 1],
            "resolution" => [$resolution, $resolution, $resolution],
            "chunk_sizes" => [[256, 256, 1]],
            "encoding" => "raw"
        ];
        $level--;
        $resolution <<= 1;
        $Width = ($Width + 1) >> 1;
        $Height = ($Height + 1) >> 1;
    }
    $json = [
        "@type" => "neuroglancer_multiscale_volume",
        "type" => "image",
        "data_type" => "uint8",
        "num_channels" => 3,
        "scales" => $scales
    ];
    echo json_encode($json);
} else {
    $levelname = substr($url, $dzi_pos + 5);
    list($level, $xfrom, $xto, $yfrom, $yto, $zfrom, $zto) = preg_split("/[\/_-]/", $levelname);
    $xfrom = intval($xfrom);
    $x = $xfrom / $TileSize;
    $xto = intval($xto) - $xfrom;
    $xfrom = 0;
    if ($x !== 0) {
        $xto++;
        $xfrom = 1;
    }
    $yfrom = intval($yfrom);
    $y = $yfrom / $TileSize;
    $yto = intval($yto) - $yfrom;
    $yfrom = 0;
    if ($y !== 0) {
        $yto++;
        $yfrom = 1;
    }
    $filename = $dziless_url . "_files/{$level}/{$x}_{$y}.{$Format}";
    if ($Format === "png")
        $image = imagecreatefrompng($filename);
    elseif ($Format === "jpg")
        $image = imagecreatefromjpeg($filename);
    for ($y = $yfrom; $y < $yto; $y++)
        for ($x = $xfrom; $x < $xto; $x++)
            echo chr(imagecolorat($image, $x, $y));
    for ($y = $yfrom; $y < $yto; $y++)
        for ($x = $xfrom; $x < $xto; $x++)
            echo chr(imagecolorat($image, $x, $y) >> 8);
    for ($y = $yfrom; $y < $yto; $y++)
        for ($x = $xfrom; $x < $xto; $x++)
            echo chr(imagecolorat($image, $x, $y) >> 16);
}
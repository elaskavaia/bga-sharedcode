<?php

/* Scrab bgg info about game to create gameinfos.php
 * 
 * usage:
 * bgg_scrabber.php <project_path> <bgg_id>
 * 
 * example:
 * bgg_scrabber.php /home/alena/games/khronos 25674
 */

function varsubquoted($key, $value, $incontents) {
    $incontents = preg_replace("/'$key'\s*=>.*/", "'$key' => '$value',", $incontents);
    return $incontents;
}
function varsubdirect($key, $value, $incontents) {
    $incontents = preg_replace("/'$key'\s*=>.*/", "'$key' => $value,", $incontents);
    return $incontents;
}
function totranslate($x) {
	return $x; // stub
}

// MAIN
$indir = $argv [1];
$infile = "$indir/gameinfos.inc.php";

if (isset($argv [2])) {
    $bgg_id = $argv [2];
} else {
    require_once $infile;
    $bgg_id = $gameinfos['bgg_id'];
}
$outfile = $infile . ".out";
//$out = fopen($outfile, "w") or die("Unable to open file! $outfile");
$incontents = file_get_contents($infile) or die("Cannot open $infile");
$xmldata = file_get_contents("https://www.boardgamegeek.com/xmlapi2/thing?id=$bgg_id") or die("Cannot get bgg content");;
//$xmldata = file_get_contents("bgg_example.xml");
//print($xmldata);
$xml = simplexml_load_string($xmldata) or die("Error: Cannot create xml object for $bgg_id");
$item = $xml->item;
//print_r($item);


$links = $item->link;
$links_arr = array ();
foreach ( $links as $i => $link ) {
    $atts = $link->attributes();
    $type = ( string ) $atts->type;
    if ($type) {
        if (! array_key_exists($type, $links_arr)) {
            $links_arr [$type] = array ();
        }
        $value = (String)$atts -> value;
        $id = (String)$atts -> id;
        $links_arr [$type] [$id] = $value;
    }
}
print_r($links_arr);

$name = (String) $item->name [0]->attributes() ['value'];
$incontents = varsubquoted("game_name", $name, $incontents);
$designer = implode(",", $links_arr['boardgamedesigner']);
$incontents = varsubquoted("designer", $designer, $incontents);
$incontents = varsubquoted("artist", implode(",", $links_arr['boardgameartist']), $incontents);
$pubs =$links_arr['boardgamepublisher'];
foreach($pubs as $id => $name) {
    $incontents = varsubquoted("publisher", $name, $incontents);
    $incontents = varsubdirect("publisher_bgg_id", $id, $incontents);
    $publisher_id = $id;
    break;
}
$incontents = varsubdirect("bgg_id", $bgg_id, $incontents);
$minplayers = (String) $item->minplayers->attributes() ['value'];
$maxplayers = (String) $item->maxplayers->attributes() ['value'];
$plarr = array();
for ($i=(int)$minplayers;$i<=$maxplayers;$i++) {
    $plarr[]=$i;
}
$year = (String) $item->yearpublished->attributes() ['value'];
$incontents = varsubdirect("year", $year, $incontents);
$incontents = varsubdirect("players", "array(". (implode(",",$plarr)).")", $incontents);
$incontents = varsubdirect("estimated_duration", (String) $item->minplaytime->attributes() ['value'], $incontents);

file_put_contents($outfile, $incontents) or die;
rename($outfile, $infile);




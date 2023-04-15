<?php
/**
 * This script generates (updates) material.inc.php from CSV like file (pipe separated) defining game element problems.
 * File material.inc.php must have special mark up to identify insertion points (see example below). 
 * You need to install php cli to run it.
 * 
 * usage: php genmap.php material.csv [<path to material.inc.php>]
 * 
 * Sample input file
 * 
 id|type|name|tooltip|tooltip_action|php
 train|token|Locomotive|The number of a locomotive indicates the number of spaces it can reach on a railroad:
 factory|token|Factory|
 slot_action_1|slot_action|2 Black Track Advancements|This action gives you two advancements of black track. You cannot use this action if you cannot complete all advancements.|'o'=>"1,0,1,bb"
 *
 * Sample material.inc.php BEFORE change

 $this->token_types = array(
 // --- gen php begin ---

 // --- gen php end --- 
 );

 * After running script

 $this->token_types = array(
 // --- gen php begin ---
 'train' => array(
 'type' => 'token',
 'name' => clienttranslate("Locomotive"),
 'tooltip' => clienttranslate("The number of a locomotive indicates the number of spaces it can reach on a railroad:"),
 ),
 'factory' => array(
 'type' => 'token',
 'name' => clienttranslate("Factory"),
 ),
 'slot_action_1' => array(
 'type' => 'slot_action',
 'name' => clienttranslate("2 Black Track Advancements"),
 'tooltip' => clienttranslate("This action gives you two advancements of black track. You cannot use this action if you cannot complete all advancements."),
 'o'=>"1,0,1,bb",
 ),       
 // --- gen php end --- 
 );

 * If file is not called material.csv, if called foo.csv use  // --- gen php begin foo ---
 * This way can generate multiple sections

 Special columns: 
 id - the id of object (string or number)
 variant - special field appended to string as as @xxx  - used to have versions of material file depending on game variants
 -con - way to use php constant to alias an numeric id, defines are currently not injected but dumped on console
 name, tooltip, tooltip_action - default fields which are translatable

 #set tr=color - using this directive make 'color' translatable field also
 #set sep=, - change separator
 #set sub=/ - use this character or string to replace default separator, i.e. a/b will be replaced to a|b
 #set noquotes=field - do not quotes for field when outputing
 */
$g_field_names = null;
$g_field_extra = [ ];
$g_index = 1;
$g_trans = [ 'name','tooltip','tooltip_action' ];
$g_separator = '|';
$g_separator_sub = '';
$g_noquotes = [ ];

function handle_header($fields) {
    global $g_field_names;
    global $g_field_extra;
    if ($fields [0] == 'element_id') {
        // old style header
        $g_field_names = [ 'id','type','name','tooltip','tooltip_action' ];
    } else {
        $g_field_names = $fields;
    }
    return true;
}

function get_field($key, $fields, $default = null) {
    global $g_field_extra;
    if ( !is_array($fields))
        throw new Exception("fields should be an array");
    return $fields [$key] ?? $g_field_extra [$key] ?? $default;
}

function isTranslatable($key) {
    global $g_trans;
    if (array_search($key, $g_trans) !== false) {
        return true;
    }
    return false;
}

function genbody($incsv) {
    global $out;
    $ins = fopen($incsv, "r") or die("Unable to open file! $ins");
    $comment = "";
    global $g_field_names, $g_index, $g_separator, $g_separator_sub, $g_trans, $g_noquotes;
    global $g_field_extra;
    $limit = false;
    $matches = [ ];
    while ( ($line = fgets($ins)) !== false ) {
        $line = trim($line);
        if (empty($line))
            continue;
        if (startsWith($line, '#')) {
            //special comment
            if (preg_match("/#set  *(?P<name>\w+)=(?P<value>.*)/", $line, $matches)) {
                $key = $matches ['name'];
                $value = trim($matches ['value']);
                switch ($key) {
                    case '_sep' :
                        $g_separator = $value; #field separator
                        break;
                    case '_sub' :
                        // use another character insped of sepator if separttor is needed, 
                        // i.e. if sepator is | and you need this in string, you can define / as replacement so / will be replace to | in the string 
                        $g_separator_sub = $value;
                        break;
                    case '_tr' :
                        // field with this name will be translated
                        $g_trans [] = $value;
                        break;
                    case '_noquotes' :
                        // field with this name will be generated without quotes (i.e. direct constant or php expresson)
                        $g_noquotes [] = $value;
                        break;
                    default :
                        if (startsWith($key, "_")) {
                            print("Error: unknown key $key for #set directive\n");
                            exit(2);
                        } else {
                            $g_field_extra [$key] = $value;
                        }
                }
                continue;
            }
            $comment .= "// $line\n";
            continue;
        }
        if ($g_field_names == null) {
            $raw_fields = explode($g_separator, $line);
            $limit = count($raw_fields);
            if (handle_header($raw_fields))
                continue;
        }
        $raw_fields = explode($g_separator, $line, $limit);
        $fields = [ ];
        $f = 0;
        foreach ( $g_field_names as $key ) {
            if (count($raw_fields) >= $f + 1)
                $fields [$key] = $raw_fields [$f];
            else
                $fields [$key] = null;
            $f++;
        }
        $id = get_field('id', $fields);
        if (!$id) {
            echo "Error: missing required id column in input file\n";
            exit(2);
        }
        $ftype = get_field('type', $fields, '');
        $ftype = varsub($ftype, $fields);
        $extra = '';
        // old way of getting extra fields
        $pos = strpos($ftype, '{');
        if ($pos !== false) {
            $extra = substr($ftype, $pos + 1);
            $ftype = trim(substr($ftype, 0, $pos));
            $fields ['type'] = $ftype;
            $pos = strrpos($extra, '}');
            if ($pos !== false) {
                $extra = substr($extra, 0, $pos);
                $fields ['php'] = $extra;
            }
        }
        if ($comment) {
            fwrite($out, "$comment");
            $comment = "";
        }
        $id = varsub($id, $fields);
        $fullid = $id;
        $variant = get_field('variant', $fields);
        if ($variant)
            $fullid = "${id}@" . $fields ['variant'];
        $con = get_field('-con', $fields);
        $concomment = " //";
        if ($con) {
            print("define(\"$con\", $id);\n");
            $concomment = " // $con";
        }
        if (is_numeric($fullid) || array_search('id', $g_noquotes) !== false)
            fwrite($out, " $fullid");
        else
            fwrite($out, " '$fullid'");
        fwrite($out, " => [ ${concomment}\n");
        $map = array_merge($g_field_extra, $fields);
        foreach ( $fields as $key => $value ) {
            if ($value === null && array_key_exists($key, $g_field_extra)) {
                $map [$key] = $g_field_extra [$key];
            }
        }

        foreach ( $map as $key => $value ) {
            if (startsWith($key, "-"))
                continue;
            if ($key == 'id')
                continue;
            if ($key == 'variant')
                continue;
            if ( !$value && $value !== '0')
                continue;
            $value = str_replace("’", "'", $value);
            $value = str_replace("‘", "'", $value);
            $value = varsub($value, $fields);
            if ($g_separator_sub)
                $value = str_replace($g_separator_sub, $g_separator, $value);
            if ($key == 'php') {
                fwrite($out, "  $value,\n");
                continue;
            }
            if (isTranslatable($key)) {
                $value = trim($value);
                if ($value)
                    $exp = "clienttranslate(\"$value\")";
                else
                    continue;
            } else if (is_numeric($value) || array_search($key, $g_noquotes) !== false) {
                $exp = $value;
            } else {
                $exp = "'$value'";
            }
            fwrite($out, "  '$key' => $exp,\n");
        }
        fwrite($out, "],\n");
    }
}

function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}

function endsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($needle)) !== false;
}

/**
 * subsitute variables like {VARIABLE} using keymap
 */
function varsub($line, $keymap) {
    if ($line === null)
        throw new Exception("varsub: line cannot be null");
    global $g_index;
    global $g_field_extra;
    $line = preg_replace("/\{GINDEX\}/", $g_index, $line);
    if (strpos($line, "{") !== false) {
        $map = array_merge($g_field_extra, $keymap);
        foreach ( $map as $key => $value ) {
            if (strpos($line, "{$key}") !== false) {
                $line = preg_replace("/\{$key\}/", $value, $line);
            }
        }
    }
    return $line;
}
// MAIN
$incsv = $argv [1];
$basename = basename($incsv, '.csv');
if ($basename == 'material')
    $module = '';
else
    $module = "$basename ";
echo "Reading $incsv\n";
if (isset($argv [2])) {
    $infile = $argv [2];
} else {
    $infile = dirname("$incsv") . "/../material.inc.php";
}
echo "Writing $infile\n";
$in = fopen($infile, "r") or die("Unable to open file! $in");
$markup_begin = "--- gen php begin $module---";
$markup_end = "--- gen php end $module---";
$outfile = $infile . ".out";
$out = fopen($outfile, "w") or die("Unable to open file! $outfile");
//$out = fopen("php://stdout", "w") or die("Unable to open file! $outfile");
// find markup line
$found = false;
while ( ($line = fgets($in)) !== false ) {
    fwrite($out, $line);
    $line = trim($line);
    $k = strpos($line, $markup_begin);
    if ($k !== false) {
        $found = true;
        break;
    }
}
if ( !$found) {
    echo "Error: missing markup $markup_begin in $infile\n";
    unlink($outfile);
    exit(2);
}
// generate data
genbody($incsv);
// skip all lines until end markup
while ( ($line = fgets($in)) !== false ) {
    if (strpos($line, $markup_end) !== false) {
        fwrite($out, $line);
        break;
    }
}
// write rest of file
while ( ($line = fgets($in)) !== false ) {
    fwrite($out, $line);
}
fclose($out);
fclose($in);
rename($outfile, $infile);
echo "Generated => $infile\n";
?>
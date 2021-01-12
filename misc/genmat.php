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
id|type|name|tooltip|tooltip_action
train|token|Locomotive|The number of a locomotive indicates the number of spaces it can reach on a railroad:
factory|token|Factory|
slot_action_1|slot_action {'o'=>"1,0,1,bb"}|2 Black Track Advancements|This action gives you two advancements of black track. You cannot use this action if you cannot complete all advancements.
 *
 * Sample material.inc.php BEFORE change
 * If file is not called material.csv, ut foo.csv use  // --- gen php begin foo ---
 * This way can generate multiple sections
 

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

 */

$g_field_names = null;

$g_index = 1;

$g_trans = [ 'name','tooltip','tooptip_action' ];

function handle_header($fields) {
    global $g_field_names;
    if ($fields [0] == 'element_id') {
        // old style header
        $g_field_names = [ 'id','type','name','tooltip','tooptip_action' ];
    } else {
        $g_field_names = $fields;
        if (array_search('id',$g_field_names)===false) {
            echo "Error: missing required id column in input file\n";
            exit(2);
        }
        if (array_search('name',$g_field_names)===false) {
            echo "Error: missing required name column in input file\n";
            exit(2);
        }
    }
    return true;
}

function get(&$var, $default=null) {
    return isset($var) ? $var : $default;
}

function isTranslatable($key) {
    global $g_trans;
    if (array_search($key, $g_trans)!==false) {
        return true;
    }
    return false;
}

function genbody($incsv) {
    global $out;
    $ins = fopen($incsv, "r") or die("Unable to open file! $ins");
    $comment = "";
    global $g_field_names, $g_index;
    

    while ( ($line = fgets($ins)) !== false ) {
        $line=trim($line);
        if (empty($line)) continue;
        //special comment
        #$matches = [ ];
        #if (preg_match("#default  *(?P<name>\w+)=(?P<value>.*)", $line, $matches)) {
        #    $g_field_defaults [$matches ['name']] = $matches ['value'];
        #    continue;
        #}
        
        if (startsWith($line, '#')) {
            $comment.="// $line\n";
            continue;
        }
        $raw_fields = explode('|', $line);
        if ($g_field_names==null) {
            if (handle_header($raw_fields))
                continue;
        }
        $fields=[];
        $f=0;
        foreach ($g_field_names as $key) {
            if (count($raw_fields) >= $f+1)
                $fields[$key] = $raw_fields [$f];
            else
                $fields[$key]= null;
            $f++;
        }

        $id=$fields['id'];
        $ftype = get($fields ['type'], '');
        $ftype=varsub($ftype,$fields);
        $extra = '';
        // old way of getting extra fields
        $pos = strpos($ftype, '{');
        if ($pos !== false) {
            $extra = substr($ftype, $pos + 1);
            $ftype = trim(substr($ftype, 0, $pos));
            $fields['type']=$ftype;
            $pos = strpos($extra, '}');
            if ($pos !== false) {
                $extra = substr($extra, 0, $pos);
                $fields ['php']=$extra;
            }
        }

        if ($comment) {
            fwrite($out, "$comment");
            $comment="";
        }
        $id=varsub($id,$fields);

        
    
        fwrite($out, "'$id' => [\n");

        foreach ( $fields as $key => $value ) {
            if ($key=='id') continue;
            if (startsWith($key, "-")) continue;
            if (!$value && $value!=='0') continue;
            $value=varsub($value,$fields);
            if ($key=='php') {
                $value=str_replace("’", "'", $value);
               // $value=str_replace(",", ",\n  ", $value);// XXX remove
               
                fwrite($out, "  $value,\n");
                continue;
            }
         
            if (isTranslatable($key)) {
                $value=trim($value);
                if ($value)
                   $exp = "clienttranslate(\"$value\")";
                else 
                   continue;
            } else if (is_numeric($value)) {
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
    return $needle === "" || strrpos($haystack, $needle, - strlen($haystack)) !== false;
}

function endsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, - strlen($needle)) !== false;
}

/** subsitute variables like {VARIABLE} using keymap*/
function varsub($line, $keymap) {
    if ($line === null)
        throw new Exception("varsub: line cannot be null");
    global $g_index;
    $line = preg_replace("/\{GINDEX\}/", $g_index, $line);
    if (strpos($line, "{") !== false) {
        foreach ( $keymap as $key => $value ) {
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
        $found=true;
        break;
    }
}
if (!$found) {
    echo "Error: missing markup $markup_begin in $infile\n";
    unlink($outfile);
    exit(2);
}
// generate data
genbody($incsv);
// skip all lines until end markup
while ( ($line = fgets($in)) !== false ) {
    if (strpos($line, $markup_end ) !== false) {
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
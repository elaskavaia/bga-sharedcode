<?php

function isKeyBegin($line, $key) {
    if (strpos($line, "--- $key begin ---") !== false || strpos($line, "-- BEGIN $key --") !== false) {
        return true;
    }
    return false;
}

function isKeyEnd($line, $key) {
    if (strpos($line, "--- $key end ---") !== false || strpos($line, "-- END $key --") !== false) {
        return true;
    }
    return false;
}

function inject($infile, $key, $body) {
    $outfile = $infile . ".out";
    $out = fopen($outfile, "w") or die("Unable to open file! $outfile");
    $in = fopen($infile, "r") or die("Unable to open file! $in");
    while ( ($line = fgets($in)) !== false ) {
        fwrite($out, $line);
        if (isKeyBegin($line, $key)) {
            fwrite($out, $body);
            fwrite($out, "\n");
            break;
        }
    }
    while ( ($line = fgets($in)) !== false ) {
        if (isKeyEnd($line, $key)) {
            fwrite($out, $line);
            break;
        }
    }
    while ( ($line = fgets($in)) !== false ) {
        fwrite($out, $line);
    }
    fclose($out);
    fclose($in);
    rename($outfile, $infile);
    echo "Generated => $infile\n";
}

function inject_functions($infile, $key, $func_arr) {
    $outfile = $infile . ".out";
    $out = fopen($outfile, "w") or die("Unable to open file! $outfile");
    $in = fopen($infile, "r") or die("Unable to open file! $in");
    $genon = false;
    while ( ($line = fgets($in)) !== false ) {
        fwrite($out, $line);
        if (isKeyBegin($line, $key)) {
            $genon = true;
            break;
        }
    }
    if ($genon) {
        while ( ($line = fgets($in)) !== false ) {
            if (isKeyEnd($line, $key)) {
                foreach ( $func_arr as $name => $text ) {
                    echo "Generated func => $infile '$name'\n";
                    fwrite($out, $text . "\n");
                }
                fwrite($out, $line);
                $genon = false;
                break;
            }
            if (strpos($line, "function ") !== false) {
                $name = trim($line);
                $name = preg_replace("/^ *function (.*) *\(.*$/", "\\1", $name);
                unset($func_arr [$name]);
                echo "-> skip '$name'\n";
            }
            fwrite($out, $line);
        }
        while ( ($line = fgets($in)) !== false ) {
            fwrite($out, $line);
        }
    }
    fclose($out);
    fclose($in);
    rename($outfile, $infile);
}

function filevarsub($infile, $outfile, $keymap, $page) {
    $lines = file($infile, FILE_IGNORE_NEW_LINES);
    if ($lines === false)
        die("Unable to open file! $infile");
    $outlines = array ();
    $stackstates = array ();
    $line_num = 0;
    $total_lines = count($lines);
    while ( $line_num < $total_lines ) {
        $line = $lines [$line_num];
        if (preg_match("/-- BEGIN (?P<name>\w+) --/", $line, $matches)) {
            $block = $matches ['name'];
            if (isset($page->blocks [$block])) {
                $args = $page->blocks [$block];
          
            } else {
                echo "$infile:$line_num:Warning: No rule defined for block $block\n";
                $args = array (
                        array () 
                );
            }
            $state = array (
                    'block' => $block,
                    'index' => 0,
                    'prevmap' => $keymap,
                    'linenum' => $line_num,
                    'args' => $args 
            );
            $curmap = array_shift($state ['args']);
            $stackstates [] = $state;
            $keymap = array_merge($keymap, $curmap);
        }
        if (preg_match("/-- END (?P<name>\w+) --/", $line, $matches)) {
            $block = $matches ['name'];
            $state = array_pop($stackstates);
            $keymap = $state ['prevmap'];
            $curmap = array_shift($state ['args']);
           // print_r($curmap);
            if ($curmap) {
                $keymap = array_merge($keymap, $curmap);
                $line_num = $state ['linenum'];
                $stackstates [] = $state;
            }
        }
        $outline = varsub($line, $keymap);
        $outlines [] = $outline;
        $line_num ++;
    }
    file_put_contents($outfile, implode(PHP_EOL, $outlines)) or die("Unable to open file! $outfile");
    echo "Generated => $outfile\n";
}

function varsub($line, $keymap) {
    if (strpos($line, "{") !== false) {
        foreach ( $keymap as $key => $value ) {
            if (strpos($line, "{$key}") !== false) {
                $line = preg_replace("/\{$key\}/", $value, $line);
            }
        }
    }
    return $line;
}

function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, - strlen($haystack)) !== false;
}

function endsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, - strlen($needle)) !== false;
}

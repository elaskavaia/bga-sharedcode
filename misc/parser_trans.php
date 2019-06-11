<?php
/**
 * String translation parser, run on bga project
 * 
 * @author elaskavaia
 *
 */
class PhpParser {

    function PhpParser() {
        $this->keys_to_translate = array ();
    }

    /**
     * Original parsing function
     */
    function parse_php($file) {
        $content = file_get_contents( $file );
        $nbr = 0;
        // Remove php comments
        $content = preg_replace( "/^ *\/\/.*?$/m", "", $content );
        $content = preg_replace( "/\/\*.*?\*\//s", "", $content );
        // Serverside
        // _( "key" )
        preg_match_all( "/_\( *(\"(.*?)\") *\)/s", $content, $matches );
        foreach ( $matches [2] as $val ) {
            self::addKey( stripcslashes( $val ), false, true );
            $nbr ++;
        }
        // _( 'key' )
        preg_match_all( "/_\( *('(.*?)') *\)/s", $content, $matches );
        foreach ( $matches [2] as $val ) {
            self::addKey( stripcslashes( $val ), false, true );
            $nbr ++;
        }
        // totranslate( "key" )
        preg_match_all( "/totranslate\( *(\"(.*?)\") *\)/s", $content, $matches );
        foreach ( $matches [2] as $val ) {
            self::addKey( stripcslashes( $val ), false, true );
            $nbr ++;
        }
        // totranslate( 'key' )
        preg_match_all( "/totranslate\( *('(.*?)') *\)/s", $content, $matches );
        foreach ( $matches [2] as $val ) {
            self::addKey( stripcslashes( $val ), false, true );
            $nbr ++;
        }
        // Clientside
        // clienttranslate( "key" )
        preg_match_all( "/clienttranslate\( *(\"(.*?)\") *\)/s", $content, $matches );
        foreach ( $matches [2] as $val ) {
            self::addKey( stripcslashes( $val ), true, false );
            $nbr ++;
        }
        // clienttranslate( 'key' )
        preg_match_all( "/clienttranslate\( *('(.*?)') *\)/s", $content, $matches );
        foreach ( $matches [2] as $val ) {
            self::addKey( stripcslashes( $val ), true, false );
            $nbr ++;
        }
        return $nbr;
    }
    
    /**
     * Parse tpl file for server translation keys and write them to the serverside keys destination file.
     * @param string $filepath
     * @param file handle $OutputFileServer
     */
    private function parse_tpl( $file ) {
        $this->curfile = $file;
        $content = $this->original = file_get_contents( $file );
        $nbr= 0;
        // {LB_XX}
        preg_match_all( "/\{(LB_[A-Z0-9_]*)\}/", $content, $matches );
    
        foreach( $matches[1] as $val )
        {
            self::addKey( $val, false, true );
            $nbr++;
        }
        return $nbr;
    }

    function parse_js_fixed($file) {
        $this->curfile = $file;
        $content = $this->original = file_get_contents( $file );
        // Remove js comments
        $content = preg_replace( "/^ *\/\/.*?$/m", "", $content );
        $content = preg_replace( "/\/\*.*?\*\//s", "", $content );
        $nbr = 0;
        $nbr += self::parse_single_function( $content, "_", true, false );
        $nbr += self::parse_single_function( $content, "__", true, false, 2 );
        return $nbr;
    }

    function parse_php_fixed($file) {
        $this->curfile = $file;
        $content = $this->original = file_get_contents( $file );
        // Remove php comments
        $content = preg_replace( "/^ *\/\/.*?$/m", "", $content );
        $content = preg_replace( "/\/\*.*?\*\//s", "", $content );
        $nbr = 0;
        $nbr += self::parse_single_function( $content, "self\\s*::\\s*_", false, true );
        $nbr += self::parse_single_function( $content, "->\\s*_", false, true );
        $nbr += self::parse_single_function( $content, "totranslate", false, true );
        $nbr += self::parse_single_function( $content, "_", false, true );
        $nbr += self::parse_single_function( $content, "clienttranslate", true, false );
        return $nbr;
    }

    function parse_single_function(&$content, $func, $bClient, $bServer, $argNum = 1) {
        $nbr = 0;
        // split file by function, matching word bounder, i.e. no foo_ or x_clienttranslate
        $chunks = preg_split( "/\\b${func}\\s*\(\\s*/", $content, - 1, PREG_SPLIT_OFFSET_CAPTURE );
        // first string was not function call, begging of the file, discard it
        $first = array_shift( $chunks );
        $this->curline = 0;
        $quotes = array (
                "'",
                "\"",
                "`" 
        );
        $isPhp = substr($this->curfile,-3,3) == 'php';
        foreach ( $chunks as $funcparsarr ) {
            $funcpars = $funcparsarr [0];
            $snippet = "${func}($funcpars";
            $this->curoffset = $funcparsarr [1];
            //            echo "->$funcpars\n";
            foreach ( $quotes as $quot ) {
                // /"(?:[^"\\]|\\.)*"/ 
                // Two quotes surrounding zero or more of "any character that's not a quote or a backslash" or 
                // "a backslash followed by any character".
                $regex = "/^${quot}((?:[^${quot}\\\\]|\\\\.)*)${quot}\\s*(.*)/s";
                $res = preg_match( $regex, $funcpars, $matches );
                if ($res === 1) {
                    break;
                }
            }
            if ($res === 1 && $argNum == 2) {
                $res = 0;
                $funcpars = $matches [2];
                foreach ( $quotes as $quot ) {
                    $regex = "/^,\\s*${quot}((?:[^${quot}\\\\]|\\\\.)*)${quot}\\s*(.*)/s";
                    $res = preg_match( $regex, $funcpars, $matches );
                    if ($res === 1) {
                        break;
                    }
                }
            }
            if ($res === 1) {
                $val = $matches [1];
                $rest = $matches [2];
                $end = substr( $rest, 0, 1 );
                if (empty( $val )) {
                    self::printError( "empty string", $snippet );
                } else if ($end != ')') {
                    self::printError( "unexpected character $end expecting )", $snippet );
                } else if (strstr( $val, '$' ) !== false && $quot == '"') {
                    if (preg_match( "/.*[^\\\\]\\$/s", $val ) === 1 && $isPhp) {
                        self::printError( "double quoted string contains unescaped $", $snippet, 'Warning'  );
                    }
                } else if ($quot == '`') {
                    self::printError( "avoid using template strings they not supported by many browsers", $snippet, 'Warning' );
                } else if ($func == '_' &&  $isPhp) {
                    //self::printError( "avoid using '_' function in php, use 'self::_' instead", $snippet, 'Warning'  );
                }
                self::addKey( stripcslashes( $val ), $bClient, $bServer );
                $nbr ++;
            } else if ($res === 0) {
                // we did not found any suitable pattern, this is bad, report it
                self::printError( "invalid translatable string", $snippet );
            } else {
                self::printError( "failed to match $regex", $snippet );
            }
        }
        return $nbr;
    }

    function printError($err, $snippet, $severity = 'Error') {
        $line = $this->curline;
        $pos = $this->curoffset;
        if ($line == 0 && $pos == 0) {
            $pos = strpos( $this->original, $snippet );
        }
        if ($line == 0 && $pos > 0) {
            $line = substr_count( substr( $this->original, 0, $pos ), "\n" );
        }
        $snippet = preg_replace( "/;.*/s", ";...", $snippet );
        if (strlen( $snippet ) > 100) {
            $snippet = substr( $snippet, 0, 100 ) . "...";
        }
        echo "$severity:$this->curfile:$line: $err: $snippet\n";
    }

    /**
     * Extra function to show untranslated strings
     */
    function parse_php_untranslated($file) {
        $this->curfile = $file;
        $this->original = file_get_contents( $file );
        $this->curoffset = 0;
        $this->curline = 0;
        self::report_untranslated( $this->original );
    }

    function report_untranslated(&$content) {
        $lines = explode( "\n", $content );
        $this->curline = 0;
        $quotes = array (
                "'",
                "\"" 
        );
        foreach ( $lines as $line ) {
            $line_mod = preg_replace( "/^ *\/\/.*?$/m", "", $line );
            $this->curline ++;
            foreach ( $quotes as $quot ) {
                // /"(?:[^"\\]|\\.)*"/
                // Two quotes surrounding zero or more of "any character that's not a quote or a backslash" or
                // "a backslash followed by any character".
                $regex = "/${quot}((?:[^${quot}\\\\]|\\\\.)*)${quot}/s";
                $res = preg_match_all( $regex, $line_mod, $matches );
                foreach ( $matches [1] as $val ) {
                    $str = stripcslashes( $val );
                    if (empty( $str ))
                        continue;
                    if (preg_match( "/^[^ ]+$/", $str ))
                        continue;
                    if (preg_match( "/^\\W+$/", $str ))
                        continue;
                    if (preg_match( "/^[A-Z_]+/", $str ))
                        continue;
                    if (preg_match( "/^-/", $str ))
                        continue;
                        if (preg_match( "/trace|warn|error|assertTrue/", $line_mod ))
                            continue;
                    if (! isset( $this->keys_to_translate [$str] ) && !strstr($line, "NOI18N")) {
                        self::printError( "Possibly untranslated", "$line", 'Warning' );
                    }
                }
            }
        }
    }

    function addKey($key, $bClient, $bServer, $bHidden = 0) {
        if ($key == '')
            return; // Never add an empty key
        //echo sprintf( "** '%s' ** => (%b,%b,%b)\n", $key, $bClient, $bServer, $bHidden );
        if (! isset( $this->keys_to_translate [$key] ))
            $this->keys_to_translate [$key] = array (
                    's' => 0,
                    'c' => 0,
                    'h' => 0 
            );
        if ($bClient)
            $this->keys_to_translate [$key] ['c'] = 1;
        if ($bServer)
            $this->keys_to_translate [$key] ['s'] = 1;
        if ($bHidden)
            $this->keys_to_translate [$key] ['h'] = 1;
    }

    function runTest() {
        // $this->parse_php( 'parser_test.php' );
        $this->parse_php_fixed( 'parser_test.php' );
        //$this->parse_js_fixed( 'parser_test.php' );
    }
    
    function runOnModule($base_path){
        ////////// PHP files ////////////
        $files = self::dir_tree( $base_path, ".php" );
        
        echoinstant( "" );
        echoinstant( count( $files ). " PHP files to scan:" );
        foreach( $files as $file )
        {
            echoinstant( "__".$file );
            $nbr = self::parse_php_fixed( $file );
            echoinstant( "_____".$nbr." strings found");
            self::parse_php_untranslated($file);
        }
        
        ////////// TPL files ////////////
        $files = self::dir_tree( $base_path, ".tpl" );
        
        echoinstant( "" );
        echoinstant( count( $files ). " TPL files to scan:" );
        foreach( $files as $file )
        {
            echoinstant( "__".$file );
            $nbr = self::parse_tpl( $file );
            echoinstant( "_____".$nbr." strings found");
        }
        
        ////////// JS files ////////////
        $files = self::dir_tree( $base_path, ".js" );
        
        echoinstant( "" );
        echoinstant( count( $files ). " JS files to scan:" );
        foreach( $files as $file )
        {
            echoinstant( "__".$file );
            $nbr = self::parse_js_fixed( $file );
            echoinstant( "_____".$nbr." strings found");
        }
    }
    
    /**
     * Returns an array of all files matching the extension in $dir and its subdirectories
     * @param string $dir
     * @param string $extension
     * @return array
     */
    private function dir_tree($dir, $extension) {
        $path = array();
        $stack[] = $dir;
        while ($stack) {
            $thisdir = array_pop($stack);
            if ($dircont = scandir($thisdir)) {
                $i=0;
                while (isset($dircont[$i])) {
                    if ($dircont[$i] !== '.'
                            && $dircont[$i] !== '..'
                            && $dircont[$i] !== '.svn'
                            && $dircont[$i] !== 'translation'
                            && $dircont[$i] !== 'clienttranslations'
                            && $dircont[$i] !== 'img'
                            && $dircont[$i] !== 'games'
                            && $dircont[$i] !== 'layer'
                            && $dircont[$i] !== 'dojoroot'
                            && $dircont[$i] !== 'nls'
                            && $dircont[$i] !== 'data'       // Note: data has some recursion...
                            && $dircont[$i] !== 'tpl_translate'
                            ) {
                                 
                                $current_file = "{$thisdir}/{$dircont[$i]}";
                                if (is_file($current_file)
                                        && $dircont[$i] !== 'jstranslations.game.php') {
                                            if ($this->endsWith( $dircont[$i], $extension ) !== FALSE) {
                                                $path[] = $current_file;
                                            }
                                        } elseif (is_dir($current_file)) {
                                            $stack[] = $current_file;
                                        }
                            }
                            $i++;
                }
            }
        }
        return $path;
    }
    
    function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }
    
        return (substr($haystack, -$length) === $needle);
    }
}
function echoinstant($msg) {
    echo $msg."\n";
}
$p = new PhpParser();
//$p->runTest();
//$p->parse_php_fixed( '../../hive/hive/hive.game.php' );
//$p->parse_php_untranslated( '../../hive/hive/hive.game.php' );

$indir = $argv [1];
$p->runOnModule($indir);
echo "found " . count( $p->keys_to_translate ) . " keys \n";
foreach ($p->keys_to_translate as $key => $value) {
    echoinstant($key);
}


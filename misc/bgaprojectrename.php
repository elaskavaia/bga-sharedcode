<?php


// MAIN CODE
$rest_index = null;
$options = getopt("", ["all","old-name:","new-name:","exclude:"], $rest_index);
$args = array_slice($argv, $rest_index);
if (! isset($args [0]) || ! isset($args[1])) {
    echo "Make a project copy by moving files into new project and renaming some known files and strings inside them\n";
    echo "The new project directory must be empty. The name of the new project is the name of the directory. It must be all lowercase.\n";
    echo "usage: bgaprojectrename.php [options] <oldprojectfullpath> <newprojectfullpath>\n";
    echo "  note: options must come BEFORE the paths (getopt stops at the first path)\n";
    echo "options:\n";
    echo " --all - renamed in text files and strings also";
    echo " --old-name <OldProjectName>\n";
    echo " --new-name <NewProjectName>\n";
    echo " --exclude <name,name,...> - extra file/dir names to skip during copy (added to the built-in .git/.svn/node_modules)\n";
    exit(0);
}

$oldprojectpath = realpath($args [0]); 
if ($oldprojectpath===false) die("Path does not exists: $args[0]\n");
$newprojectpath = $args [1];
if (!is_dir($newprojectpath)) {
	mkdir($newprojectpath, 0777, true) || die ("Cannot create dir $newprojectpath\n");
}
$newprojectpath = realpath($newprojectpath);
if ($newprojectpath===false) die("Path does not exists: $args[1]\n");

$oldprojectname = $options['old-name'] ?? basename($oldprojectpath);
$newprojectname = $options['new-name'] ?? basename($newprojectpath);
$replace_content = array_key_exists('all',$options);
$exclude_list = array_merge(['.svn', '.git', 'node_modules'], array_filter(explode(',', $options['exclude'] ?? '')));
echo "replace all: $replace_content\n";
$res = preg_match( "/^[a-z]+$/", $newprojectname );
if ($res !== 1) {
    echo "Error: new project name can only have [a-z] letters (was $newprojectname)\n";
    exit(1);
}
echo "$oldprojectpath:$oldprojectname => $newprojectpath:$newprojectname\n";

copyr($oldprojectpath, $newprojectpath);
replacecontent($newprojectpath);




// UTILS
function replacecontent($newprojectpath) {
    global $oldprojectname;
    global $newprojectname;
    global $replace_content;
    // case-preserving forms (namespace/class use the capitalized name)
    $oldcap = ucfirst($oldprojectname);
    $newcap = ucfirst($newprojectname);
    $dir_handle = opendir($newprojectpath);
    while ( $file = readdir($dir_handle) ) {
        if ($file != "." && $file != "..") {
            $path = "$newprojectpath/$file";
            
            if (is_dir($path)) {
                // recurse everywhere - copyr already skipped the excluded names during the copy
                replacecontent($path);
                // rename the directory itself too (e.g. .claude/skills/create-euro-game)
                $corrdir = preg_replace( "/\\b${oldprojectname}\\b/", "${newprojectname}", $file);
                $corrdir = preg_replace( "/\\b${oldcap}\\b/", "${newcap}", $corrdir);
                if ($corrdir != $file) {
                    echo "Renaming dir $file => $corrdir\n";
                    rename($path, "$newprojectpath/$corrdir");
                }
            } else {
                // file
                $corrfile = $file;
                $corrfile = preg_replace( "/\\b${oldprojectname}\\b/", "${newprojectname}", $corrfile);
                $corrfile = preg_replace( "/\\b${oldcap}\\b/", "${newcap}", $corrfile);
                $corrfile = preg_replace( "/\\b${oldprojectname}_${oldprojectname}\\b/", "${newprojectname}_${newprojectname}", $corrfile);
                if ($corrfile != $file) {
                    echo "Renaming $file => $corrfile\n";
                    rename($path,"$newprojectpath/$corrfile");
                    $path="$newprojectpath/$corrfile";
                }
                echo "Replacing string in $corrfile\n";
                
                
                $content = file_get_contents( $path );
                if ($corrfile != $file) {
                    $content = preg_replace( "/\\b${file}\\b/", "${corrfile}", $content);
                }
                
                $content= preg_replace( "/${oldprojectname} implementation/i", "${newprojectname} implementation", $content);
                $content= preg_replace( "/\"bgagame\\.${oldprojectname}\"/", "\"bgagame.${newprojectname}\"", $content);
                $content= preg_replace( "/${oldprojectname}_${oldprojectname}/", "${newprojectname}_${newprojectname}", $content);
                $content= preg_replace( "/action_${oldprojectname}\\b/", "action_${newprojectname}", $content);
                $content= preg_replace( "/\\/${oldprojectname}\\/${oldprojectname}\\//", "/${newprojectname}/${newprojectname}/", $content);
                $content= preg_replace( "/class ${oldprojectname} extends/i", "class $newprojectname extends", $content);
                //function __construct(
                $content= preg_replace( "/function\\s+${oldprojectname}\\(/i", "function __construct\\(", $content);
                $content= preg_replace("/game_version_${oldprojectname}/", "game_version_${newprojectname}", $content);
                $content= preg_replace("/${oldprojectname}\.js/i", "${newprojectname}.js", $content);
                $content= preg_replace("/${oldprojectname}\.css/", "${newprojectname}.css", $content);
                $content= preg_replace("/${oldprojectname}\.scss/", "${newprojectname}.scss", $content);
                $content= preg_replace("/${oldprojectname}\.game.php/", "${newprojectname}.game.php", $content);
                $content= preg_replace("/${oldprojectname}\.action.php/", "${newprojectname}.action.php", $content);
                $content= preg_replace("/${oldprojectname} game/i", "${newprojectname} game", $content);
                $content= preg_replace("/bga\\.${oldprojectname}/", "bga.${newprojectname}", $content);
                $content= preg_replace("/\/${oldprojectname}\//", "/${newprojectname}/", $content);
                $content= preg_replace("/return \"${oldprojectname}\"/", "return \"${newprojectname}\"", $content);
                
                // PHP namespace segment is code-critical - always rename (handles both Bga\Games\Name and escaped Bga\\Games\\Name)
                $content = preg_replace('/(Bga\\\\+Games\\\\+)' . preg_quote($oldcap, '/') . '\\b/', '${1}' . $newcap, $content);

                if ($replace_content) {
                    // case-preserving whole-word catch-all (quoted ids, display name, titles, prose)
                    $content= preg_replace( "/\\b${oldprojectname}\\b/", "${newprojectname}", $content);
                    $content= preg_replace( "/\\b${oldcap}\\b/", "${newcap}", $content);
                }
                
                file_put_contents($path,$content);
            }
        }
    }
    closedir($dir_handle);
}

function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, - strlen($haystack)) !== false;
}

function copyr($source, $dest) {
    global $oldprojectname;
    global $exclude_list;
    if (!is_dir($dest)) mkdir($dest);
    if (is_dir($source)) {
        $dir_handle = opendir($source);
        while ( $file = readdir($dir_handle) ) {
            if ($file != "." && $file != ".." && !in_array($file, $exclude_list)) {
                if (is_dir($source . "/" . $file)) {
                    if (! is_dir($dest . "/" . $file)) {
                        mkdir($dest . "/" . $file);
                    }
                    copyr($source . "/" . $file, $dest . "/" . $file);
                } else {
                    if (endsWith($file, '.game.php')) {
                        $oldprojectname = preg_replace("/.game.php/", "", $file);
                        echo "Using $oldprojectname as old project name\n";
                    }
                    copy($source . "/" . $file, $dest . "/" . $file);
                }
            }
        }
        closedir($dir_handle);
    } else {
        copy($source, $dest);
    }
}

function endsWith($haystack, $needle) {
	    $length = strlen($needle);
	        return $length === 0 || (substr($haystack, -$length) === $needle);
}
?>

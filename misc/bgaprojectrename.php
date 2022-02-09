<?php



// MAIN CODE
if (! isset($argv [1])) {
    echo "Make a project copy by moving files into new project and renaming some known files and strings inside them\n";
    echo "The new project directory must be empty. The name of the new project is the name of the directory. It must be all lowercase.\n";
    echo "usage: bgaprojectrename.php <oldprojectfullpath> <newprojectfullpath>\n";
    exit(0);
}
$oldprojectpath = $argv [1];
$newprojectpath = $argv [2];
$oldprojectname = basename($argv [1]);
$newprojectname = basename($argv [2]);
$res = preg_match( "/^[a-z]+$/", $newprojectname );
if ($res !== 1) {
    echo "Error: new project name can only have [a-z] letters (was $newprojectname)\n";
    exit(1);
}
array ("dbmodel.sql","version.php" );
echo "$oldprojectname => $newprojectname\n";
$newprojectnameCap = ucfirst($newprojectname);
$oldprojectnameCap = ucfirst($oldprojectname);
echo "Copying $oldprojectpath => $newprojectpath\n";
copyr($oldprojectpath, $newprojectpath);
$dir_handle = opendir($newprojectpath);
while ( $file = readdir($dir_handle) ) {
    if ($file != "." && $file != "..") {
        $path = "$newprojectpath/$file";
        
        if (is_dir($path)) {
            // ignore subdirs
        } else {
            // file
            $corrfile = $file;
            $corrfile = preg_replace( "/\\b${oldprojectname}\\b/", "${newprojectname}", $corrfile);
            $corrfile = preg_replace( "/\\b${oldprojectname}_${oldprojectname}\\b/", "${newprojectname}_${newprojectname}", $corrfile);
            if ($corrfile != $file) {
                echo "Renaming $file => $corrfile\n";
                rename($path,"$newprojectpath/$corrfile");
                $path="$newprojectpath/$corrfile";
            }
            echo "Replacing string in $corrfile\n";
            
            $matches = array();
            $content = file_get_contents( $path );
            if ($corrfile != $file) {
                $content = preg_replace( "/\\b${file}\\b/", "${corrfile}", $content);
            }
            $content= preg_replace( "/\"${oldprojectname}\"/", "\"${newprojectname}\"", $content);
            $content= preg_replace( "/${oldprojectnameCap} implementation :/", "${newprojectnameCap} implementation :", $content);
            $content= preg_replace( "/\"bgagame\\.${oldprojectname}\"/", "\"bgagame.${newprojectname}\"", $content);
            $content= preg_replace( "/${oldprojectname}_${oldprojectname}/", "${newprojectname}_${newprojectname}", $content);
            $content= preg_replace( "/action_${oldprojectname}\\b/", "action_${newprojectname}", $content);
            $content= preg_replace( "/\\/${oldprojectname}\\/${oldprojectname}\\//", "/${newprojectname}/${newprojectname}/", $content);
            $content= preg_replace( "/class ${oldprojectname} extends/i", "class $newprojectnameCap extends", $content);
            //function __construct(
            $content= preg_replace( "/function\\s+${oldprojectname}\\(/i", "function __construct\\(", $content);
            

       
            file_put_contents($path,$content);
        }
    }
}
closedir($dir_handle);

// UTILS


function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, - strlen($haystack)) !== false;
}

function copyr($source, $dest) {
    mkdir($dest);
    if (is_dir($source)) {
        $dir_handle = opendir($source);
        while ( $file = readdir($dir_handle) ) {
            if ($file != "." && $file != ".." && $file != ".svn" && $file != ".git") {
                if (is_dir($source . "/" . $file)) {
                    if (! is_dir($dest . "/" . $file)) {
                        mkdir($dest . "/" . $file);
                    }
                    copyr($source . "/" . $file, $dest . "/" . $file);
                } else {
                    copy($source . "/" . $file, $dest . "/" . $file);
                }
            }
        }
        closedir($dir_handle);
    } else {
        copy($source, $dest);
    }
}

?>

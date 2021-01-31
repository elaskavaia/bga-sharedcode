<?php
define( "APP_GAMEMODULE_PATH", "./" ); // include path to mocks, this defined "Table" and other classes
require_once ('sharedcode.game.php'); // include real game class

class SharedCodeTest1 extends SharedCode {

    function __construct() {
        parent::__construct();
        include '../material.inc.php';
        $this->resources = array ();
    }
    // override methods here that access db and stuff
    
    function getGameStateValue($var) {
        if ($var=='round') return 3;
        return 0;
    }
}

$x = new SharedCodeTest1();
$p = $x->getGameProgression();
if ($p != 50) echo "Test1: FAILED";
else echo "Test1: PASSED";

<?php

/**
 * Collection of stub classes for testing
 */

class APP_Object {    
    function dump($v, $value) {
        echo "$v=";
        var_dump($value);
    }
    
    function info($value) {
        echo "$value\n";
    }
    
    function trace($value) {
        echo "$value\n";
    }
    
    function debug($value) {
        echo "$value\n";
    }
    
    function watch($value) {
        echo "$value\n";
    }
    
    function warn($value) {
        echo "$value\n";
    }
    
    function error($msg) {
        echo "$msg\n";
    }
}
class APP_DbObject {
    public $query;

    function DbQuery($str) {
        $this->query = $str;
        echo "dbquery: $str\n";
    }

    function getCollectionFromDB($query, $single = false) {
        echo "dbquery coll: $query\n";
        return array ();
    }

    function getObjectListFromDB($query, $single = false) {
        echo "dbquery list: $query\n";
        return array ();
    }

    function getUniqueValueFromDB() {
        return 0;
    }
    

}

class APP_GameClass extends APP_DbObject {
}

class GameState {

    function GameState() {
    }

    function state() {
        return array ();
    }
}

class BgaUserException extends Exception {
}
class feException extends Exception {
}


class Table extends APP_GameClass {

    public function __construct() {
        parent::__construct();
        $this->gamestate = new GameState();
        $this->players = array (
                1 => array ('player_name' => $this->getActivePlayerName(),'player_color' => 'ff0000' ),
                2 => array ('player_name' => 'player2','player_color' => '0000ff' ) );
    }

    function getActivePlayerId() {
        return 1;
    }

    function getActivePlayerName() {
        return "player1";
    }

    function initGameStateLabels($labels) {
    }

    function notifyAllPlayers($type, $message, $args) {
        $args2 = array ();
        foreach ( $args as $key => $val ) {
            $key = '${' . $key . '}';
            $args2 [$key] = $val;
        }
        echo "$type: $message\n";
        //. strtr($message,                $args2)
        echo "\n";
    }



    function getGameStateValue($var) {
        return 0;
    }

    function _($s) {
        return $s;
    }

    function getPlayersNumber() {
        return 2;
    }



    function setStat($key, $value) {
        echo "stat: $key=$value\n";
    }

    function getStatTypes() {
        return array ();
    }

    function loadPlayersBasicInfos() {
        $default_colors = array ("ff0000","008000","0000ff","ffa500","4c1b5b" );
        $values = array ();
        $id = 1;
        foreach ( $default_colors as $color ) {
            $values [$id] = array ('player_id' => $id,'player_color' => $color,'player_name' => "player$id" );
            $id ++;
        }
        return $values;
    }
}

class Page {
    public $blocks = array ();

    public function begin_block($template, $block) {
        $this->blocks [$block] = array ();
    }

    public function insert_block($block, $args) {
        $this->blocks [$block] [] = $args;
    }
}

class GUser {

    public function get_id() {
        return 1;
    }
}

function clienttranslate($x) {
    return $x;
}

function mysql_fetch_assoc($res) {
    return array ();
}

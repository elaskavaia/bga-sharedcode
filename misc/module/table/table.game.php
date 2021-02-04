<?php

define('APP_GAMEMODULE_PATH', '');

class APP_Object {    
    static function dump($name_of_variable, $variable) {}
    static function info($message) {}
    static function trace($message) {}
    static function debug($message) {}
    static function watch($message) {}
    static function warn($message) {}
    static function error($message) {}
}

class APP_DbObject extends APP_Object{
    static function DbQuery($sql) {}
    static function getUniqueValueFromDB($sql) {}
    static function getCollectionFromDB($sql, $bSingleValue=false) {return array();}
    static function getNonEmptyCollectionFromDB($sql) {return array();}
    static function getObjectFromDB($sql) {return array();}
    static function getNonEmptyObjectFromDB($sql) {return array();}
    static function getObjectListFromDB($sql, $bUniqueValue=false) {return array();}
    static function getDoubleKeyCollectionFromDB($sql, $bSingleValue=false) {return array();}
    static function DbGetLastId() {}
    static function DbAffectedRow() {}
    static function escapeStringForDB($string) {}
}

class APP_GameClass extends APP_DbObject {
    public function __construct() {}
}

class GameState {
    function GameState() {}
    function state() {}
    function changeActivePlayer($player_id) {}
    function setAllPlayersMultiactive() {}
    function setAllPlayersNonMultiactive($next_state) {}
    function setPlayersMultiactive($players, $next_state, $bExclusive = false) {}
    function setPlayerNonMultiactive($player_id, $next_state) {}
    function getActivePlayerList() {}
    function updateMultiactiveOrNextState($next_state_if_none) {}
    function nextState($transition) {}
    function checkPossibleAction($action) {}
}

class BgaUserException extends Exception {}
class BgaVisibleSystemException extends Exception {}
class feException extends Exception {}

class Table extends APP_GameClass {
    public static $gamestate;
    public static $players;

    public function __construct() {
        parent::__construct();
        $this->gamestate = new GameState();
        $this->players = array ();
    }

    static function getPlayersNumber() {return 0;}
    static function getActivePlayerId() {return 0;}
    static function getActivePlayerName() {return '';}
    static function loadPlayersBasicInfos() {return array();}
    static function getCurrentPlayerId() {return 0;}
    static function getCurrentPlayerName() {return '';}
    static function getCurrentPlayerColor() {return '';}
    static function isCurrentPlayerZombie() {return false;}
    static function getActivePlayerColor() {return '';}

    static function initGameStateLabels($labels) {}
    static function setGameStateInitialValue($value_label, $value_value) {}
    static function getGameStateValue($value_label) {return 0;}
    static function setGameStateValue($value_label, $value_value) {}
    static function incGameStateValue($value_label, $increment) {return 0;}

    static function activeNextPlayer() {}
    static function activePrevPlayer() {}
    static function checkAction($actionName, $bThrowException=true) {}
    
    static function getNextPlayerTable() {return 0;}
    static function getPrevPlayerTable() {return 0;}
    static function getPlayerAfter($player_id) {return 0;}
    static function getPlayerBefore($player_id) {return 0;}
    static function createNextPlayerTable($players, $bLoop=true) {return array();}
    static function createPrevPlayerTable($players, $bLoop=true) {return array();}

    static function notifyAllPlayers($notification_type, $notification_log, $notification_args) {}
    static function notifyPlayer($player_id, $notification_type, $notification_log, $notification_args) {}

    static function initStat($table_or_player, $name, $value, $player_id = null) {}
    static function setStat($value, $name, $player_id = null) {}
    static function incStat($delta, $name, $player_id = null) {}
    static function getStat($name, $player_id = null) {return 0;}

    static function _($s) {return $s;}

    static function reattributeColorsBasedOnPreferences($players, $colors) {}
    static function reloadPlayersBasicInfos() {}
    static function getNew($deck_definition) {}
    static function giveExtraTime($player_id) {}
    static function getStandardGameResultObject() {return array();}
    static function applyDbChangeToAllDB($sql) {} // DEPRECATED
    static function applyDbUpgradeToAllDB($sql) {} // DEPRECATED
}

class Page {
    public $blocks = array ();

    public function begin_block($template, $block) {}
    public function insert_block($block, $args) {}
}

class GUser {
    public function get_id() {return 0;}
}

class game_view {}
class APP_GameAction {}

function clienttranslate($x) {return $x;}
function mysql_fetch_assoc($res) {return array();}
function bga_rand($min, $max) {return 0;}
function getKeysWithMaximum($array) {return array();}
function getKeyWithMaximum($array) {return '';}
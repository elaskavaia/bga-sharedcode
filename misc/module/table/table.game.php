<?php

if ( !defined('APP_GAMEMODULE_PATH')) { 
    define('APP_GAMEMODULE_PATH', '');
}

/**
 * Collection of stub classes for testing and stubs
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

class APP_DbObject extends APP_Object {
    public $query;
    
    function DbQuery($str) {
        $this->query = $str;
        echo "dbquery: $str\n";
    }
    
    function getUniqueValueFromDB($sql) {
        return 0;
    }
    
    function getCollectionFromDB($query, $single = false) {
        echo "dbquery coll: $query\n";
        return array ();
    }
    
    function getNonEmptyCollectionFromDB($sql) {
        return array ();
    }
    
    function getObjectFromDB($sql) {
        return array ();
    }
    
    function getNonEmptyObjectFromDB($sql) {
        return array ();
    }
    
    function getObjectListFromDB($query, $single = false) {
        echo "dbquery list: $query\n";
        return array ();
    }
    
    function getDoubleKeyCollectionFromDB($sql, $bSingleValue = false) {
        return array ();
    }
    
    function DbGetLastId() {
    }
    
    function DbAffectedRow() {
    }
    
    function escapeStringForDB($string) {
    }
}

class APP_GameClass extends APP_DbObject {
    
    public function __construct() {
    }
}

class GameState {
    
    function GameState() {
    }
    
    function state() {
        return array ();
    }
    
    function changeActivePlayer($player_id) {
    }
    
    function setAllPlayersMultiactive() {
    }
    
    function setAllPlayersNonMultiactive($next_state) {
    }
    
    function setPlayersMultiactive($players, $next_state, $bExclusive = false) {
    }
    
    function setPlayerNonMultiactive($player_id, $next_state) {
    }
    
    function getActivePlayerList() {
    }
    
    function updateMultiactiveOrNextState($next_state_if_none) {
    }
    
    function nextState($transition) {
    }
    
    function checkPossibleAction($action) {
    }
    
    function reloadState()
    {
        return $this->state();
    }
}

class BgaUserException extends Exception {
}

class BgaVisibleSystemException extends Exception {
}

class feException extends Exception {
}

abstract class Table extends APP_GameClass {
    var $players = array();
    var $gamename;
    var $gamestate = null;
    
    public function __construct() {
        parent::__construct();
        $this->gamestate = new GameState();
        $this->players = array (1 => array ('player_name' => $this->getActivePlayerName(),'player_color' => 'ff0000' ),
                2 => array ('player_name' => 'player2','player_color' => '0000ff' ) );
    }
    
    /** Report gamename for translation function */
    abstract protected function getGameName( );
    
    function getActivePlayerId() {
        return 1;
    }
    
    function getActivePlayerName() {
        return "player1";
    }
    
    function getTableOptions() {
        return [ ];
    }
    
    function getTablePreferences() {
        return [ ];
    }
    
    function loadPlayersBasicInfos() {
        $default_colors = array ("ff0000","008000","0000ff","ffa500","4c1b5b" );
        $values = array ();
        $id = 1;
        foreach ( $default_colors as $color ) {
            $values [$id] = array ('player_id' => $id,'player_color' => $color,'player_name' => "player$id" );
            $id++;
        }
        return $values;
    }
    
    protected function getCurrentPlayerId() {
        return 0;
    }
    
    protected function getCurrentPlayerName() {
        return '';
    }
    
    protected function getCurrentPlayerColor() {
        return '';
    }
    
    function isCurrentPlayerZombie() {
        return false;
    }
    
    public function getPlayerNameById( $player_id )
    {
        $players = self::loadPlayersBasicInfos();
        return $players[ $player_id ]['player_name'];
    }
    public function getPlayerNoById( $player_id )
    {
        $players = self::loadPlayersBasicInfos();
        return $players[ $player_id ]['player_no'];
    }
    public function getPlayerColorById( $player_id )
    {
        $players = self::loadPlayersBasicInfos();
        return $players[ $player_id ]['player_color'];
    }
    
    
    /**
     * Setup correspondance "labels to id"
     * @param [] $labels - map string -> int (label of state variable -> numeric id in the database)
     */
    function initGameStateLabels($labels) {
    }
    
    function setGameStateInitialValue($value_label, $value_value) {
    }
    
    function getGameStateValue($value_label) {
        return 0;
    }
    
    function setGameStateValue($value_label, $value_value) {
    }
    
    function incGameStateValue($value_label, $increment) {
        return 0;
    }
    
    /**
     *   Make the next player active (in natural order)
     */
    protected function activeNextPlayer() {
    }
    
    /**
     *   Make the previous player active  (in natural order)
     */
    protected function activePrevPlayer() {
    }
    
    /**
     * Check if action is valid regarding current game state (exception if fails)
     if "bThrowException" is set to "false", the function return false in case of failure instead of throwing and exception
     * @param string $actionName
     * @param boolean $bThrowException
     */
    function checkAction($actionName, $bThrowException = true) {
    }
    
    function getNextPlayerTable() {
        return 0;
    }
    
    function getPrevPlayerTable() {
        return 0;
    }
    
    function getPlayerAfter($player_id) {
        return 0;
    }
    
    function getPlayerBefore($player_id) {
        return 0;
    }
    
    function createNextPlayerTable($players, $bLoop = true) {
        return array ();
    }
    
    function createPrevPlayerTable($players, $bLoop = true) {
        return array ();
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
    
    function notifyPlayer($player_id, $notification_type, $notification_log, $notification_args) {
    }
    
    function getStatTypes() {
        return array ();
    }
    
    function initStat($table_or_player, $name, $value, $player_id = null) {
    }
    
    function setStat($value, $name, $player_id = null, $bDoNotLoop = false) {
        echo "stat: $name=$value\n";
    }
    
    function incStat($delta, $name, $player_id = null) {
    }
    
    function getStat($name, $player_id = null) {
        return 0;
    }
    
    function _($s) {
        return $s;
    }
    
    function getPlayersNumber() {
        return 2;
    }
    
    function reattributeColorsBasedOnPreferences($players, $colors) {
    }
    
    function reloadPlayersBasicInfos() {
    }
    
    function getNew($deck_definition) {
    }
    
    // Give standard extra time to this player
    // (standard extra time is a game option)
    function giveExtraTime( $player_id, $specific_time=null ) {
        
    }
    
    function getStandardGameResultObject() {
        return array ();
    }
    
    function applyDbChangeToAllDB($sql) {
    }
    
    /**
     *
     * @deprecated
     */
    function applyDbUpgradeToAllDB($sql) {
    }
    
    
    function getGameinfos() {
        unset($gameinfos);
        require ('gameinfos.inc.php');
        if (isset($gameinfos)) {
            return $gameinfos;
        }
        throw new feException("gameinfos.inp.php suppose to define \$gameinfos variable");
    }
    
    /* Method to override to set up each game */
    abstract protected function setupNewGame( $players, $options = array() );
    
    public function stMakeEveryoneActive() {
        $this->gamestate->setAllPlayersMultiactive();
    }
    
    /* save undo state after all transations are done */
    function undoSavepoint()
    {

    }
    
    /* restored db to saved state */
    function undoRestorePoint()
    {
    
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

class game_view {
}

class APP_GameAction {
    function getArg($name,$type,$mandatory=true,$default=null) {
        return '';
    }
}

function totranslate($text) {
    return $text;
}

function clienttranslate($x) {
    return $x;
}

function mysql_fetch_assoc($res) {
    return array ();
}

function bga_rand($min, $max) {
    return 0;
}

function getKeysWithMaximum( $array, $bWithMaximum=true ) {
    return array ();
}

function getKeyWithMaximum($array) {
    return '';
}
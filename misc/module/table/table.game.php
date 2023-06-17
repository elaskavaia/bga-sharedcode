<?php

if (!defined('APP_GAMEMODULE_PATH')) {
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
        return array();
    }

    function getNonEmptyCollectionFromDB($sql) {
        return array();
    }

    function getObjectFromDB($sql) {
        return array();
    }

    function getNonEmptyObjectFromDB($sql) {
        return array();
    }

    function getObjectListFromDB($query, $single = false) {
        echo "dbquery list: $query\n";
        return array();
    }

    function getDoubleKeyCollectionFromDB($sql, $bSingleValue = false) {
        return array();
    }

    function DbGetLastId() {
    }

    function DbAffectedRow() {
    }

    function escapeStringForDB($string) {
    }
}

class APP_Action extends APP_DbObject {
}

class APP_GameClass extends APP_DbObject {

    public function __construct() {
    }
}

class GameState {
    public $table_globals;
    private $current_state = 2;
    private $active_player = null;
    private $states = [];
    private $private_states = [];

    function __construct($states = []) {
        $this->states = $states;
    }

    function state() {
        if (array_key_exists($this->current_state, $this->states)) {
            $state =  $this->states[$this->current_state];
            $state['id'] = $this->current_state;
            return $state;
        }
        return [];
    }

    function getStateNumberByTransition($transition) {
        $state = $this->state();
        foreach ($state['transitions'] as $pos => $next_state) {
            if ($transition == $pos || !$transition) {
                return $next_state;
            }
        }

        throw new feException("This transition ($transition) is impossible at this state ($this->current_state)");
    }

    function changeActivePlayer($player_id) {
        $this->active_player = $player_id;
        $this->states[$this->current_state]['active_player'] = $player_id;
    }

    function setAllPlayersMultiactive() {
    }

    function setPlayersMultiactive($players, $next_state, $bExclusive = false) {
    }

    function setPlayerNonMultiactive($player_id, $next_state) {
    }

    public function isMutiactiveState() {
        $state = $this->state();
        return ($state['type'] == 'multipleactiveplayer');
    }

    public function getPlayerActiveThisTurn() {
        $state = $this->state();
        return $state['active_player'] ?? $this->active_player;
    }

    public function getActivePlayerList() {
        $state = $this->state();

        if ($state['type'] == 'activeplayer') {
            return [$this->getPlayerActiveThisTurn()];
        } else if ($state['type'] == 'multipleactiveplayer') {
            return $state['multiactive'] ?? [];
        } else
            return [];
    }

    // Return true if specified player is active right now.
    // This method take into account game state type, ie nobody is active if game state is "game" and several
    // players can be active if game state is "multiplayer"
    public function isPlayerActive($player_id) {
        return false;
    }


    function updateMultiactiveOrNextState($next_state_if_none) {
    }

    function nextState($transition) {
        $x = $this->getStateNumberByTransition($transition);
        $this->jumpToState($x);
    }

    function jumpToState($stateNum) {
        $this->current_state = $stateNum;
    }

    function checkPossibleAction($action) {
    }

    function reloadState() {
        return $this->state();
    }


    function getPrivateState($playerId) {
        return  $this->private_states[$playerId] ?? null;
    }

    function nextPrivateStateForPlayers($ids, $transition) {
    }

    function nextPrivateStateForAllActivePlayers($transition) {
    }

    function nextPrivateState($playerId, $transition) {
        $privstate = $this->getStateNumberByTransition($transition);
        $this->setPrivateState($playerId, $privstate);
    }

    function setPrivateState($playerId, $newStateId) {
        $this->private_states[$playerId] = $newStateId;
    }

    function initializePrivateStateForAllActivePlayers() {
    }

    function initializePrivateStateForPlayers($ids) {
    }

    function initializePrivateState($playerId) {
        $state = $this->state();
        $privstate = $state['initialprivate'];
        $this->setPrivateState($playerId, $privstate);
    }

    function unsetPrivateState($playerId) {
        $this->private_states[$playerId] = null;
    }

    function unsetPrivateStateForPlayers($ids) {
    }

    function unsetPrivateStateForAllPlayers() {
    }
}

class BgaUserException extends feException {
    public function __construct($message, $code = 100) {
        parent::__construct($message, true, true, $code);
    }
}

class BgaSystemException extends feException {
    public function __construct($message, $code = 100) {
        parent::__construct($message, false, false, $code);
    }
}


class BgaVisibleSystemException extends BgaSystemException {
    public function __construct($message, $code = 100) {
        parent::__construct($message, $code);
    }
}

class feException extends Exception {
    public function __construct($message, $expected = false, $visibility = true, $code = 100, $publicMsg = '') {
        parent::__construct($message, $code);
    }
}

abstract class Table extends APP_GameClass {
    var $players = array();
    public $gamename;
    public $gamestate = null;
    public bool $not_a_move_notification = false;

    public function __construct() {
        parent::__construct();
        $this->gamestate = new GameState();
        $this->players = array(
            1 => array('player_name' => $this->getActivePlayerName(), 'player_color' => 'ff0000'),
            2 => array('player_name' => 'player2', 'player_color' => '0000ff')
        );
    }

    /** Report gamename for translation function */
    abstract protected function getGameName();

    function getAllTableDatas() {
        return [];
    }

    function getActivePlayerId() {
        return 1;
    }

    function getActivePlayerName() {
        return "player1";
    }

    function getTableOptions() {
        return [];
    }

    function getTablePreferences() {
        return [];
    }

    function loadPlayersBasicInfos() {
        $default_colors = array("ff0000", "008000", "0000ff", "ffa500", "4c1b5b");
        $values = array();
        $id = 1;
        foreach ($default_colors as $color) {
            $values[$id] = array('player_id' => $id, 'player_color' => $color, 'player_name' => "player$id", 'player_zombie' => 0);
            $id++;
        }
        return $values;
    }

    protected function getCurrentPlayerId() {
        return 1;
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

    public function getPlayerNameById($player_id) {
        $players = self::loadPlayersBasicInfos();
        return $players[$player_id]['player_name'];
    }
    public function getPlayerNoById($player_id) {
        $players = self::loadPlayersBasicInfos();
        return $players[$player_id]['player_no'];
    }
    public function getPlayerColorById($player_id) {
        $players = self::loadPlayersBasicInfos();
        return $players[$player_id]['player_color'];
    }
    function eliminatePlayer($player_id) {

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
     * if "bThrowException" is set to "false", the function return false in case of failure instead of throwing and exception
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
        return array();
    }

    function createPrevPlayerTable($players, $bLoop = true) {
        return array();
    }

    function notifyAllPlayers($type, $message, $args) {
        $args2 = array();
        foreach ($args as $key => $val) {
            $key = '${' . $key . '}';
            $args2[$key] = $val;
        }
        echo "$type: $message\n";
        //. strtr($message,                $args2)
        echo "\n";
    }

    function notifyPlayer($player_id, $notification_type, $notification_log, $notification_args) {
    }

    function getStatTypes() {
        return [
            'player' => [],
            'table' => [],
        ];
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

    function getNew($deck_definition): object {
        return null;
    }

    // Give standard extra time to this player
    // (standard extra time is a game option)
    function giveExtraTime($player_id, $specific_time = null) {
    }

    function getStandardGameResultObject(): array {
        return array();
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
        require('gameinfos.inc.php');
        if (isset($gameinfos)) {
            return $gameinfos;
        }
        throw new feException("gameinfos.inp.php suppose to define \$gameinfos variable");
    }

    /* Method to override to set up each game */
    abstract protected function setupNewGame($players, $options = array());

    public function stMakeEveryoneActive() {
        $this->gamestate->setAllPlayersMultiactive();
    }

    /* save undo state after all transations are done */
    function undoSavepoint() {
    }

    /* restored db to saved state */
    function undoRestorePoint() {
    }

    function getBgaEnvironment() {
        return "studio";
    }

    function say($text) {
        return;
    }
}

class Page {
    public $blocks = array();

    public function begin_block($template, $block) {
        $this->blocks[$block] = array();
    }

    public function insert_block($block, $args) {
        $this->blocks[$block][] = $args;
    }
}

class GUser {

    public function get_id() {
        return 1;
    }
}


// Arg types
define('AT_int', 0);        //  an integer
define('AT_posint', 1);     //  a positive integer 
define('AT_float', 2);      //  a float
define('AT_email', 3);      //  an email  
define('AT_url', 4);        //  a URL
define('AT_bool', 5);       //  1/0/true/false
define('AT_enum', 6);       //  argTypeDetails list the possible values
define('AT_alphanum', 7);   //  only 0-9a-zA-Z_ and space
define('AT_username', 8);   //  TEL username policy: alphanum caracters + accents
define('AT_login', 9);      //  AT_username | AT_email
define('AT_numberlist', 13);   //  exemple: 1,4;2,3;-1,2
define('AT_uuid', 17);         // an UUID under the forme 0123-4567-89ab-cdef
define('AT_version', 18);         // A tournoi site version (ex: 100516-1243)
define('AT_cityname', 20);         // City name: 0-9a-zA-Z_ , space, accents, ' and -
define('AT_filename', 21);         // File name: 0-9a-zA-Z_ , and "."
define('AT_groupname', 22);   //  4-50 alphanum caracters + accents + :
define('AT_timezone', 23);   //  alphanum caracters + /
define('AT_mediawikipage', 24);   // Mediawiki valid page name
define('AT_html_id', 26);   // HTML identifier: 0-9a-zA-Z_-
define('AT_alphanum_dash', 27);   //  only 0-9a-zA-Z_ and space + dash
define('AT_date', 28);   //  0-9 + "/" + "-"
define('AT_num', 29);   //  0-9
define('AT_alpha_strict', 30);   //  only a-zA-Z
define('AT_namewithaccent', 31);         // Like City name: 0-9a-zA-Z_ , space, accents, ' and -
define('AT_json', 32);         // JSON string
define('AT_base64', 33);         // Base64 string

define("FEX_bad_input_argument", 300);

class APP_GameAction extends  APP_Action {
    protected $game;
    protected $view;
    protected $viewArgs;
    function getArg($name, $type, $mandatory = true, $default = null) {
        return '';
    }
    protected function setAjaxMode($bCheckCsrf = true) {
    }
    protected function ajaxResponse($data = '') {
    }
    protected function isArg($argName) {
        return true;
    }
}

function totranslate($text) {
    return $text;
}

function clienttranslate($x) {
    return $x;
}

function mysql_fetch_assoc($res) {
    return array();
}

function bga_rand($min, $max) {
    return 0;
}

function getKeysWithMaximum($array, $bWithMaximum = true) {
    return array();
}

function getKeyWithMaximum($array) {
    return '';
}

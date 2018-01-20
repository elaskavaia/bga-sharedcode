<?php

/**
 * This class contains more useful method which is missing from Table class.
 * To use extend this instead instead of Table, i.e
 *
 <code>
 require_once (APP_GAMEMODULE_PATH . 'module/table/table.game.php');
 require_once ('modules/tokens.php');
 require_once ('modules/APP_Extended.php');
 
 class BattleShip extends APP_Extended {
 }
 </code>
 *
 */
abstract class APP_Extended extends Table {

    function __construct() {
        parent::__construct();
        self::initGameStateLabels(array ("move_nbr" => 6 ));
        $this->gameinit = false;
    }
    
    public function initPlayers($players) {
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos ['player_colors'];
        shuffle($default_colors);
        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array ();
        foreach ( $players as $player_id => $player ) {
            $color = array_shift($default_colors);
            $values [] = "('" . $player_id . "','$color','" . $player ['player_canal'] . "','" . addslashes($player ['player_name']) . "','" . addslashes($player ['player_avatar']) . "')";
        }
        $sql .= implode($values, ',');
        self::DbQuery($sql);
        if ($gameinfos ['favorite_colors_support'])
            self::reattributeColorsBasedOnPreferences($players, $gameinfos ['player_colors']);
        self::reloadPlayersBasicInfos();
    }
    
    public function initStats() {
        // INIT GAME STATISTIC
        $all_stats = $this->getStatTypes();
        $player_stats = $all_stats ['player'];
        // all my stats starts with la_, that is not French article
        foreach ( $player_stats as $key => $value ) {
            if (startsWith($key, 'game_')) {
                $this->initStat('player', $key, 0);
            }
            if ($key === 'turns_number') {
                $this->initStat('player', $key, 0);
            }
        }
        $table_stats = $all_stats ['table'];
        // all my stats starts with la_, that is not French article
        foreach ( $table_stats as $key => $value ) {
            if (startsWith($key, 'game_')) {
                $this->initStat('table', $key, 0);
            }
            if ($key === 'turns_number') {
                $this->initStat('table', $key, 0);
            }
        }
    }

    // ------ ERROR HANDLING ----------
    /**
     * This will throw an exception if condition is false.
     * The message should be translated and shown to the user.
     *
     * @param $log string
     *            user side log message, translation is needed, use self::_() when passing string to it
     * @throws BgaUserException
     */
    function userAssertTrue($message, $cond = false, $log = "") {
        if ($cond)
            return;
        if ($log)
            $this->warn("$message $log|");
        throw new BgaUserException($message);
    }

    /**
     * This will throw an exception if condition is false.
     * This only can happened if user hacks the game, client must prevent this
     *
     * @param $log string
     *            server side log message, no translation needed
     * @throws BgaUserException
     */
    function systemAssertTrue($log, $cond = false) {
        if ($cond)
            return;
        $move = $this->getGameStateValue('move_nbr');
        //trigger_error("bt") ;
        //$bt = debug_backtrace();
        //$this->dump('bt',$bt);
        $this->error("Internal Error during move $move: $log|");
        throw new BgaUserException(self::_("Internal Error. That should not have happened. Please raise a bug."));
    }

    // ------ NOTIFICATIONS ----------
    function notifyWithName($type, $message = '', $args = null, $player_id = -1) {
        if ($args == null)
            $args = array ();
        $this->systemAssertTrue("Invalid notification signature", is_array($args));
        if (array_key_exists('player_id', $args) && $player_id == - 1) {
            $player_id = $args ['player_id'];
        }
        if ($player_id == - 1)
            $player_id = $this->getActivePlayerId();
        $args ['player_id'] = $player_id;
        if ($message) {
            $player_name = $this->getPlayerName($player_id);
            $args ['player_name'] = $player_name;
        }
        
        if (array_key_exists('_notifType', $args)) {
            $type = $args ['_notifType'];
            unset($args ['_notifType']);
        } 
        if (array_key_exists('noa', $args) || array_key_exists('nop', $args) || array_key_exists('nod', $args)) {
            $type += "Async";
        }
        
        if (array_key_exists('_private', $args) && $args['_private']) {
            unset($args ['_private']);
            $this->notifyPlayer($player_id, $type, $message, $args);
        } else {
            $this->notifyAllPlayers($type, $message, $args);
        }
    }
    
    function notifyAnimate() {
        $this->notifyAllPlayers('animate', '', array());
    }
    
    function notifyAction($action_id) {
        //$this->notifyMoveNumber();
        $this->notifyWithName('playerLog', clienttranslate('${player_name} took action ${token_name}'), array (
                'token_id' => $action_id,  'token_name' => $action_id
        ));
    }

    // ------ PLAYERS ----------
    /**
     *
     * @return integer first player in natural player order
     */
    function getFirstPlayer() {
        $table = $this->getNextPlayerTable();
        return $table [0];
    }

    /**
     *
     * @return string hex color as in players table for the player with $player_id
     */
    function getPlayerColor($player_id) {
        if (! isset($this->players_basic)) {
            $this->players_basic = $this->loadPlayersBasicInfos();
        }
        if (! isset($this->players_basic [$player_id])) {
            return 0;
        }
        return $this->players_basic [$player_id] ['player_color'];
    }

    /**
     *
     * @return string player name based on $player_id
     */
    function getPlayerName($player_id) {
        if (! isset($this->players_basic)) {
            $this->players_basic = $this->loadPlayersBasicInfos();
        }
        if (! isset($this->players_basic [$player_id])) {
            return "unknown";
        }
        return $this->players_basic [$player_id] ['player_name'];
    }

    /**
     *
     * @return integer player id based on hex $color
     */
    function getPlayerIdByColor($color) {
        if (! isset($this->players_basic)) {
            $this->players_basic = $this->loadPlayersBasicInfos();
        }
        if (! isset($this->player_colors)) {
            $this->player_colors = array ();
            foreach ( $this->players_basic as $player_id => $info ) {
                $this->player_colors [$info ['player_color']] = $player_id;
            }
        }
        if (! isset($this->player_colors [$color])) {
            return 0;
        }
        return $this->player_colors [$color];
    }

    /**
     *
     * @return integer player position (as player_no) from database
     */
    function getPlayerPosition($player_id) {
        if (! isset($this->players_basic)) {
            $this->players_basic = $this->loadPlayersBasicInfos();
        }
        if (! isset($this->players_basic [$player_id])) {
            return 0;
        }
        return $this->players_basic [$player_id] ['player_no'];
    }

    /**
     *
     * @return integer number of players
     */
    public function getNumPlayers() {
        if (! isset($this->players_basic)) {
            $this->players_basic = $this->loadPlayersBasicInfos();
        }
        return count($this->players_basic);
    }

    /**
     * Change activate player, also increasing turns_number stats and giving extra time
     */
    function setNextActivePlayerCustom($next_player_id) {
        $this->giveExtraTime($next_player_id);
        $this->incStat(1, 'turns_number', $next_player_id);
        $this->incStat(1, 'turns_number');
        $this->gamestate->changeActivePlayer($next_player_id);
    }

    // ------ DB ----------
    function dbGetScore($player_id) {
        return $this->getUniqueValueFromDB("SELECT player_score FROM player WHERE player_id='$player_id'");
    }

    function dbSetScore($player_id, $count) {
        $this->DbQuery("UPDATE player SET player_score='$count' WHERE player_id='$player_id'");
    }
    
    function dbSetAuxScore($player_id, $score) {
        $this->DbQuery("UPDATE player SET player_score_aux=$score WHERE player_id='$player_id'");
    }
    
    function dbIncScore($inc) {
        $count = $this->dbGetScore($player_id);
        if ($inc != 0) {
            $count += $inc;
            $this->dbSetScore($player_id, $count);
        }
        return $count;
    }
    

    /**
     * Changes the player scrore and sends notification, also update statistic if provided
     * 
     * @param number $player_id - player id
     * @param number $inc - increment of score, can be negative
     * @param string $notif - notification string, '*' - for default notification, '' - for none
     * @param string $stat - name of the player statistic to update (points source) 
     * @return number - current score after increase/descrease
     */
    function dbIncScoreValueAndNotify($player_id, $inc, $notif = '*', $stat = '') {
        $count = $this->dbIncScore($inc);
        if ($notif == '*') {
            if ($inc >= 0)
                $notif = clienttranslate('${player_name} scores ${inc} point(s)');
            else
                $notif = clienttranslate('${player_name} loses ${modinc} point(s)');
        }
        $this->notifyWithName("score", $notif, 
                array ('player_score' => $count,
                       'inc' => $inc, 
                       'modinc' => abs($inc) 
                ), $player_id);
        if ($stat) {
            $this->dbIncStatChecked($inc, $stat, $player_id);
        }
        return $count;
    }


    function dbIncStatChecked($inc, $stat, $player_id) {
        try {
            $all_stats = $this->getStatTypes();
            $player_stats = $all_stats ['player'];
            if (isset($player_stats [$stat])) {
                $this->incStat($inc, $stat, $player_id);
            } else {
                $this->error("statistic $stat is not defined");
            }
        } catch ( Exception $e ) {
            $this->error("error while setting statistic $stat");
            $this->dump('err', $e);
        }
    }
    

}

function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, - strlen($haystack)) !== false;
}

function endsWith($haystack, $needle) {
    $length = strlen($needle);
    return $length === 0 || (substr($haystack, - $length) === $needle);
}

function getPart($haystack, $i) {
    try {
        $parts = explode('_', $haystack);
        return $parts [$i];
    } catch ( Exception $e ) {
        throw new BgaUserException("Internal error: Access $i to $haystack: $e");
    }
}
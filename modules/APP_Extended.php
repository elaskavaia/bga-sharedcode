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
        self::initGameStateLabels(
                array (
                        "move_nbr" => 6,
                ));
        $this->gameinit = false;
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
            $this->warn($message . " " . $log);
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
        //throw new feException($log);
        throw new BgaUserException(self::_("Internal Error. That should not have happened. Please raise a bug. ")); 
    }
    
    // ------ NOTIFICATIONS ----------

    function notifyWithName($type, $message = '', $args = null, $player_id = -1) {
        if ($args == null)
            $args = array ();
        $this->systemAssertTrue("Invalid notification signature", is_array($args));
        if ($player_id == - 1)
            $player_id = $this->getActivePlayerId();
        $args ['player_id'] = $player_id;
        if ($message) {
            $player_name = $this->getPlayerName($player_id);
            $args ['player_name'] = $player_name;
        }
        if (isset($args ['_private'])) {
            unset($args ['_private']);
            $this->notifyPlayer($player_id, $type, $message, $args);
        } else {
            $this->notifyAllPlayers($type, $message, $args);
        }
    }
    
    // ------ PLAYERS ----------
    
    /**
     *
     * @return integer first player in natural player order
     */
    function getFirstPlayer(){
        $table = $this->getNextPlayerTable();
        return $table[0];
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
     *
     * Change activate player, also increasing turns_number stats and giving extra time
     */
    function setNextActivePlayerCustom($next_player_id) {
        $this->giveExtraTime($next_player_id);
        $this->incStat(1, 'turns_number', $next_player_id);
        $this->incStat(1, 'turns_number');
        $this->gamestate->changeActivePlayer($next_player_id);
    }
    
    // ------ DB ----------
    
    function dbGetScoreValue($player_id) {
        return $this->getUniqueValueFromDB("SELECT player_score FROM player WHERE player_id='$player_id'");
    }
    
    function dbSetScoreValue($player_id, $count) {
        $this->DbQuery("UPDATE player SET player_score='$count' WHERE player_id='$player_id'");
    }
    
    function dbIncScoreValueAndNotify($player_id, $inc, $notif = '') {
        $count = $this->dbGetScoreValue($player_id);
        if ($inc != 0) {
            $count += $inc;
            $this->dbSetScoreValue($player_id, $count);
        }
        $this->notifyWithName("score", $notif, array ('player_score' => $count,'inc' => $inc ), $player_id);
    }
    
}


function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, - strlen($haystack)) !== false;
}

function getPart($haystack, $i) {
    try {
        $parts = explode('_', $haystack);
        return $parts [$i];
    } catch ( Exception $e ) {
        $this->dump('err', $e);
        return '';
    }
}
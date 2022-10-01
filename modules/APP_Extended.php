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
define("GS_PLAYER_TURN_NUMBER", 'playerturn_nbr');

abstract class APP_Extended extends Table
{
    function __construct()
    {
        parent::__construct();
    }

    /*
     * setupNewGame:
     *
     * This method is called only once, when a new game is launched.
     * In this method, you must setup the game according to the game rules, so that
     * the game is ready to be played.
     */
    protected function setupNewGame($players, $options = array())
    {
        /**
         * ********** Start the game initialization ****
         */
        $this->initPlayers($players);
        $this->initStats();
        // Setup the initial game situation here
        $this->initTables();
        /**
         * ********** End of the game initialization ****
         */
    }


    /**
     * override to setup all custom tables
     */
    protected function initTables()
    {
    }



    public function initPlayers($players)
    {
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];
        shuffle($default_colors);
        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach ($players as $player_id => $player) {
            $color = array_shift($default_colors);
            $values[] = "('" . $player_id . "','$color','" . $player['player_canal'] . "','" . addslashes($player['player_name']) . "','" . addslashes($player['player_avatar']) . "')";
        }
        $sql .= implode($values, ',');
        self::DbQuery($sql);
        if ($gameinfos['favorite_colors_support'])
            self::reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        self::reloadPlayersBasicInfos();
        $this->activeNextPlayer(); // just in case so its not 0, dev code can change it later
    }

    public function initStats()
    {
        // INIT GAME STATISTIC
        $all_stats = $this->getStatTypes();
        $player_stats = $all_stats['player'];
        // auto-initialize all stats that starts with game_
        // we need a prefix because there is some other system stuff
        foreach ($player_stats as $key => $value) {
            if (startsWith($key, 'game_')) {
                $this->initStat('player', $key, 0);
            }
            if ($key === 'turns_number') {
                $this->initStat('player', $key, 0);
            }
        }
        $table_stats = $all_stats['table'];
        foreach ($table_stats as $key => $value) {
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
     * @param $message string
     *            user side error message, translation is needed, use self::_() when passing string to it
     * @param $cond boolean condition of assert
     * @param $log string optional log message, not need to translate
     * @throws BgaUserException
     */
    function userAssertTrue($message, $cond = false, $log = "")
    {
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
     * @param  string $log  
     *                      server side log message, no translation needed
     * @param  bool   $cond 
     *                      condition of assert
     * @throws   BgaUserException
     */
    function systemAssertTrue($log, $cond = false)
    {
        if ($cond)
            return;
        $move = $this->getGameStateValue('playerturn_nbr');
        $this->error("Internal Error during move $move: $log|");
        $e = new Exception($log);
        $this->error($e->getTraceAsString());
        throw new BgaUserException($this->_("Internal Error. That should not have happened. Please raise a bug."));
    }

    // ------ NOTIFICATIONS ----------
    function notifyWithName($type, $message = '', $args = null, $player_id = -1)
    {
        if ($args == null)
            $args = array();
        $this->systemAssertTrue("Invalid notification signature", is_array($args));
        if (array_key_exists('player_id', $args) && $player_id == -1) {
            $player_id = $args['player_id'];
        }
        if ($player_id == -1)
            $player_id = $this->getMostlyActivePlayerId();
        if ($player_id != 'all')
            $args['player_id'] = $player_id;
        if ($message) {
            $player_name = $this->getPlayerNameById($player_id);
            $args['player_name'] = $player_name;
        }
        if (array_key_exists('_notifType', $args)) {
            $type = $args['_notifType'];
            unset($args['_notifType']);
        }
        if (array_key_exists('noa', $args) || array_key_exists('nop', $args) || array_key_exists('nod', $args)) {
            $type .= "Async";
        }
        if (array_key_exists('_private', $args) && $args['_private']) {
            unset($args['_private']);
            $this->notifyPlayer($player_id, $type, $message, $args);
        } else {
            $this->notifyAllPlayers($type, $message, $args);
        }
    }

    function getMostlyActivePlayerId()
    {
        $state = $this->gamestate->state();
        if ($state['type'] === "multipleactiveplayer") {
            return $this->getCurrentPlayerId();
        } else {
            return $this->getActivePlayerId();
        }
    }
    function notifyAnimate()
    {
        $this->notifyAllPlayers('animate', '', array());
    }

    function notifyAction($action_id)
    {
        //$this->notifyMoveNumber();
        $this->notifyWithName('playerLog', clienttranslate('${player_name} took action ${token_name}'), array(
            'token_id' => $action_id, 'token_name' => $action_id
        ));
    }

    // ------ PLAYERS ----------
    /**
     *
     * @return integer first player in natural player order
     */
    function getFirstPlayer()
    {
        $table = $this->getNextPlayerTable();
        return $table[0];
    }

    /**
     *
     * @return string hex color as in players table for the player with $player_id
     */
    function getPlayerColor($player_id)
    {
        $players = $this->loadPlayersBasicInfos();
        if (!isset($players[$player_id])) {
            return 0;
        }
        return $players[$player_id]['player_color'];
    }

    /**
     *
     * @return integer player id based on hex $color
     */
    function getPlayerIdByColor($color)
    {
        $players = $this->loadPlayersBasicInfos();
        if (!isset($this->player_colors)) {
            $this->player_colors = array();
            foreach ($players as $player_id => $info) {
                $this->player_colors[$info['player_color']] = $player_id;
            }
        }
        if (!isset($this->player_colors[$color])) {
            return 0;
        }
        return $this->player_colors[$color];
    }

    /**
     *
     * @return integer player position (as player_no) from database
     */
    function getPlayerPosition($player_id)
    {
        $players = $this->loadPlayersBasicInfos();
        if (!isset($players[$player_id])) {
            return -1;
        }
        return $players[$player_id]['player_no'];
    }

    public function getStateName()
    {
        $state = $this->gamestate->state();
        return $state['name'];
    }

    /**
     * @return array of player ids
     */
    function getPlayerIds()
    {
        $players = $this->loadPlayersBasicInfos();
        return array_keys($players);
    }

    function getPlayerIdsInOrder($starting)
    {
        $player_ids = $this->getPlayerIds();
        $rotate_count = array_search($starting, $player_ids);
        if ($rotate_count === false) {
            return $player_ids;
        }
        for ($i = 0; $i < $rotate_count; $i++) {
            array_push($player_ids, array_shift($player_ids));
        }
        return $player_ids;
    }

    /**
     * Return player table in order starting from $staring player id, if $starting is not in the player table
     * i.e. spectator returns same as loadPlayersBasicInfos(), i.e. natural player order
     * This is useful in view.php file
     * @param number $starting - player number
     * @return string[][] - map of playerId => playerInfo
     */
    function getPlayersInOrder($starting)
    {
        $players = $this->loadPlayersBasicInfos();
        $player_ids = $this->getPlayerIdsInOrder($starting);
        $result = [];
        foreach ($player_ids as $player_id) {
            $result[$player_id] = $players[$player_id];
        }
        return $result;
    }

    function debugConsole($info, $args = array())
    {
        $this->notifyAllPlayers("log", $info, $args);
        $this->warn($info);
    }

    /**
     * Change activate player, also increasing turns_number stats and giving extra time
     */
    function setNextActivePlayerCustom($next_player_id)
    {
        if ($this->getActivePlayerId() == $next_player_id)
            return;
        $this->giveExtraTime($next_player_id);
        $this->incStat(1, 'turns_number', $next_player_id);
        $this->incStat(1, 'turns_number');
        $this->gamestate->changeActivePlayer($next_player_id);
    }

    // ------ DB ----------
    function dbGetScore($player_id)
    {
        return $this->getUniqueValueFromDB("SELECT player_score FROM player WHERE player_id='$player_id'");
    }

    function dbSetScore($player_id, $count)
    {
        $this->DbQuery("UPDATE player SET player_score='$count' WHERE player_id='$player_id'");
    }

    function dbSetAuxScore($player_id, $score)
    {
        $this->DbQuery("UPDATE player SET player_score_aux=$score WHERE player_id='$player_id'");
    }

    function dbIncScore($player_id, $inc)
    {
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
     * @param number $player_id
     *            - player id
     * @param number $inc
     *            - increment of score, can be negative
     * @param string $notif
     *            - notification string, '*' - for default notification, '' - for none
     * @param string $stat
     *            - name of the player statistic to update (points source)
     * @return number - current score after increase/descrease
     */
    function dbIncScoreValueAndNotify($player_id, $inc, $notif = '*', $stat = '', $args = null)
    {
        if ($args == null)
            $args = [];
        $count = $this->dbIncScore($player_id, $inc);
        if ($notif == '*') {
            if ($inc >= 0)
                $notif = clienttranslate('${player_name} scores ${inc} point(s)');
            else
                $notif = clienttranslate('${player_name} loses ${mod} point(s)');
        }
        $this->notifyWithName(
            "score",
            $notif, // 
            array_merge(array('player_score' => $count, 'inc' => $inc, 'mod' => abs($inc)), $args), // 
            $player_id
        );
        if ($stat) {
            $this->dbIncStatChecked($inc, $stat, $player_id);
        }
        return $count;
    }

    function dbIncStatChecked($inc, $stat, $player_id)
    {
        try {
            $all_stats = $this->getStatTypes();
            $player_stats = $all_stats['player'];
            if (isset($player_stats[$stat])) {
                $this->incStat($inc, $stat, $player_id);
            } else {
                $this->error("statistic $stat is not defined");
            }
        } catch (Exception $e) {
            $this->error("error while setting statistic $stat");
            $this->dump('err', $e);
        }
    }


    /**
     * Changes values of multiactivity in db, does not sent notifications.
     * To send notifications after use updateMultiactiveOrNextState
     * @param number $player_id, player id <=0 or null - means ALL
     * @param number $value - 1 multiactive, 0 non multiactive
     */
    function dbSetPlayerMultiactive($player_id = -1, $value = 1)
    {
        if (!$value)
            $value = 0;
        else
            $value = 1;
        $sql = "UPDATE player SET player_is_multiactive = '$value' WHERE player_zombie = 0 and player_eliminated = 0";
        if ($player_id > 0) {
            $sql .= " AND player_id = $player_id";
        }
        self::DbQuery($sql);
    }

    function isPlayerMaskSet($player_id, $variable)
    {
        $mask = $this->getGameStateValue($variable);
        $no = $this->getPlayerPosition($player_id);
        $bit = (1 << $no);
        if (($mask & $bit) == 0) {
            return false;
        } else {
            return true;
        }
    }

    function setPlayerMask($player_id, $variable, $force = false)
    {
        $mask = $this->getGameStateValue($variable);
        $no = $this->getPlayerPosition($player_id);
        $bit = (1 << $no);
        if ($force || ($mask & $bit) == 0) {
            $mask |= $bit; // set the bit
        } else {
            $this->systemAssertTrue("Player already has this mask set for $variable");
        }
        $this->setGameStateValue($variable, $mask);
    }

    function clearPlayerMask($player_id, $variable)
    {
        $mask = $this->getGameStateValue($variable);
        $no = $this->getPlayerPosition($player_id);
        $bit = (1 << $no);
        $mask &= ~$bit; // clear bit
        $this->setGameStateValue($variable, $mask);
    }
}

// GLOBAL utility functions

function startsWith($haystack, $needle)
{
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    return $length === 0 || (substr($haystack, -$length) === $needle);
}

function getPart($haystack, $i, $bNoexeption = false)
{
    $parts = explode('_', $haystack);
    $len = count($parts);
    if ($bNoexeption && $i >= $len)
        return "";
    return $parts[$i];
}

function getPartsPrefix($haystack, $i)
{
    $parts = explode('_', $haystack);
    $len = count($parts);
    if ($i < 0) {
        $i = $len + $i;
    }
    if ($i <= 0)
        return '';
    for (; $i < $len; $i++) {
        unset($parts[$i]);
    }
    return implode('_', $parts);
}

if (!function_exists('array_key_first')) {
    function array_key_first(array $arr)
    {
        foreach ($arr as $key => $unused) {
            return $key;
        }
        return NULL;
    }
}

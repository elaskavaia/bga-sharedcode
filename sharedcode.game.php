<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SharedCode implementation : © Alena Laskavaia <laskava@gmail.com>
 * 
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 * 
 * sharedcode.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 */
require_once (APP_GAMEMODULE_PATH . 'module/table/table.game.php');

class SharedCode extends Table {

    function __construct() {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
        $this->gameinit = false;
        self::initGameStateLabels(array (
           // reserved globals 1..10
           'move_nbr' => 6,
           // game global vars starts at 10
           "round" => 10,
           "resource_id_counter" => 11,
         
        //      ...
        //    "my_first_game_variant" => 100,
        //    "my_second_game_variant" => 101,
        //      ...
        ));
    }

    protected function getGameName() {
        // Used for translations and stuff. Please do not modify.
        return "sharedcode";
    }

        /*
     * setupNewGame:
     *
     * This method is called only once, when a new game is launched.
     * In this method, you must setup the game according to the game rules, so that
     * the game is ready to be played.
     */
    protected function setupNewGame($players, $options = array()) {
        $this->gameinit = true;
        try {
            // Set the colors of the players with HTML color code
            // The default below is red/green/blue/orange/brown
            // The number of colors defined here must correspond to the maximum number of players allowed for the gams
            $default_colors = array ("ff0000","008000","0000ff","ffa500","773300" );
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
            self::reattributeColorsBasedOnPreferences($players, array ("ff0000","008000","0000ff","ffa500","773300" ));
            self::reloadPlayersBasicInfos();
            /**
             * ********** Start the game initialization ****
             */
            // Init global values with their initial values
            self::setGameStateInitialValue('resource_id_counter', 1);
            self::setGameStateInitialValue('round', 0);
            // Init game statistics
            // (note: statistics used in this file must be defined in your stats.inc.php file)
            //self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
            //self::initStat( 'player', 'player_teststat1', 0 );  // Init a player statistics (for all players)
            // TODO: setup the initial game situation here

        } catch ( Exception $e ) {
            $this->dump('err', $e);
            $this->error("Error during game initialization: $e");
        }
        $this->gameinit = false;
        $this->activeNextPlayer();
    /**
     * ********** End of the game initialization ****
     */
    }

    /*
     * getAllDatas:
     *
     * Gather all informations about current game situation (visible by the current player).
     *
     * The method is called each time the game interface is displayed to a player, ie:
     * _ when the game starts
     * _ when a player refreshes the game page (F5)
     */
    protected function getAllDatas() {
        $result = array ('players' => array () );
        $current_player_id = self::getCurrentPlayerId(); // !! We must only return informations visible by this player !!
        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score FROM player ";
        $result ['players'] = self::getCollectionFromDb($sql);
        // TODO: Gather all information about current game situation (visible by player $current_player_id).
        return $result;
    }

    /*
     * getGameProgression:
     *
     * Compute and return the current game progression.
     * The number returned must be an integer beween 0 (=the game just started) and
     * 100 (= the game is finished or almost finished).
     *
     * This method is called each time we are in a game state with the "updateGameProgression" property set to true
     * (see states.inc.php)
     */
    function getGameProgression() {
        $round = $this->getGameStateValue('round');
        if ($round>=6) return 100;
        return 100*$round/6;
    }
        //////////////////////////////////////////////////////////////////////////////
        //////////// Utility functions
        ////////////    
        /*
     * In this space, you can put any utility methods useful for your game logic
     */
    /**
     * This will throw an exception if condition is false.
     * The message should be translated and shown to the user.
     *
     * @param $log is
     *            server side log message, no translation needed
     * @throws BgaUserException
     */
    function userAssertTrue($message, $cond = false, $log = "") {
        if ($cond)
            return;
        if ($log) $this->warn($message . " " . $log);
        throw new BgaUserException($message);
    }

    /**
     * This will throw an exception if condition is false.
     * This only can happened if user hacks the game, client must prevent this
     *
     * @param $log is
     *            server side log message, no translation needed
     * @throws BgaUserException
     */
    function systemAssertTrue($log, $cond = false) {
        if ($cond)
            return;
        $move = $this->getGameStateValue('move_nbr');
        $this->error("Internal Error during move $move: $log|");
        throw new BgaUserException(self::_("Internal Error. That should not have happened. Please raise a bug")); 
    }
    
    /**
     * 
     * @return first player in natural player order
     */
    function getFirstPlayer(){
        $table = $this->getNextPlayerTable();
        return $table[0];
    }
    
    function getPlayerColor($player_id) {
        if (! isset($this->players_basic)) {
            $this->players_basic = $this->loadPlayersBasicInfos();
        }
        if (! isset($this->players_basic [$player_id])) {
            return 0;
        }
        return $this->players_basic [$player_id] ['player_color'];
    }
    
    function getPlayerName($player_id) {
        if (! isset($this->players_basic)) {
            $this->players_basic = $this->loadPlayersBasicInfos();
        }
        if (! isset($this->players_basic [$player_id])) {
            return "unknown";
        }
        return $this->players_basic [$player_id] ['player_name'];
    }
    
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

    function notifyWithName($type, $message = '', $args = null, $player_id = -1) {
        if ($this->gameinit)
            return;
        if ($args == null)
            $args = array ();
        $this->systemAssertTrue("Invalid notification signature", is_array($args));

        if ($player_id==-1)
           $player_id = $this->getActivePlayerId();
        $args ['player_id'] = $player_id;
        if ($message) {
            $player_name = $this->getPlayerName($player_id);
            $args ['player_name'] = $player_name;
        }
        if (isset($args['_private'])) {
            unset($args ['_private']);
            $this->notifyPlayer($player_id, $type, $message, $args);
        } else {
            $this->notifyAllPlayers($type, $message, $args);
        }
    }
    
    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    //////////// 
    /*
     * Each time a player is doing some game action, one of the methods below is called.
     * (note: each method below must match an input method in sharedcode.action.php)
     */
    
    function action_pass(){
        self::notifyWithName( "message", clienttranslate( '${player_name} is passed' ));
        $this->gamestate->nextState('pass');
    }
    
    function action_selectWorkerAction($action_id, $worker_id) {
        self::checkAction('selectWorkerAction');
        switch ($action_id) {
            case 'action_space_1':
                $this->gamestate->nextState('playCubes');
                return;
            default:
                $this->userAssertTrue(self::_("Action is not supported yet"),false,$action_id);
                return;
        }
    }
    
    function action_takeCube($token_id) {
        self::checkAction('takeCube');
        $player_id = self::getActivePlayerId();
        $this->gamestate->nextState('next');
    }
    
    function action_moveCube($token_id) {
        self::checkAction('moveCube');
        $player_id = self::getActivePlayerId();
        $this->gamestate->nextState('next');
    }
    
    function stMultiactive() {
        $this->gamestate->setAllPlayersMultiactive();
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state arguments
    ////////////
    /*
     * Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
     * These methods function is to return some additional information that is specific to the current
     * game state.
     */
    function arg_playerTurn() {
        $round = self::getGameStateValue('round');
        return array ('round' => $round );
    }

    function arg_playerTurnPlayCubes() {
        // Get some values from the current game situation in database...
        $takeCubeNumber = rand(0, 10);
        $counter = self::getGameStateValue('resource_id_counter');
        // return values:
        return array ('cubeTypeNumber' => $takeCubeNumber,'resource_id_counter' => $counter );
    }
        //////////////////////////////////////////////////////////////////////////////
        //////////// Game state actions
        ////////////
    /**
     * Typical function that changes to next player
     */
    function st_gameTurn() {
        $this->activeNextPlayer();
        $player_id = $this->getActivePlayerId();
        $this->giveExtraTime($player_id);
        $first_player_id = $this->getFirstPlayer();
        if ($player_id == $first_player_id) {
            $round = $this->incGameStateValue('round', 1);
            if ($round >= 6) {
                $this->gamestate->nextState('endGame');
                return;
            }
            $this->incStat(1, 'turns_number');
        }
        $this->incStat(1, 'turns_number', $player_id);
        $this->gamestate->nextState('next');
    }
    //////////////////////////////////////////////////////////////////////////////
    //////////// Zombie
    ////////////
    /*
     * zombieTurn:
     *
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: pass).
     */
    function zombieTurn($state, $active_player) {
        $statename = $state ['name'];
        if ($state ['type'] == "activeplayer") {
            switch ($statename) {
                default :
                    $this->gamestate->nextState("zombiePass");
                    break;
            }
            return;
        }
        if ($state ['type'] == "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $sql = "
                UPDATE  player
                SET     player_is_multiactive = 0
                WHERE   player_id = $active_player
            ";
            self::DbQuery($sql);
            $this->gamestate->updateMultiactiveOrNextState('');
            return;
        }
        throw new feException("Zombie mode not supported at this game state: " . $statename);
    }
    ///////////////////////////////////////////////////////////////////////////////////:
    ////////// DB upgrade
    //////////
    /*
     * upgradeTableDb:
     *
     * You don't have to care about this until your game has been published on BGA.
     * Once your game is on BGA, this method is called everytime the system detects a game running with your old
     * Database scheme.
     * In this case, if you change your Database scheme, you just have to apply the needed changes in order to
     * update the game database and allow the game to continue to run with your new version.
     *
     */
    function upgradeTableDb($from_version) {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345
        // Example:
        //        if( $from_version <= 1404301345 )
        //        {
        //            $sql = "ALTER TABLE xxxxxxx ....";
        //            self::DbQuery( $sql );
        //        }
        //        if( $from_version <= 1405061421 )
        //        {
        //            $sql = "CREATE TABLE xxxxxxx ....";
        //            self::DbQuery( $sql );
        //        }
        //        // Please add your future database scheme changes here
        //
        //
    }
}

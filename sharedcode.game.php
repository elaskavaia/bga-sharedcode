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
require_once (APP_GAMEMODULE_PATH . 'module/common/deck.game.php');


require_once ('modules/EuroGame.php');

class SharedCode extends EuroGame {

    function __construct() {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
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
        $this->cards = self::getNew( "module.common.deck" );
        $this->cards->init( "card" );

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
    protected function setupNewGame($players, $options = array ()) {
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

    protected function initTables() {
  
        // Create cards
        $cards = array();
 
        $cards[] = array( 'type' => 'card_red', 'type_arg' => 1, 'nbr' => 10);
        $cards[] = array( 'type' => 'card_blue', 'type_arg' => 2, 'nbr' => 8);
        $cards[] = array( 'type' => 'card_green', 'type_arg' => 3, 'nbr' => 5);
     
        
        $this->cards->createCards( $cards, 'deck' );
        $this->cards->shuffle('deck');
        $players = self::loadPlayersBasicInfos();
        foreach ( $players as $player_id => $player ) {
            $this->cards->pickCards(3, 'deck', $player_id);
        }
        
        // Init global values with their initial values
        self::setGameStateInitialValue('resource_id_counter', 1);
        self::setGameStateInitialValue('round', 0);
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
        $result = parent::getAllDatas();
        $current_player_id = self::getCurrentPlayerId();
        $cards = $this->cards->getCardsInLocation( 'hand', $current_player_id+0, "type_arg" );
        
        $result['hand']=$cards;
        $result['playarea']= $this->cards->getCardsInLocation( 'playarea', null, "location_arg"  );
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
            case 'action_space_2':
                $this->gamestate->nextState('playCards');
                return;
            default:
                $this->userAssertTrue(self::_("Action is not supported yet"),false,$action_id);
                return;
        }
    }

    function action_takeCube($token_id, $place_id) {
        self::checkAction('takeCube');
        $this->dbSetTokenLocation($token_id, $place_id, 0);
        $this->gamestate->nextState('next');
    }

    function action_moveCube($token_id, $place_id) {
        self::checkAction('moveCube');
        $this->dbSetTokenLocation($token_id, $place_id, 0);
        $this->gamestate->nextState('next');
    }
    
    function action_drawCard() {
        self::checkAction('drawCard');
        $player_id = self::getActivePlayerId();
        $cards = $this->cards->pickCards(1, 'deck', $player_id);
        $this->notifyPlayer($player_id, 'moveCard', '${You} draw a card', [ 'You' => 'You', 'card' => $cards[0] ]);
        $this->gamestate->nextState('next');
    }
    
    function action_playCard($card_id) {
        self::checkAction('playCard');
        $player_id = self::getActivePlayerId();
        $this->cards->moveCard($card_id,'playarea');
        $this->notifyWithName('moveCard', '${player_name} draw a card', [  'card' => $this->cards->getCard($card_id) ], $player_id);
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
        $takeCubeNumber = bga_rand(0, 10);
        $counter = self::getGameStateValue('resource_id_counter');
        // return values:
        return array ('cubeTypeNumber' => $takeCubeNumber,'resource_id_counter' => $counter );
    }
    function arg_playerTurnPlayCards() {
        return [];
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

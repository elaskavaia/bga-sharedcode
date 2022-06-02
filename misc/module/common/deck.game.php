<?php

/*
 * -
 * This is STUB for Deck component
 *
 * On DB side this is based on a standard table with the following fields:
 *
 *
 * CREATE TABLE IF NOT EXISTS `card` (
 * `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
 * `card_type` varchar(16) NOT NULL,
 * `card_type_arg` int(11) NOT NULL,
 * `card_location` varchar(16) NOT NULL,
 * `card_location_arg` int(11) NOT NULL,
 * PRIMARY KEY (`card_id`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
 *
 *
 */
class Deck extends APP_GameClass {
    var $table;
    var $autoreshuffle = false; // If true, a new deck is automatically formed with a reshuffled discard as soon at is needed
    var $autoreshuffle_trigger = null; // Callback to a method called when an autoreshuffle occurs
    // autoreshuffle_trigger = array( 'obj' => object, 'method' => method_name )
    // If defined, tell the name of the deck and what is the corresponding discard (ex : "mydeck" => "mydiscard")
    var $autoreshuffle_custom = array ();


    function __construct() {
        $this->table = 'card';
    }

    // MUST be called before any other method if db table is not called 'card'
    function init($table) {
        $this->table = $table;
    }

    // This is the way cards are created and should not be called during the game.
    // Cards are added to the deck (not shuffled)
    // Cards is an array of "card types" with at least the followin fields:
    // array(
    //      array(                              // This is my first card type
    //          "type" => "name of this type"   // Note: <10 caracters
    //          "type_arg" => <type arg>        // Argument that should be applied to all card of this card type
    //          "nbr" => <nbr>                  // Number of cards with this card type to create in game
    //
    // If location_arg is not specified, cards are placed at location extreme position
    function createCards($cards, $location_global, $card_state_global = null) {
       return;
    }

    // Get max on min state on the specific location
    function getExtremePosition($getMax, $location, $card_key = null) {
            return 0;
    }

    // Shuffle card of a specified location, result of the operation will changes state of the card to be a position after shuffling
    function shuffle($location) {

    }

    function deleteAll() {
        self::DbQuery("DELETE FROM " . $this->table);
    }
    
    // Pick the first card on top of specified deck and give it to specified player
    // Return card infos or null if no card in the specified location
    function pickCard( $location, $player_id )
    {
        return self::pickCardForLocation( $location, "hand", $player_id );
    }
    
    // Pick the "nbr" first cards on top of specified deck and give it to specified player
    // Return card infos (array) or null if no card in the specified location
    function pickCards( $nbr, $location, $player_id )
    {
        return self::pickCardsForLocation( $nbr, $location, "hand", $player_id );
    }
    
    // Pick the first card on top of specified deck and place it in target location
    // Return card infos or null if no card in the specified location
    function pickCardForLocation( $from_location, $to_location, $location_arg=0 )
    {
        return null;
    }

    // Pick the first "$nbr" cards on top of specified deck and place it in target location
    // Return cards infos or void array if no card in the specified location
    function pickCardsForLocation($nbr, $from_location, $to_location, $state = 0, $no_deck_reform = false) {
        self::checkLocation($from_location);
        return [];
    }
    
    

    /**
     * Return card on top of this location, top defined as item with higher state value
     */
    function getCardOnTop($location) {
        self::checkLocation($location);
        return null;
    }

    /**
     * Return "$nbr" cards on top of this location, top defined as item with higher state value
     */
    function getCardsOnTop($nbr, $location) {
        self::checkLocation($location);
        return [];
    }

    function reformDeckFromDiscard( $from_location='deck' ) {
        self::checkLocation($from_location);
    }


    // Move a card to specific location
    function moveCard( $card_id, $location, $location_arg=0 )
    {
        self::checkLocation($location);
        self::checkLocationArg($location_arg);
    }

    // Move cards to specific location
    function moveCards( $cards, $location, $location_arg=0 )
    {
        self::checkLocation($location);
        self::checkLocationArg($location_arg);
    }

    // Move a card to a specific location where card are ordered. If location_arg place is already taken, increment
    // all cards after location_arg in order to insert new card at this precise location
    function insertCard( $card_id, $location, $location_arg ) {
        self::checkLocation($location);
        self::checkLocationArg($location_arg);

    }

    function insertCardOnExtremePosition($card_key, $location, $bOnTop) {
        $extreme_pos = self::getExtremePosition($bOnTop, $location);
        if ($bOnTop)
            self::insertCard($card_key, $location, $extreme_pos + 1);
        else
            self::insertCard($card_key, $location, $extreme_pos - 1);
    }

        // Move all cards from a location to another
        // !!! state is reset to 0 or specified value !!!
        // if "from_location" and "from_state" are null: move ALL cards to specific location
    function moveAllCardsInLocation($from_location, $to_location, $from_location_arg = null, $to_location_arg = 0) {
        if ($from_location != null)
            self::checkLocation($from_location);
        self::checkLocation($to_location);
    }

    /**
     * Move all cards from a location to another location arg stays with the same value
     */
    function moveAllCardsInLocationKeepOrder($from_location, $to_location) {
        self::checkLocation($from_location);
        self::checkLocation($to_location);

    }

    // Return all cards in specific location
    // note: if "order by" is used, result object is NOT indexed by card ids
    function getCardsInLocation( $location, $location_arg = null, $order_by = null )
    {
        return [];
    }

    function getPlayerHand( $player_id )
    {
        return self::getCardsInLocation( "hand", $player_id );
    }


    // Get specific card infos
    function getCard( $card_id )
    {
        $sql = "SELECT card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg ";
        $sql .= "FROM ".$this->table;
        $sql .= " WHERE card_id='$card_id' ";
        $dbres = self::DbQuery( $sql );
        return mysql_fetch_assoc( $dbres );
    }

    // Get specific cards infos
    function getCards( $cards_array )
    {
        return [];
    }

    
    // Get cards from their IDs (same as getCards), but with a location specified. Raises an exception if the cards are not in the specified location.
    function getCardsFromLocation( $cards_array, $location, $location_arg = null )
    {
        return [];
    }

    // Get card of a specific type
    function getCardsOfType( $type, $type_arg=null )
    {
      return [];
    }
    
    // Get cards of a specific type in a specific location
    function getCardsOfTypeInLocation( $type, $type_arg=null, $location, $location_arg = null )
    {
        return [];
    }
    
    // Move a card to discard pile
    function playCard( $card_id )
    {

    }
    
    
    // Return count of cards in location with optional arg
    function countCardsInLocation( $location, $location_arg=null )
    {
        return 0;
    }
    
    // Return an array "location" => number of cards
    function countCardsInLocations( )
    {

        return [];
    }
    // Return an array "location_arg" => number of cards (for this location)
    function countCardsByLocationArgs( $location )
    {
        return [];
        
    }

    final function checkLocation($location, $like = false) {
        if ($location == null)
            throw new feException("location cannot be null");
        $extra = "";
        if ($like)
            $extra = "%";
        if (preg_match("/^[A-Za-z${extra}][A-Za-z_0-9${extra}-]*$/", $location) == 0) {
            throw new feException("location must be alphanum and underscore non empty string");
        }
    }

    final function checkLocationArg($location_arg, $canBeNull = false) {
        if ($location_arg === null && $canBeNull == false)
            throw new feException("state cannot be null");
        if ($location_arg !== null && preg_match("/^-*[0-9]+$/", $location_arg) == 0) {
            // $bt = debug_backtrace();
            // trigger_error("bt ".print_r($bt[2],true)) ;
            throw new feException("state must be integer number");
        }
    }
}

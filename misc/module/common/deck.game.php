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
    function init(string $table): void {
        $this->table = $table;
    }

    /**
     * This is the way cards are created and should not be called during the game.
     * Cards are added to the deck (not shuffled)
     * Cards is an array of "card types" with at least the followin fields:
     * array(
     *      array(                              // This is my first card type
     *          "type" => "name of this type"   // Note: <10 caracters
     *          "type_arg" => <type arg>        // Argument that should be applied to all card of this card type
     *          "nbr" => <nbr>                  // Number of cards with this card type to create in game
     *
     * If location_arg is not specified, cards are placed at location extreme position
     */
    function createCards(array $cards, string $location = 'deck', ?int $location_arg = null) {}

    /**
     * Get position of extreme cards (top or back) on the specific location.
     */
    function getExtremePosition(bool $getMax , string $location): int
    {
        return 0;
    }
    

    /**
     * Shuffle cards of a specified location.
     */
    function shuffle(string $location)
    {
    }
    

    function deleteAll() {
        self::DbQuery("DELETE FROM " . $this->table);
    }
    
    /**
     * Pick the first card on top of specified deck and give it to specified player.
     * Return card infos or null if no card in the specified location.
     */
    function pickCard(string $location, int $player_id): ?array
    {
        return self::pickCardForLocation( $location, "hand", $player_id );
    }
    
    /**
     * Pick the "nbr" first cards on top of specified deck and give it to specified player.
     * Return card infos (array) or null if no card in the specified location.
     */
    function pickCards(int $nbr, string $location, int $player_id): ?array
    {
        return self::pickCardsForLocation( $nbr, $location, "hand", $player_id );
    }
    
    /**
     * Pick the first card on top of specified deck and place it in target location.
     * Return card infos or null if no card in the specified location.
     */
    function pickCardForLocation(string $from_location, string $to_location, int $location_arg=0 ): ?array
    {
        return null;
    }

    /**
     * Pick the first "$nbr" cards on top of specified deck and place it in target location.
     * Return cards infos or void array if no card in the specified location.
     */
    function pickCardsForLocation(int $nbr, string $from_location, string $to_location, int $location_arg=0, bool $no_deck_reform=false ): ?array {
        self::checkLocation($from_location);
        return [];
    }
    
    

    /**
     * Return card on top of this location.
     */
    function getCardOnTop(string $location): ?array {
        self::checkLocation($location);
        return null;
    }

    /**
     * Return "$nbr" cards on top of this location.
     */
    function getCardsOnTop(int $nbr, string $location): ?array{
        self::checkLocation($location);
        return [];
    }

    function reformDeckFromDiscard( $from_location='deck' ) {
        self::checkLocation($from_location);
    }


    /**
     * Move a card to specific location.
     */
    function moveCard(int $card_id, string $location, int $location_arg=0): void
    {
        self::checkLocation($location);
        self::checkLocationArg($location_arg);
    }
    
    /**
    * Move cards to specific location.
    */
    function moveCards(array $cards, string $location, int $location_arg=0): void
    {
        self::checkLocation($location);
        self::checkLocationArg($location_arg);
    }

    /**
     * Move a card to a specific location where card are ordered. If location_arg place is already taken, increment
     * all cards after location_arg in order to insert new card at this precise location.
     */
    function insertCard(int $card_id, string $location, int $location_arg ): void
    {
        self::checkLocation($location);
        self::checkLocationArg($location_arg);

    }

    /**
     * Move a card on top or at bottom of given "pile" type location. (Lower numbers: bottom of the deck. Higher numbers: top of the deck.)
     */
    function insertCardOnExtremePosition(int $card_id, string $location, bool $bOnTop): void
    {
        $extreme_pos = self::getExtremePosition($bOnTop, $location);
        if ($bOnTop)
            self::insertCard($card_key, $location, $extreme_pos + 1);
        else
            self::insertCard($card_key, $location, $extreme_pos - 1);
    }

    
    /**
     * Move all cards from a location to another.
     * !!! location arg is reseted to 0 or specified value !!!
     * if "from_location" and "from_location_arg" are null: move ALL cards to specific location
     */
    function moveAllCardsInLocation(?string $from_location, ?string $to_location, ?int $from_location_arg=null, int $to_location_arg=0 ): void
    {
        if ($from_location != null)
            self::checkLocation($from_location);
        self::checkLocation($to_location);
    }

    /**
     * Move all cards from a location to another.
     * location arg stays with the same value
     */
    function moveAllCardsInLocationKeepOrder(string $from_location, string $to_location): void {
        self::checkLocation($from_location);
        self::checkLocation($to_location);

    }

    
    /**
     * Return all cards in specific location.
     * note: if "order by" is used, result object is NOT indexed by card ids
     */
    function getCardsInLocation(string|array $location, ?int $location_arg = null, ?string $order_by = null ): array
    {
        return [];
    }
    
    /**
     * Get all cards in given player hand.
     * Note: This is an alias for: getCardsInLocation( "hand", $player_id )
     */
    function getPlayerHand(int $player_id): array
    {
        return [];
    }
    

    
    /**
     * Get specific card infos
     */
    function getCard(int $card_id ): ?array
    {
        $sql = "SELECT card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg ";
        $sql .= "FROM ".$this->table;
        $sql .= " WHERE card_id='$card_id' ";
        $dbres = self::DbQuery( $sql );
        return mysql_fetch_assoc( $dbres );
    }

    /**
     * Get specific cards infos
     */
    function getCards(array $cards_array ): array
    {
        return [];
    }

    
    /**
     * Get cards from their IDs (same as getCards), but with a location specified. Raises an exception if the cards are not in the specified location.
     */
    function getCardsFromLocation(array $cards_array, string $location, ?int $location_arg = null ): array
    {
        return [];
    }

    /**
     * Get card of a specific type.
     */
    function getCardsOfType(mixed $type, ?int $type_arg=null ): array
    {
      return [];
    }
    
    /**
     * Get cards of a specific type in a specific location.
     */
    function getCardsOfTypeInLocation(mixed $type, ?int $type_arg=null, string $location, ?int $location_arg = null ): array
    {
        return [];
    }
    
    /**
     * Move a card to discard pile.
     */
    function playCard(int $card_id): void
    {
    }
    
    
    /**
     * Return the number of cards in specified location.
     */
    function countCardInLocation(string $location, ?int $location_arg=null): int|string
    {
        return '0';
    }
    
    /**
     * Return the number of cards in specified location.
     */
    function countCardsInLocation(string $location, ?int $location_arg=null): int|string
    {
        return '0';
    }
    
    /**
     * Return an array "location" => number of cards.
     */
    function countCardsInLocations(): array
    {
        return [];
    }
    
    /**
     * Return an array "location_arg" => number of cards (for this location).
     */
    function countCardsByLocationArgs(string $location): array
    {
        return [];
    }

    final function checkLocation(string $location, bool $like = false) {
        if ($location == null)
            throw new feException("location cannot be null");
        $extra = "";
        if ($like)
            $extra = "%";
        if (preg_match("/^[A-Za-z{$extra}][A-Za-z_0-9{$extra}-]*$/", $location) == 0) {
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

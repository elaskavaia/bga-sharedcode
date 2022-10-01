<?php

/*
 * This is a generic class to manage game pieces.
 *
 * On DB side this is based on a standard table with the following fields:
 * token_key (string), token_location (string), token_state (int)
 *
 *
 * CREATE TABLE IF NOT EXISTS `token` (
 * `token_key` varchar(32) NOT NULL,
 * `token_location` varchar(32) NOT NULL,
 * `token_state` int(10),
 * PRIMARY KEY (`token_key`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
 *
 *
 */
class Tokens extends APP_GameClass {
    var $table;
    var $autoreshuffle = false; // If true, a new deck is automatically formed with a reshuffled discard as soon at is needed
    var $autoreshuffle_trigger = null; // Callback to a method called when an autoreshuffle occurs
    // autoreshuffle_trigger = array( 'obj' => object, 'method' => method_name )
    // If defined, tell the name of the deck and what is the corresponding discard (ex : "mydeck" => "mydiscard")
    var $autoreshuffle_custom = array ();
    private $custom_fields;
    private $g_index;

    function __construct() {
        $this->table = 'token';
        $this->custom_fields = array ();
        $this->g_index = array ();
    }

    // MUST be called before any other method if db table is not called 'token'
    function init($table) {
        $this->table = $table;
    }

    // This inserts new records in the database. Generically speaking you should only be calling during setup with some
    // rare exceptions.
    //
    // Tokens are added into location specified, (default is 'deck')
    //
    // Tokens is an array with at least the following fields:
    // array(
    //      array(                              // This is my first token
    //          "key" => <unique key>           // This unique alphanum and underscore key, use {INDEX} to replace with index if 'nbr' > 1, i..e "meeple_{INDEX}_red"
    //          "nbr" => <nbr>                  // Number of tokens with this key, default is 1. If nbr >1 and key does not have {INDEX} it will throw an exception
    //          "location" => <location>        // Optional argument specifies the location, alphanum and underscore
    //          "state" => <state>              // Optional argument specifies integer state, if not specified and $token_state_global is not specified auto-increment is used
    function createTokens($tokens, $location_global, $token_state_global = null) {
        if ($location_global)
            $next_pos = $this->getExtremePosition(true, $location_global) + 1;
        else
            $next_pos = 0;
        $values = array ();
        $keys = array ();
        foreach ( $tokens as $token_info ) {
            if (isset($token_info ['nbr']))
                $n = $token_info ['nbr'];
            else
                $n = 1;
            if (isset($token_info ['nbr_start']))
                $start = $token_info ['nbr_start'];
            else
                $start = 0;
            for ($i = $start; $i < $n + $start; $i++) {
                if (isset($token_info ['location']))
                    $location = $token_info ['location'];
                else
                    $location = $location_global;
                if (isset($token_info ['state']))
                    $token_state = ( int ) ($token_info ['state']);
                else
                    $token_state = $token_state_global;
                if ($token_state === null) {
                    if ($location == $location_global) {
                        $token_state = $next_pos;
                        $next_pos++;
                    } else {
                        $token_state = 0;
                    }
                }
                $key = $token_info ['key'];
                if ($key == null)
                    throw new feException("createTokens: key cannot be null");
                $key = $this->varsub($key, array_merge($token_info, array ('INDEX' => $i )), true);
                if ($location == null)
                    throw new feException("createTokens: location cannot be null (set per token location or location_global");
                self::checkLocation($location);
                self::checkKey($key);
                $values [] = "( '$key', '$location', '$token_state' )";
                $keys [] = $key;
            }
        }
        $sql = "INSERT INTO " . $this->table . " (token_key,token_location,token_state)";
        $sql .= " VALUES " . implode(",", $values);
        $this->DbQuery($sql);
        return $keys;
    }

    function createToken($key, $location, $token_state = 0) {
        self::checkLocation($location);
        self::checkState($token_state);
        self::checkKey($key);
        $values = array ();
        $values [] = "( '$key', '$location', '$token_state' )";
        $sql = "INSERT INTO " . $this->table . " (token_key,token_location,token_state)";
        $sql .= " VALUES " . implode(",", $values);
        $this->DbQuery($sql);
        return $key;
    }

    /**
     * Create tokens during the game (dynamic piles) with auto increment number
     * token must be in form of "prefix_index" where prefix is passed to this function, and index is generated as max
     * number of tokens starting prefix pre-existing in db
     *
     * @param string $type
     * @param string $location
     * @return string - token key
     */
    function createTokenAutoInc($type, $location = 'limbo', $token_state = 0) {
        $allsuf = $this->getTokensOfTypeInLocation($type);
        $resnum = count($allsuf);
        return $this->createToken("${type}_${resnum}", $location, $token_state);
    }

    function createTokensPack($key, $location, $nbr = 1, $nbr_start = 1, $iterArr = null, $token_state = null) {
        if ($iterArr == null)
            $iterArr = array ('' );
        if ( !is_array($iterArr))
            throw new feException("iterArr must be an array");
        if (count($iterArr) == 0)
            $iterArr = array ('' );
        $tokenSpec = array ('key' => $key,'location' => $location,'nbr' => $nbr,'nbr_start' => $nbr_start );
        $tokens = array ();
        foreach ( $iterArr as $iterKey ) {
            $newspec = array ();
            foreach ( $tokenSpec as $tokenSpecKey => $value ) {
                $value = $this->varsub($value, array ('TYPE' => $iterKey ));
                $newspec [$tokenSpecKey] = $value;
            }
            $tokens [] = $newspec;
        }
        return $this->createTokens($tokens, null, $token_state);
    }

    // Get max on min state on the specific location
    function getExtremePosition($getMax, $location, $token_key = null) {
        self::checkLocation($location, true);
        if ($getMax)
            $sql = "SELECT MAX( token_state ) res ";
        else
            $sql = "SELECT MIN( token_state ) res ";
        $sql .= "FROM " . $this->table;
        $like = "LIKE";
        if (strpos($location, "%") === false) {
            $like = "=";
        }
        $sql .= " WHERE token_location $like '$location' ";
        if ($token_key != null) {
            self::checkKey($token_key, true);
            $like = "LIKE";
            if (strpos($token_key, "%") === false) {
                $like = "=";
            }
            $sql .= " AND token_key $like '$token_key' ";
        }
        $dbres = self::DbQuery($sql);
        $row = mysql_fetch_assoc($dbres);
        if ($row)
            return $row ['res'];
        else
            return 0;
    }

    // Shuffle token of a specified location, result of the operation will changes state of the token to be a position after shuffling
    function shuffle($location) {
        self::checkLocation($location);
        $token_keys = self::getObjectListFromDB("SELECT token_key FROM " . $this->table . " WHERE token_location='$location'", true);
        shuffle($token_keys);
        $n = 0;
        foreach ( $token_keys as $token_key ) {
            self::DbQuery("UPDATE " . $this->table . " SET token_state='$n' WHERE token_key='$token_key'");
            $n++;
        }
    }

    function deleteAll() {
        self::DbQuery("DELETE FROM " . $this->table);
    }

    // Pick the first "$nbr" cards on top of specified deck and place it in target location
    // Return cards infos or void array if no card in the specified location
    function pickTokensForLocation($nbr, $from_location, $to_location, $state = 0, $no_deck_reform = false) {
        $tokens = self::getTokensOnTop($nbr, $from_location);
        $tokens_ids = array ();
        foreach ( $tokens as $i => $card ) {
            $tokens_ids [] = $card ['key'];
            $tokens [$i] ['location'] = $to_location;
            $tokens [$i] ['state'] = $state;
        }
        $sql = "UPDATE " . $this->table . " SET token_location='" . addslashes($to_location) . "', token_state='$state' ";
        $sql .= "WHERE token_key IN ('" . implode("','", $tokens_ids) . "') ";
        self::DbQuery($sql);
        if (isset($this->autoreshuffle_custom [$from_location]) && count($tokens) < $nbr && $this->autoreshuffle && !$no_deck_reform) {
            // No more cards in deck & reshuffle is active => form another deck
            $nbr_token_missing = $nbr - count($tokens);
            self::reformDeckFromDiscard($from_location);
            $newcards = self::pickTokensForLocation($nbr_token_missing, $from_location, $to_location, $state, true); // Note: block anothr deck reform
            foreach ( $newcards as $card ) {
                $tokens [] = $card;
            }
        }
        return $tokens;
    }

    /**
     * Return token on top of this location, top defined as item with higher state value
     */
    function getTokenOnTop($location, $no_deck_reform = true) {
        $result_arr = $this->getTokensOnTop(1, $location);
        if (count($result_arr) > 0)
            return $result_arr [0];
        if (isset($this->autoreshuffle_custom [$location]) && $this->autoreshuffle && !$no_deck_reform) {
            // No more cards in deck & reshuffle is active => form another deck
            self::reformDeckFromDiscard($location);
            $result_arr = $this->getTokensOnTop(1, $location);
            if (count($result_arr) > 0)
                return $result_arr [0];
        }
        return null;
    }

    /**
     * Return "$nbr" tokens on top of this location, top defined as item with higher state value
     */
    function getTokensOnTop($nbr, $location) {
        self::checkLocation($location);
        self::checkPosInt($nbr);
        $result = array ();
        $sql = $this->getSelectQuery();
        $sql .= " WHERE token_location='$location'";
        $sql .= " ORDER BY token_state DESC";
        $sql .= " LIMIT $nbr";
        $dbres = self::DbQuery($sql);
        while ( $row = mysql_fetch_assoc($dbres) ) {
            $result [] = $row;
        }
        return $result;
    }

    function reformDeckFromDiscard($from_location) {
        self::checkLocation($from_location);
        if (isset($this->autoreshuffle_custom [$from_location]))
            $discard_location = $this->autoreshuffle_custom [$from_location];
        else
            throw new feException("reformDeckFromDiscard: Unknown discard location for $from_location !");
        self::checkLocation($discard_location);
        self::moveAllTokensInLocation($discard_location, $from_location);
        self::shuffle($from_location);
        if ($this->autoreshuffle_trigger) {
            $obj = $this->autoreshuffle_trigger ['obj'];
            $method = $this->autoreshuffle_trigger ['method'];
            $obj->$method($from_location);
        }
    }

    // Set token state
    function setTokenState($token_key, $state) {
        self::checkState($state);
        self::checkKey($token_key);
        $sql = "UPDATE " . $this->table;
        $sql .= " SET token_state='$state'";
        $sql .= " WHERE token_key='$token_key'";
        self::DbQuery($sql);
        return $state;
    }

    /**
     * Move a token to specific location in specific state, if state set to null do not change state
     */
    function moveToken($token_key, $location, $state = 0) {
        self::checkLocation($location);
        self::checkState($state, true);
        self::checkKey($token_key);
        $sql = "UPDATE " . $this->table;
        $sql .= " SET token_location='$location'";
        if ($state !== null) {
            $sql .= ", token_state='$state'";
        }
        $sql .= " WHERE token_key='$token_key'";
        return self::DbQuery($sql);
    }

    /**
     * Move tokens (array of ids) to specific location in specific state, if state set to null do not change state
     */
    function moveTokens($tokens, $location, $state = 0) {
        self::checkLocation($location);
        self::checkState($state, true);
        self::checkTokenKeyArray($tokens);
        $sql = "UPDATE " . $this->table;
        $sql .= " SET token_location='$location'";
        if ($state !== null) {
            $sql .= ", token_state='$state'";
        }
        $sql .= " WHERE token_key IN ('" . implode("','", $tokens) . "')";
        self::DbQuery($sql);
    }

    // Move a card to a specific location where card are ordered. If location_arg place is already taken, increment
    // all tokens after location_arg in order to insert new card at this precise location
    function insertToken($token_key, $location, $state = 0) {
        self::checkLocation($location);
        self::checkState($state);
        $sql = "UPDATE " . $this->table;
        $sql .= " SET token_state=token_state+1";
        $sql .= " WHERE token_location='$location' ";
        $sql .= " AND token_state>=$state";
        self::DbQuery($sql);
        self::moveToken($token_key, $location, $state);
    }

    function insertTokenOnExtremePosition($token_key, $location, $bOnTop) {
        $extreme_pos = self::getExtremePosition($bOnTop, $location);
        if ($bOnTop)
            self::insertToken($token_key, $location, $extreme_pos + 1);
        else
            self::insertToken($token_key, $location, $extreme_pos - 1);
    }

    // Move all tokens from a location to another
    // !!! state is reset to 0 or specified value !!!
    // if "from_location" and "from_state" are null: move ALL cards to specific location
    function moveAllTokensInLocation($from_location, $to_location, $from_state = null, $to_state = 0) {
        if ($from_location != null)
            self::checkLocation($from_location);
        self::checkLocation($to_location);
        $sql = "UPDATE " . $this->table . " ";
        $sql .= "SET token_location='$to_location', token_state='$to_state' ";
        if ($from_location !== null) {
            $sql .= "WHERE token_location='" . addslashes($from_location) . "' ";
            if ($from_state !== null)
                $sql .= "AND token_state='$from_state' ";
        }
        self::DbQuery($sql);
    }

    /**
     * Move all tokens from a location to another location arg stays with the same value
     */
    function moveAllTokensInLocationKeepOrder($from_location, $to_location) {
        self::checkLocation($from_location);
        self::checkLocation($to_location);
        $sql = "UPDATE " . $this->table;
        $sql .= " SET token_location='$to_location'";
        $sql .= " WHERE token_location='$from_location'";
        self::DbQuery($sql);
    }

    /**
     * Return all tokens in specific location
     * note: if "order by" is used, result object is NOT indexed by ids
     */
    function getTokensInLocation($location, $state = null, $order_by = null) {
        return $this->getTokensOfTypeInLocation(null, $location, $state, $order_by);
    }

    function getTokenOnLocation($location) {
        $res = $this->getTokensOfTypeInLocation(null, $location);
        return array_shift($res);
    }

    /**
     * Get tokens of a specific type in a specific location, since there is no field for type we use like expression on
     * key
     *
     * @param string $type -  if type contains % it will be treated as LIKE, otherwise % will be appended
     * @param string $location - if location contains % it will be treated as LIKE
     * @param int|string $state - state can be just numeric or can be expression i.e. "!=0"
     * @param string $order_by - field to order by, if used keys will be numberic in returned array
     *
     * @return array mixed
     */
    function getTokensOfTypeInLocation($type, $location = null, $state = null, $order_by = null) {
        $sql = $this->getSelectQuery();
        $sql .= " WHERE true ";
        if ($type !== null) {
            if (strpos($type, "%") === false) {
                $type .= "%";
            }
            self::checkType($type);
            $sql .= " AND token_key LIKE '$type'";
        }
        if ($location !== null) {
            self::checkLocation($location, true, false);
            $like = "LIKE";
            if (strpos($location, "%") === false) {
                $like = "=";
            }
            $sql .= " AND token_location $like '$location' ";
        }
        if ($state !== null) {
            $op = $this->extractStateOperator($state);
            self::checkState($state, false);
            $sql .= " AND token_state $op '$state'";
        }
        if ($order_by !== null)
            $sql .= " ORDER BY $order_by";
        $dbres = self::DbQuery($sql);
        $result = array ();
        $i = 0;
        while ( $row = mysql_fetch_assoc($dbres) ) {
            if ($order_by !== null) {
                $result [$i] = $row;
            } else {
                $result [$row ['key']] = $row;
            }
            $i++;
        }
        return $result;
    }

    function getTokenOfTypeInLocation($type, $location = null, $state = null, $order_by = null) {
        $res = $this->getTokensOfTypeInLocation($type, $location, $state, $order_by);
        return reset($res);
    }

    function getTokenState($token_id) {
        $res = $this->getTokenInfo($token_id);
        if ($res == null)
            return null;
        return $res ['state'];
    }

    function getTokenLocation($token_id) {
        $res = $this->getTokenInfo($token_id);
        if ($res == null)
            return null;
        return $res ['location'];
    }

    /**
     * Get specific token info
     */
    function getTokenInfo($token_key) {
        self::checkKey($token_key);
        $sql = $this->getSelectQuery();
        $sql .= " WHERE token_key='$token_key' ";
        $dbres = self::DbQuery($sql);
        return mysql_fetch_assoc($dbres);
    }

    /**
     * Get specific tokens info
     */
    function getTokensInfo($tokens_array) {
        self::checkTokenKeyArray($tokens_array);
        if (count($tokens_array) == 0)
            return array ();
        $sql = $this->getSelectQuery();
        $sql .= " WHERE token_key IN ('" . implode("','", $tokens_array) . "') ";
        $dbres = self::DbQuery($sql);
        $result = array ();
        while ( $row = mysql_fetch_assoc($dbres) ) {
            $result [$row ['key']] = $row;
        }
        if (count($result) != count($tokens_array)) {
            self::error("getTokens: some cards have not been found:");
            self::error("requested: " . implode(",", $tokens_array));
            self::error("received: " . implode(",", array_keys($result)));
            throw new feException("getTokens: Some cards have not been found !");
        }
        return $result;
    }

    function countTokensInLocation($location, $state = null) {
        self::checkLocation($location, true);
        self::checkState($state, true);
        $like = "LIKE";
        if (strpos($location, "%") === false) {
            $like = "=";
        }
        $sql = "SELECT COUNT( token_key ) cnt FROM " . $this->table;
        $sql .= " WHERE token_location $like '$location' ";
        if ($state !== null)
            $sql .= "AND token_state='$state' ";
        $dbres = self::DbQuery($sql);
        if ($row = mysql_fetch_assoc($dbres))
            return $row ['cnt'];
        else
            return 0;
    }

    // Return an array "location" => number of cards
    function countTokensInLocations() {
        $result = array ();
        $sql = "SELECT token_location, COUNT( token_key ) cnt FROM " . $this->table . " GROUP BY token_location ";
        $dbres = self::DbQuery($sql);
        while ( $row = mysql_fetch_assoc($dbres) ) {
            $result [$row ['token_location']] = $row ['cnt'];
        }
        return $result;
    }

    // Return an array "state" => number of tokens (for this location)
    function countTokensByState($location) {
        self::checkLocation($location);
        $result = [];
        $sql = "SELECT token_state, COUNT( token_key ) cnt FROM " . $this->table . " ";
        if ($location !== null)
            $sql .= "WHERE token_location='$location' ";
        $sql .= "GROUP BY token_state ";
        $dbres = self::DbQuery($sql);
        while ( $row = mysql_fetch_assoc($dbres) ) {
            $result [$row ['token_state']] = $row ['cnt'];
        }
        return $result;
    }

    function varsub($line, $keymap) {
        if ($line === null)
            throw new feException("varsub: line cannot be null");
        if (strpos($line, "{") !== false) {
            foreach ( $keymap as $key => $value ) {
                if (strpos($line, "{$key}") !== false) {
                    $line = preg_replace("/\{$key\}/", $value, $line);
                }
            }
        }
        return $line;
    }

    final function checkLocation($location, $like = false, $canBeNull = false) {
        if ($location === null)
            if ($canBeNull === false)
                throw new feException("location cannot be null");
            else
                return;
        $extra = "";
        if ($like)
            $extra = "%";
        if (preg_match("/^[A-Za-z${extra}][A-Za-z_0-9${extra}-]*$/", $location) == 0) {
            throw new feException("location must be alphanum and underscore non empty string");
        }
    }

    function extractStateOperator(&$state) {
        $op='';
        if ($state !== null) {
            $state = trim($state);
            $matches = [ ];
            $res = preg_match("/^(>=|>|<=|<|<>|!=|=|) *(-?[0-9]+)$/", $state, $matches, PREG_OFFSET_CAPTURE);
            if ($res == 1) {
                $op = $matches [1] [0];
                $rest = $matches [2] [0];
                $state = $rest;
            }
        }
        if ( !$op)
            $op = '=';
        return $op;
    }

    final function checkState($state, $canBeNull = false) {
        if ($state === null && $canBeNull == false)
            throw new feException("state cannot be null");
        if ($state !== null && preg_match("/^-?[0-9]+$/", $state) != 1) {
            // $bt = debug_backtrace();
            // trigger_error("bt ".print_r($bt[2],true)) ;
            throw new feException("state must be integer number");
        }
    }

    final function checkTokenKeyArray($token_arr) {
        $res = $this->checkListOrTokenArray($token_arr);
        if ($res != 1) {
            $debug = var_export($token_arr, true);
            throw new feException("token_arr is not a list of token ids $res: $debug");
        }
    }

    final function checkKey($key, $like = false) {
        if ($key == null)
            throw new feException("key cannot be null");
        if (!is_string($key))
                throw new feException("key is not a string");
        $extra = "";
        if ($like)
            $extra = "%";
        if (preg_match("/^[A-Za-z_0-9${extra}]+$/", $key) == 0) {
            throw new feException("key must be alphanum and underscore non empty string '$key'");
        }
    }

    final function checkType($key) {
        if ($key == null)
            throw new feException("type cannot be null");
        $this->checkKey($key, true);
    }

    final function checkPosInt($key) {
        if ($key && preg_match("/^[0-9]+$/", $key) == 0) {
            throw new feException("must be integer number");
        }
    }

    /**
     * Checks that given array either list of keys or list returned by function such get getTokensInfo which is map of
     * key => info pairs
     * throws exception if not of any of this structures, otherwise it returns
     *
     * @param * $token_arr
     * @return number below: <br>
     *         0 - array is empty
     *         1 - array of token ids
     *         2 - array of key => info map with token_id as index
     *         3 - array of key => info with number as index (returned by sorting methods)
     */
    final function checkListOrTokenArray($token_arr, $bThrow = true) {
        try {
            if ($token_arr === null)
                throw new feException("token_arr cannot be null");
            $debug = var_export($token_arr, true);
            if ( !is_array($token_arr)) {
                throw new feException("token_arr is not an array: $debug");
            }
            if (count($token_arr) == 0)
                return 0;
            $type = -1;
            foreach ( $token_arr as $key => $info ) {
                $typeone = $this->checkTokenIdOrInfo($info);
                if ($type == -1)
                    $type = $typeone;
                else if ($type != $typeone)
                    throw new feException("token_arr data has mixed types $type != $typeone: $debug");
                if (is_numeric($key)) {
                    // ok
                    if ($typeone == 2)
                        $typeone = 3;
                } else if ($typeone == 2) {
                    $k = $info ['key'];
                    if ($key != $k) {
                        throw new feException("token_arr data key info mismatch $key != $k: $debug");
                    }
                }
                if ($key === 'key' || $key === 'location' || $key === 'state') {
                    throw new feException("token_arr data is not right array: $key $debug");
                }                      
            }
            return $type;
        } catch ( feException $e ) {
            if ($bThrow)
                throw $e;
            return -1;
        }
    }

    final function checkTokenIdOrInfo($info, $bThrow = true) {
        try {
            if (is_array($info)) {
                if (array_key_exists('key', $info)) {
                    $this->checkKey($info['key']);
                    return 2;
                }
                $debug = var_export($info, true);
                throw new feException("token info structure is not correct: $debug");
            } else {
                $this->checkKey($info);
                return 1;
            }
        } catch ( feException $e ) {
            if ($bThrow)
                throw $e;
            return -1;
        }
    }

    /**
     * Converts arbitrary data structure to token key list
     * Accepted structures
     *    <li> string id
     *    <li> info - i.e. ['key'=>'x', 'state'=>1, 'location'=>'y']
     *    <li> info[] - info array (with key or integer as index) 
     *    <li> id[] - id array 
     * @param * $token_arr
     * @return string[] - token id list
     */
    function toTokenKeyList($tokens) {
        if ( !$tokens)
            return [ ];
        $kind = $this->checkTokenIdOrInfo($tokens, false);
        switch ($kind) {
            case 1 : // key
                return [ $tokens ];
            case 2 : // info
                return [ $tokens ['key'] ];
        }
        $kind = $this->checkListOrTokenArray($tokens);
        switch ($kind) {
            case 0 :
                return [ ];
            case 1 :
                return $tokens;
            case 2 :
                return array_keys($tokens);
            case 3 :
                $keys = [ ];
                foreach ( $tokens as $info ) {
                    $keys [] = $info ['key'];
                }
                return $keys;
            default :
                $debug = var_export($tokens, true);
                throw new feException("tokens structure is not supported: $debug");
        }
    }


     function getSelectQuery() {
        $sql = "SELECT token_key AS \"key\", token_location AS \"location\", token_state AS \"state\"";
        if (count($this->custom_fields)) {
            $sql .= ", ";
            $sql .= implode(', ', $this->custom_fields);
        }
        $sql .= " FROM " . $this->table;
        return $sql;
    }

    function setCustomFields($fields_array) {
        $this->checkTokenKeyArray($fields_array);
        $this->custom_fields = $fields_array;
    }
}

<?php
/**
 * This class contants functions that work with tokens SQL model and tokens class
 *
 <code>
 require_once (APP_GAMEMODULE_PATH . 'module/table/table.game.php');
 
 require_once ('modules/EuroGame.php');
 
 class EpicKingdom extends EuroGame {
 }
 </code>
 *
 */
require_once ('APP_Extended.php');
require_once ('tokens.php');

abstract class EuroGame extends APP_Extended {
    protected $tokens;
    protected $token_types;

    public function __construct() {
        parent::__construct();
        self::initGameStateLabels(array ("move_nbr" => 6 ));
        $this->tokens = new Tokens();
    }

    protected function initTables() {
        $this->tokens->initGlobalIndex('GINDEX', 0);
        $this->players_basic = $this->loadPlayersBasicInfos();
        //$num = $this->getNumPlayers();
    }

    protected function setCounter(&$array, $key, $value) {
        $array [$key] = array ('counter_value' => $value,'counter_name' => $key );
    }

    protected function fillCounters(&$array, $locs, $create = true) {
        foreach ( $locs as $location => $count ) {
            $key = $location . "_counter";
            if ($create || array_key_exists($key, $array))
                $this->setCounter($array, $key, $count);
        }
    }

    protected function fillTokensFromArray(&$array, $cards) {
        foreach ( $cards as $pos => $card ) {
            $id = $card ['key'];
            $array [$id] = $card;
        }
    }

    protected function getAllDatas() {
        $result = array ();
        $current_player_id = self::getCurrentPlayerId(); // !! We must only return informations visible by this player !!
        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score, player_no no FROM player ";
        $result ['players'] = self::getCollectionFromDb($sql);
        $result ['token_types'] = $this->token_types;
        $result ['tokens'] = array ();
        $result ['counters'] = $this->getDefaultCounters();
        $locs = $this->tokens->countTokensInLocations();
        $color = $this->getPlayerColor($current_player_id);
        foreach ( $locs as $location => $count ) {
            if ($this->isCounterAllowedForLocation($current_player_id, $location)) {
                $this->fillCounters($result ['counters'], [ $location => $count ]);
            } else {
                continue;
            }
            $content = $this->isContentAllowedForLocation($current_player_id, $location);
            if ($content !== false) {
                if ($content === true) {
                    $tokens = $this->tokens->getTokensInLocation($location);
                    $this->fillTokensFromArray($result ['tokens'], $tokens);
                } else {
                    $num = floor($content);
                    if ($count < $num)
                        $num = $count;
                    $tokens = $this->tokens->getTokensOnTop($num, $location);
                    $this->fillTokensFromArray($result ['tokens'], $tokens);
                }
            }
        }
        return $result;
    }
    protected function getDefaultCounters() {
        return [];
    }
    protected function isContentAllowedForLocation($player_id, $location) {
        if (startsWith($location, 'discard') || startsWith($location, 'deck'))
            return false;
        return true;
    }

    protected function isCounterAllowedForLocation($player_id, $location) {
        if ($location === 'dev_null' || $location === 'GINDEX')
            return false;
        return true;
    }
    
    function dbSetTokenState($token_id, $state = null, $notif = '*', $args = null) {
        $this->dbSetTokenLocation($token_id, null, $state, $notif, $args);
    }
    
    function dbSetTokenLocation($token_id, $place_id, $state = null, $notif = '*', $args = null) {
        $this->systemAssertTrue("token_id is null/empty $token_id, $place_id $notif", $token_id != null && $token_id != '');
        if ($args == null)
            $args = array ();
        if ($notif === '*')
            $notif = clienttranslate('${player_name} moves ${token_name} into ${place_name}');
        if ($state === null) {
            $state = $this->tokens->getTokenState($token_id);
        }
        if ($place_id === null) {
            $place_id = $this->tokens->getTokenLocation($token_id);
        }
        $this->tokens->moveToken($token_id, $place_id, $state);
        $notifyArgs = array ('token_id' => $token_id,'place_id' => $place_id,
                'token_name' => $token_id,
                'place_name' => $place_id,'new_state' => $state );
        $args = array_merge($notifyArgs, $args);
            //$this->warn("$type $notif ".$args['token_id']." -> ".$args['place_id']."|");
    
        
        $this->notifyWithName("tokenMoved", $notif, $args);
    }
    
    /**
     * This method will increase/descrease resource counter (as state)
     * @param string $token_id - token key
     * @param int $num - increment of the change
     * @param string $place - optional $place, only used in notification to show where "resource" 
     *   is gain or where it "goes" when its paid, used in client for animation
     */
    function dbResourceInc($token_id, $num, $place = null) {
        $player_id = $this->getActivePlayerId();
        $color = $this->getPlayerColor($player_id);
       
        $current = $this->tokens->getTokenState($token_id);
        $value = $this->tokens->setTokenState($token_id, $current + $num);
        if ($value < 0) {
            $this->userAssertTrue(self::_("Not enough resources to pay"), $current >= - $num);
        }

        if ($num < 0) {
            if ($place)
                $message = clienttranslate('${player_name} pays ${inc_resource} for ${place_name}');
            else
                $message = clienttranslate('${player_name} pays ${inc_resource}');
        } else {
            if ($place)
                $message = clienttranslate('${player_name} gains ${inc_resource} from ${place_name}');
            else
                $message = clienttranslate('${player_name} gains ${inc_resource}');
        }
        //$this->warn("playing inc $token_id, $num, $place");
        $this->notifyWithName("counter", $message, [
                'counter_name'=>$token_id,
                'counter_value'=>$value,
                'place'=>$place,
                'place_name'=>$place,
                'mod' => abs($num),
                'inc' => $num,
                'inc_resource' => [ 'log' => '${token_name} x${mod}',
                        'args' => [ 'token_name' => $token_id,
                                    'mod' => abs($num),
                                    'inc' => $num,
                        ] ]
                
        ]);
    }
    
    function notifyCounter($location) {
        $key = $location . "_counter";
        $this->notifyWithName("counter", '', [
                'counter_name'=> $key,
                'counter_value'=>($this->tokens->countTokensInLocation($location)) ]);
    }
}

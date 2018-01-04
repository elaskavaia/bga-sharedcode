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
    
    function __construct() {
        parent::__construct();
        self::initGameStateLabels(array ("move_nbr" => 6 ));
        $this->gameinit = false;
        $this->tokens = new Tokens();
    }
}

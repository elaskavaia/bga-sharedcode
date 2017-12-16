<?php

if (! isset($argv [1])) {
    echo "This script reads gamename.states.php and generates states.inc.php, gamename.action.php,\n";
    echo "and then injects actions, game state functions and state args functions into gamename.game.php.\n";
    echo "usage: genstate.php <path_to_project_dir>\n";
    exit(0);
}

require_once 'game_util.php';

$indir = $argv [1];
$gamename = basename($indir);


/**
 * This script reads ${gamename}.states.php and generates states.inc.php, ${gamename}.action.php,
 * and then injects actions, game state functions and state args functions into ${gamename}.game.php,
 * 
 * Usage: genstate.php <path_to_project_dir>
 */
require_once "$indir/${gamename}.states.php";

function clienttranslate($str) {
    return $str;
}

require_once "$indir/states.inc.php";
$prev_machinestates = $machinestates;
$prev_gamestate = array ();
foreach ( $prev_machinestates as $state_num => $stateinfo ) {
    $statename = $stateinfo ['name'];
    $prev_gamestate [$statename] = $stateinfo;
    $prev_gamestate [$statename] ['number'] = $state_num;
}
$actions = array ();
$game_func_arr = array ();
$player_func_arr = array ();
$state_args_arr = array ();
$machinestates = array (
        1 => array (
                "name" => "gameSetup",
                "description" => "Game setup",
                "type" => "manager",
                "action" => "stGameSetup",
                "transitions" => array (
                        "" => 2 
                ) 
        ),
        99 => array (
                "name" => "gameEnd",
                "description" => "End of game",
                "type" => "manager",
                "action" => "stGameEnd",
                "args" => "argGameEnd" 
        ) 
);
$stateNumber = 2;

foreach ( $gamestates as $statename => $stateinfo ) {
    if (isset($prev_gamestate [$statename] ['number'])) {
        $stateNumber = $prev_gamestate [$statename] ['number'];
    } else {
        while ( isset($prev_machinestates [$stateNumber]) ) {
            $stateNumber ++;
        }
    }
    $gamestates [$statename] ['number'] = $stateNumber;
    echo "$statename => $stateNumber\n";
    if (isset($stateinfo['startingState'])) {
        if ($stateinfo['startingState']) {
            $machinestates[1]["transitions"] = array (
                        "" => $stateNumber 
                );
        }
    }
    $stateNumber ++;
}
foreach ( $gamestates as $statename => $stateinfo ) {
    if (! $stateinfo ['type'])
        die("Missing type $statename");
    $stateNumber = $gamestates [$statename] ['number'];
    $type = $stateinfo ['type'];
    switch ($type) {
        case 'activeplayer' :
        case 'multiplayer' :
        case 'multipleactiveplayer':
            $player = true;
            break;
        case 'game' :
            $player = false;
            break;
        default :
            die("Unknown type $type for $statename");
    }
    $trans_full = $stateinfo ['transitions'];
    $gamestates [$statename] ['transitions'] = array ();
    $firsttrans = '';
    foreach ( $trans_full as $trans_name => $trans_state ) {
        if ($trans_name == 'loopback')
            $trans_state = $statename;
        if ($trans_state == 'endGame')
            $num = 99;
        else
            $num = $gamestates [$trans_state] ['number'];
        $gamestates [$statename] ['transitions'] [$trans_name] = $num;
        if (! $firsttrans)
            $firsttrans = $trans_name;
    }
    if ($player) {
        $actions_full = $stateinfo ['possibleactions'];
        $gamestates [$statename] ['possibleactions'] = array ();
        foreach ( $actions_full as $action_str ) {
            $k = strpos($action_str, '(');
            $args = array ();
            if ($k !== false) {
                $action_name = substr($action_str, 0, $k);
                $l = strpos($action_str, ')');
                if ($l === false)
                    die("Missing ) in $action_name");
                $args_str = substr($action_str, $k + 1, $l - $k - 1);
                $args_arr = explode(',', $args_str);
                
                foreach ( $args_arr as $arg_str ) {
                    if (!$arg_str) continue;
                    list ( $atype, $avar ) = explode(' ', trim($arg_str));
                    if (endsWith($avar,'?')) {
                      $avar = substr($avar, 0, -1);  
                      $args [$avar] = "\$$avar = self::getArg('$avar', AT_$atype, false, null);";
                    } else {
                      $args [$avar] = "\$$avar = self::getArg('$avar', AT_$atype, true);";
                    }
                }
            } else {
                $action_name = $action_str;
            }
            $actions [$action_name] = $args;
            $gamestates [$statename] ['possibleactions'] [] = $action_name;
        }
        if (! isset($stateinfo ['args'])) {
            $gamestates [$statename] ['args'] = "arg_$statename";
        }
    } else {
        if (! isset($stateinfo ['action'])) {
            $gamestates [$statename] ['action'] = "st_$statename";
        }
    }
    if (isset($gamestates [$statename] ['action'])) {
        $stfunc = $gamestates [$statename] ['action'];
        $game_func_arr [$stfunc] = "    function $stfunc() {\n        \$this->gamestate->nextState( '$firsttrans' );\n    }";
    }
    if (isset($gamestates [$statename] ['args'])) {
        $argfunc = $gamestates [$statename] ['args'];
        $state_args_arr [$argfunc] = "    function $argfunc() {\n        return array();\n    }";
    }
    $machinestates [$stateNumber] = $gamestates [$statename];
    $machinestates [$stateNumber] ['name'] = $statename;
    unset($machinestates [$stateNumber] ['number']);
}
$states_str = var_export($machinestates, true) . ";";
$states_str = preg_replace("/^ *'description' => ('.*'),$/m", "    'description' => clienttranslate(\\1),", $states_str);
$states_str = preg_replace("/^ *'descriptionmyturn' => ('.*'),$/m", "    'descriptionmyturn' => clienttranslate(\\1),", $states_str);
inject("$indir/states.inc.php", 'generated states', $states_str);
$infile = "$indir/${gamename}.action.php";
$body = "";
foreach ( $actions as $action => $args ) {
    $func = "
    public function $action() {
        self::setAjaxMode();\n";
    $vars = array ();
    foreach ( $args as $aname => $acall ) {
        $vars [] = "\$$aname";
        $func .= "        $acall\n";
    }
    $call = implode(', ', $vars);
    $func .= "        \$this->game->action_$action( $call );
        self::ajaxResponse( );
    }";
    $body .= $func . "\n";
    // action funcs
    $checkArgs = "";
    foreach ( $args as $aname => $acall) {
        $var = "\$$aname";
        $checkArgs .= "        \$this->check_$aname( $var );\n";
    }
    $player_func_arr ["action_$action"] = "
    function action_$action($call) {
        \$this->checkAction( '$action' );
$checkArgs
        \$player_id = \$this->getActivePlayerId();
        \$this->notifyWithName( '$action', clienttranslate( '\${player_name} $action' ), array());
        \$this->gamestate->nextState( 'next' );
    }";
}
inject($infile, 'generated actions', $body);
inject_functions("$indir/${gamename}.game.php", 'Game state actions generated', $game_func_arr);
inject_functions("$indir/${gamename}.game.php", 'Player actions generated', $player_func_arr);
inject_functions("$indir/${gamename}.game.php", 'Game state arguments generated', $state_args_arr);
?>

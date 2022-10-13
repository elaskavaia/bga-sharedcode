<?php

function clienttranslate($x) {
    return $x;
}
$infile = isset($argv [1])?$argv [1]:"states.inc.php";
$extra = isset($argv [2])?$argv [2]:""; // i.e. rankdir=LR;

 include($infile);
 echo "digraph D {\n";
 if ($extra) echo "$extra;\n";
 foreach ($machinestates as $state_id => $state) {
    $color = "red";
    $shape = "ellipse";
    if ($state["name"] == "gameSetup") {
        $shape = "Msquare";
    }
    if ($state["name"] == "gameEnd") {
        $shape = "Msquare";
    }
    if ($state["type"] == "game") {
        $color = "orange";
        $shape = "diamond";
    }
    if ($state["type"] == "activeplayer") {
        $color = "blue";
    }
    if ($state["type"] == "multipleactiveplayer") {
        $color = "green";
    }
    echo "n" . $state_id . " [label=\"".$state_id."_".$state["name"]."\" color=".$color." shape=".$shape."];\n";
 }

 
 foreach ($machinestates as $state_id => $state) {
    if (array_key_exists("transitions", $state)) {
        foreach ($state["transitions"] as $transition_label => $transition) {
            echo "n" . $state_id . " -> " . "n". $transition . " [label=\"".$transition_label."\"];\n";
        }
    }
 }

 echo "}\n";
?>

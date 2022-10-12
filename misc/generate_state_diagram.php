<?php

function clienttranslate($x) {
    return $x;
}

 include("states.inc.php");
 echo "digraph D {\n";
 foreach ($machinestates as $state_id => $state) {
    $color = "red";
    $shape = "ellipse";
    if ($state["name"] == "gameSetup") {
        $shape = "Mdiamond";
    }
    if ($state["name"] == "gameEnd") {
        $shape = "Msquare";
    }
    if ($state["type"] == "game") {
        $color = "yellow";
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

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
 * material.inc.php
 *
 * SharedCode game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *   
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
          */

$this->token_types = array(
        // --- gen php begin ---
// #this used to generate part of matherial.inc.php using genmat.php
'wcube' => array(
  'type' => 'cube',
  'name' => clienttranslate("Cube"),
),
'card_red' => array(
  'type' => 'card',
  'name' => clienttranslate("Red Spell"),
  'tooltip' => clienttranslate("This is tooltip for red spell"),
  't'=>1,'cn'=>'red','ipos'=>3,
),
'card_blue' => array(
  'type' => 'card',
  'name' => clienttranslate("Blue Spell"),
  'tooltip' => clienttranslate("This is tooltip for blue spell"),
  'tooltip_action' => clienttranslate("Click to cast it"),
  't'=>2,'cn'=>'blue','ipos'=>4,
),
'card_green' => array(
  'type' => 'card',
  'name' => clienttranslate("Green Spell"),
  't'=>3,'cn'=>'green','ipos'=>6,
),
        // --- gen php end ---
);






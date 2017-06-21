{OVERALL_GAME_HEADER}

<!-- 
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- SharedCode implementation : © Alena Laskavaia <laskava@gmail.com>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------

    sharedcode_sharedcode.tpl
    
    This is the HTML template of your game.
    
    Everything you are writing in this file will be displayed in the HTML page of your game user interface,
    in the "main game zone" of the screen.
    
    You can use in this template:
    _ variables, with the format {MY_VARIABLE_ELEMENT}.
    _ HTML block, with the BEGIN/END format
    
    See your "view" PHP file to check how to set variables and control blocks
    
    Please REMOVE this comment before publishing your game on BGA
-->

<div id="mydiv">


	<div id="mainboard" class="mainboard shadow">
		This is fake game board.
		<div id="action_space_1" class="action_space action_space_1"><span class="splabel">Moving tokens and counters</span></div>
		<div id="action_space_2" class="action_space action_space_2"></div>
		<div id="action_space_3" class="action_space action_space_3"></div>
		<div id="action_space_4" class="action_space action_space_4"></div>
				
		<div id="basket_1" class="basket basket_1"></div>
		<div id="basket_2" class="basket basket_2"></div>
	</div>
	<div class="funcontrols">
<div class="flip-container">
	<div class="flipper">
		<div class="front card" id="card1">
			<!-- front content -->
		</div>
		<div class="back card" id="card1_back">
			<!-- back content -->

		</div>
	</div>
</div>

<div>
<input type="radio" checked id="radio-front" name="select-face"/>    
<input type="radio" id="radio-left" name="select-face"/>
<input type="radio" id="radio-right" name="select-face"/>
<input type="radio" id="radio-top" name="select-face"/>
<input type="radio" id="radio-bottom" name="select-face"/>
<input type="radio" id="radio-back" name="select-face"/>

<!-- separator -->
<div id="sep" class="separator" style="height: 40px;"> Click on radio buttons </div>

<div class="scene">
  <div id="cube1" class="cube">
      <div class="cube-face  cube-face-front"></div>
      <div class="cube-face  cube-face-back"></div>
      <div class="cube-face  cube-face-left"></div>
      <div class="cube-face  cube-face-right"></div>
      <div class="cube-face  cube-face-top"></div>
      <div class="cube-face  cube-face-bottom"></div>
   </div>
</div>
</div>
</div>

</div>
<script type="text/javascript">
    // Javascript HTML templates
    var jstpl_resource_counter='<div class="mini_board_item"><div id="${type}_${color}_${id}_div" class="${type} ${type}_${color}"></div><span id="${type}_${color}_${id}">0</span></div>';
    var jstpl_resource='<div class="${type} ${type}_${color}" id="${type}_${color}_${id}"></div>';
    var jstpl_player_board='<div class="boardblock mini_board mini_board_${id} mini_board_color_${color}" id="mini_board_${id}"></div>';
    
   
</script>

{OVERALL_GAME_FOOTER}

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
		<div id="action_space_1" class="action_space action_space_1">
			<span class="splabel">Moving tokens and counters</span>
		</div>
		<div id="action_space_2" class="action_space action_space_2">
			<span class="splabel">Playing with cards</span>
		</div>
		<div id="action_space_3" class="action_space action_space_3">
			<span class="splabel">Something else</span>
		</div>
		<div id="action_space_4" class="action_space action_space_4"></div>

		<div id="basket_1" class="basket basket_1"></div>
		<div id="basket_2" class="basket basket_2"></div>
	</div>
	<div class="cardgame">
		<h2>My Hand</h2>
	     <div id="hand" class="hand active_hand whiteblock location"></div>
	     <h2>Play Area</h2>
	     <div id="playarea" class="playarea whiteblock location"></div>
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
			<input type="radio" checked id="radio-front" name="select-face" /> <input
				type="radio" id="radio-left" name="select-face" /> <input
				type="radio" id="radio-right" name="select-face" /> <input
				type="radio" id="radio-top" name="select-face" /> <input
				type="radio" id="radio-bottom" name="select-face" /> <input
				type="radio" id="radio-back" name="select-face" />

			<!-- separator -->
			<div id="sep" class="separator" style="height: 40px;">Click on
				radio buttons</div>

			<div class="cube-scene">
				<div id="cube1" class="cube shape">
					<div class="cube-face  cube-face-front"></div>
					<div class="cube-face  cube-face-back"></div>
					<div class="cube-face  cube-face-left"></div>
					<div class="cube-face  cube-face-right"></div>
					<div class="cube-face  cube-face-top"></div>
					<div class="cube-face  cube-face-bottom"></div>
				</div>
				<div class="shape cylinder-1 cyl-1">
					<div class="face bm">
						<div class="photon-shader"
							style="background-color: rgba(0, 0, 0, 0.125);"></div>
					</div>
					<div class="face tp">
						<div class="photon-shader"
							style="background-color: rgba(0, 0, 0, 0.125);"></div>
					</div>
					<div class="face side s0">
						<div class="photon-shader"
							style="background-color: rgba(0, 0, 0, 0.07);"></div>
					</div>
					<div class="face side s1">
						<div class="photon-shader"
							style="background-color: rgba(255, 255, 255, 0.016);"></div>
					</div>
					<div class="face side s2">
						<div class="photon-shader"
							style="background-color: rgba(255, 255, 255, 0.016);"></div>
					</div>
					<div class="face side s3">
						<div class="photon-shader"
							style="background-color: rgba(0, 0, 0, 0.125);"></div>
					</div>
					<div class="face side s4">
						<div class="photon-shader"
							style="background-color: rgba(0, 0, 0, 0.184);"></div>
					</div>
					<div class="face side s5">
						<div class="photon-shader"
							style="background-color: rgba(0, 0, 0, 0.3);"></div>
					</div>
					<div class="face side s6">
						<div class="photon-shader"
							style="background-color: rgba(0, 0, 0, 0.42);"></div>
					</div>
					<div class="face side s7">
						<div class="photon-shader"
							style="background-color: rgba(0, 0, 0, 0.53);"></div>
					</div>
					<div class="face side s8">
						<div class="photon-shader"
							style="background-color: rgba(0, 0, 0, 0.616);"></div>
					</div>
					<div class="face side s9">
						<div class="photon-shader"
							style="background-color: rgba(0, 0, 0, 0.616);"></div>
					</div>
					<div class="face side s10">
						<div class="photon-shader"
							style="background-color: rgba(0, 0, 0, 0.125);"></div>
					</div>
					<div class="face side s11">
						<div class="photon-shader"
							style="background-color: rgba(0, 0, 0, 0.416);"></div>
					</div>
					<div class="face side s12">
						<div class="photon-shader"
							style="background-color: rgba(0, 0, 0, 0.298);"></div>
					</div>
					<div class="face side s13">
						<div class="photon-shader"
							style="background-color: rgba(0, 0, 0, 0.18);"></div>
					</div>
				</div>

			</div>
		</div>
	</div>
	<div class="scrollmappanel">
	<h2>Scroll Map Area</h2>
		<div id="map_container" class="map_container">
			<div id="map_scrollable" class="map_scrollable map_scrollable_layer map_layer"></div>
			<div id="map_surface" class="map_surface map_layer"></div>
			<div id="map_scrollable_oversurface" class="map_scrollable_oversurface map_scrollable_layer map_layer"></div>

			<div class="movetop movearrow"></div>
			<div class="movedown movearrow"></div>
			<div class="moveleft movearrow"></div>
			<div class="moveright movearrow"></div>
		</div>
	</div>
</div>
<script type="text/javascript">
    // Javascript HTML templates
    var jstpl_resource_counter='<div class="mini_board_item"><div id="${type}_${color}_${id}_div" class="${type} ${type}_${color}"></div><span id="${type}_${color}_${id}">0</span></div>';
    var jstpl_resource='<div class="${type} ${type}_${color}" id="${type}_${color}_${id}"></div>';
    var jstpl_resource_log='<div class="${type} ${type}_${color} logitem" id="${type}_${color}_${id}"></div>';
    var jstpl_player_board='<div class="boardblock mini_board mini_board_${id} mini_board_color_${color}" id="mini_board_${id}"></div>';
    var jstpl_token = '<div class="${classes} ${id} token" id="${id}"></div>';
   
</script>

{OVERALL_GAME_FOOTER}

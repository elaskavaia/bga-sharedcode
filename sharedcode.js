/**
Copyright (c) 2017 Board Game Arena
All rights reserved. This program and the accompanying materials are made
available under the terms of the Eclipse Public License v1.0 which
accompanies this distribution, and is available at
http://www.eclipse.org/legal/epl-v10.html

Contributors:
  BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
  Alena Laskavaia <laskava@gmail.com> - initial API and implementation

This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
See http://en.boardgamearena.com/#!doc/Studio for more information.
*/

define([ "dojo", "dojo/_base/declare", "ebg/core/gamegui", "ebg/counter" ], function(dojo, declare) {
    return declare("bgagame.sharedcode", ebg.core.gamegui, {
        constructor : function() {
            console.log('sharedcode constructor');

            // Here, you can init the global variables of your user interface
            // Example:
            // this.myGlobalValue = 0;

        },

        /*
         * setup:
         * 
         * This method must set up the game user interface according to current game situation specified in parameters.
         * 
         * The method is called each time the game interface is displayed to a player, ie: _ when the game starts _ when a player refreshes
         * the game page (F5)
         * 
         * "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
         */

        setup : function(gamedatas) {
            console.log("Starting game setup");

            // Setting up player boards
            for ( var player_id in gamedatas.players) {
                var player = gamedatas.players[player_id];

                // TODO: Setting up players boards if needed
            }

            // TODO: Set up your game interface here, according to "gamedatas"

            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            console.log("Ending game setup");
        },

        // /////////////////////////////////////////////////
        // // Game & client states

        // onEnteringState: this method is called each time we are entering into a new game state.
        // You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState : function(stateName, args) {
            console.log('Entering state: ' + stateName);

            switch (stateName) {

                /*
                 * Example:
                 * 
                 * case 'myGameState': // Show some HTML block at this game state dojo.style( 'my_html_block_id', 'display', 'block' );
                 * 
                 * break;
                 */

                case 'dummmy':
                    break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        // You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState : function(stateName) {
            console.log('Leaving state: ' + stateName);

            switch (stateName) {

                /*
                 * Example:
                 * 
                 * case 'myGameState': // Hide the HTML block we are displaying only during this game state dojo.style( 'my_html_block_id',
                 * 'display', 'none' );
                 * 
                 * break;
                 */

                case 'dummmy':
                    break;
            }
        },

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        // action status bar (ie: the HTML links in the status bar).
        //        
        onUpdateActionButtons : function(stateName, args) {
            console.log('onUpdateActionButtons: ' + stateName);

            if (this.isCurrentPlayerActive()) {
                switch (stateName) {
                /*
                 * Example:
                 * 
                 * case 'myGameState': // Add 3 action buttons in the action status bar:
                 * 
                 * this.addActionButton( 'button_1_id', _('Button 1 label'), 'onMyMethodToCall1' ); this.addActionButton( 'button_2_id',
                 * _('Button 2 label'), 'onMyMethodToCall2' ); this.addActionButton( 'button_3_id', _('Button 3 label'), 'onMyMethodToCall3' );
                 * break;
                 */
                }
            }
        },

        // /////////////////////////////////////////////////
        // // Utility methods

        /**
         * This method can be used instead of addActionButton, to add a button which is an image (i.e. resource). Can be useful when player
         * need to make a choice of resources or tokens.
         */
        addImageActionButton : function(id, div, handler) {
            // this will actually make a transparent button
            this.addActionButton(id, div, handler, '', false, 'gray');
            // remove boarder, for images it better without
            dojo.style(id, "border", "none");
            // but add shadow style (box-shadow, see css)
            dojo.addClass(id, "shadow");
            // you can also add addition styles, such as background
            // dojo.style(id, "background-color", "white");
        },
        
        /**
         * Convenient method to get state name
         */
        getStateName : function() {
            return this.gamedatas.gamestate.name;
        },
        
        /**
         * This method will remove all inline style added to element that affect positioning
         */
        stripPosition : function(token) {
            // console.log(token + " STRIPPING");
            // remove any added positioning style
            dojo.style(token, "display", null);
            dojo.style(token, "top", null);
            dojo.style(token, "left", null);
            dojo.style(token, "position", null);
        },
        
        /**
         * This method will attach mobile to a new_parent without destroying,  
         * unlike original attachToNewParent which destroys mobile and all its connectors (onClick, etc)
         */
        
        attachToNewParentNoDestroy : function(mobile, new_parent) {
            if (typeof mobile == "string") {
                mobile = $(mobile);
            }
            if (typeof new_parent == "string") {
                new_parent = $(new_parent);
            }
            if (mobile === null) {
                console.error("attachToNewParent: mobile obj is null");
            }
            if (new_parent === null) {
                console.error("attachToNewParent: new_parent is null");
            }
            var tgt = dojo.position(mobile);
            var clone = mobile;
            dojo.style(clone, "position", "absolute");
            dojo.place(clone, new_parent, "last");
            var src = dojo.position(clone);
            var left = dojo.style(clone, "left");
            var top = dojo.style(clone, "top");
            left = left + tgt.x - src.x;
            top = top + tgt.y - src.y;
            dojo.style(clone, "top", top + "px");
            dojo.style(clone, "left", left + "px");
            return clone;
        },

        /**
         * This method is similar to slideToObject but works on object which do not use inline style positioning
         */
        slideAndPlace : function(token, finalPlace, tlen, tdelay, onEnd) {
            this.stripPosition(token);
   
            this.attachToNewParentNoDestroy(token, finalPlace);
            var anim = this.slideToObject(token, finalPlace, tlen, tdelay);
            
            dojo.connect(anim, "onEnd", dojo.hitch(this,function(token) {
                this.stripPosition(token);
                if (onEnd) onEnd(token);
            }));

            anim.play();
        },

        // /////////////////////////////////////////////////
        // // Player's action
        /*
         * 
         * Here, you are defining methods to handle player's action (ex: results of mouse click on game objects).
         * 
         * Most of the time, these methods: _ check the action is possible at this game state. _ make a call to the game server
         * 
         */

        /*
         * Example:
         * 
         * onMyMethodToCall1: function( evt ) { console.log( 'onMyMethodToCall1' ); // Preventing default browser reaction dojo.stopEvent(
         * evt ); // Check that this action is possible (see "possibleactions" in states.inc.php) if( ! this.checkAction( 'myAction' ) ) {
         * return; }
         * 
         * this.ajaxcall( "/sharedcode/sharedcode/myAction.html", { lock: true, myArgument1: arg1, myArgument2: arg2, ... }, this, function(
         * result ) { // What to do after the server call if it succeeded // (most of the time: nothing) }, function( is_error) { // What to
         * do after the server call in anyway (success or failure) // (most of the time: nothing) } ); },
         * 
         */

        // /////////////////////////////////////////////////
        // // Reaction to cometD notifications
        /*
         * setupNotifications:
         * 
         * In this method, you associate each of your game notifications with your local method to handle it.
         * 
         * Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in your sharedcode.game.php file.
         * 
         */
        setupNotifications : function() {
            console.log('notifications subscriptions setup');

            // TODO: here, associate your game notifications with local methods

            // Example 1: standard notification handling
            // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );

            // Example 2: standard notification handling + tell the user interface to wait
            // during 3 seconds after calling the method in order to let the players
            // see what is happening in the game.
            // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );
            // this.notifqueue.setSynchronous( 'cardPlayed', 3000 );
            // 
        },

    // TODO: from this point and below, you can write your game notifications handling methods

    /*
     * Example:
     * 
     * notif_cardPlayed: function( notif ) { console.log( 'notif_cardPlayed' ); console.log( notif ); // Note: notif.args contains the
     * arguments specified during you "notifyAllPlayers" / "notifyPlayer" PHP call // TODO: play the card in the user interface. },
     * 
     */
    });
});

/**
 * Copyright (c) 2017 Board Game Arena All rights reserved. This program and the accompanying materials are made available under the terms
 * of the Eclipse Public License v1.0 which accompanies this distribution, and is available at http://www.eclipse.org/legal/epl-v10.html
 * 
 * Contributors: BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com> Alena
 * Laskavaia <laskava@gmail.com> - initial API and implementation
 * 
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com. See
 * http://en.boardgamearena.com/#!doc/Studio for more information.
 */
define([ "dojo", "dojo/_base/declare", "ebg/core/gamegui", "ebg/counter",
    // load my own module!!!
    g_gamethemeurl + "modules/sharedparent.js" ], function(dojo,
        declare) {
    return declare("bgagame.sharedcode", bgagame.sharedparent, {
        constructor : function() {
            console.log('sharedcode constructor');
            console.log(this.globalid);
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
            this.inherited(arguments); // parent common setup
            // TODO: Set up your game interface here, according to "gamedatas"
            this.resourceIdCounterLocal = 1;
            // connect zones, they always there
            this.connectClass("basket", 'onclick', 'onBasket');
            this.connectClass("action_space", 'onclick', 'onActionSpace');
            // connect cubes
            this.connectClass("wcube", 'onclick', 'onCube');
            console.log("Ending game setup");
        },
        setupPlayer : function(playerId, playerInfo) {
            var playerBoardDiv = dojo.byId('player_board_' + playerId);
            var div = this.format_block('jstpl_player_board', playerInfo);
            var block = dojo.place(div, playerBoardDiv);
            var cubeColors = [ "gray", "pink", "purple" ];
            for ( var i in cubeColors) {
                var color = cubeColors[i];
                var tokenDiv = this.format_block('jstpl_resource_counter', {
                    "id" : "p" + playerInfo.color,
                    "type" : "wcube",
                    "color" : color,
                });
                dojo.place(tokenDiv, block);
            }
            // this is meeple in the player color
            var playerColorName = g_colorHexToName[playerInfo.color];
            var tokenDiv = this.format_block('jstpl_resource_counter', {
                "id" : "pc",
                "type" : "smeeple",
                "color" : playerColorName,
            });
            dojo.place(tokenDiv, block);
        },
        // /////////////////////////////////////////////////
        // // Game & client states
        // onEnteringState: this method is called each time we are entering into a new game state.
        // You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState : function(stateName, args) {
            console.log('Entering state: ' + stateName);
            this.inherited(arguments); // parent common code
            switch (stateName) {
                case 'playerTurn':
                    break;
            }
        },
        // onLeavingState: this method is called each time we are leaving a game state.
        // You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState : function(stateName) {
            this.inherited(arguments); // parent common code
            console.log('Leaving state: ' + stateName);
        },
        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        // action status bar (ie: the HTML links in the status bar).
        //        
        onUpdateActionButtons : function(stateName, args) {
            this.inherited(arguments); // parent common code
            console.log('onUpdateActionButtons: ' + stateName);
            if (this.isCurrentPlayerActive()) {
                switch (stateName) {
                    case 'playerTurn':
                        dojo.query(".action_space").addClass('active_slot');
                        // add text action button
                        this.addActionButton('button_pass', _('Pass'), dojo.hitch(this, function() {
                            this.ajaxAction('pass', {});
                        }));
                        break;
                    case 'client_selectCubeLocation':
                        dojo.query(".basket").addClass('active_slot');
                        dojo.addClass(this.clientStateArgs.token_id, 'selected');
                        this.addActionButton('button_cancel', _('Cancel'), 'onCancel');
                        break;
                    case 'playerTurnPlayCubes':
                        console.log(args);
                        // get arguments from state args
                        this.cubeTypeNumber = args.cubeTypeNumber;
                        this.resourceIdCounter = args.resource_id_counter;
                        dojo.query(".wcube").addClass('active_slot');
                        // add image action button
                        var keys = Object.keys(g_colorNameToHex);
                        this.takeCubeColor = keys[this.cubeTypeNumber];
                        var tokenDiv = this.format_block('jstpl_resource', {
                            "id" : "0",
                            "type" : "wcube",
                            "color" : this.takeCubeColor,
                        });
                        this.addImageActionButton('button_take', tokenDiv, 'onTakeCube');
                        break;
                }
            }
        },
        // /////////////////////////////////////////////////
        // // Utility methods
        /** @Override */
        format_string_recursive : function(log, args) {
            try {
                if (log && args && !args.processed) {
                    args.processed = true;
                    args.You = this.divYou(); // will replace ${You} with colored version
                    var keys = [ 'place_name', 'token_name' ];
                    for ( var i in keys) {
                        var key = keys[i];
                        console.log("checking " + key + " for " + log);
                        if (typeof args[key] == 'string') {
                            if (this.getTranslatable(key, args) == -1) {
                                var res = this.getTokenDiv(key, args);
                                console.log("subs " + res);
                                if (res) args[key] = res;
                            }
                        }
                    }
                }
            } catch (e) {
                console.error(log, args, "Exception thrown", e.stack);
            }
            return this.inherited(arguments);
        },
        getTokenDiv : function(key, args) {
            // ... implement whatever html you want here
            var token_id = args[key];
            var item_type = getPart(token_id, 0);
            var logid = "log" + (this.globalid++) + "_" + token_id;
            switch (item_type) {
                case 'wcube':
                    var tokenDiv = this.format_block('jstpl_resource_log', {
                        "id" : logid,
                        "type" : "wcube",
                        "color" : getPart(token_id, 1),
                    });
                    return tokenDiv;
                    break;
                case 'meeple':
                    if ($(token_id)) {
                        var clone = dojo.clone($(token_id));
                        dojo.attr(clone, "id", logid);
                        this.stripPosition(clone);
                        dojo.addClass(clone, "logitem");
                        return clone.outerHTML;
                    }
                    break;
                default:
                    break;
            }
            return "'" + this.getTokenName(token_id) + "'";
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
        onTakeCube : function(event) {
            var id = event.currentTarget.id;
            dojo.stopEvent(event);
            // create new cube
            this.resourceIdCounterLocal += 1;
            var tokenDiv = this.format_block('jstpl_resource', {
                "id" : this.resourceIdCounterLocal,
                "type" : "wcube",
                "color" : this.takeCubeColor,
            });
            // place on the button
            var cubeNode = dojo.place(tokenDiv, id);
            // connect clicker
            this.connect(cubeNode, 'onclick', 'onCube');
            // slide
            this.slideToObjectRelative(cubeNode, "basket_1");
            this.clientStateArgs.token_id = cubeNode.id;
            this.clientStateArgs.place_id = "basket_1";
            this.ajaxAction("takeCube", this.clientStateArgs);
        },
        onCube : function(event) {
            var id = event.currentTarget.id;
            dojo.stopEvent(event);
            console.log("on cube " + id);
            if (!this.checkActivePlayerAndSlot(id)) return;
            dojo.addClass(id, "selected");
            this.clientStateArgs.token_id = id;
            this.setClientState("client_selectCubeLocation", {
                descriptionmyturn : _('${you} must select location for the cube'),
            });
        },
        onBasket : function(event) {
            var id = event.currentTarget.id;
            dojo.stopEvent(event);
            console.log("on zone " + id);
            if (!this.checkActivePlayerAndSlot(id)) return;
            if (this.clientStateArgs.token_id) {
                dojo.addClass(id, "selected");
                this.clientStateArgs.place_id = id;
                this.resourceIdCounterLocal++;
                var clone = dojo.clone($(this.clientStateArgs.token_id));
                clone.id = "cube_" + this.resourceIdCounterLocal;
                dojo.place(clone, $(this.clientStateArgs.token_id));
                this.slideToObjectRelative(clone.id, id, 500, 0);
                this.ajaxAction("moveCube", this.clientStateArgs);
            }
        },
        onActionSpace : function(event) {
            var id = event.currentTarget.id;
            dojo.stopEvent(event);
            console.log("on zone " + id);
            if (!this.checkActivePlayerAndSlot(id)) return;
            dojo.addClass(id, "selected");
            this.clientStateArgs.action_id = id;
            // create new meeple
            this.resourceIdCounterLocal += 1;
            var playerColorName = g_colorHexToName[this.player_color];
            var tokenDiv = this.format_block('jstpl_resource', {
                "id" : this.resourceIdCounterLocal,
                "type" : "smeeple",
                "color" : playerColorName,
            });
            // place on the player board
            var meepleNode = dojo.place(tokenDiv, "smeeple_" + playerColorName + "_pc_div");
            this.slideToObjectRelative(meepleNode, id);
            this.clientStateArgs.worker_id = meepleNode.id;
            this.ajaxAction("selectWorkerAction", this.clientStateArgs);
        },
        /**
         * This is light weight undo support. You use local states, and this one erases it.
         */
        onCancel : function(event) {
            dojo.stopEvent(event);
            console.log("on cancel");
            this.cancelLocalStateEffects();
        },
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
            this.inherited(arguments); // parent common notifications for euro game
        },
    });
});

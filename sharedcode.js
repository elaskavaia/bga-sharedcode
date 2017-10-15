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

var colorNameToHex = {
    gray : "808080",
    blue : "0000ff",
    red : "ff0000",
    yellow : "ffff00",
    green : "008000",
    white : "ffffff",
    black : "000000",
    brown : "773300",
    purple : "4c1b5b",
    pink : "ffc0cb",
    orange : "ffa500",
};

var colorHexToName = {
    "808080" : "gray",
    "0000ff" : "blue",
    "ff0000" : "red",
    "ffff00" : "yellow",
    "008000" : "green",
    "ffffff" : "white",
    "000000" : "black",
    "773300" : "brown",
    "4c1b5b" : "purple",
    "ffc0cb" : "pink",
    "ffa500" : "orange",
};

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

                this.setupPlayerMiniBoard(player_id, player);
            }

            this.first_player_id = Object.keys(gamedatas.players)[0];

            if (!this.isSpectator) 
                this.player_color = gamedatas.players[this.player_id].color;
            else
                this.player_color = gamedatas.players[this.first_player_id].color;
            

            // TODO: Set up your game interface here, according to "gamedatas"
            this.resourceIdCounterLocal = 1;
            // connect zones, they always there
            this.connectClass("basket", 'onclick', 'onBasket');
            this.connectClass("action_space", 'onclick', 'onActionSpace');
            // connect cubes
            this.connectClass("wcube", 'onclick', 'onCube');
            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            console.log("Ending game setup");
        },

        setupPlayerMiniBoard : function(playerId, playerInfo) {
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
            var playerColorName = colorHexToName[playerInfo.color];
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

            if (!this.on_client_state) {
                // we can use it to preserve arguments for client states
                this.clientStateArgs = {};
            }

            switch (stateName) {
                case 'playerTurn':

                    break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        // You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState : function(stateName) {
            console.log('Leaving state: ' + stateName);
            dojo.query(".active_slot").removeClass('active_slot');
            dojo.query(".selected").removeClass('selected');
        },

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        // action status bar (ie: the HTML links in the status bar).
        //        
        onUpdateActionButtons : function(stateName, args) {
            console.log('onUpdateActionButtons: ' + stateName);

            if (this.isCurrentPlayerActive()) {
                switch (stateName) {
                    case 'playerTurn':

                        dojo.query(".action_space").addClass('active_slot');
                        // add text action button
                        this.addActionButton('button_pass', _('Pass'), dojo.hitch(this,function() {
                            this.ajaxAction('pass',{});
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
                        var keys = Object.keys(colorNameToHex);
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
        
        stripTransition : function(token) {
            this.setTransition(token, null);  
        },
        
        setTransition : function(token, value) {
            dojo.style(token, "transition", value);
            dojo.style(token, "-webkit-transition", value);
            dojo.style(token, "-moz-transition", value);
            dojo.style(token, "-o-transition", value);
            
        },
        
        getPlayerColor : function(id) {
            for ( var playerId in this.gamedatas.players) {
                var playerInfo = this.gamedatas.players[playerId];
                if (id == playerId) { return playerInfo.color; }
            }
            return '000000';
        },

        /**
         * This method will attach mobile to a new_parent without destroying, unlike original attachToNewParent which destroys mobile and
         * all its connectors (onClick, etc)
         */

        attachToNewParentNoDestroy : function(mobile, new_parent) {
            if (mobile === null) {
                console.error("attachToNewParent: mobile obj is null");
                return;
            }
            if (new_parent === null) {
                console.error("attachToNewParent: new_parent is null");
                return;
            }
            if (typeof mobile == "string") {
                mobile = $(mobile);
            }
            if (typeof new_parent == "string") {
                new_parent = $(new_parent);
            }

            var src = dojo.position(mobile);
            dojo.style(mobile, "position", "absolute");
            dojo.place(mobile, new_parent, "last");
            var tgt = dojo.position(mobile);
            var box = dojo.marginBox(mobile);
            var cbox = dojo.contentBox(mobile);
            var left = box.l + src.x - tgt.x;
            var top = box.t + src.y - tgt.y;
            dojo.style(mobile, "top", top + "px");
            dojo.style(mobile, "left", left + "px");
            box.l += box.w-cbox.w;
            box.t += box.h-cbox.h;
            return box;
        },

        /**
         * This method is similar to slideToObject but works on object which do not use inline style positioning. It also
         * attaches object to new parent immediately, so parent is correct during animation
         */
        slideToObjectRelative : function(token, finalPlace, duration, delay, onEnd) {
            if (typeof token == 'string') {
                token = $(token);
            }
       
            var self = this;
            this.delayedExec(function() {                
                self.stripTransition(token);
                self.stripPosition(token);
                var box = self.attachToNewParentNoDestroy(token, finalPlace);
                self.setTransition(token, "all " + duration + "ms ease-in-out");
 
                // set abs position causing css animation
                var left = dojo.style(token, "left"); // does not work if not called
                var top = dojo.style(token, "top");
                dojo.style(token, "left", 0 + "px");
                dojo.style(token, "top", 0 + "px");
            }, function() {
                self.stripTransition(token);
                self.stripPosition(token);
                if (onEnd) onEnd(token);
            }, duration, delay);
        },

        slideToObjectAbsolute : function(token, finalPlace, x, y, duration, delay, onEnd) {
            if (typeof token == 'string') {
                token = $(token);
            }
            var self = this;
            this.delayedExec(function() {
                self.stripTransition(token);
                var box = self.attachToNewParentNoDestroy(token, finalPlace);
                self.setTransition(token, "all " + duration + "ms ease-in-out");
                // set abs position causing css animation
                var left = dojo.style(token, "left"); // does not work if not called
                var top = dojo.style(token, "top");
                dojo.style(token, "left", x + "px");
                dojo.style(token, "top", y + "px");
            }, function() {
                self.stripTransition(token);
                if (onEnd) onEnd(token);
            }, duration, delay);
        },
        
        delayedExec: function(onStart, onEnd, duration, delay) {
            if (typeof duration == "undefined") {
                duration = 500;
            }
            if (typeof delay == "undefined") {
                delay = 0;
            }
            if (this.instantaneousMode) {
                delay = Math.min(1, delay);
                duration = Math.min(1, duration);
            }
            if (delay) {
                setTimeout(function() {
                    onStart();
                    if (onEnd) {
                        setTimeout(onEnd, duration);
                    }
                }, delay);
            } else {
                onStart();
                if (onEnd) {
                    setTimeout(onEnd, duration);
                }
            }
        },

        /** More convenient version of ajaxcall, do not to specify game name, and any of the handlers */

        ajaxAction : function(action, args, func, err) {
            console.log("ajax action " + action);
            if (!args) {
                args = [];
            }
            delete args.action;
            if (typeof func == "undefined" || func == null) {
                func = function(result) {

                };
            }

            if (this.on_client_state) {
                // restore server server if error happened
                if (typeof err == "undefined") {
                    var self = this;
                    err = function(iserr, message) {
                        if (iserr) {
                            console.log('restoring server state, error: ' + message);
                            self.cancelLocalStateEffects();
                        }
                    };
                }
            }
            var name = this.game_name;
            if (this.checkAction(action)) {
                // args.lock = true;
                this.ajaxcall("/" + name + "/" + name + "/" + action + ".html", args,// 
                this, func, err);
            }
        },

        cancelLocalStateEffects : function() {
            if (this.on_client_state) {
                // do something to cancel local state effects
            }
            this.restoreServerGameState();
        },

        isActiveSlot : function(id) {
            if (!dojo.hasClass(id, 'active_slot')) { return false; }

            return true;
        },
        checkActiveSlot : function(id) {
            if (!this.isActiveSlot(id)) {
                this.showMoveUnauthorized();
                return false;
            }

            return true;
        },
        checkActivePlayer : function() {
            if (!this.isCurrentPlayerActive()) {
                this.showMessage(__("lang_mainsite", "This is not your turn"), "error");
                return false;
            }
            return true;
        },

        checkActivePlayerAndSlot : function(id) {
            if (!this.checkActivePlayer()) { return false; }
            if (!this.checkActiveSlot(id)) { return false; }
            return true;
        },
        
        setMainTitle : function(text) {
            var main = $('pagemaintitletext');
            main.innerHTML = text;
        },

        divYou : function() {
            var color = this.gamedatas.players[this.player_id].color;
            var color_bg = "";
            if (this.gamedatas.players[this.player_id] && this.gamedatas.players[this.player_id].color_back) {
                color_bg = "background-color:#" + this.gamedatas.players[this.player_id].color_back + ";";
            }
            var you = "<span style=\"font-weight:bold;color:#" + color + ";" + color_bg + "\">" +
                    __("lang_mainsite", "You") + "</span>";
            return you;
        },

        setDescriptionOnMyTurn : function(text) {
            this.gamedatas.gamestate.descriptionmyturn = text;
            // this.updatePageTitle();
            var tpl = dojo.clone(this.gamedatas.gamestate.args);
            if (tpl === null) {
                tpl = {};
            }
            var title = "";
            if (this.isCurrentPlayerActive() && text !== null) {
                tpl.you = this.divYou();
                title = this.format_string_recursive(text, tpl);
            }

            if (title == "") {
                this.setMainTitle("&nbsp;");
            } else {
                this.setMainTitle(title);
            }
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
            this.clientStateArgs.token_id=cubeNode.id;
            this.clientStateArgs.place_id="basket_1";
            this.ajaxAction("takeCube",this.clientStateArgs);
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
                clone.id = "cube_"+this.resourceIdCounterLocal;
             
                dojo.place(clone, $(this.clientStateArgs.token_id));
                this.slideToObjectRelative(clone.id, id, 500, 0);
                this.ajaxAction("moveCube",this.clientStateArgs);
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
            var playerColorName = colorHexToName[this.player_color];
            var tokenDiv = this.format_block('jstpl_resource', {
                "id" : this.resourceIdCounterLocal,
                "type" : "smeeple",
                "color" : playerColorName,
            });
            // place on the player board
            var meepleNode = dojo.place(tokenDiv, "smeeple_" + playerColorName + "_pc_div");
            this.slideToObjectRelative(meepleNode, id);
            this.clientStateArgs.worker_id = meepleNode.id;
            
            this.ajaxAction("selectWorkerAction",this.clientStateArgs);
        },

        /**
         * This is light weight undo support. You use local states, and this one erases it.
         */
        onCancel : function(event) {
            dojo.stopEvent(event);
            console.log("on cancel");
            this.cancelLocalStateEffects();
        },

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

function joinId(first, second) {
    return first + '_' + second;
};

function getIntPart(word, i) {
    var arr = word.split('_');
    return parseInt(arr[i]);
};

function getPart(word, i) {
    var arr = word.split('_');
    return arr[i];
};

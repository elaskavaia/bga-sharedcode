var g_colorNameToHex = {
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
var g_colorHexToName = {
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
function getFirstParts(word, count) {
    var arr = word.split('_');
    var res = arr[0];
    for (var i = 1; i < arr.length && i < count; i++) {
        res += "_" + arr[i];
    }
    return res;
};
function getParentParts(word) {
    var arr = word.split('_');
    if (arr.length <= 1) return "";
    return getFirstParts(word, arr.length - 1);
};
function cleanArray(actual) {
    var newArray = [];
    for (var i = 0; i < actual.length; i++) {
        if (actual[i] !== null) {
            newArray.push(actual[i]);
        }
    }
    return newArray;
};
/* ! https://mths.be/startswith v0.2.0 by @mathias */
if (!String.prototype.startsWith) {
    (function() {
        'use strict'; // needed to support `apply`/`call` with `undefined`/`null`
        var defineProperty = (function() {
            // IE 8 only supports `Object.defineProperty` on DOM elements
            try {
                var object = {};
                var $defineProperty = Object.defineProperty;
                var result = $defineProperty(object, object, object) && $defineProperty;
            } catch (error) {
            }
            return result;
        }());
        var toString = {}.toString;
        var startsWith = function(search) {
            if (this == null) { throw TypeError(); }
            var string = String(this);
            if (search && toString.call(search) == '[object RegExp]') { throw TypeError(); }
            var stringLength = string.length;
            var searchString = String(search);
            var searchLength = searchString.length;
            var position = arguments.length > 1 ? arguments[1] : undefined;
            // `ToInteger`
            var pos = position ? Number(position) : 0;
            if (pos != pos) { // better `isNaN`
                pos = 0;
            }
            var start = Math.min(Math.max(pos, 0), stringLength);
            // Avoid the `indexOf` call if no match is possible
            if (searchLength + start > stringLength) { return false; }
            var index = -1;
            while (++index < searchLength) {
                if (string.charCodeAt(start + index) != searchString.charCodeAt(index)) { return false; }
            }
            return true;
        };
        if (defineProperty) {
            defineProperty(String.prototype, 'startsWith', {
                'value' : startsWith,
                'configurable' : true,
                'writable' : true
            });
        } else {
            String.prototype.startsWith = startsWith;
        }
    }());
};
define([ "dojo", "dojo/_base/declare", "ebg/core/gamegui" ], function(dojo, declare) {
    return declare("bgagame.sharedparent", ebg.core.gamegui, {
        constructor : function() {
            console.log('sharedparent constructor');
            this.globalid = 1; // global id used to inject tmp id's of objects
        },
        setup : function(gamedatas) {
            this.inherited(arguments);
            console.log("Starting game setup parent");
            this.gamedatas = gamedatas;
            // Setting up player boards
            for ( var player_id in gamedatas.players) {
                var playerInfo = gamedatas.players[player_id];
                this.setupPlayer(player_id, playerInfo);
            }
            this.first_player_id = Object.keys(gamedatas.players)[0];
            if (!this.isSpectator) this.player_color = gamedatas.players[this.player_id].color;
            else
                this.player_color = gamedatas.players[this.first_player_id].color;
            if (!this.gamedatas.tokens) {
                console.error("Missing gamadatas.tokens!");
                this.gamedatas.tokens = {};
            }
            if (!this.gamedatas.token_types) {
                console.error("Missing gamadatas.token_types!");
                this.gamedatas.token_types = {};
            }
            this.restoreList = []; // list of object dirtied during client state visualization
            this.gamedatas_local = dojo.clone(this.gamedatas);
            this.gamedatas_server = dojo.clone(this.gamedatas);
            this.globalid = 1; // global id used to inject tmp id's of objects
            this.clientStateArgs = {}; // collector of client state arguments
            this.setupGameTokens();
            this.setupNotifications();
            console.log("Ending game setup parent");
        },
        setupPlayer : function(player_id, playerInfo) {
            // does nothing here, override
            console.log("player info " + player_id, playerInfo);
        },
        setupGameTokens : function() {
            // does nothing here, override
        },
        // /////////////////////////////////////////////////
        // // Game & client states
        // onEnteringState: this method is called each time we are entering into a new game state.
        // You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState : function(stateName, args) {
            if (!this.on_client_state) {
                // we can use it to preserve arguments for client states
                this.clientStateArgs = {};
            }
        },
        // onLeavingState: this method is called each time we are leaving a game state.
        // You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState : function(stateName) {
            dojo.query(".active_slot").removeClass('active_slot');
            dojo.query(".selected").removeClass('selected');
        },
        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        // action status bar (ie: the HTML links in the status bar).
        //        
        onUpdateActionButtons : function(stateName, args) {
        },
        // /////////////////////////////////////////////////
        // // Utility methods
        /**
         * This method can be used instead of addActionButton, to add a button which is an image (i.e. resource). Can be useful when player
         * need to make a choice of resources or tokens.
         */
        addImageActionButton : function(id, div, handler, bcolor) {
            if (typeof bcolor == "undefined") {
                bcolor = "gray";
            }
            // this will actually make a transparent button
            this.addActionButton(id, div, handler, '', false, bcolor);
            // remove boarder, for images it better without
            dojo.style(id, "border", "none");
            // but add shadow style (box-shadow, see css)
            dojo.addClass(id, "shadow bgaimagebutton");
            // you can also add addition styles, such as background
            // dojo.style(id, "background-color", "white");
        },
        /**
         * Convenient method to get state name
         */
        getStateName : function() {
            return this.gamedatas.gamestate.name;
        },
        getPlayerColor : function(id) {
            for ( var playerId in this.gamedatas.players) {
                var playerInfo = this.gamedatas.players[playerId];
                if (id == playerId) { return playerInfo.color; }
            }
            return '000000';
        },
        /**
         * This method will remove all inline style added to element that affect positioning
         */
        stripPosition : function(token) {
            // console.log(token + " STRIPPING");
            // remove any added positioning style
            dojo.style(token, "display", "");
            dojo.style(token, "top", "");
            dojo.style(token, "left", "");
            dojo.style(token, "position", "");
            // dojo.style(token, "transform", null);
        },
        stripTransition : function(token) {
            this.setTransition(token, "");
        },
        setTransition : function(token, value) {
            dojo.style(token, "transition", value);
            dojo.style(token, "-webkit-transition", value);
            dojo.style(token, "-moz-transition", value);
            dojo.style(token, "-o-transition", value);
        },
      		/**
		 * This method will attach mobile to a new_parent without destroying, unlike original attachToNewParent which destroys mobile and
		 * all its connectors (onClick, etc)
		 */
		attachToNewParentNoDestroy: function(mobile, new_parent, relation) {
			//console.log("attaching ",mobile,new_parent,relation);
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
			if (typeof relation == "undefined") {
				relation = "last";
			}
			var src = dojo.position(mobile);
			dojo.style(mobile, "position", "absolute");
			dojo.place(mobile, new_parent, relation);
			var tgt = dojo.position(mobile);
			var box = dojo.marginBox(mobile);
			var cbox = dojo.contentBox(mobile);
			var left = box.l + src.x - tgt.x;
			var top = box.t + src.y - tgt.y;
			this.positionObjectDirectly(mobile, left, top);
			box.l += box.w - cbox.w;
			box.t += box.h - cbox.h;
			return box;
		},
		/**
		 * This method is similar to slideToObject but works on object which do not use inline style positioning. It also attaches object to
		 * new parent immediately, so parent is correct during animation
		 */
		slideToObjectRelative: function(token, finalPlace, duration, delay, onEnd, relation) {
			if (typeof token == 'string') {
				token = $(token);
			}

			this.delayedExec(() => {
				dojo.addClass(token, 'moving_token');
				this.setTransition(token, "none");
				this.stripPosition(token);
				var box = this.attachToNewParentNoDestroy(token, finalPlace, relation);
				this.setTransition(token, "all " + duration + "ms ease-in-out");
				this.positionObjectDirectly(token, box.l, box.t);
			}, () => {
				this.stripTransition(token);
				this.stripPosition(token);
				dojo.removeClass(token, 'moving_token');
				if (onEnd) onEnd(token);
			}, duration, delay);
		},
		slideToObjectAbsolute: function(token, finalPlace, x, y, duration, delay, onEnd, relation) {
			if (typeof token == 'string') {
				token = $(token);
			}
			this.delayedExec(() => {
				dojo.addClass(token, 'moving_token');
				this.setTransition(token, "none");
				this.attachToNewParentNoDestroy(token, finalPlace, relation);
				this.setTransition(token, "all " + duration + "ms ease-in-out");
				this.positionObjectDirectly(token, x, y);
			}, () => {
				this.stripTransition(token);
				dojo.removeClass(token, 'moving_token');
				if (onEnd) onEnd(token);
			}, duration, delay);
		},

		positionObjectDirectly: function(mobileObj, x, y) {
			// do not remove this "dead" code some-how it makes difference
			dojo.style(mobileObj, "left"); // bug? re-compute style
			// console.log("place " + x + "," + y);
			dojo.style(mobileObj, {
				left: x + "px",
				top: y + "px"
			});
			dojo.style(mobileObj, "left"); // bug? re-compute style
		},
        delayedExec : function(onStart, onEnd, duration, delay) {
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
        ajaxAction : function(action, args, func, err, lock) {
            // console.log("ajax action " + action);
            if (!args) {
                args = [];
            }
            // console.log(args);
            delete args.action;
            if (!args.hasOwnProperty('lock') || lock) {
                args.lock = true;
            } else {
                delete args.lock;
            }
            if (typeof func == "undefined" || func == null) {
                var self = this;
                func = function(result) {
                    self.ajaxActionResultCallback(action, args, result);
                };
            }
            if (typeof err == "undefined") {
                var self = this;
                err = function(iserr, message) {
                    if (iserr) {
                        self.ajaxActionErrorCallback(action, args, message);
                    }
                };
            }
            var name = this.game_name;
            if (this.checkAction(action)) {
                this.ajaxcall("/" + name + "/" + name + "/" + action + ".html", args,// 
                this, func, err);
            }
        },
        ajaxActionResultCallback : function(action, args, result) {
            console.log('server ack');
        },
        ajaxActionErrorCallback : function(action, args, message) {
            console.log('restoring server state, error: ' + message);
            this.cancelLocalStateEffects();
        },
        ajaxClientStateHandler : function(event) {
            dojo.stopEvent(event);
            this.ajaxClientStateAction();
        },
        ajaxClientStateAction : function(action) {
            if (typeof action == 'undefined') {
                action = this.clientStateArgs.action;
            }
            if (this.clientStateArgs.handler) {
                var handler = this.clientStateArgs.handler;
                delete this.clientStateArgs.handler;
                handler();
                return;
            }
            console.log("sending " + action);
            this.ajaxAction(action, this.clientStateArgs);
        },
        setClientStateAction : function(stateName, desc, delay, moreargs) {
            var args = {};
            if (typeof this.gamedatas.gamestate.args != 'undefined') {
                args = dojo.clone(this.gamedatas.gamestate.args);
           
            } 
       
            if (typeof desc == 'undefined' || !desc) {
                desc = '';
            }
     
            if (this.clientStateArgs.action) args.actname = this.getTr(this.clientStateArgs.action);
            if (typeof moreargs != 'undefined') {
                for (var property in moreargs) {
                    if (moreargs.hasOwnProperty(property)) {
                        args[property] = moreargs[property];
                    }
                }
            }
            var newargs = {
                descriptionmyturn : this.getTr(desc),
                args : args
            };
            if (delay && delay > 0) {
                setTimeout(dojo.hitch(this, function() {
                    this.setClientState(stateName, newargs);
                }, delay));
            } else {
                this.setClientState(stateName, newargs);
            }
        },
        cancelLocalStateEffects : function() {
            this.clientStateArgs = {};
            if (typeof this.gamedatas.gamestate.reflexion.initial != 'undefined') {
                this.gamedatas_server.gamestate.reflexion.initial = this.gamedatas.gamestate.reflexion.initial;
                this.gamedatas_server.gamestate.reflexion.initial_ts = this.gamedatas.gamestate.reflexion.initial_ts;
            }

            this.gamedatas_local = dojo.clone(this.gamedatas_server);
            this.gamedatas = dojo.clone(this.gamedatas_server);
            if (this.restoreList) {
                var restoreList = this.restoreList;
                this.restoreList = [];
                for (var i = 0; i < restoreList.length; i++) {
                    var token = restoreList[i];
                    var tokenInfo = this.gamedatas.tokens[token];
                    this.placeTokenWithTips(token, tokenInfo);
                }
            }
            this.updateCountersSafe(this.gamedatas_server.counters);
            this.restoreServerGameState();
        },
        /**
         * This is convenient function to be called when processing click events, it - remembers id of object - stops propagation - logs to
         * console - the if checkActive is set to true check if element has active_slot class
         */
        onClickSanity : function(event, checkActive) {
            // Stop this event propagation
            var id = event.currentTarget.id;
            this.original_id = id;
            dojo.stopEvent(event);
            console.log("on slot " + id);
            if (!id) return null;
            if (checkActive && ! (id.startsWith('button_')) && !this.checkActiveSlot(id)) { return null; }
            return this.onClickSanityId(id);
        },
        onClickSanityId : function(id) {
            if (!this.checkActivePlayer()) { return null; }
            id = id.replace("tmp_", "");
            id = id.replace("button_", "");
            return id;
        },
        checkActivePlayer : function() {
            if (!this.isCurrentPlayerActive()) {
                this.showMessage(__("lang_mainsite", "This is not your turn"), "error");
                return false;
            }
            return true;
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
            var color = "black";
            var color_bg = "";
            if (this.gamedatas.players[this.player_id]) {
                color = this.gamedatas.players[this.player_id].color;
            }
            if (this.gamedatas.players[this.player_id] && this.gamedatas.players[this.player_id].color_back) {
                color_bg = "background-color:#" + this.gamedatas.players[this.player_id].color_back + ";";
             
            }
            var you = "<span style=\"font-weight:bold;color:#" + color + ";" + color_bg + "\">" + __("lang_mainsite", "You") + "</span>";
            return you;
        },
        setDescriptionOnMyTurn : function(text, moreargs) {
            this.gamedatas.gamestate.descriptionmyturn = text;
            // this.updatePageTitle();
            //console.log('in',   this.gamedatas.gamestate.args, moreargs);
            var tpl = dojo.clone(this.gamedatas.gamestate.args);
            
            if (!tpl) {
                tpl = {};
            }
            if (typeof moreargs != 'undefined') {
                for ( var key in moreargs) {
                    if (moreargs.hasOwnProperty(key)) {
                        tpl[key]=moreargs[key];
                    }
                }
            }
           // console.log('tpl', tpl);
            var title = "";
            if (this.isCurrentPlayerActive() && text !== null) {
                tpl.you = this.divYou();
            }
            if (text !== null) {
                title = this.format_string_recursive(text, tpl);
            }
            if (title == "") {
                this.setMainTitle("&nbsp;");
            } else {
                this.setMainTitle(title);
            }
        },
        getTranslatable : function(key, args) {
            if (typeof args.i18n == 'undefined') {
                return -1;
            } else {
                var i = args.i18n.indexOf(key);
                if (i >= 0) { return i; }
            }
            return -1;
        },
        getTokenMainType : function(token) {
            var tt = token.split('_');
            var tokenType = tt[0];
            return tokenType;
        },
		updateTooltip: function(token, attachTo) {
			if (typeof attachTo == 'undefined') {
				attachTo = token;
			}
			if ($(attachTo)) {
				// console.log("tooltips for "+token);
				if (typeof token != 'string') {
					console.error("cannot calc tooltip" + token);
					return;
				}
				var tokenInfo = this.getTokenDisplayInfo(token);
				// console.log(tokenInfo);
				if (!tokenInfo) return;

				if (!tokenInfo.tooltip && !tokenInfo.name) {
					return;
				}
				if (tokenInfo.showname == false) {
					return;
				}

				if (!tokenInfo.tooltip && tokenInfo.name) {
					$(attachTo).title = this.getTr(tokenInfo.name);
					return;
				}

				var main = this.getTooptipHtmlForTokenInfo(tokenInfo);
				if (main) {
					this.addTooltipHtml(attachTo, main);
					dojo.removeAttr(attachTo, 'title'); // unset title so both title and tooltip do not show up
				}
			}
		},
        getTooptipHtmlForToken : function(token) {
            if (typeof token != 'string') {
                console.error("cannot calc tooltip" + token);
                return null;
            }
            var tokenInfo = this.getTokenDisplayInfo(token);
            // console.log(tokenInfo);
            if (!tokenInfo) return;
            return this.getTooptipHtmlForTokenInfo(tokenInfo);
            
        },
        getTooptipHtmlForTokenInfo : function(tokenInfo) {
            var main = this.getTooptipHtml(tokenInfo.name, tokenInfo.tooltip, tokenInfo.imageTypes, "<hr/>");
            var action = tokenInfo.tooltip_action;
            if (action && main !== null) {
                main += "<br/>" + this.getActionLine(action);
            }
            return main;
        },

        getTooptipHtml : function(name, message, imgTypes, sep, dyn) {
            if (name == null || message == "-") return "";
            if (!message) message = "";
            if (!dyn) dyn = "";
            var divImg = "";
            var containerType = "tooltipcontainer ";
            if (imgTypes) {
                divImg = "<div class='tooltipimage " + imgTypes + "'></div>";
                var itypes = imgTypes.split(" ");
                for (var i = 0; i < itypes.length; i++) {
                    containerType+=itypes[i]+"_tooltipcontainer ";
                }
            }
            return "<div class='"+containerType+"'><span class='tooltiptitle'>" + this.getTr(name) + "</span>" + sep + "<div>" + divImg +
                    "<div class='tooltipmessage tooltiptext'>" + this.getTr(message) + dyn + "</div></div></div>";
        },
        getTokenName : function(token) {
            var tokenInfo = this.getTokenDisplayInfo(token);
            if (tokenInfo) {
                return this.getTr(tokenInfo.name);
            } else {
                return "? " + token;
            }
        },
        getTr : function(name) {
            if (typeof name == 'undefined') return null;
            if (typeof name.log != 'undefined') {
                name = this.format_string_recursive(name.log, name.args);
            } else {
                name = this.clienttranslate_string(name);
            }
            return name;
        },
        getActionLine : function(text) {
            return "<img class='imgtext' src='" + g_themeurl + "img/layout/help_click.png' alt='action' /> <span class='tooltiptext'>" +
                    text + "</span>";
        },
        getTokenDisplayInfo : function(token) {
            var arr = token.split(' ');
            var tokenId = arr[0];
            var tokenKey = tokenId;
            var tokenMainType = this.getTokenMainType(token);
            var tokenInfo = this.gamedatas.token_types[tokenKey];
            var parts = token.split('_');
            var imageTypes = "";
            while (!tokenInfo && tokenKey) {
                tokenKey = getParentParts(tokenKey);
                tokenInfo = this.gamedatas.token_types[tokenKey];
                imageTypes += " " + tokenKey + " ";
            }
            if (parts.length >= 4) {
                imageTypes += " " + parts[0] + "_" + parts[2] + " ";
            }
            // console.log("request for " + token);
            // console.log(tokenInfo);
            if (!tokenInfo) return null;
            tokenInfo = dojo.clone(tokenInfo);
            tokenInfo.tokenKey = tokenKey;
            tokenInfo.mainType = tokenMainType;
            tokenInfo.imageTypes = token + " " + tokenMainType + " " + tokenInfo.type + " " + tokenKey + imageTypes;
            if (!tokenInfo.key) {
                tokenInfo.key = tokenId;
            }
            return tokenInfo;
        },
        changeTokenStateTo : function(token, newState) {
            var node = $(token);
            // console.log(token + "|=>" + newState);
            if (!node) return;
            if (this.on_client_state) {
                if (this.restoreList.indexOf(token) < 0) {
                    this.restoreList.push(token);
                }
            }
            var arr = node.className.split(' ');
            for (var i = 0; i < arr.length; i++) {
                var cl = arr[i];
                if (cl.startsWith("state_")) {
                    dojo.removeClass(token, cl);
                }
            }
            dojo.addClass(token, "state_" + newState);
        },
        placeTokenWithTips : function(token, tokenInfo, args) {
            if (!tokenInfo) {
                tokenInfo = this.gamedatas.tokens[token];
            }
            this.placeToken(token, tokenInfo, args);
            this.updateTooltip(token);
            this.updateTooltip(tokenInfo.location);
        },
        getPlaceRedirect : function(token, tokenInfo) {
            var location = tokenInfo.location;
            var result = {
                location : location,
                inlinecoords : false
            };
            if (location === 'discard') {
                result.temp = true;
            } else if (location.startsWith('deck')) {
                result.temp = true;
            }
            return result;
        },
        placeTokenLocal : function(token, place, state) {
            var tokenInfo = this.gamedatas_local.tokens[token];
            tokenInfo.location = place;
            if (state !== null) tokenInfo.state = state;
            this.on_client_state = true;
            this.placeToken(token, tokenInfo);
        },
        incCounterLocal : function(counter_id, inc, place) {
            var cur_value = this.gamedatas_local.counters[counter_id].counter_value;
            var notif_args = {
                counter_name : counter_id,
                counter_value : parseInt(cur_value) + inc,
                inc : inc,
                place : place
            };
            this.gamedatas_local.counters[notif_args.counter_name].counter_value = notif_args.counter_value;
            this.updateCountersSafe(this.gamedatas_local.counters);
            this.animCounter(notif_args);
        },
        /**
         * Using of this method also require tmp_obj and vapor_anim defined in css
         */
        animEvaporResource : function(animNodeId, inc, div) {
            var value = inc > 0 ? "+" + inc : inc;
            var mod = Math.abs(inc);
            if (div && !div.startsWith("<")) {
                var classes = div + " tmp_obj";
                var jstpl_score = '<div class="${classes}" id="${id}">${value}</div>';
                var scoring_marker_id = "scorenumber_" + animNodeId;
                div = this.format_string_recursive(jstpl_score, {
                    "id" : scoring_marker_id,
                    "value" : value,
                    "classes" : classes
                });
            }
            for (var i = 0; i < mod; i++) {
                var sNode = dojo.place(div, animNodeId);
                dojo.setAttr(sNode, "id", sNode.id + "_" + i);
                var scoring_marker_id = sNode.id;
                dojo.addClass(scoring_marker_id, "tmp_obj");
                if (i == mod - 1) sNode.innerHTML = value;
                this.placeOnObject(scoring_marker_id, animNodeId);// ?
                setTimeout(dojo.hitch(this, function(local) {
                    // console.log("apply "+local);
                    dojo.addClass(local, "vapor_anim");
                    this.fadeOutAndDestroy(local, 1000, 0);
                }), i * 200, scoring_marker_id);
            }
            return scoring_marker_id;
        },
        /**
         * Using of this method require scorenumber_anim defined in css
         */
        animSpinCounter : function(animNodeId, inc, playerId) {
            var value = inc > 0 ? "+" + inc : inc;
            var classes = "scorenumber tmp_obj";
            var jstpl_score = '<div class="${classes}" id="${id}">${value}</div>';
            var scoring_marker_id = "scorenumber_" + animNodeId;
            div = this.format_string_recursive(jstpl_score, {
                "id" : scoring_marker_id,
                "value" : value,
                "classes" : classes
            });
            var sNode = dojo.place(div, animNodeId);
            if (this.playerId) {
                dojo.style(scoring_marker_id, 'color', '#' + this.gamedatas.players[playerId].color);
            }
            this.placeOnObject(scoring_marker_id, animNodeId);// ?
            dojo.addClass(scoring_marker_id, "scorenumber_anim");
            this.fadeOutAndDestroy(scoring_marker_id, 2000, 300);
            return scoring_marker_id;
        },
        animCounter : function(args) {
            // args.counter_name
            // args.counter_value
            // args.place
            // args.inc
            var anim_node = args.place;
            if (anim_node && $(anim_node)) {
                //console.log("animCounter", args);
                var tokenDiv = this.divInlineToken(args['counter_name']);
                this.animEvaporResource(anim_node, args.inc, tokenDiv);
            }
        },
        animScore : function(args) {
            var anim_node = args.place;
            if (anim_node && $(anim_node)) {
                // local animation
                //console.log("animScore", args);
                this.animSpinCounter(anim_node, args.inc, args.player_id);
            }
        },
        placeToken : function(token, tokenInfo, args) {
            try {
                if (!tokenInfo) {
                    tokenInfo = this.gamedatas.tokens[token];
                }
                var placeInfo = this.getPlaceRedirect(token, tokenInfo);
                var place = placeInfo.location;
                if (typeof args == 'undefined') {
                    args = {};
                }
                var noAnnimation = false;
                if (args.noa) {
                    noAnnimation = true;
                }
                // console.log(token + ": " + " -place-> " + place + " " + tokenInfo.state);
                var tokenNode = $(token);
                if (place == "destroy") {
                    if (tokenNode) {
                        // console.log(token + ": " + tokenInfo.type + " -destroy-> " + place + " " + tokenInfo.state);
                        dojo.destroy(tokenNode);
                    }
                    return;
                }

                if (this.on_client_state) {
                    if (this.restoreList.indexOf(token) < 0) {
                        this.restoreList.push(token);
                    }
                }
                if (tokenNode == null) {
                    if (placeInfo.temp) { return; }
                    tokenNode = this.createToken(token, tokenInfo, placeInfo.createOn ? placeInfo.createOn : place, placeInfo.onclick);
                    if (tokenNode == null) { return; }
                }
                if (!$(place)) {
                    console.error("Unknown place " + place + " for " + tokenInfo.key + " " + token);
                    return;
                }
                if (place == "dev_null") {
                    // no annimation
                    noAnnimation = true;
                }
                if (this.inSetupMode || this.instantaneousMode) {
                    noAnnimation = true;
                }
                // console.log(token + ": " + tokenInfo.key + " -move-> " + place + " " + tokenInfo.state);
                this.changeTokenStateTo(token, tokenInfo.state);
                if (placeInfo.transform) {
                    dojo.style(token, 'transform', placeInfo.transform);
                }
                if (placeInfo.zindex) {
                    dojo.style(token, 'zIndex', parseInt(placeInfo.zindex));
                }
                if (placeInfo.inlinecoords) {
                    this.slideToObjectAbsolute(token, place, placeInfo.x, placeInfo.y, 500, 0, null, placeInfo.relation);
                } else {
                    this.stripPosition(token);
                    this.stripTransition(token);
                    if (tokenNode.parentNode.id == place) {
                        // already there
                        //console.log(token+" already there at "+place);
                    } else {
                        if (noAnnimation) {
                            if (placeInfo.temp) {
                                dojo.destroy(token);
                            } else {
                                dojo.place(token, place, placeInfo.relation);
                            }
                        } else {
                            if (placeInfo.temp) {
                                this.slideToObjectRelative(token, place, 500, 0, this.fadeOutAndDestroy, placeInfo.relation);
                            } else {
                                this.slideToObjectRelative(token, place, 500, 0, null, placeInfo.relation);
                            }
                        }
                    }
                }
                if (this.inSetupMode || this.instantaneousMode) {
                    // skip counters update
                } else {
                    this.updateMyCountersAll();
                }
            } catch (e) {
                console.error("Exception thrown", e.stack);
                // this.showMessage(token + " -> FAILED -> " + place + "\n" + e, "error");
            }
        },
        countOccurences : function(needle, heystack) {
            for (var count = -1, index = -2; index != -1; count++, index = heystack.indexOf(needle, index + 1))
                ;
            return count;
        },
        createDivFromInfo : function(token, extraClasses, id) {
            var info = this.getTokenDisplayInfo(token);
            if (info == null) {
                console.error("Don't know how to create ", token);
                return null;
            }
            
            if (typeof extraClasses == 'undefined') extraClasses = '';
            if (typeof id == 'undefined') id = info.key;
            return this.createDiv(info.imageTypes + " " + extraClasses, id);
        },
        createDiv : function(classes, id, value) {
            if (typeof value == 'undefined') value = "";
            var jstpl_token = id? '<div class="${classes} ${id}" id="${id}">${value}</div>': '<div class="${classes}">${value}</div>';
            var tokenDiv = this.format_string_recursive(jstpl_token, {
                id : id,
                classes : classes,
                value: value
            });
            return tokenDiv;
        },
        createToken : function(token, tokenInfo, place, connectFunc) {
            var info = this.getTokenDisplayInfo(token);
            if (info == null) {
                console.error("Don't know how to create ", token, tokenInfo);
                return;
            }
            var tokenDiv = this.createDivFromInfo(token, "token");
            if (!connectFunc && info.connectFunc) {
                connectFunc = info.connectFunc;
            }
            if (place) {
                if (tokenInfo && tokenInfo.place) {
                    place = tokenInfo.place;
                }
                // console.log(token + ":  - created -> " + place, tokenInfo);
                tokenNode = dojo.place(tokenDiv, place);
                if (connectFunc) {
                    // console.log("new connect "+tokenNode+" -> "+connectFunc);
                    this.connect(tokenNode, 'onclick', connectFunc);
                }
            } else {
                return tokenDiv;
            }
            return tokenNode;
        },
        updateMyCountersAll : function() {
            if (this.gamedatas.gamestate.args && this.gamedatas.gamestate.args.counters) {
                // console.log(this.gamedatas.gamestate.args.counters);
                this.updateCountersSafe(this.gamedatas.gamestate.args.counters);
            }
        },
        updateCountersSafe : function(counters) {
            // console.log(counters);
            var safeCounters = {};
            for ( var key in counters) {
                if (counters.hasOwnProperty(key) && $(key)) {
                    safeCounters[key] = counters[key];
                } else {
                    //console.log("unknown counter "+key);
                }
            }
            this.updateCounters(safeCounters);
        },
        divInlineTokens : function(token_ids, max) {
            var div = "";
            if (typeof max == 'undefined') max = 10;
            for ( var i in token_ids) {
                if (i >= max) break;
                var token_id = token_ids[i];
                if (token_id) div += this.divInlineToken(token_id);
            }
            return div;
        },
        divInlineToken : function(token_id) {
            var node = $(token_id);
            if (!node) console.log("no div node for " + token_id);
            if (node) {
                var clone = dojo.clone(node);
                this.formatLogNode(clone, token_id);
                dojo.query("div", clone).forEach(dojo.destroy);
                div = clone.outerHTML;
            } else {
                var name = this.getTokenName(token_id);
                div = "<span>'" + name + "'</span>";
            }
            return div;
        },
        formatLogNode : function(clone, token_id) {
            var name = this.getTokenName(token_id);
            var logid = "log" + (this.globalid++) + "_" + token_id;
            dojo.attr(clone, "id", logid);
            dojo.removeClass(clone, 'active_slot selected');
            dojo.addClass(clone, "logitem");
            this.stripPosition(clone);
            dojo.attr(clone, "title", name);
            dojo.attr(clone, "aria-label", name);
            return clone;
        },
        showError : function(log, args) {
            if (typeof args == 'undefined') {
                args = {};
            }
            args.you = this.divYou();
            var message = this.format_string_recursive(log, args);
            this.showMessage(log, 'error');
            console.error(log);
            return;
        },
        // /////////////////////////////////////////////////
        // // Player's action
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
            console.log('notifications subscriptions setup');
            dojo.subscribe('tokenMoved', this, "notif_tokenMoved");
            dojo.subscribe('tokenMovedAsync', this, "notif_tokenMoved"); // same as tokenMoved but no delay
            dojo.subscribe('playerLog', this, "notif_playerLog");
            dojo.subscribe('counter', this, "notif_counter");
            dojo.subscribe('counterAsync', this, "notif_counter"); // same as conter but no delay
            dojo.subscribe('score', this, "notif_score");
            dojo.subscribe('log', this, "notif_log");
            dojo.subscribe('animate', this, "notif_animate");
            this.notifqueue.setSynchronous('tokenMoved', 550);
            this.notifqueue.setSynchronous('animate', 1000);
        },
        notif_playerLog : function(notif) {
            // pure log
        },
        notif_animate : function(notif) {
            // do nothing, just there to play animation from previous notifications
        },
        notif_tokenMoved : function(notif) {
            // console.log('notif_tokenMoved');
            // console.log(notif);
            var token = notif.args.token_id;
            if (!this.gamedatas.tokens[token]) {
                this.gamedatas.tokens[token] = {
                    key : token
                };
            }
            this.gamedatas.tokens[token].location = notif.args.place_id;
            this.gamedatas.tokens[token].state = notif.args.new_state;
//            console.log("** notif moved " + token + " -> " + notif.args.place_id + " (" + notif.args.new_state + ")");
            this.gamedatas_local.tokens[token] = dojo.clone(this.gamedatas.tokens[token]);
            this.gamedatas_server.tokens[token] = dojo.clone(this.gamedatas.tokens[token]);
            this.placeTokenWithTips(token, this.gamedatas.tokens[token], notif.args);
        },
        notif_log : function(notif) {
            // this is for debugging php side
            console.log(notif.log);
            console.log(notif.args);
        },
        notif_counter : function(notif) {
            try {
                this.gamedatas.counters[notif.args.counter_name].counter_value = notif.args.counter_value;
                this.gamedatas_local.counters[notif.args.counter_name].counter_value = notif.args.counter_value;
                this.gamedatas_server.counters[notif.args.counter_name].counter_value = notif.args.counter_value;
                this.updateCountersSafe(this.gamedatas.counters);
//                console.log("** notif counter " + notif.args.counter_name + " -> " + notif.args.counter_value);
                this.animCounter(notif.args);
            } catch (ex) {
                console.error("Cannot update " + notif.args.counter_name, notif, ex, ex.stack);
            }
        },
        notif_score : function(notif) {
            // console.log(notif);
            this.scoreCtrl[notif.args.player_id].setValue(notif.args.player_score);
            this.animScore(notif.args);
        },
    });
});

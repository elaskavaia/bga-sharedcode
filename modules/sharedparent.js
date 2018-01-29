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
            this.globalid = 1; // global id used to inject tmp id's of objects
            this.clientStateArgs = {}; // collector of client state arguments
            this.setupNotifications();
            console.log("Ending game setup parent");
        },
        setupPlayer : function(player_id, playerInfo) {
            // does nothing here, override
            console.log("player info "+player_id,playerInfo);
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
            dojo.style(mobile, {
                left : left + "px",
                top : top + "px"
            });
            // console.log("attache " + left + "," + top);
            box.l += box.w - cbox.w;
            box.t += box.h - cbox.h;
            return box;
        },
        /**
         * This method is similar to slideToObject but works on object which do not use inline style positioning. It also attaches object to
         * new parent immediately, so parent is correct during animation
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
                self.placeOnObjectDirect(token, finalPlace, box.l, box.t);
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
                self.placeOnObjectDirect(token, finalPlace, x, y);
            }, function() {
                self.stripTransition(token);
                if (onEnd) onEnd(token);
            }, duration, delay);
        },
        placeOnObjectDirect : function(mobileObj, targetObj, x, y) {
            var left = dojo.style(mobileObj, "left");
            var top = dojo.style(mobileObj, "top");
            // console.log("place " + x + "," + y);
            dojo.style(mobileObj, {
                left : x + "px",
                top : y + "px"
            });
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
        setClientStateAction : function(stateName, desc, delay) {
            var args = dojo.clone(this.gamedatas.gamestate.args);
            if (this.clientStateArgs.action) 
                args.actname = this.getTr(this.clientStateArgs.action);
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
            if (this.on_client_state) {
                this.clientStateArgs = {};
                this.gamedatas_local = dojo.clone(this.gamedatas);
                if (this.restoreList) {
                    var restoreList = this.restoreList;
                    this.restoreList = [];
                    for (var i = 0; i < restoreList.length; i++) {
                        var token = restoreList[i];
                        var tokenInfo = this.gamedatas.tokens[token];
                        this.placeTokenWithTips(token, tokenInfo);
                    }
                }
            }
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
            var color = this.gamedatas.players[this.player_id].color;
            var color_bg = "";
            if (this.gamedatas.players[this.player_id] && this.gamedatas.players[this.player_id].color_back) {
                color_bg = "background-color:#" + this.gamedatas.players[this.player_id].color_back + ";";
            }
            var you = "<span style=\"font-weight:bold;color:#" + color + ";" + color_bg + "\">" + __("lang_mainsite", "You") + "</span>";
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
        updateTooltip : function(token, attachTo) {
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
                var main = this.getTooptipHtml(tokenInfo.name, tokenInfo.tooltip, tokenInfo.imageTypes, "<hr/>");
                if (!tokenInfo.tooltip && tokenInfo.name) {
                    $(token).title = this.getTr(tokenInfo.name);
                    return;
                }
                if (main) {
                    var action = tokenInfo.tooltip_action;
                    if (action) {
                        this.addTooltipHtml(attachTo, main + "<br/>" + this.getActionLine(action));
                    } else {
                        this.addTooltipHtml(attachTo, main);
                    }
                    dojo.removeAttr(attachTo, 'title'); // unset title so both title and tooltip do not show up
                }
            }
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
        getTooptipHtml : function(name, message, imgTypes, sep, dyn) {
            if (name == null || message == "-") return "";
            if (!message) message = "";
            if (!dyn) dyn = "";
            var divImg = "";
            if (imgTypes) divImg = "<div class='tooltipimage " + imgTypes + "'></div>";
            return "<div class='tooltipcontainer'><span class='tooltiptitle'>" + this.getTr(name) + "</span>" + sep + "<div>" + divImg +
                    "<div class='tooltipmessage tooltiptext'>" + this.getTr(message) + dyn + "</div></div></div>";
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
                imageTypes += " "+tokenKey + " ";
            }
            if (parts.length>=4) {
                imageTypes += " "+parts[0] + "_"+ parts[2]+" ";
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
            var playerId = this.getActivePlayerId();
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
            if (state !== null) 
                tokenInfo.state = state;
            this.on_client_state = true;
            this.placeToken(token, tokenInfo);
        },
        placeToken : function(token, tokenInfo, args) {
            try {
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
                if (!$(place)) {
                    console.error("Unknown place " + place + " for " + tokenInfo.key + " " + token);
                    return;
                }
                if (this.on_client_state) {
                    if (this.restoreList.indexOf(token) < 0) {
                        this.restoreList.push(token);
                    }
                }
                if (tokenNode == null) {
                    if (placeInfo.temp) { return; }
                    tokenNode = this.createToken(token, tokenInfo, place);
                    if (tokenNode == null) { return; }
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
                    dojo.style(token, 'z-index', parseInt(placeInfo.zindex));
                }
                if (placeInfo.inlinecoords) {
                    this.slideToObjectAbsolute(token, place, placeInfo.x, placeInfo.y, 500);
                } else {
                    this.stripPosition(token);
                    this.stripTransition(token);
                    if (tokenNode.parentNode.id == place) {
                        // already there
                    } else {
                        if (noAnnimation) {
                            if (placeInfo.temp) {
                                dojo.destroy(token);
                            } else {
                                dojo.place(token, place);
                            }
                        } else {
                            if (placeInfo.temp) {
                                this.slideToObjectRelative(token, place, 500, 0, this.fadeOutAndDestroy);
                            } else {
                                this.slideToObjectRelative(token, place, 500);
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
        createToken : function(token, tokenInfo, place, connectFunc) {
            var info = this.getTokenDisplayInfo(token);
            
            if (info==null) {
                console.error("Don't know how to create ",token,tokenInfo);
                return;
            }
            var tokenMainType = info.mainType;
            var jstpl_token = '<div class="${classes} ${id} token" id="${id}"></div>';
            var tokenDiv = this.format_string_recursive(jstpl_token, {
                "id" : token,
                "classes" : info.imageTypes,
            });
            
            if (!connectFunc && info.connectFunc) {
                connectFunc = info.connectFunc;
            }
            
           
            if (place) {
                // console.log(token + ": " + tokenInfo.key + " - created -> " + place + " " + tokenInfo.state);
                tokenNode = dojo.place(tokenDiv, tokenInfo.from_place ? tokenInfo.from_place : place);
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
                }
            }
            this.updateCounters(safeCounters);
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
            dojo.subscribe('score', this, "notif_score");
            dojo.subscribe('log', this, "notif_log");
            dojo.subscribe('animate', this, "notif_animate");
            this.notifqueue.setSynchronous('tokenMoved', 500);
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
            console.log("** notif moved " + token + " -> " + notif.args.place_id + " (" + notif.args.new_state + ")");
            this.gamedatas_local.tokens[token] = dojo.clone(this.gamedatas.tokens[token]);
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
                this.updateCounters(this.gamedatas.counters);
            } catch (ex) {
                console.error("Cannot update " + notif.args.counter_name, nofif, ex.stack);
            }
            // this.placeResource(notif.args.resource_id, notif.args.place_id, notif.args.inc, notif.args.player_id);
        },
        notif_score : function(notif) {
            // console.log(notif);
            this.scoreCtrl[notif.args.player_id].setValue(notif.args.player_score);
            // var color = this.getPlayerColor(notif.args.player_id);
            // this.setCounter('coin_' + color + '_counter', notif.args.player_score);
            if (notif.args.source) {
                // local animation
            }
        },
    });
});

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
define([
  "dojo",
  "dojo/_base/declare",
  "ebg/core/gamegui",
  "ebg/counter",
  "ebg/stock", // stock
  "ebg/scrollmap", // scollmap
  // load my own module!!!
  g_gamethemeurl + "modules/sharedparent.js",
], function (dojo, declare) {
  return declare("bgagame.sharedcode", bgagame.sharedparent, {
    constructor: function () {
      console.log("sharedcode constructor");
      console.log(this.globalid);
      // Here, you can init the global variables of your user interface
      // Example:
      // this.myGlobalValue = 0;
      // Scrollable area
      this.scrollmap = new ebg.scrollmap();
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
    setup: function (gamedatas) {
      console.log("Starting game setup", gamedatas);
      this.inherited(arguments); // parent common setup
      // TODO: Set up your game interface here, according to "gamedatas"
      this.resourceIdCounterLocal = 1;
      // connect zones, they always there
      this.connectClass("basket", "onclick", "onBasket");
      this.connectClass("action_space", "onclick", "onActionSpace");
      // connect cubes
      this.connectClass("wcube", "onclick", "onCube");
      this.connectClass("location", "onclick", "onLocation");

      dojo.create("div", { class: "card purple", id: "card_purple" }, "basket_1");

      this.purpleDrag = this.createMyDraggable("card_purple", ["basket_1", "basket_2"]);
      this.playerHand = new ebg.stock();
      this.initMyStock(this.playerHand, $("hand"));

      this.playArea = new ebg.stock();
      this.initMyStock(this.playArea, $("playarea"));

      document.querySelectorAll("#select-stock-conatiner > *").forEach((item) => {
        item.addEventListener("click", (event) => this.onChangeStockType(event));
      });

      var hand = this.gamedatas.hand;
      for (var i = 0; i < hand.length; i++) {
        var card = this.gamedatas.hand[i];
        this.playerHand.addToStockWithId(card.type_arg, card.id);
      }
      var hand = this.gamedatas.playarea;
      for (var i = 0; i < hand.length; i++) {
        var card = this.gamedatas.hand[i];
        this.playArea.addToStockWithId(card.type_arg, card.id);
      }
      // Make map scrollable
      this.scrollmap.create($("map_container"), $("map_scrollable"), $("map_surface"), $("map_scrollable_oversurface"));
      this.scrollmap.setupOnScreenArrows(150); // this will hook buttons to onclick functions with 150px scroll step
      dojo.create("div", { class: "smeeple" }, "map_scrollable_oversurface");

      //Add animation hooks for flexbox
      this.connectClass("card_x", "onclick", "onCardX");
      this.connectClass("location_x", "onclick", "onLocationX");
      // add dialog buttons
      dojo.connect($("button_askForValueDialog"), "onclick", () => {
        this.askForValueDialog("Enter VALUE", (x) => alert("yes! " + x), "anything");
      });
      var button_ask = $("button_askForValueDialog2");
      dojo.connect(button_ask, "onclick", () => {
        this.askForValueDialog("Edit button text", (x) => {
          button_ask.innerHTML = x;
        });
        $("choicedlg_value").value = button_ask.innerHTML;
      });
      //multipleChoiceDialog
      var button_md = $("button_multipleChoiceDialog");
      dojo.connect(button_md, "onclick", () => {
        this.multipleChoiceDialog(
          "Select one",
          {
            1: "One",
            2: "Two",
            none: "None",
          },
          (x) => {
            button_md.innerHTML = "multipleChoiceDialog picked " + x;
          }
        );
      });
      //			var button_edittitle = $('button_editBugTitle');
      //			dojo.connect(button_edittitle, 'onclick', () => {
      //				var oldTitle=$('report_name').innerHTML;
      //				//#49: "Let developers rename bug reports"
      //				// we need to leave title without bug id and quotes
      //				oldTitle=oldTitle.split('"')[1];
      //				this.askForValueDialog(_("Edit bug title"), (title) => {
      //					this.ajaxcall('/bug/bug/editBugTitle.html', { name: title }, this, function(result) {
      //						mainsite.gotourl_forcereload('bug?id=' + this.report_id);
      //					});
      //				});
      //				// set default value (the old title), doing it after dialog is open because this method does not have default value parameter
      //				$('choicedlg_value').value = oldTitle;
      //			});
      //
      // add reload Css debug button
      var parent = document.querySelector(".debug_section");
      if (parent) {
        var butt = dojo.create("a", { class: "bgabutton bgabutton_gray", innerHTML: "Reload CSS" }, parent);
        dojo.connect(butt, "onclick", () => reloadCss());
      }

      console.log("Ending game setup");
    },

    setupPlayer: function (playerId, playerInfo) {
      var playerBoardDiv = dojo.byId("player_board_" + playerId);
      var div = this.format_block("jstpl_player_board", playerInfo);
      var block = dojo.place(div, playerBoardDiv);
      var cubeColors = ["gray", "pink", "purple"];
      for (var i in cubeColors) {
        var color = cubeColors[i];
        var tokenDiv = this.format_block("jstpl_resource_counter", {
          id: "p" + playerInfo.color,
          type: "wcube",
          color: color,
        });
        dojo.place(tokenDiv, block);
      }
      // this is meeple in the player color
      var playerColorName = g_colorHexToName[playerInfo.color];
      var tokenDiv = this.format_block("jstpl_resource_counter", {
        id: "pc",
        type: "smeeple",
        color: playerColorName,
      });
      dojo.place(tokenDiv, block);
    },
    // /////////////////////////////////////////////////
    // // Game & client states
    // onEnteringState: this method is called each time we are entering into a new game state.
    // You can use this method to perform some user interface changes at this moment.
    //
    onEnteringState: function (stateName, args) {
      console.log("Entering state: " + stateName);
      this.inherited(arguments); // parent common code
      switch (stateName) {
        case "playerTurn":
          break;
      }
    },
    // onLeavingState: this method is called each time we are leaving a game state.
    // You can use this method to perform some user interface changes at this moment.
    //
    onLeavingState: function (stateName) {
      this.inherited(arguments); // parent common code
      console.log("Leaving state: " + stateName);
    },
    // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
    // action status bar (ie: the HTML links in the status bar).
    //
    onUpdateActionButtons: function (stateName, args) {
      this.inherited(arguments); // parent common code
      console.log("onUpdateActionButtons: " + stateName);
      if (this.isCurrentPlayerActive()) {
        switch (stateName) {
          case "playerTurn":
            dojo.query(".action_space").addClass("active_slot");
            // add text action button
            this.addActionButton(
              "button_pass",
              _("Pass"),
              dojo.hitch(this, function () {
                this.ajaxAction("pass", {});
              })
            );
            break;
          case "client_selectCubeLocation":
            dojo.query(".basket").addClass("active_slot");
            dojo.addClass(this.clientStateArgs.token_id, "selected");
            this.addActionButton("button_cancel", _("Cancel"), "onCancel");
            break;
          case "playerTurnPlayCubes":
            console.log(args);
            // get arguments from state args
            this.cubeTypeNumber = args.cubeTypeNumber;
            this.resourceIdCounter = args.resource_id_counter;
            dojo.query(".wcube").addClass("active_slot");
            // add image action button
            var keys = Object.keys(g_colorNameToHex);
            this.takeCubeColor = keys[this.cubeTypeNumber];
            var tokenDiv = this.format_block("jstpl_resource", {
              id: "0",
              type: "wcube",
              color: this.takeCubeColor,
            });
            this.addImageActionButton("button_take", tokenDiv, "onTakeCube");
            break;

          case "playerTurnPlayCards":
            dojo.query(".playarea,.hand").addClass("active_slot");
            this.addActionButton("button_draw", _("Draw"), () => {
              this.ajaxAction("drawCard", {});
            });
            this.addActionButton("button_play", _("Play"), () => {
              this.clientStateArgs.action = "playCard";
              const selItem = document.querySelector(".stockitem.card.selected");
              if (!selItem) {
                this.setClientState("client_selectCard");
                return;
              }
              this.clientStateArgs.card_id = selItem.getAttribute("data-card-id");
              this.ajaxClientStateAction();
            });
            this.addActionButton("button_done", _("Done"), "onDone");
            break;
          case "client_selectCard":
            this.setDescriptionOnMyTurn(_("Select card from hand"));
            dojo.query(".hand > .card").addClass("active_slot");
            this.addActionButton("button_cancel", _("Cancel"), "onCancel");
            break;
          case "client_selectCardLocation":
            this.setDescriptionOnMyTurn(_("Select location"));
            dojo.query(".playarea,.hand").addClass("active_slot");
            const selItem = document.querySelector(`[data-card-id='${this.clientStateArgs.card_id}']`);
            dojo.addClass(selItem, "selected");
            this.addActionButton("button_cancel", _("Cancel"), "onCancel");
            break;
        }
      }
    },
    // /////////////////////////////////////////////////
    // // Utility methods

    getDragTarget: function (item_id, allDragTargets, left, top) {
      for (var i = 0; i < allDragTargets.length; i++) {
        var pid = allDragTargets[i];
        var coords = dojo.position(pid);
        var coordsItem = dojo.position(item_id);

        var inb = this.isInBounds(coordsItem.x, coordsItem.y, coords, 10);
        //console.log("coords ", coords.x, coords.y, coords.w, coords.h, "pos", left, top, "coords2 ", coordsItem.x, coordsItem.y,"=>",inb);
        if (inb) return pid;
      }
      return null;
    },
    isInBounds: function (x, y, box, margin) {
      if (typeof margin == "undefined") margin = 5;
      if (x < box.x - margin) return false;
      if (x > box.x + box.w + margin) return false;
      if (y < box.y - margin) return false;
      if (y > box.y + box.h + margin) return false;
      return true;
    },
    createMyDraggable: function (targetDivId, allDragTargets) {
      var draggableObj = new ebg.draggable();
      draggableObj.create(this, targetDivId, targetDivId);

      dojo.connect(draggableObj, "onStartDragging", this, (item_id, left, top) => {
        console.log("onStart", item_id, left, top);
        this.attachToNewParentNoDestroy(targetDivId, $(targetDivId).parentNode);

        for (var i = 0; i < allDragTargets.length; i++) {
          dojo.addClass(allDragTargets[i], "drag_target");
        }
      });
      dojo.connect(draggableObj, "onDragging", this, (item_id, left, top, dx, dy) => {
        //console.log("onDrag", item_id, left, top, dx, dy);

        var targetParent = this.getDragTarget(item_id, allDragTargets, left, top);
        if (targetParent) {
          dojo.query(".drag_target_hover").removeClass("drag_target_hover");
          dojo.addClass(targetParent, "drag_target_hover");
        }
      });
      dojo.connect(draggableObj, "onEndDragging", this, (item_id, left, top, bDragged) => {
        console.log("onDrop", item_id, left, top, bDragged);

        if (bDragged) {
          var targetParent = this.getDragTarget(item_id, allDragTargets, left, top);
          if (targetParent) {
            this.attachToNewParentNoDestroy(item_id, targetParent);
            this.stripPosition(item_id);
          } else {
            bDragged = false;
          }
        }
        if (!bDragged) {
          this.stripPosition(item_id);
        }
        for (var i = 0; i < allDragTargets.length; i++) {
          dojo.removeClass(allDragTargets[i], "drag_target");
          dojo.removeClass(allDragTargets[i], "drag_target_hover");
        }
      });
      return draggableObj;
    },

    getStockByTargetId: function (locationId) {
      var tostock = null;
      if (locationId == "playarea") {
        tostock = this.playArea;
      } else if (locationId == "hand") {
        tostock = this.playerHand;
      }
      return tostock;
    },
    getStockSourceByDivId: function (cardDivId) {
      var first = getPart(cardDivId, 0);
      return this.getStockByTargetId(first);
    },
    getStockCardIdByDivId: function (cardDivId) {
      var num = getIntPart(cardDivId, -1);
      return num;
    },

    createMyDraggableInStock: function (targetDivId, allDragTargets) {
      var draggableObj = new ebg.draggable();
      draggableObj.create(this, targetDivId, targetDivId);

      dojo.connect(draggableObj, "onStartDragging", this, (item_id, left, top) => {
        //console.log("onStart", item_id, left, top);
      });
      dojo.connect(draggableObj, "onDragging", this, (item_id, left, top, dx, dy) => {
        //console.log("onDrag", item_id, left, top, dx, dy);
        if (item_id instanceof HTMLElement) item_id = item_id.id;
        var targetParent = this.getDragTarget(item_id, allDragTargets, left, top);
        if (targetParent) {
          dojo.query(".drag_target_hover").removeClass("drag_target_hover");
          dojo.addClass(targetParent, "drag_target_hover");
        }
      });
      dojo.connect(draggableObj, "onEndDragging", this, (item_id, left, top, bDragged) => {
        if (!bDragged) return;
        if (item_id instanceof HTMLElement) item_id = item_id.id;
        //console.log("onDrop", item_id, left, top, bDragged);
        var targetParent = this.getDragTarget(item_id, allDragTargets, left, top);
        const fromstock = this.getStockSourceByDivId(item_id);
        const cardId = this.getStockCardIdByDivId(item_id);
        const tostock = this.getStockByTargetId(targetParent);
        if (tostock && tostock != fromstock) {
          var cardType = fromstock.getItemTypeById(cardId);
          tostock.addToStockWithId(cardType, cardId, item_id);
          fromstock.removeFromStockById(cardId, undefined, true);
        } else {
          fromstock.resetItemsPosition();
        }

        dojo.query(".drag_target_hover").removeClass("drag_target_hover");
      });
      return draggableObj;
    },

    // initialize any stock component that can work with my cards
    initMyStock: function (mystock, parent) {
      mystock.create(this, parent, 64, 78);
      //mystock.order_items = false;
      mystock.selectionApparance = "class";
      mystock.selectionClass = "selected";
      mystock.onChangeSelection = (parent_id, item_id) => {
        if (item_id !== undefined) {
          if (mystock.isSelected(item_id)) {
            this.onCardHandler(item_id);
          }
        }
      };
      mystock.onItemDelete = (card_div, card_type_id, card_id) => {
        console.log("card deleted from myStock: " + card_id);
      };
      mystock.onItemCreate = (div, card_type_id, card_id) => {
        card_id = this.getStockCardIdByDivId(div.id);
        console.log("card added myStock: " + card_id);

        var cardInfo = this.getCardInfoByTypeId(card_type_id);
        dojo.addClass(div, "card " + cardInfo.key);
        div.setAttribute("data-card-id", card_id);

        dojo.setStyle(div, "background-color", cardInfo.cn);
        //console.log(`setting bg ${card.cn} on ${div}`);
        this.updateTooltip(cardInfo.type, div);

        this.createMyDraggableInStock(div, ["playarea", "hand"]);
      };
      //var cardImage =  g_gamethemeurl + 'img/78_64_stand_meeples.png';
      var cardImage = g_gamethemeurl + "img/none.png";
      for (var key in this.gamedatas.token_types) {
        if (key.startsWith("card")) {
          var cardInfo = this.gamedatas.token_types[key];

          mystock.addItemType(cardInfo.t, cardInfo.t, cardImage, cardInfo.ipos);
        }
      }
    },
    onChangeStockType: function (event) {
      const stock = this.playerHand;
      console.log(event);
      stock.container_div.style.removeProperty("width");
      stock.container_div.style.removeProperty("height");
      stock.horizontal_overlap = parseInt($("h-overlap").value);
      stock.vertical_overlap = parseInt($("v-overlap").value);
      stock.item_margin = parseInt($("item_margin").value);
      const s_width = parseInt($("s_width").value);
      stock.autowidth = !!$("s_autowidth").checked;
      stock.use_vertical_overlap_as_offset = !!$("s_as_offset").checked;
      stock.centerItems = !!$("centerItems").checked;
      stock.order_items = !!$("order_items").checked;

      if ($("radio-vertical").checked) {
        stock.container_div.style.width = s_width + "px";
      }

      stock.updateDisplay();
    },
    getCardInfoByTypeId: function (card_type_id) {
      for (var key in this.gamedatas.token_types) {
        if (key.startsWith("card")) {
          var cardInfo = this.gamedatas.token_types[key];
          cardInfo.key = key;
          if (parseInt(cardInfo.t) == parseInt(card_type_id)) {
            return cardInfo;
          }
        }
      }
    },

    /** @Override */
    format_string_recursive: function (log, args) {
      try {
        if (log && args && !args.processed) {
          args.processed = true;
          args.You = this.divYou(); // will replace ${You} with colored version
          var keys = ["place_name", "token_name"];
          for (var i in keys) {
            var key = keys[i];
            // console.log("checking " + key + " for " + log);
            if (typeof args[key] == "string") {
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
    getTokenDiv: function (key, args) {
      // ... implement whatever html you want here
      var token_id = args[key];
      var item_type = getPart(token_id, 0);
      var logid = "log" + this.globalid++ + "_" + token_id;
      switch (item_type) {
        case "wcube":
          var tokenDiv = this.format_block("jstpl_resource_log", {
            id: logid,
            type: "wcube",
            color: getPart(token_id, 1),
          });
          return tokenDiv;
        case "meeple":
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
    onTakeCube: function (event) {
      var id = event.currentTarget.id;
      dojo.stopEvent(event);
      // create new cube
      this.resourceIdCounterLocal += 1;
      var tokenDiv = this.format_block("jstpl_resource", {
        id: this.resourceIdCounterLocal,
        type: "wcube",
        color: this.takeCubeColor,
      });
      // place on the button
      var cubeNode = dojo.place(tokenDiv, id);
      // connect clicker
      this.connect(cubeNode, "onclick", "onCube");
      // slide
      this.slideToObjectRelative(cubeNode, "basket_1");
      this.clientStateArgs.token_id = cubeNode.id;
      this.clientStateArgs.place_id = "basket_1";
      this.ajaxAction("takeCube", this.clientStateArgs);
    },
    onCube: function (event) {
      var id = event.currentTarget.id;
      dojo.stopEvent(event);
      console.log("on cube " + id);
      if (!this.checkActivePlayerAndSlot(id)) return;
      dojo.addClass(id, "selected");
      this.clientStateArgs.token_id = id;
      this.setClientState("client_selectCubeLocation", {
        descriptionmyturn: _("${you} must select location for the cube"),
      });
    },
    onBasket: function (event) {
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

    onActionSpace: function (event) {
      var id = event.currentTarget.id;
      dojo.stopEvent(event);
      console.log("on zone " + id);
      if (!this.checkActivePlayerAndSlot(id)) return;
      dojo.addClass(id, "selected");
      this.clientStateArgs.action_id = id;
      // create new meeple
      this.resourceIdCounterLocal += 1;
      var playerColorName = g_colorHexToName[this.player_color];
      var tokenDiv = this.format_block("jstpl_resource", {
        id: this.resourceIdCounterLocal,
        type: "smeeple",
        color: playerColorName,
      });
      // place on the player board
      var meepleNode = dojo.place(tokenDiv, "smeeple_" + playerColorName + "_pc_div");
      this.slideToObjectRelative(meepleNode, id);
      this.clientStateArgs.worker_id = meepleNode.id;
      this.ajaxAction("selectWorkerAction", this.clientStateArgs);
    },
    onDone: function (event) {
      dojo.stopEvent(event);
      console.log("on done");
      //...
      this.ajaxClientStateAction();
    },
    onCard: function (event) {
      var id = event.currentTarget.id;
      dojo.stopEvent(event);
      console.log("on card " + id);
      //..
    },
    onCardHandler: function (card_id) {
      this.clientStateArgs.card_id = card_id;
      switch (this.getStateName()) {
        case "client_selectCard":
          this.setClientState("client_selectCardLocation");
          break;
      }
    },

    onCardX: function (event) {
      var id = event.currentTarget.id;
      dojo.stopEvent(event);
      console.log("on card " + id);
      //..
      this.moveClass("selected", id);
    },

    onLocationX: function (event) {
      var id = event.currentTarget.id;
      dojo.stopEvent(event);
      console.log("on location " + id);

      var node = document.querySelector(".selected.card_x");
      if (!node) {
        this.showError("Nothing is selected");
        return;
      }
      var newLocId = id;
      var to = $(newLocId);
      var relation;

      if (newLocId.startsWith("abs")) {
        // Then refer to
        var rect = event.target.getBoundingClientRect();
        var x = event.clientX - rect.left; //x position within the element.
        var y = event.clientY - rect.top; //y position within the element.

        this.slideToObjectAbsolute(node, to, x, y, 1000, 0, null);
      } else {
        var num = getPart(node.id, 2);

        while (--num > 0) {
          var tnode = $("card_x_" + num);
          if (tnode && tnode.parentNode.id == newLocId) {
            newLocId = tnode;
            relation = "after";
            break;
          }
        }
        var to = $(newLocId);
        this.slideToObjectRelative(node, to, 1000, 0, null, relation);
      }
    },

    onLocation: function (event) {
      var id = event.currentTarget.id;
      dojo.stopEvent(event);
      console.log("on zone " + id);
      if (!this.checkActivePlayerAndSlot(id)) return;
      var fromstock = this.playerHand;
      const tostock = this.getStockByTargetId(id);
      if (!tostock) {
        this.showError("Invalid move");
        return;
      }
      if (fromstock == tostock) fromstock = this.playArea;

      var selected = fromstock.getSelectedItems();
      dojo.addClass(id, "selected");
      this.clientStateArgs.place_id = id;

      for (var i = 0; i < selected.length; i++) {
        var scard = selected[i];
        // client side animation
        tostock.addToStockWithId(scard.type, scard.id, fromstock.container_div.id);
        fromstock.removeFromStockById(scard.id);
      }
      switch (this.getStateName()) {
        case "client_selectCardLocation":
          this.ajaxClientStateAction();
          break;
      }
    },
    /**
     * This is light weight undo support. You use local states, and this one erases it.
     */
    onCancel: function (event) {
      dojo.stopEvent(event);
      console.log("on cancel");
      this.cancelLocalStateEffects();
    },

    moveCard: function (card) {
      const loc = card.location;
      const prev = document.querySelector("[data-card-id='" + card.id + "']");
      let fromstock = null;
      if (prev) {
        fromstock = this.getStockSourceByDivId(prev.id);
      }
      const tostock = this.getStockByTargetId(loc);

      if (loc == "hand") {
        if (this.player_id != card.location_arg) {
          tostock = null;
        }
      }
      if (tostock == fromstock && tostock != null) {
        tostock.resetItemsPosition();
        return;
      }

      if (tostock != null) {
        tostock.addToStockWithId(card.type_arg, card.id);
      }

      if (fromstock != null) {
        fromstock.removeFromStockById(card.id);
      }
    },

    notif_moveCard: function (notif) {
      this.moveCard(notif.args.card);
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
    setupNotifications: function () {
      this.inherited(arguments); // parent common notifications for euro game
      dojo.subscribe("moveCard", this, "notif_moveCard");
      this.notifqueue.setSynchronous("moveCard", 550);
    },
  });
});

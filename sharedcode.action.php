<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SharedCode implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 * 
 * sharedcode.action.php
 *
 * SharedCode main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/sharedcode/sharedcode/myAction.html", ...)
 *
 */
class action_sharedcode extends APP_GameAction {
    // Constructor: please do not modify
    public function __default() {
        if (self::isArg('notifwindow')) {
            $this->view = "common_notifwindow";
            $this->viewArgs ['table'] = self::getArg("table", AT_posint, true);
        } else {
            $this->view = "sharedcode_sharedcode";
            self::trace("Complete reinitialization of board game");
        }
    }

    public function selectWorkerAction() {
        self::setAjaxMode();
        $arg1 = self::getArg("action_id", AT_alphanum, true);
        $arg2 = self::getArg("worker_id", AT_alphanum, true);
        $this->game->action_selectWorkerAction($arg1, $arg2);
        self::ajaxResponse();
    }
    
    public function takeCube() {
        self::setAjaxMode();
        $arg1 = self::getArg("token_id", AT_alphanum, true);
        $arg2 = self::getArg("place_id", AT_alphanum, false);
        $this->game->action_takeCube($arg1, $arg2);
        self::ajaxResponse();
    }
    
    public function moveCube() {
        self::setAjaxMode();
        $arg1 = self::getArg("token_id", AT_alphanum, true);
        $arg2 = self::getArg("place_id", AT_alphanum, true);
        $this->game->action_moveCube($arg1, $arg2);
        self::ajaxResponse();
    }
    
    public function pass() {
        self::setAjaxMode();
        $this->game->action_pass();
        self::ajaxResponse();
    }
}
  


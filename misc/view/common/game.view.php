<?php

/**
 * game_view.php
 * stubs
 *
 */
class APP_Template {
    function __construct() {
    }


    function begin_block($template_name, $block_name) {
    }

    function begin_subblock($template_name, $block_name) {
    }

    function insert_block($block_name, $tpl = array ()) {
    }

    function insert_subblock($block_name, $tpl = array ()) {
    }

    function insert_template($target_variable, $template_name, $tpl_variable_table) {
        
    }

    function reset_subblocks($subblock_name) {
    }
}

abstract class APP_View extends APP_DbObject {
    // Data to be analyzed by template
    protected array $tpl = array ();
    // Template object
    protected APP_Template $page;
    // View main template name
    protected string $template_name;
}

abstract class ebg_view extends APP_View {
}

abstract class game_view extends ebg_view {
    var $table_id;
    var $game;
    var $game_id;
    var $bIsSpectator = true;

    abstract protected function getGameName();

    // Translation function 
    protected function _($text) {
        return $text;
    }

    // Function to be override by children
    abstract protected function build_page($viewArgs);

    protected function isArchiveMode() {
        return false;
    }

    protected function isRealtimeTable() {
        return true;
    }
}



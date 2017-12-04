<?php
if(!defined('DOKU_INC')) {
    define('DOKU_INC', /** @scrutinizer ignore-type */ realpath(dirname(__FILE__).'/../../../').'/');
}
if(!defined('DOKU_PLUGIN')) {
    define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
}
require_once(DOKU_PLUGIN.'admin.php');

/**
 * All DokuWiki plugins to extend the admin function
 * need to inherit from this class
 */
class admin_plugin_siteexport extends DokuWiki_Admin_Plugin {

    /**
     * Constructor
     */
    public function __construct() {
        $this->setupLocale();
    }

    /**
     * return sort order for position in admin menu
     */
    public function getMenuSort() {
        return 100;
    }

    public function forAdminOnly() {
        return false;
    }

    /**
     * handle user request
     */
    public function handle() {
    }

    /**
     * output appropriate html
     */
    public function html() {

        if (!$functions = & plugin_load('helper', 'siteexport')) {
            msg("Can't initialize");
            return false;
        }
        
        $functions->__siteexport_addpage();
    }
}
//Setup VIM: ex: et ts=4 enc=utf-8 :

<?php
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'admin.php');
require_once(DOKU_PLUGIN.'siteexport/preload.php');

/**
 * All DokuWiki plugins to extend the admin function
 * need to inherit from this class
 */
class admin_plugin_siteexport extends DokuWiki_Admin_Plugin {

    /**
     * Constructor
     */
    function __construct() {
        $this->setupLocale();
    }

    /**
     * for backward compatability
     * @see inc/DokuWiki_Plugin#getInfo()
     */
    function getInfo(){
        if ( method_exists(parent, 'getInfo')) {
            $info = parent::getInfo();
        }
        return is_array($info) ? $info : confToHash(dirname(__FILE__).'/plugin.info.txt');
    }

    /**
     * return sort order for position in admin menu
     */
    function getMenuSort() {
        return 100;
    }

    function forAdminOnly(){
        return false;
    }

    /**
     * handle user request
     */
    function handle() {
    }

    /**
     * output appropriate html
     */
    function html() {

        if ( ! $functions=& plugin_load('helper', 'siteexport') ) {
            msg("Can't initialize");
            return false;
        }
        
        $functions->__siteexport_addpage();
    }
}
//Setup VIM: ex: et ts=4 enc=utf-8 :

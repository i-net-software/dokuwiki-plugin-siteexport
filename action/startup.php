<?php
/**
 * Site Export Plugin
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_siteexport_startup extends DokuWiki_Action_Plugin {

	/**
	 * for backward compatability
	 * @see inc/DokuWiki_Plugin#getInfo()
	 */
    function getInfo(){
        if ( method_exists(parent, 'getInfo')) {
            $info = parent::getInfo();
        }
        return is_array($info) ? $info : confToHash(dirname(__FILE__).'/../plugin.info.txt');
    }
	
    /**
	* Register Plugin in DW
	**/
	function register(&$controller) {
		$controller->register_hook('DOKUWIKI_STARTED', 'AFTER', $this, 'siteexport_check_template');
		$controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'siteexport_check_export');
	}
	
	/**
	* Check for Template changes
	**/
	function siteexport_check_template()
	{
		global $conf, $INFO;
	
		if ( !defined('SITEEXPORT_TPL') ) { return; }
		$conf['template'] = SITEEXPORT_TPL;
	}
	
	function siteexport_check_export(&$event)
	{
	    global $conf;
	    if ( $event->data == 'export_siteexport_pdf')
	    {
	        $event->data = 'show';
	        $conf['renderer_xhtml'] = 'siteexport_pdf';
	    }
	}
}
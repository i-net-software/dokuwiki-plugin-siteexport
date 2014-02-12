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
		$controller->register_hook('INIT_LANG_LOAD', 'BEFORE', $this, 'siteexport_check_template');
		$controller->register_hook('DOKUWIKI_STARTED', 'AFTER', $this, 'siteexport_check_template');
		$controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'siteexport_check_export');
		$controller->register_hook('TEMPLATE_PAGETOOLS_DISPLAY', 'BEFORE', $this, 'siteexport_add_page_export');
		$controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE',  $this, 'siteexport_addpage');
	        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'siteexport_metaheaders');
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
	    $command = is_array($event->data) ? array_keys($event->data)[0] : $event->data;
	    if ( $command == 'export_siteexport_pdf')
	    {
	        $event->data = 'show';
	        $conf['renderer_xhtml'] = 'siteexport_pdf';
	    } 
	    
	    if ( $command == 'siteexport_addpage' && ($this->getConf('allowallusers') || auth_isadmin() || auth_ismanager() ) )
	    {
			$event->preventDefault();
	    }
	}
	
	function siteexport_addpage(&$event)
	{
		if ( $event->data != 'siteexport_addpage' || ! ($this->getConf('allowallusers') || auth_isadmin() || auth_ismanager()) ) { return; }
        if ( ! $functions=& plugin_load('helper', 'siteexport') ) {
            msg("Can't initialize");
            return false;
        }
        
        $functions->__siteexport_addpage();
	    $event->preventDefault();
	}
	
	function siteexport_add_page_export(&$event)
	{
		global $ID;
		
		if ( ($this->getConf('allowallusers') || auth_isadmin() || auth_ismanager()) ) {
			$event->data['items'][] = '<li>' . tpl_link(wl($ID, array('do' => 'siteexport_addpage')), '<span>Export Page</span>',
												'class="action siteexport_addpage" title="Add page"', 1) . '</li>';
		}
	}
	
	function siteexport_metaheaders(&$event)
	{
		if ( defined('SITEEXPORT_TPL') ) {
			
			$head =& $event->data;
			
			foreach( $head['script'] as &$script ) {
				if ( !empty($script['src']) && strstr($script['src'], 'js.php') ) {
					$script['src'] .= '&template=' . SITEEXPORT_TPL;
				}
			}
		}
		
		return true;
	}
}

<?php
/**
 * Site Export Plugin
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) define('DOKU_INC', realpath(dirname(__FILE__) . '/../../') . '/');
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'action.php');

class action_plugin_siteexport_startup extends DokuWiki_Action_Plugin {
	
    /**
     * Register Plugin in DW
     **/
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('INIT_LANG_LOAD', 'BEFORE', $this, 'siteexport_check_template');
        $controller->register_hook('DOKUWIKI_STARTED', 'AFTER', $this, 'siteexport_check_template');
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'siteexport_check_export');
        $controller->register_hook('TEMPLATE_PAGETOOLS_DISPLAY', 'BEFORE', $this, 'siteexport_add_page_export');
        $controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE',  $this, 'siteexport_addpage');
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'siteexport_metaheaders');
        $controller->register_hook('JS_CACHE_USE', 'BEFORE', $this, 'siteexport_check_js_cache');
        $controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'siteexport_toolbar_define');
    }
	
    private function hasSiteexportHeaders() {
        $headers = function_exists('getallheaders') ? getallheaders() : null;
        return is_array($headers) && array_key_exists('X-Site-Exporter', $headers) && $headers['X-Site-Exporter'] = getSecurityToken();
    }
	
    /**
     * Check for Template changes
     **/
    function siteexport_check_template()
    {
        global $conf, $INFO;


        if ( $this->hasSiteexportHeaders() || defined('SITEEXPORT_TPL') ) {
            // This is a request via the HTTPProxy of the SiteExporter ... set config to what we need here.
            $conf['useslash'] = 1;
        }
	
        if ( !defined('SITEEXPORT_TPL') ) { return; }
        $conf['template'] = SITEEXPORT_TPL;
    }

    /**
     * Check for Template changes in JS
     **/
    function siteexport_check_js_cache(&$event)
    {
        global $conf, $INFO;
	
        if ( !defined('SITEEXPORT_TPL') ) { return; }
        $event->data->key .= SITEEXPORT_TPL;
        $event->data->cache = getCacheName($event->data->key,$event->data->ext);
    }
	
    function siteexport_check_export(&$event)
    {
        global $conf;
        $command = is_array($event->data) ? array_shift(array_keys($event->data)) : $event->data;
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
        global $conf;
        $template = defined('SITEEXPORT_TPL') ? SITEEXPORT_TPL : $conf['template'];
			
        $head =& $event->data;
		
        foreach( $head['script'] as &$script ) {
            if ( !empty($script['src']) && strstr($script['src'], 'js.php') ) {
                $script['src'] .= '&template=' . $template;
            }
        }
		
        return true;
    }
	
    function siteexport_toolbar_define(&$event) {
    	
        if ( $this->hasSiteexportHeaders() ) {
            // Remove Toolbar
            // This is pr 5.4 syntax.
            $event->data = array();
        }
    }
}

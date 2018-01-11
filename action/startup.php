<?php
/**
 * Site Export Plugin
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) define('DOKU_INC', /** @scrutinizer ignore-type */ realpath(dirname(__FILE__) . '/../../') . '/');
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
        $controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE',  $this, 'siteexport_addpage');
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'siteexport_metaheaders');
        $controller->register_hook('JS_CACHE_USE', 'BEFORE', $this, 'siteexport_check_js_cache');

        $controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'siteexport_toolbar_define');

        $controller->register_hook('TEMPLATE_PAGETOOLS_DISPLAY', 'BEFORE', $this, 'siteexport_add_page_export');
        $controller->register_hook('MENU_ITEMS_ASSEMBLY', 'AFTER', $this, 'siteexport_add_svg_page_export', array());
    }

    private function hasSiteexportHeaders() {
        $headers = function_exists('getallheaders') ? getallheaders() : null;
        return is_array($headers) && array_key_exists('X-Site-Exporter', $headers);
    }

    /**
     * Check for Template changes
     **/
    public function siteexport_check_template()
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
    public function siteexport_check_js_cache(Doku_Event &$event)
    {
        global $conf, $INFO;

        if ( !defined('SITEEXPORT_TPL') ) { return; }
        $event->data->key .= SITEEXPORT_TPL;
        $event->data->cache = getCacheName($event->data->key,$event->data->ext);
    }

    public function siteexport_check_export(Doku_Event &$event)
    {
        global $conf;
        $keys = is_array($event->data) ? array_keys($event->data) : null;
        $command = is_array($keys) ? array_shift($keys) : $event->data;
        if ( $command == 'export_siteexport_pdf')
        {
            $event->data = 'show';
            $conf['renderer_xhtml'] = 'siteexport_pdf';
        } 

        if ( $command == 'siteexport_addpage' && $this->__executeCommand() )
        {
            $event->preventDefault();
        }
    }

    public function siteexport_addpage(Doku_Event &$event)
    {
        if ( $event->data != 'siteexport_addpage' || ! $this->__executeCommand() ) { return; }
        if ( ! $functions=& plugin_load('helper', 'siteexport') ) {
            msg("Can't initialize");
            return false;
        }

        $functions->__siteexport_addpage();
        $event->preventDefault();
    }

    public function siteexport_add_page_export(Doku_Event &$event)
    {
        global $ID;

        if ( $this->__executeCommand() ) {
            $event->data['items'][] = '<li>' . tpl_link(wl($ID, array('do' => 'siteexport_addpage')), '<span>Export Page</span>',
                                                'class="action siteexport_addpage" title="Export Page (Siteexport)"', 1) . '</li>';

            require_once(DOKU_PLUGIN . 'siteexport/inc/functions.php');
            $functions = new siteexport_functions();

            $check = array();
            $mapIDs = $functions->getMapID($ID, null, $check);
            $mapID = array_shift($mapIDs);
            if ( !empty($mapID) ) {
                $event->data['items'][] = '<li>' . tpl_link('', '<span>Copy Map-ID: <span class="mapID" data-done="Done.">'.$mapID.'</span></span>',
                                                   'class="action siteexport_mapid" title="Show Map-ID"" data-mapid="'.$mapID.'" onclick="copyMapIDToClipBoard.call(this); return false;"', 1) . '</li>';
            }
        }
    }

    public function siteexport_add_svg_page_export(Doku_Event $event) {      
       /* if this is not a page OR ckgedit/ckgedoku is not  active -> return */
       if($event->data['view'] != 'page') return;
       array_splice($event->data['items'], -1, 0, [new \dokuwiki\plugin\siteexport\MenuItem()]);
    }

    public function siteexport_metaheaders(Doku_Event &$event)
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

    public function siteexport_toolbar_define(Doku_Event &$event) {

        if ( $this->hasSiteexportHeaders() ) {
            // Remove Toolbar
            // This is pr 5.4 syntax.
            $event->data = array();
        }
    }
    
    private function __executeCommand() {
        return ($this->getConf('allowallusers') || auth_isadmin() || auth_ismanager());
    }
}

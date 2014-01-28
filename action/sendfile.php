<?php
/**
 * Siteexport SendFile Plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'action.php');
require_once(DOKU_PLUGIN.'siteexport/inc/debug.php');
require_once(DOKU_PLUGIN.'siteexport/inc/functions.php');

class action_plugin_siteexport_sendfile extends DokuWiki_Action_Plugin {

    function getInfo(){
        return array_merge(confToHash(dirname(__FILE__).'/../info.txt'), array(
				'name' => 'i-net Download (Send File Action Component)',
        ));
    }

    function register(&$controller) {
        // Download of a file
        
        $controller->register_hook('MEDIA_SENDFILE', 'BEFORE', $this, 'siteexport_sendfile');
        $controller->register_hook('FETCH_MEDIA_STATUS', 'BEFORE', $this, 'siteexport_sendfile_not_found');
    }

    /*
     * Redirect File to real File
     */
    function siteexport_sendfile(&$event, $args) {
        global $conf;

        if ( empty($_REQUEST['siteexport']) /* || $event->data['orig'] != $this->getConf('zipfilename') */ ) {
            return;
        }

        $functions = new siteexport_functions();
        $functions->settings->pattern = $_REQUEST['siteexport'];
        $filewriter = new siteexport_zipfilewriter($functions);

        // Try injecting another name ... can't do, because sendFile sets this right after me and right before sending the actual data.
        // header('Content-Disposition: attachment; filename="'. basename($functions->settings->zipFile) .'";');
        
        // Try getting the cached file ...
        $event->data['file'] = $functions->getCacheFileNameForPattern();
        
        $functions->debug->message("fetching cached file from pattern '{$functions->settings->pattern}' with name '{$event->data['file']}'", null, 2);

        $functions->checkIfCacheFileExistsForFileWithPattern($event->data['file'], $_REQUEST['siteexport']);
        $filewriter->getOnlyFileInZip($event->data['file'], $event->data['orig']);
    }

    function siteexport_sendfile_not_found(&$event, $args)
    {
        if ( empty($_REQUEST['siteexport']) /*|| $event->data['orig'] != $this->getConf('zipfilename')*/ || $event->data['status'] != 404 ) { return true; }
        $event->data['status'] = 200;
        return true;
    }
}

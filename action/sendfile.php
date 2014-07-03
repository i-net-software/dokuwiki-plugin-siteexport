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

    public function register(Doku_Event_Handler $controller) {
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
        $functions->debug->message("Starting to send a file from siteexporter", null, 2);
        $filewriter = new siteexport_zipfilewriter($functions);
        $functions->settings->pattern = $_REQUEST['siteexport'];

        // Try injecting another name ... can't do, because sendFile sets this right after me and right before sending the actual data.
        // header('Content-Disposition: attachment; filename="'. basename($functions->settings->zipFile) .'";');
        
        // Try getting the cached file ...
        $event->data['file'] = $functions->getCacheFileNameForPattern();
        
        $functions->debug->message("fetching cached file from pattern '{$functions->settings->pattern}' with name '{$event->data['file']}'", null, 2);
        $functions->debug->message("Event Data Before:", $event->data, 3);

        $functions->checkIfCacheFileExistsForFileWithPattern($event->data['file'], $_REQUEST['siteexport']);

        $filewriter->getOnlyFileInZip($event->data);

        header('Set-Cookie: fileDownload=true; path=' . DOKU_BASE);
        header('Cache-Control: max-age=60, must-revalidate');

        $functions->debug->message("Event Data After:", $event->data, 3);
    }

    function siteexport_sendfile_not_found(&$event, $args)
    {
        if ( empty($_REQUEST['siteexport']) ||
        /**
        $event->data['media'] != $this->getConf('zipfilename')
        /*/
        $event->data['status'] >= 500
        //*/
        ) { return true; }
        $event->data['status'] = 200;
        return true;
    }
}

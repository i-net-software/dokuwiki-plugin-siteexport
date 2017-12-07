<?php
/**
 * DokuWiki Plugin inetmodifications (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  i-net /// software <tools@inetsoftware.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class action_plugin_siteexport_pdfstyles extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {
        global $INPUT;
        if ( !strrpos( $_SERVER['SCRIPT_FILENAME'], 'css.php', -7 ) ) { return; }
        if ( !$INPUT->has('pdfExport') ) { return true; }

        $controller->register_hook('CSS_STYLES_INCLUDED', 'BEFORE', $this, 'handle_css_styles');
        $controller->register_hook('CSS_CACHE_USE', 'BEFORE', $this, 'handle_use_cache');
    }

    /**
     * This function serves debugging purposes and has to be enabled in the register phase
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_use_cache(Doku_Event &$event, $param) {
        global $INPUT;

        // We need different keys for each style sheet.
        $event->data->key .= $INPUT->str('pdfExport', '0');
        $event->data->cache = getCacheName( $event->data->key, $event->data->ext );
    }

    /**
     * Finally, handle the JS script list. The script would be fit to do even more stuff / types
     * but handles only admin and default currently.
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_css_styles(Doku_Event &$event, $param) {
        global $INPUT, $conf;

        $conf['cssdatauri'] = false;

        switch( $event->data['mediatype'] ) {
            
            case 'print':
            case 'all':
                // Keep the styles
                break;
            case 'screen':
            case 'speech':
            case 'DW_DEFAULT':
                // throw away
                $event->preventDefault();
                break;
        }
    }
}

// vim:ts=4:sw=4:et:

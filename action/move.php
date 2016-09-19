<?php
/**
 * Site Export Plugin page move support for the move plugin
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Hamann <michael@content-space.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die('Not inside DokuWiki');

class action_plugin_siteexport_move extends DokuWiki_Action_Plugin {
    /**
     * Register Plugin in DW
     **/
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('PLUGIN_MOVE_HANDLERS_REGISTER', 'BEFORE', $this, 'register_move_handler');
    }

    /**
     * Register the handler for the move plugin.
     */
    public function register_move_handler(Doku_Event $event) {
        $event->data['handlers']['siteexport_toc'] = array($this, 'move_handler');
    }

    /**
     * Handle rewrites for the move plugin. Currently only the link/toc syntax is handled.
     */
    public function move_handler($match, $state, $pos, $pluginname, $handler) {
        if ($state === DOKU_LEXER_SPECIAL) {
            $handler->internallink($match, $state, $pos);
            return '';
        } else {
            return $match;
        }
    }
}

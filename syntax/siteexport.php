<?php
/**
 * Siteexport Plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

if (!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_siteexport_siteexport extends DokuWiki_Syntax_Plugin {

    private $headers = array();

    public function getType() { return 'substition'; }
    public function getPType() { return 'block'; }
    public function getSort() { return 300; }

    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{siteexport .*?\}\}', $mode, 'plugin_siteexport_siteexport');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler) {
    
        $options = explode(' ', trim(substr($match, 2, -2)?:""));
        return $options;
    }
    
    public function render($mode, Doku_Renderer $renderer, $data) {
        global $ID, $conf, $INFO;

        $namespace = $INFO['id'] != $ID ? $INFO['id'] : $ID;
        $id = $INFO['id'] != $ID ? $INFO['id'] : $ID;

        if ($mode == 'xhtml'){
        
            $renderer->info['toc'] = false;
            $renderer->nocache();
            
            $formParams = array( 'id' => sectionID('siteexport_siteexporter', $this->headers), 'action' => wl($id), 'class' => 'siteexport siteexporter' );
            $form = new Doku_Form($formParams);

            $form->addHidden('ns', $id);
            $form->addHidden('site', $id);
            $form->addHidden('baseID', $id);

            $submitLabel = $this->getLang('SiteSubmitLabel');
            foreach( $data as $option ) {
                
                list($key, $value) = explode('=', $option);
                switch ($key) {
                    case "buttonTitle":
                    $submitLabel = urldecode($value);
                    break;
                    default:
                    $form->addHidden($key, $value);
                    break;
                }
            }
            
            $renderer->doc .= '<div class="siteexporter">';
            $form->addElement(form_makeButton('submit', 'siteexport', $submitLabel, array('class' => 'button download')));

            ob_start();
            $form->printForm();
            $renderer->doc .= ob_get_contents();
            ob_end_clean();
             
            $renderer->doc .= '</div>';
            return false;
        } else if ($mode == 'metadata') {
            $renderer->meta['siteexport']['siteexporter'] = true;
            $renderer->meta['siteexport']['baseID'] = $namespace;
        }

        return false;
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :

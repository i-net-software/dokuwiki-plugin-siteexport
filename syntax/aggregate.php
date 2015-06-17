<?php
/**
 * Siteexport Plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_siteexport_aggregate extends DokuWiki_Syntax_Plugin {

    private $headers = array();

    function getType(){ return 'substition';}
    function getPType(){ return 'block';}
    function getSort(){ return 300; }

    function connectTo($mode){
        $this->Lexer->addSpecialPattern('\{\{(?=siteexport|siteexportAGGREGATOR).*?\}\}',$mode,'plugin_siteexport_aggregate');
    }

    function handle($match, $state, $pos, Doku_Handler $handler){
    
    	$options = explode(' ', trim(substr($match, 2, -2)));
        return $options;
    }
    
    function render($mode, Doku_Renderer $renderer, $data) {
        global $ID, $conf;

        $isAggregator = (array_shift($data) == 'siteexportAGGREGATOR');
		$namespace = $ID;
        foreach( $data as $option ) {
            
            list($key, $value) = explode('=', $option);
            if ($key == "namespace") {
                $namespace = $value;
                if ( substr($value, -1) != ':' ) {
    	            $namespace .= ':';
                }

                // The following function wants an page but we only have an NS at this moment
                $namespace .= 'index';
	            break;
            }
        }

        if ($mode == 'xhtml'){
        
            $renderer->info['toc'] = false;
            $renderer->nocache();
            
            $formParams = array( 'id' => sectionID('siteexport_site_aggregator', $this->headers), 'action' => wl($ID), 'class' => 'siteexport aggregator' );
            $form = new Doku_Form($formParams);
            $functions=& plugin_load('helper', 'siteexport');

        	$form->addHidden('ns', $ID);
        	$form->addHidden('site', $ID);

			if ( $isAggregator ) {
	        	$form->addHidden('siteexport_aggregate', '1');
			}

            $submitLabel = $this->getLang('AggregateSubmitLabel');
            $introduction = $this->getLang('AggragateExportPages');
            foreach( $data as $option ) {
	            
	            list($key, $value) = explode('=', $option);
	            switch ($key) {
    	            case "namespace":
    	            // Done at the top.
		            break;
    	            case "buttonTitle":
		            $submitLabel = urldecode($value);
		            break;
    	            case "introduction":
		            $introduction = urldecode($value);
		            break;
		            default:
                    $form->addHidden($key, $value);
                    break;
	            }
            }
            
            $values = $functions->__getOrderedListOfPagesForID($namespace);
            $renderer->doc .= '<div class="siteaggregator">';

            if ( empty($values) ) {
	            $renderer->doc .= '<span style="color: #a00">'.$this->getLang('NoEntriesFoundHint').'</span>';
            } else {
	            $form->addElement(form_makeMenuField('baseID', $values, isset($_REQUEST['baseID']) ? $_REQUEST['baseID'] : $values[0], $introduction));
	            $form->addElement(form_makeButton('submit', 'siteexport', $submitLabel, array('class' => 'button download')));
	
		        ob_start();
		        $form->printForm();
		        $renderer->doc .= ob_get_contents();
		        ob_end_clean();
            }
             
            $renderer->doc .= '</div>';
            return true;
        } else if ($mode == 'metadata') {
            $renderer->meta['siteexport']['hasaggregator'] = $isAggregator;
            $renderer->meta['siteexport']['baseID'] = $namespace;
        }

        return false;
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
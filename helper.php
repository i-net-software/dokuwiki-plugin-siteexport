<?php
/**
 * Translation Plugin: Simple multilanguage plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

    
class helper_plugin_siteexport_page_remove {
    private $newerThanPage;

    function __construct($newerThanPage) {
            $this->newerThanPage = $newerThanPage;
    }

    function _page_remove($elem) {
    	return $elem[2] >= $this->newerThanPage;
    }
}

class helper_plugin_siteexport extends DokuWiki_Plugin {

	/**
	 * for backward compatability
	 * @see inc/DokuWiki_Plugin#getInfo()
	 */
    function getInfo(){
        if ( method_exists(parent, 'getInfo')) {
            $info = parent::getInfo();
        }
        return is_array($info) ? $info : confToHash(dirname(__FILE__).'/plugin.info.txt');
    }
	
    /*
     * return all the templates that this wiki has
     */
	function __getTemplates() {

		// populate $this->_choices with a list of directories
		$list = array();

		$_dir = DOKU_INC . 'lib/tpl/';
		$_pattern = '/^[\w-]+$/';
		if ($dh = @opendir($_dir)) {
			while (false !== ($entry = readdir($dh))) {
				if ($entry == '.' || $entry == '..') continue;
				if ($entry == '.' || $entry == '..') continue;
				if ($_pattern && !preg_match($_pattern,$entry)) continue;

				$file = (is_link($_dir.$entry)) ? readlink($_dir.$entry) : $entry;
				if (is_dir($_dir.$file)) $list[] = $entry;
			}
			closedir($dh);
		}


		sort($list);
		return $list;
	}
	
	/*
	 * Return array list of plugins that exist
	 */
	function __getPluginList() {
	    global $plugin_controller;
	    
	    $allPlugins = array();
	    foreach($plugin_controller->getList(null,true) as $plugin ) { // All plugins
	    	// check for CSS or JS
	    	if ( !file_exists(DOKU_PLUGIN."$plugin/script.js") && !file_exists(DOKU_PLUGIN."$plugin/style.css") && !file_exists(DOKU_PLUGIN."$plugin/print.css") ) { continue; }
	    	$allPlugins[] = $plugin;
	    }
	    
    	return array($allPlugins, $plugin_controller->getList());
	}
    
    private function _page_sort($a, $b)
    {
	    if ( $a[2] == $b[2] ) {
		    return 0;
	    }
	    
	    return $a[2] > $b[2] ? -1 : 1;
    }
    
    function __getOrderedListOfPagesForID($ID, $newerThanPage=null)
	{
		global $conf;
		require_once(dirname(__FILE__)."/inc/functions.php");
		$functions = new siteexport_functions(false);

        $sites = $values = array();
        $page = null;
		search($sites, $conf['datadir'], 'search_allpages', array(), $functions->getNamespaceFromID($ID, $page));
        
        foreach( $sites as $site ) {
        	
        	if ( $ID == $site['id'] ) continue;
        	$sortIdentifier = intval(p_get_metadata($site['id'], 'mergecompare'));
        	
        	if ( $site['id'] == $newerThanPage ) {
        		// If the ID matches a given page we use the sortidentifier for filtering
	        	$newerThanPage = $sortIdentifier;
        	}
        	
            array_push($values, array(':' . $site['id'], $functions->getSiteTitle($site['id']), $sortIdentifier));
        }
        
        if ( $newerThanPage != null ) {
        	// filter using the newerThanPage indicator
	        $values = array_filter($values, array(new helper_plugin_siteexport_page_remove($newerThanPage), '_page_remove'));
        }
        
        usort($values, array($this, '_page_sort'));

        return $values;
	}
	
	function __siteexport_addpage() {
		
        global $ID, $conf;

	    $templateSwitching = false;
	    $pdfExport = false;
	    $usenumberedheading = false;
	    $cronEnabled = false;
	    $translation = null;
	    $translationAvailable = false;
	    $usenumberedheading = true;
	
        if ( $functions=& plugin_load('preload', 'siteexport') && $functions->__create_preload_function() ) {
            $templateSwitching = true;
        }

        if ( $functions =& plugin_load('action', 'dw2pdf' ) ) {
            $pdfExport = true;
        }

        // if ( $functions =& plugin_load('renderer', 'nodetailsxhtml' ) ) {
        // }

        if ( $functions =& plugin_load('cron', 'siteexport' ) ) {
            $cronEnabled = $functions->canWriteSettings();
        }
        
        if ( $translation =& plugin_load('helper', 'translation' ) ) {
            $translationAvailable = true;
        }

        $regenerateScript = '';
        print $this->locale_xhtml(( defined('DOKU_SITEEXPORT_MANAGER') ? 'manager' : '') . 'intro');

        $form = new Doku_Form('siteexport', null, 'post');
        $form->startFieldset( $this->getLang('startingNamespace') );

        $form->addElement(form_makeTextField('ns', $ID, $this->getLang('ns') . ':', 'ns'));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeTextField('ens', $ID, $this->getLang('ens') . ':', 'ens'));

        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeListboxField('depthType', array( "0.0" => $this->getLang('depth.pageOnly'), "1.0" => $this->getLang('depth.allSubNameSpaces'), "2.0" => $this->getLang('depth.specifiedDepth') ), (empty($_REQUEST['depthType']) ? $this->getLang('depth.allSubNameSpaces') : $_REQUEST['depthType']), $this->getLang('depthType') . ':', 'depthType', null, array_merge(array('class' => 'edit'))));

        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeOpenTag("div", array('style' => 'display:' . ($_REQUEST['depthType'] == "2" ? "block" : "none") . ';', 'id' => 'depthContainer')));
        $form->addElement(form_makeTextField('depth', $this->getConf('depth'), $this->getLang('depth') . ':', 'depth'));
        $form->addElement(form_makeCloseTag("div"));

        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeOpenTag("div", array('style' => 'display:none;', 'id' => 'depthContainer')));
        $form->addElement(form_makeCheckboxField('exportLinkedPages', 1, $this->getLang('exportLinkedPages') . ':', 'exportLinkedPages'));
        $form->addElement(form_makeCloseTag("div"));

        $form->endFieldset();
        $form->addElement(form_makeTag('br'));

        $form->startFieldset( $this->getLang('selectYourOptions') );
        $form->addElement(form_makeCheckboxField('absolutePath', 1, $this->getLang('absolutePath') . ':', 'absolutePath'));
        $form->addElement(form_makeTag('br'));
        // The parameter needs lowercase
        $form->addElement(form_makeCheckboxField('exportbody', 1, $this->getLang('exportBody') . ':', 'exportbody'));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeCheckboxField('disableCache', 1, $this->getLang('disableCache') . ':', 'disableCache'));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeCheckboxField('addParams', 1, $this->getLang('addParams') . ':', 'addParams', null, array_merge(array('checked' => ($conf['userewrite'] != 1 ? 'checked' : '' ) ))));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeListboxField('renderer', array_merge(array('','xhtml'), plugin_list('renderer')), '', $this->getLang('renderer') . ':', 'renderer', null, array_merge(array('class' => 'edit'))));

        $form->addElement(form_makeTag('br'));
        if ( $templateSwitching ) {
            $form->addElement(form_makeListboxField('template', $this->__getTemplates(), $conf['template'], $this->getLang('template') . ':', 'template', null, array_merge(array('class' => 'edit'))));
            $form->addElement(form_makeTag('br'));
        } else
        {
            $form->addElement(form_makeTag('br'));
            $form->addElement(form_makeOpenTag('p', array('style' => 'color: #a00;' )));
            $form->addElement('Can\'t create preload file in \'inc\' directory. Template switching is not available. Plugin disabling is not available.');
            $form->addElement(form_makeCloseTag('p'));
        }
        
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeCheckboxField('pdfExport', 1, $this->getLang('pdfExport') . ':', 'pdfExport', null, $pdfExport ? array() : array_merge(array('disabled' => 'disabled')) ));
        if ( !$pdfExport ) {
            $form->addElement(form_makeOpenTag('p', array('style' => 'color: #a00;' )));
            $form->addElement('In order to use the PDF export, please ');
            $form->addElement(form_makeOpenTag('a', array('href' => 'http://www.dokuwiki.org/plugin:dw2pdf', 'alt' => 'install plugin', 'target' => '_blank')));
            $form->addElement('install the dw2pdf plugin.');
            $form->addElement(form_makeCloseTag('a'));
            $form->addElement(form_makeCloseTag('p'));
        }

        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeCheckboxField('usenumberedheading', 1, $this->getLang('usenumberedheading') . ':', 'usenumberedheading', null, $usenumberedheading && $pdfExport ? array() : array_merge(array('disabled' => 'disabled')) ));
        $form->addElement(form_makeTag('br'));
        
        if ( !$usenumberedheading ) {
            $form->addElement(form_makeOpenTag('p', array('style' => 'color: #a00;' )));
            $form->addElement('In order to use numbered headings, please ');
            $form->addElement(form_makeOpenTag('a', array('href' => 'http://www.dokuwiki.org/plugin:nodetailsxhtml', 'alt' => 'install plugin', 'target' => '_blank')));
            $form->addElement('install the nodetailsxhtml plugin.');
            $form->addElement(form_makeCloseTag('a'));
            $form->addElement(form_makeCloseTag('p'));
        }

        $form->endFieldset();
        $form->addElement(form_makeTag('br'));

        $form->startFieldset( $this->getLang('helpCreationOptions') );
        $form->addElement(form_makeCheckboxField('eclipseDocZip', 1, $this->getLang('eclipseDocZip') . ':', 'eclipseDocZip'));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeCheckboxField('JavaHelpDocZip', 1, $this->getLang('JavaHelpDocZip') . ':', 'JavaHelpDocZip'));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeCheckboxField('useTocFile', 1, $this->getLang('useTocFile') . ':', 'useTocFile'));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeCheckboxField('emptyTocElem', 1, $this->getLang('emptyTocElem') . ':', 'emptyTocElem'));
        $form->addElement(form_makeTag('br'));
        if ( !$translationAvailable ) {
            $form->addElement(form_makeCheckboxField('TOCMapWithoutTranslation', 1, $this->getLang('TOCMapWithoutTranslation') . ':', 'TOCMapWithoutTranslation'));
            $form->addElement(form_makeTag('br'));
        } else {
            $form->addElement(form_makeListboxField('defaultLang', $translation->trans, $conf['lang'], $this->getLang('defaultLang') . ':', 'defaultLang'));
            $form->addElement(form_makeTag('br'));
        }
        $form->endFieldset();
        $form->addElement(form_makeTag('br'));

        if ( $templateSwitching )
        {
            $form->startFieldset( $this->getLang('disablePluginsOption') );
            	
            $form->addElement(form_makeCheckboxField("disableall", 1, 'Disable All:', "disableall", 'forceVisible'));
            $form->addElement(form_makeTag('br'));
            $form->addElement(form_makeTag('br'));

            list($allPlugins, $enabledPlugins) = $this->__getPluginList();
            foreach ( $allPlugins as $plugin ) {
                $form->addElement(form_makeCheckboxField("disableplugin[]", $plugin, $plugin . ':', "disableplugin_$plugin", null, (!in_array($plugin, $enabledPlugins) ? array('checked' => 'checked', 'disabled' => 'disabled') : array() )));
                $form->addElement(form_makeTag('br'));
            }
            	
            $form->endFieldset();
            $form->addElement(form_makeTag('br'));
        }

        $form->startFieldset( $this->getLang('customOptions') );
        $form->addElement(form_makeOpenTag('p'));
        $form->addElement( $this->getLang('customOptionsDescription') );
        $form->addElement(form_makeCloseTag('p'));
        
        $form->addElement(form_makeOpenTag('ul', array('id' => 'siteexport__customActions')));
        $form->addElement(form_makeCloseTag('ul'));
        $form->addElement(form_makeTag('br', array('class'=>'clear')));
        $form->addElement(form_makeButton('submit', 'addoption', $this->getLang('addCustomOption') , array('style' => 'float:right;') ));
        
        $form->endFieldset();
        $form->addElement(form_makeTag('br'));

		if ( !defined('DOKU_SITEEXPORT_MANAGER') ) {
			
        
	        $form->startFieldset( $this->getLang('startProcess') );
	        $form->addElement(form_makeTextField('copyurl', "", $this->getLang('directDownloadLink') . ':', 'copyurl', null, array('readonly' => 'readonly') ));
	        $form->addElement(form_makeTag('br'));
	        $form->addElement(form_makeTextField('wgeturl', "", $this->getLang('wgetURLLink') . ':', 'wgeturl', null, array('readonly' => 'readonly') ));
	        $form->addElement(form_makeTag('br'));
	        $form->addElement(form_makeTextField('curlurl', "", $this->getLang('curlURLLink') . ':', 'curlurl', null, array('readonly' => 'readonly') ));
	        $form->addElement(form_makeTag('br', array('class'=>'clear')));
	        $form->addElement(form_makeButton('submit', 'siteexport', $this->getLang('start') , array('style' => 'float:right;')));
	        $form->endFieldset();
	        $form->addElement(form_makeTag('br'));
	
	        $form->endFieldset();
			$form->addElement(form_makeTag('br'));

	        $form->startFieldset( $this->getLang('status') );
	        $form->addElement(form_makeOpenTag('span', array('id' => 'siteexport__out')));
	
	        $form->addElement(form_makeCloseTag('span'));
	        $form->addElement(form_makeOpenTag('span', array('class' => 'siteexport__throbber')));
	        $form->addElement(form_makeTag('img', array('src' => DOKU_BASE.'lib/images/loading.gif', 'id' => 'siteexport__throbber')));
	        $form->addElement(form_makeCloseTag('span'));
	        $form->endFieldset();
	        $form->addElement(form_makeTag('br'));
	
	        if ( $cronEnabled )
	        {
	            $form->startFieldset( $this->getLang('cronSaveProcess') );
	            $form->addElement(form_makeOpenTag('p'));
	            $form->addElement( $this->getLang('cronDescription') );
	            $form->addElement(form_makeCloseTag('p'));
	
	            $form->addElement(form_makeCheckboxField("cronOverwriteExisting", 1, $this->getLang('canOverwriteExisting'), "cronOverwriteExisting"));
	            $form->addElement(form_makeTag('br', array('class'=>'clear')));
	            $form->addElement(form_makeButton('submit', 'cronDeleteAction', $this->getLang('cronDeleteAction') , array('id' => 'cronDeleteAction', 'style' => 'float:left;display:none') ));
	            $form->addElement(form_makeButton('submit', 'cronSaveAction', $this->getLang('cronSaveAction') , array('id' => 'cronSaveAction', 'style' => 'float:right;') ));
	            $form->addElement(form_makeTag('br', array('class'=>'clear')));
	            
	            $form->addElement(form_makeOpenTag('a', array('href' => '#cronactions', 'alt' => 'show cron jobs', 'id' => 'showcronjobs', 'target' => '_blank', 'style' => 'float:right;')));
	            $form->addElement('show all cron jobs');
	            $form->addElement(form_makeCloseTag('a'));
	        }
	
		} else {
	        $form->startFieldset( $this->getLang('startProcess') );
	        $form->addElement(form_makeButton('submit', 'siteexport', $this->getLang('useOptionsInEditor') , array('style' => 'width:100%;')));
		}

        $form->endFieldset();
        $form->addElement(form_makeTag('br'));

        $form->printForm();
	}
}

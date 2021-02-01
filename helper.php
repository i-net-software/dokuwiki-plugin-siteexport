<?php
/**
 * Siteexport Plugin helper
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
require_once(DOKU_PLUGIN.'siteexport/preload.php');

class helper_plugin_siteexport_page_remove {
    private $start, $end;

    /**
     * @param integer $start
     * @param integer $end
     */
    public function __construct($start, $end=null) {
        $this->start = $start;
        $this->end = $end;
    }

    public function _page_remove($elem) {
        return $elem[2] >= $this->start && ( is_null( $this->end ) || $elem[2] <= $this->end);
    }
}

class helper_plugin_siteexport extends DokuWiki_Plugin {
    
    /*
     * return all the templates that this wiki has
     */
    public function __getTemplates() {

        // populate $this->_choices with a list of directories
        $list = array();

        $_dir = DOKU_INC . 'lib/tpl/';
        $_pattern = '/^[\w-]+$/';
        if ($dh = @opendir($_dir)) {
            while (false !== ($entry = readdir($dh))) {
                if ($entry == '.' || $entry == '..') {
                    continue;
                }
                if ($entry == '.' || $entry == '..') {
                    continue;
                }
                if ($_pattern && !preg_match($_pattern,$entry)) {
                    continue;
                }

                $file = (is_link($_dir.$entry)) ? readlink($_dir.$entry) : $entry;
                if (is_dir($_dir.$file)) {
                    $list[] = $entry;
                }
            }
            closedir($dh);
        }


        sort($list);
        return $list;
    }
    
    /*
     * Return array list of plugins that exist
     */
    public function __getPluginList() {
        global $plugin_controller;
        
        $allPlugins = array();
        foreach ($plugin_controller->getList(null, true) as $plugin) { // All plugins
            // check for CSS or JS
            if (!file_exists(DOKU_PLUGIN . "$plugin/script.js") && !file_exists(DOKU_PLUGIN . "$plugin/style.css") && !file_exists(DOKU_PLUGIN . "$plugin/print.css")) { continue; }
            $allPlugins[] = $plugin;
        }
        
        return array($allPlugins, $plugin_controller->getList());
    }
    
    public function _page_sort($a, $b)
    {
        if ( $a[2] == $b[2] ) {
            return 0;
        }
        
        return $a[2] > $b[2] ? -1 : 1;
    }
    
    public function __getOrderedListOfPagesForID($IDs, $start=null)
    {
        global $conf;
        require_once(dirname(__FILE__)."/inc/functions.php");
        $functions = new siteexport_functions(false);
        
        if ( !is_array($IDs) ) {
            $IDs = array($IDs);
        }

        $sites = $values = array();
        foreach( $IDs as $ID ) {
            $page = null;
            search($sites, $conf['datadir'], 'search_allpages', array(), $functions->getNamespaceFromID($ID, $page));
            foreach( $sites as $site ) {
                
                if ( $ID == $site['id'] ) {
                    continue;
                }
                $sortIdentifier = intval(p_get_metadata($site['id'], 'mergecompare'));
                $entry = array(':' . $site['id'], $functions->getSiteTitle($site['id']), $sortIdentifier);

                if ( !in_array($entry[0], array_column($values, 0)) ) {
                    array_push($values, $entry);
                }
            }
        }


        if ( $start != null ) {
            // filter using the newerThanPage indicator
            $sortIdentifier = intval(p_get_metadata($start, 'mergecompare'));
            $values = array_filter($values, array(new helper_plugin_siteexport_page_remove($sortIdentifier), '_page_remove'));
        }
        
        usort($values, array($this, '_page_sort'));

        return $values;
    }
    
    public function __getOrderedListOfPagesForStartEnd($ID, $start, $end)
    {
        $values = $this->__getOrderedListOfPagesForID($ID);

           // filter using the newerThanPage indicator
        $values = array_filter($values, array(new helper_plugin_siteexport_page_remove(intval($start), intval($end)), '_page_remove'));
        
        usort($values, array($this, '_page_sort'));
        return $values;
    }

    public function __siteexport_addpage() {
        
        global $ID, $conf;

        $templateSwitching = false;
        $pdfExport = false;
        $translationAvailable = false;
        $usenumberedheading = true;
        $trans = array(); 
        
        $preload = plugin_load('preload', 'siteexport');
        if ($preload && $preload->__create_preload_function()) {
            $templateSwitching = true;
        }

        $dw2pdf = plugin_load('action', 'dw2pdf');
        if ($dw2pdf) {
            $pdfExport = true;
        }

        $translation = plugin_load('helper', 'autotranslation');
        if ($translation) {
            $translationAvailable = true;
            $trans = $translation->translations;
        }

        print $this->locale_xhtml((defined('DOKU_SITEEXPORT_MANAGER') ? 'manager' : '') . 'intro');

        $form = new Doku_Form('siteexport', null, 'post');
        $form->startFieldset($this->getLang('startingNamespace'));

        $form->addElement(form_makeTextField('ns', $ID, $this->getLang('ns') . ':', 'ns'));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeTextField('ens', $ID, $this->getLang('ens') . ':', 'ens'));

        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeListboxField('depthType', array("0.0" => $this->getLang('depth.pageOnly'), "1.0" => $this->getLang('depth.allSubNameSpaces'), "2.0" => $this->getLang('depth.specifiedDepth')), (empty($_REQUEST['depthType']) ? $this->getLang('depth.allSubNameSpaces') : $_REQUEST['depthType']), $this->getLang('depthType') . ':', 'depthType', null, array_merge(array('class' => 'edit'))));

        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeOpenTag("div", array('style' => 'display:' . ($_REQUEST['depthType'] == "2" ? "block" : "none") . ';', 'id' => 'depthContainer')));
        $form->addElement(form_makeTextField('depth', $this->getConf('depth'), $this->getLang('depth') . ':', 'depth'));
        $form->addElement(form_makeCloseTag("div"));

        $form->endFieldset();
        $form->addElement(form_makeTag('br'));

        $form->startFieldset($this->getLang('selectYourOptions'));
        $form->addElement(form_makeCheckboxField('absolutePath', 1, $this->getLang('absolutePath') . ':', 'absolutePath'));
        $form->addElement(form_makeTag('br'));
        // The parameter needs lowercase
        $form->addElement(form_makeCheckboxField('exportbody', 1, $this->getLang('exportBody') . ':', 'exportbody'));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeCheckboxField('exportLinkedPages', 1, $this->getLang('exportLinkedPages') . ':', 'exportLinkedPages', 'sendIfNotSet', array('checked' => 'checked')));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeCheckboxField('disableCache', 1, $this->getLang('disableCache') . ':', 'disableCache'));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeCheckboxField('addParams', 1, $this->getLang('addParams') . ':', 'addParams', null, array_merge(array('checked' => ($conf['userewrite'] != 1 ? 'checked' : '')))));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeListboxField('renderer', array_merge(array('', 'xhtml'), plugin_list('renderer')), '', $this->getLang('renderer') . ':', 'renderer', null, array_merge(array('class' => 'edit'))));

        $form->addElement(form_makeTag('br'));
        if ($templateSwitching) {
            $form->addElement(form_makeListboxField('template', $this->__getTemplates(), $conf['template'], $this->getLang('template') . ':', 'template', null, array_merge(array('class' => 'edit'))));
            $form->addElement(form_makeTag('br'));
        } else
        {
            $form->addElement(form_makeTag('br'));
            $form->addElement(form_makeOpenTag('p', array('style' => 'color: #a00;')));
            $form->addElement('Can\'t create preload file in \'inc\' directory. Template switching is not available. Plugin disabling is not available.');
            $form->addElement(form_makeCloseTag('p'));
        }
        
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeCheckboxField('pdfExport', 1, $this->getLang('pdfExport') . ':', 'pdfExport', null, $pdfExport ? array() : array_merge(array('disabled' => 'disabled'))));

        // Hint for dw2pdf
        $this->addPluginHint( $form, $pdfExport, "the PDF export", "dw2pdf" );

        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeCheckboxField('usenumberedheading', 1, $this->getLang('usenumberedheading') . ':', 'usenumberedheading', null, $usenumberedheading && $pdfExport ? array() : array_merge(array('disabled' => 'disabled'))));
        $form->addElement(form_makeTag('br'));

        // Hint for nodetailsxhtml
        $this->addPluginHint( $form, $usenumberedheading, "numbered headings", "nodetailsxhtml" );

        $form->endFieldset();
        $form->addElement(form_makeTag('br'));

        $form->startFieldset($this->getLang('helpCreationOptions'));
        $form->addElement(form_makeCheckboxField('eclipseDocZip', 1, $this->getLang('eclipseDocZip') . ':', 'eclipseDocZip'));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeCheckboxField('JavaHelpDocZip', 1, $this->getLang('JavaHelpDocZip') . ':', 'JavaHelpDocZip'));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeCheckboxField('useTocFile', 1, $this->getLang('useTocFile') . ':', 'useTocFile'));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeCheckboxField('emptyTocElem', 1, $this->getLang('emptyTocElem') . ':', 'emptyTocElem'));
        $form->addElement(form_makeTag('br'));
        if (!$translationAvailable) {
            $form->addElement(form_makeCheckboxField('TOCMapWithoutTranslation', 1, $this->getLang('TOCMapWithoutTranslation') . ':', 'TOCMapWithoutTranslation'));
            $form->addElement(form_makeTag('br'));
        } else {

            if (!is_array($trans)) {
                $trans = array($trans);
            }
            
            $trans = array_unique(array_merge($trans, array($conf['lang'])));
            $form->addElement(form_makeListboxField('defaultLang', $trans, $conf['lang'], $this->getLang('defaultLang') . ':', 'defaultLang'));
            $form->addElement(form_makeTag('br'));
        }
        $form->endFieldset();
        $form->addElement(form_makeTag('br'));

        if ($templateSwitching)
        {
            $form->startFieldset($this->getLang('disablePluginsOption'));
                
            $form->addElement(form_makeCheckboxField("disableall", 1, 'Disable All:', "disableall", 'forceVisible'));
            $form->addElement(form_makeTag('br'));
            $form->addElement(form_makeTag('br'));

            list($allPlugins, $enabledPlugins) = $this->__getPluginList();
            foreach ($allPlugins as $plugin) {
                $form->addElement(form_makeCheckboxField("disableplugin[]", $plugin, $plugin . ':', "disableplugin_$plugin", null, (!in_array($plugin, $enabledPlugins) ? array('checked' => 'checked', 'disabled' => 'disabled') : array())));
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

            $throbber = DOKU_BASE.'lib/images/loading.gif';
            if ( !file_exists( $throbber) ) {
                $throbber = DOKU_BASE.'lib/images/throbber.gif';
            }

            $form->addElement(form_makeTag('img', array('src' => $throbber, 'id' => 'siteexport__throbber')));
            $form->addElement(form_makeCloseTag('span'));
            $form->endFieldset();
            $form->addElement(form_makeTag('br'));

        } else {
            $form->startFieldset( $this->getLang('startProcess') );
            $form->addElement(form_makeButton('submit', 'siteexport', $this->getLang('useOptionsInEditor') , array('style' => 'width:100%;')));
        }

        $form->endFieldset();
        $form->addElement(form_makeTag('br'));

        $form->printForm();
    }
    
    private function addPluginHint( &$form, $condition, $hint, $plugin ) {
        if ($condition) { return; }

        $form->addElement(form_makeOpenTag('p', array('style' => 'color: #a00;')));
        $form->addElement('In order to use ' . $hint . ', please ');
        $form->addElement(form_makeOpenTag('a', array('href' => 'http://www.dokuwiki.org/plugin:' . $plugin, 'alt' => 'install plugin', 'target' => '_blank')));
        $form->addElement('install the ' . $plugin . ' plugin.');
        $form->addElement(form_makeCloseTag('a'));
        $form->addElement(form_makeCloseTag('p'));
    }

}

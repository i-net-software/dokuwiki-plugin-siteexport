<?php
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'admin.php');
require_once(DOKU_PLUGIN.'siteexport/preload.php');

/**
 * All DokuWiki plugins to extend the admin function
 * need to inherit from this class
 */
class admin_plugin_siteexport extends DokuWiki_Admin_Plugin {

    var $templateSwitching = false;
    var $pdfExport = false;
    var $usenumberedheading = false;
    var $cronEnabled = false;
    var $translationAvailable = false;

    /**
     * Constructor
     */
    function __construct() {
        $this->setupLocale();
    }

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

    /**
     * return sort order for position in admin menu
     */
    function getMenuSort() {
        return 100;
    }

    function forAdminOnly(){
        return false;
    }

    /**
     * handle user request
     */
    function handle() {

        if ( $functions=& plugin_load('preload', 'siteexport') && $functions->__create_preload_function() ) {
            $this->templateSwitching = true;
        }

        if ( $functions =& plugin_load('action', 'dw2pdf' ) ) {
            $this->pdfExport = true;
        }

        if ( $functions =& plugin_load('renderer', 'nodetailsxhtml' ) ) {
            $this->usenumberedheading = true;
        }

        if ( $functions =& plugin_load('cron', 'siteexport' ) ) {
            $this->cronEnabled = $functions->canWriteSettings();
        }
        
        if ( $functions =& plugin_load('helper', 'translation' ) ) {
            $this->cronEnabled = true;
        }
    }

    /**
     * output appropriate html
     */
    function html() {
        global $ID, $conf;

        if ( ! $functions=& plugin_load('helper', 'siteexport') ) {
            msg("Can't initialize");
            return false;
        }

        $regenerateScript = '';
        print $this->locale_xhtml('intro');

        $form = new Doku_Form('siteexport', null, 'post');
        $form->startFieldset( $this->getLang('startingNamespace') );

        $form->addElement(form_makeTextField('ns', $ID, $this->getLang('ns') . ':', 'ns'));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeTextField('ens', $ID, $this->getLang('ens') . ':', 'ens'));

        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeListboxField('depthType', array( "0.0" => $this->getLang('depth.pageOnly'), "1.0" => $this->getLang('depth.allSubNameSpaces'), "2.0" => $this->getLang('depth.specifiedDepth') ), (empty($_REQUEST['depthType']) ? $this->getLang('depth.allSubNameSpaces') : $_REQUEST['depthType']), $this->getLang('depthType') . ':', 'depthType', null, array_merge(array('class' => 'edit'))));

        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeOpenTag("div", array('style' => 'display:' . ($_REQUEST['depthType'] == "3" ? "block" : "none") . ';', 'id' => 'depthContainer')));
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
        $form->addElement(form_makeCheckboxField('exportBody', 1, $this->getLang('exportBody') . ':', 'exportBody'));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeCheckboxField('addParams', 1, $this->getLang('addParams') . ':', 'addParams', null, array_merge(array('checked' => ($conf['userewrite'] != 1 ? 'checked' : '' ) ))));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeListboxField('renderer', array_merge(array('','xhtml'), plugin_list('renderer')), '', $this->getLang('renderer') . ':', 'renderer', null, array_merge(array('class' => 'edit'))));

        $form->addElement(form_makeTag('br'));
        if ( $this->templateSwitching ) {
            $form->addElement(form_makeListboxField('template', $functions->__getTemplates(), $conf['template'], $this->getLang('template') . ':', 'template', null, array_merge(array('class' => 'edit'))));
            $form->addElement(form_makeTag('br'));
        } else
        {
            $form->addElement(form_makeTag('br'));
            $form->addElement(form_makeOpenTag('p', array('style' => 'color: #a00;' )));
            $form->addElement('Can\'t create preload file in \'inc\' directory. Template switching is not available. Plugin disabling is not available.');
            $form->addElement(form_makeCloseTag('p'));
        }
        
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeCheckboxField('pdfExport', 1, $this->getLang('pdfExport') . ':', 'pdfExport', null, $this->pdfExport ? array() : array_merge(array('disabled' => 'disabled')) ));
        if ( !$this->pdfExport ) {
            $form->addElement(form_makeOpenTag('p', array('style' => 'color: #a00;' )));
            $form->addElement('In order to use the PDF export, please ');
            $form->addElement(form_makeOpenTag('a', array('href' => 'http://www.dokuwiki.org/plugin:dw2pdf', 'alt' => 'install plugin', 'target' => '_blank')));
            $form->addElement('install the dw2pdf plugin.');
            $form->addElement(form_makeCloseTag('a'));
            $form->addElement(form_makeCloseTag('p'));
        }

        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeCheckboxField('usenumberedheading', 1, $this->getLang('usenumberedheading') . ':', 'usenumberedheading', null, $this->usenumberedheading ? array() : array_merge(array('disabled' => 'disabled')) ));
        $form->addElement(form_makeTag('br'));
        
        if ( !$this->usenumberedheading ) {
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
        if ( !$this->translationAvailable ) {
            $form->addElement(form_makeCheckboxField('TOCMapWithoutTranslation', 1, $this->getLang('TOCMapWithoutTranslation') . ':', 'TOCMapWithoutTranslation'));
            $form->addElement(form_makeTag('br'));
        }
        $form->endFieldset();
        $form->addElement(form_makeTag('br'));

        if ( $this->templateSwitching )
        {
            $form->startFieldset( $this->getLang('disablePluginsOption') );
            	
            $form->addElement(form_makeCheckboxField("disableall", 1, 'Disable All:', "disableall", 'forceVisible'));
            $form->addElement(form_makeTag('br'));
            $form->addElement(form_makeTag('br'));

            list($allPlugins, $enabledPlugins) = $functions->__getPluginList();
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

        $form->startFieldset( $this->getLang('status') );
        $form->addElement(form_makeOpenTag('span', array('id' => 'siteexport__out')));

        $form->addElement(form_makeCloseTag('span'));
        $form->addElement(form_makeOpenTag('span', array('class' => 'siteexport__throbber')));
        $form->addElement(form_makeTag('img', array('src' => DOKU_BASE.'lib/images/loading.gif', 'id' => 'siteexport__throbber')));
        $form->addElement(form_makeCloseTag('span'));
        $form->endFieldset();
        $form->addElement(form_makeTag('br'));

        if ( $this->cronEnabled )
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
                        
            $form->endFieldset();
        }

        $form->printForm();
    }
}
//Setup VIM: ex: et ts=4 enc=utf-8 :

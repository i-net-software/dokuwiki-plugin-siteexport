<?php 

if (!defined('DOKU_PLUGIN')) die('meh');
class settings_plugin_siteexport_settings extends DokuWiki_Plugin
{
    public $fileType = 'html';
    public $exportNamespace = '';
    public $pattern = null;

    public $isCLI = false;

    public $depth = '';

    public $zipFile = '';    
//    public  $origEclipseZipFile = 'doc.zip';
//    public  $eclipseZipFile = '';
    public $addParams = false;
    public $origZipFile = '';
    public $downloadZipFile = '';
    public $exportLinkedPages = true;
    public $additionalParameters = array();
    public $isAuthed = false;

    public $TOCMapWithoutTranslation = false;

    public $cachetime = 0;
    public $hasValidCacheFile = false;

    public $useTOCFile = false;
    public $cookie = null;

    public $ignoreNon200 = true;

    public $defaultLang = 'en';
    
    public $tmpDir = null;
    
    public $namespace = "";
    
    public $cookies = null;
    
    public $excludePattern = "";

    /**
     * @param siteexport_functions $functions
     */
    public function __construct($functions) {
        global $ID, $conf, $INPUT;

        $functions->debug->setDebugFile($this->getConf('debugFile'));
        $debugLevel = $INPUT->int('debug', -1, true);
        if ( $debugLevel >= 0 && $debugLevel <= 5) {
            $functions->debug->setDebugLevel($debugLevel);
        } else 
        {
            $functions->debug->setDebugLevel($this->getConf('debugLevel'));
        }

        $functions->debug->isAJAX = $this->getConf('ignoreAJAXError') ? false : $functions->debug->isAJAX;

        // Set the pattern
        $this->pattern = $INPUT->str('pattern');
        if ( empty( $this->pattern ) )
        {
            $params = $_REQUEST;
            $this->pattern = $functions->requestParametersToCacheHash($params);
        }

        $this->isCLI = (!$_SERVER['REMOTE_ADDR'] && 'cli' == php_sapi_name());

        $this->cachetime = $this->getConf('cachetime');
        if ( $INPUT->has( 'disableCache' ) ) {
            $this->cachetime = 0;
        }

        // Load variables
        $this->origZipFile = $this->getConf('zipfilename');

        $this->ignoreNon200 = $this->getConf('ignoreNon200');

        // ID
        $this->downloadZipFile = $functions->getSpecialExportFileName($this->origZipFile, $this->pattern);
        //        $this->eclipseZipFile = $functions->getSpecialExportFileName(getNS($this->origZipFile) . ':' . $this->origEclipseZipFile, $this->pattern);

        $this->zipFile = mediaFN($this->downloadZipFile);

        $this->tmpDir = mediaFN(getNS($this->origZipFile));
        $this->exportLinkedPages = $INPUT->bool( 'exportLinkedPages', true );

        $this->namespace = $functions->getNamespaceFromID( $INPUT->str('ns'), $PAGE );
        $this->addParams = $INPUT->bool( 'addParams' );

        $this->useTOCFile = $INPUT->bool( 'useTocFile' );

        // set export Namespace - which is a virtual Root
        $pg = noNS($ID);
        if (empty($this->namespace)) { $this->namespace = $functions->getNamespaceFromID(getNS($ID), $pg); }
        $ens = $INPUT->str( 'ens' );
        $this->exportNamespace = !empty($ens) && preg_match("%^" . preg_quote($functions->getNamespaceFromID($ens, $pg), '%') . "%", $this->namespace) ? $functions->getNamespaceFromID($ens, $pg) : $this->namespace;

        $this->TOCMapWithoutTranslation = intval($_REQUEST['TOCMapWithoutTranslation']) == 1 ? true : false;

        $this->defaultLang = $INPUT->str( 'defaultLang', $conf['lang'], true );

        // Strip params that should be forwarded
        $this->additionalParameters = $_REQUEST;
        $functions->removeWikiVariables($this->additionalParameters, true);
        
        if ( $INPUT->has( 'disableCache' ) ) {
            $this->additionalParameters['nocache']=1;
        }

        $this->excludePattern = $INPUT->str( 'exclude', $this->getConf('exclude'), true );
    }
}

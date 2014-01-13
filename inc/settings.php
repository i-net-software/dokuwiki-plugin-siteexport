<?php 

if(!defined('DOKU_PLUGIN')) die('meh');
class settings_plugin_siteexport_settings extends DokuWiki_Plugin
{
    var $fileType = 'html';
    var $exportNamespace = '';
    var $pattern = null;
    
    var $isCLI = false;

    var $depth = '';

    var $zipFile = '';    
//    var $origEclipseZipFile = 'doc.zip';
//    var $eclipseZipFile = '';
    var $addParams = false;
    var $origZipFile = '';
    var $downloadZipFile = '';
    var $exportLinkedPages = false;
    var $additionalParameters = array();
    var $isAuthed = false;
    
    var $TOCMapWithoutTranslation = false;
    
    var $cachetime = 0;
    var $hasValidCacheFile = false;
    
    var $useTOCFile = false;
    var $cookie = null;
    
    var $ignoreNon200 = true;
    
    function settings_plugin_siteexport_settings($functions) {
        global $ID;
        
		$functions->debug->setDebugLevel($this->getConf('debugLevel'));
        $functions->debug->setDebugFile ($this->getConf('debugFile'));

        if ( empty($_REQUEST['pattern']) )
        {
            $params = $_REQUEST;
            $this->pattern = $functions->requestParametersToCacheHash($params);
        } else {
            // Set the pattern
            $this->pattern = $_REQUEST['pattern'];
        }
        
        $this->isCLI = (!$_SERVER['REMOTE_ADDR'] && 'cli' == php_sapi_name());
        
        $this->cachetime = $this->getConf('cachetime');
        if ( !empty( $_REQUEST['disableCache'] ) ) {
	        $this->cachetime = intval($_REQUEST['disableCache']) == 1 ? 0 : $this->cachetime;
        }
        
        // Load Variables
        $this->origZipFile = $this->getConf('zipfilename');
        
        $this->ignoreNon200 = $this->getConf('ignoreNon200');

        // ID
        $this->downloadZipFile = $functions->getSpecialExportFileName($this->origZipFile, $this->pattern);
        //        $this->eclipseZipFile = $functions->getSpecialExportFileName(getNS($this->origZipFile) . ':' . $this->origEclipseZipFile, $this->pattern);
        
        $this->zipFile = mediaFN($this->downloadZipFile);

        $this->tmpDir = mediaFN(getNS($this->origZipFile));
        $this->exportLinkedPages = intval($_REQUEST['exportLinkedPages']) == 1 ? true : false;

        $this->namespace = $functions->getNamespaceFromID($_REQUEST['ns'], $PAGE);
        $this->addParams = !empty($_REQUEST['addParams']);
        
        $this->useTOCFile = !empty($_REQUEST['useTocFile']);

        // set export Namespace - which is a virtual Root
        $pg = noNS($ID);
        if ( empty( $this->namespace ) ) { $this->namespace = $functions->getNamespaceFromID(getNS($ID), $pg); }
        $this->exportNamespace = !empty($_REQUEST['ens']) && preg_match("%^" . $functions->getNamespaceFromID($_REQUEST['ens'], $pg) . "%", $this->namespace) ? $functions->getNamespaceFromID($_REQUEST['ens'], $pg) : $this->namespace;

        $this->TOCMapWithoutTranslation = intval($_REQUEST['TOCMapWithoutTranslation']) == 1 ? true : false;

        // Strip params that should be forwarded
        $this->additionalParameters = $_REQUEST;
        $functions->removeWikiVariables($this->additionalParameters, true);

        $tmpID = $ID;
        $ID = $this->origZipFile;

        $INFO = pageinfo();
        if ( !$this->isCLI )
        {
            // Workaround for the cron which cannot authenticate but has access to everything.
            if ( $INFO['perm'] < AUTH_DELETE ) {
                list ( $USER, $PASS) = $functions->basic_authentication();
                auth_login($USER, $PASS);
            }
        }

        $ID = $tmpID;
    }
}

?>
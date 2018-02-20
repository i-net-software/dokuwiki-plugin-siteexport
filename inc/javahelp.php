<?php

if (!defined('DOKU_PLUGIN')) die('meh');
require_once(DOKU_PLUGIN . 'siteexport/inc/toc.php');

class siteexport_javahelp
{
    private $functions = null;
    private $translation = null;
    private $filewriter = null;
    private $NS = null;
    
    private $tocName = 'toc.xml';
    private $mapName = 'map.xml';
    
    /**
     * @param siteexport_functions $functions
     * @param siteexport_zipfilewriter $filewriter
     */
    public function __construct($functions, $filewriter, $NS)
    {
        $this->NS = $NS;
        $this->functions = $functions;
        $this->filewriter = $filewriter;
        $translation = plugin_load('helper', 'autotranslation');
        $this->translation = &$translation;
    }

    public function createTOCFiles($data)
    {
        global $conf, $ID;

        // Split Tree for translation
        $translationHSFiles = array();
        $toc = new siteexport_toc($this->functions, $this->NS);
        $toc->debug("### Starting to create TOC Files ###");

        $count = count($data);
        for ($i = 0; $i < $count ; $i++)
        {
            $lang = '';
            if ($this->translation)
            {
                $this->translation->translationsNs = $this->translation->setupTNS($data[$i]['id'], true);
                $lang = $this->translation->getLangPart($data[$i]['id']);
                $this->functions->debug->message("Setting up translation:", array(
                    'id' => $data[$i]['id'],
                    'tns' => $this->translation->translationsNs,
                    'lang' => $lang
                ), 3);
            }

            $toc->debug($lang . " -> " . $data[$i]['id'] );
            // get all the relative URLs
            $translationHSFiles[$lang][] = $data[$i];
        }
        
        // +":" at the end becaus this is already a namespace
        $baseNameSpace = str_replace('/', ':', $this->translation && !empty($this->translation->translationsNs) ? $this->translation->translationsNs : $this->NS . ':');
        $translationRoot = curNS($baseNameSpace);
        $hsPrename = curNS(getNS($baseNameSpace));
                
        $this->functions->debug->message("HelpSetPre-Name: {$hsPrename}", null, 3);
        $this->functions->debug->message("Translation-Root: {$translationRoot}", null, 3);
        $this->functions->debug->message("HSFiles:", $translationHSFiles, 1);

        $last_key = end((array_keys($translationHSFiles)));

        foreach ($translationHSFiles as $lang => $data)
        {
            // Prepare Translations
            if (!empty($lang) && !$this->functions->settings->TOCMapWithoutTranslation)
            {
                $toc->translation = &$this->translation;
                $rootNode = cleanID($this->translation->translationsNs . $lang) . ':';
            } else {
                $toc->translation = null;
                $rootNode = '';
            }

            $toc->debug("*** Writing for Language rootNode: '".$rootNode."'***");
            
            $tsRootPath = $hsPrename . '/' . $this->translationRootPath($translationRoot);
            $this->functions->debug->message("Generating JavaHelpDocZip for language '$lang'", $tsRootPath, 3);
            
            // Create toc and map for each lang
            list($tocData, $mapData, $startPageID) = $toc->__getJavaHelpTOCXML($data);
            $this->filewriter->__moveDataToZip($tocData, $tsRootPath . (empty($lang) ? '' : $lang . '/') . $this->tocName);
            $this->filewriter->__moveDataToZip($mapData, $tsRootPath . (empty($lang) ? '' : $lang . '/') . $this->mapName);

            // Create HS File
            $HS = $this->getHSXML($startPageID, $this->functions->getSiteTitle($rootNode), $lang, $tsRootPath);
            $this->filewriter->__moveDataToZip($HS, $translationRoot . (empty($lang) ? '' : '_' . $lang) . '.hs');
            
            // Default Lang
            if ($lang == $this->functions->settings->defaultLang || $lang == $last_key)
            {
                $this->functions->debug->message("Writing Default HS File for Language:", $lang, 3);
                $this->filewriter->__moveDataToZip($HS, $translationRoot . '.hs');
                $last_key = null;
            }
        }
        
        $toc->debug("THE END", true);
    }
    
    private function translationRootPath($translationRoot = '')
    {
        if (!empty($translationRoot))
        {
            return $translationRoot . '/';
        }
        
        return $translationRoot;
    }
    
    private function getHSXML($rootID, $title, $lang = '', $translationRoot = '')
    {
        if (empty($lang) && substr($translationRoot, -1) != '/') {
            $translationRoot .= '/';
        } else if (!empty($lang) && substr($lang, -1) != '/') {
            $lang .= '/';
        }

        return <<<OUTPUT
<?xml version='1.0' encoding='ISO-8859-1' ?>
<helpset version="1.0">
 
    <title>{$title}</title>
    <maps>
        <homeID>{$rootID}</homeID>
        <mapref location="{$translationRoot}{$lang}{$this->mapName}"/>
     </maps>

    <view>
        <name>TOC</name>
        <label>{$this->functions->getLang('toc')}</label>
        <type>javax.help.TOCView</type>
        <data>{$translationRoot}{$lang}{$this->tocName}</data>
    </view>

    <view>
        <name>Search</name>
        <label>{$this->functions->getLang('search')}</label>
        <type>javax.help.SearchView</type>
        <data engine="com.sun.java.help.search.DefaultSearchEngine">
            {$translationRoot}{$lang}JavaHelpSearch
        </data>
    </view>

    <impl>
        <helpsetregistry helpbrokerclass="javax.help.DefaultHelpBroker" />
        <viewerregistry viewertype="text/html" viewerclass="com.inet.html.InetHtmlEditorKit" />
    </impl>
</helpset>
OUTPUT;
    }
}

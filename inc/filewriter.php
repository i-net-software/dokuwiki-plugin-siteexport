<?php

if(!defined('DOKU_PLUGIN')) die('meh');
require_once(DOKU_PLUGIN.'siteexport/inc/pdfgenerator.php');

class siteexport_zipfilewriter
{
    /**
     * further classes
     */
    private $pdfGenerator = false;
    private $functions = null;

    public function siteexport_zipfilewriter( $functions = null )
    {
        $this->functions = $functions;
        if ( class_exists( 'siteexport_pdfgenerator' ) )
        {
            $this->pdfGenerator = new siteexport_pdfgenerator($functions);
        }
    }

    public function canDoPDF()
    {
        return $this->pdfGenerator !== false;
    }


    /**
     * Wrapper for fetching the Context or the TOC for Eclipse Documentation
     * This also puts the file into the zip package
     **/
    public function __moveDataToZip($DATA, $FILENAME='toc.xml') {

        if ( empty($DATA) ) { return false; }

        $tmpFile = tempnam($this->functions->settings->tmpDir , 'siteexport__');

        $fp = fopen( $tmpFile, "w");
        if(!$fp) return false;

        fwrite($fp,$DATA);
        fclose($fp);

        // Add to zip
        $status = $this->__addFileToZip($tmpFile, $FILENAME);
        @unlink($tmpFile);

        return true;
    }

    /**
     * Adds a file to the zip file
     * @param $FILE file-name of the zip
     * @param $NAME name of the file that is being added
     * @param $ZIP name of the zip file to which we add
     */
    function __addFileToZip($FILE, $NAME, $ZIP=null) {

        if ( $NAME[0] === "/" ) {
            $this->functions->debug->message("Weird, the NAME for the ZIP started with a '/'. This may result in wrong links!", null, 3);
            $NAME = substr($NAME, 1);
        }

        // check for mpdf
        if ( $this->canDoPDF() ) {
            $this->functions->debug->message("Trying to create PDF from File '$FILE' with name '$NAME' for ZIP '$ZIP'", null, 2);

            if ( $this->functions->debug->debugLevel() <= 1 ) { // 2011-01-12 Write HTML to ZIP for Debug purpose
                $this->__writeFileToZip($FILE, "_debug/$NAME.html", $ZIP);
            }

            if ( !$this->pdfGenerator->createPDFFromFile($FILE, $NAME) ) {
                $this->functions->debug->runtimeException("Create PDF from File '$FILE' with name '$NAME' went wrong and is not being added!");
                return false;
            }
        }

        return $this->__writeFileToZip($FILE, $NAME, $ZIP);
    }

    /**
     * This really writes a file to a zip-file
     * @param $FILE file-name of the zip
     * @param $NAME name of the file that is being added
     * @param $ZIP name of the zip file to which we add
     */
    private function __writeFileToZip($FILE, $NAME, $ZIP) {
        if ( empty( $ZIP ) ) $ZIP = $this->functions->settings->zipFile;

        if ( !class_exists('ZipArchive') ) {
            $this->functions->debug->runtimeException("PHP class 'ZipArchive' does not exist. Please make sure that you have the ziplib extension for PHP installed.");
            return false;
        }

        $zip = new ZipArchive;
        if ( !$zip ) {
            $this->functions->debug->runtimeException("Can't create new instance of 'ZipArchive'. Please make sure that you have the ziplib extension for PHP installed.");
            return false;
        }

        $code = $zip->open($ZIP, ZipArchive::CREATE);
        if ($code === TRUE) {

            $this->functions->debug->message("Adding file '$NAME' to ZIP $ZIP", null, 2);

            $zip->addFile($FILE, $NAME);
            $zip->close();

            // If this has worked out, we may put this version into the cache ... ?

            // ALibi Touching - 2011-09-13 wird nicht gebraucht nach Umstellung
            // io_saveFile(mediaFN($this->origZipFile), "alibi file");

            return true;
        }

        $this->functions->debug->runtimeException("Zip Error #$code");
        return false;
    }

    /**
     * check if a file exists allready
     * @param $NAME name of the file in the zip
     */
    function fileExistsInZip($NAME)
    {
        $zip = new ZipArchive;
        $code = $zip->open($this->functions->settings->zipFile, ZipArchive::CREATE);
        if ($code === TRUE) {
            return !($zip->statName($NAME) === FALSE);
        }

        return false;
    }

    /**
     * Checks if a valid cache file exists for the given request parameters
     * @param $requestData
     */
    function hasValidCacheFile($requestData, $depends=array())
    {
        $this->functions->settings->hasValidCacheFile = false; // reset the cache settings
        $HASH = $this->functions->requestParametersToCacheHash($requestData);
        $this->functions->debug->message("HASH for CacheFile: ", $HASH, 2);

        $cacheFile = $this->functions->getCacheFileNameForPattern($HASH);
        
        $mtime = @filemtime($cacheFile); // 0 if not exists

        // Check if the file is expired - if so, just create a new one.
        if ( $mtime == 0 || $mtime < time()-$this->functions->settings->cachetime )
        {
            @unlink($cacheFile);
            @unlink($this->functions->settings->zipFile);
            $this->functions->debug->message("New CacheFile because the file was over the cachetime: ", $cacheFile, 2);
            return false;
        }

        // Check for dependencies
        if ( !empty($depends) )
        {
            foreach ($depends as $site) {
                
                if ( !page_exists($site['id']) )
                {
                    continue;
                }
                
                if ($mtime < @filemtime(wikiFN($site['id']))) {
                    @unlink($cacheFile);
                    @unlink($this->functions->settings->zipFile);
                    $this->functions->debug->message("New CacheFile, because a page changed: ", $cacheFile, 2);
                    return false;         // cache older than files it depends on?
                }
            }
        }

        $this->functions->debug->message("CacheFile exists: ", $cacheFile, 2);
        return $this->functions->settings->hasValidCacheFile = true;
    }
    
    public function getOnlyFileInZip(&$filename = null, &$headerFileName = null) {
    
    	if ( is_null($filename) ) $filename = $this->functions->settings->zipFile;
	    
	    $zip = new ZipArchive();
	    if ( !$zip->open($filename) ) {
		    return false;
	    }

		if ( $zip->numFiles != 1 ) {
			return false;
		}
		
		$stat = $zip->statIndex( 0 );
		if ( substr($stat['name'], -3) != 'pdf' ) {
			return false;
		}
		
		// Extract single file.
		$folder = dirname($filename);
		
		$headerFileName = utf8_basename($stat['name']);
		$zip->extractTo($folder, $stat['name']);
		$zip->close();
		
		sleep(1);
		$filename .= '.' . cleanID($headerFileName); // Wee need the other file for cache reasons.
		@rename($folder.'/'.$headerFileName, $filename);
	    return true;
    }
}

?>
<?php
/**
 * Site Export Plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../../').'/');
if(!defined('DOKU_PLUGIN')) {
    // Just for sanity
    require_once(DOKU_INC.'inc/plugin.php');
    define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
}

require_once(DOKU_PLUGIN.'action.php');
require_once(DOKU_INC.'/inc/search.php');

require_once(DOKU_PLUGIN.'siteexport/inc/functions.php');
require_once(DOKU_PLUGIN.'siteexport/inc/httpproxy.php');
require_once(DOKU_PLUGIN.'siteexport/inc/filewriter.php');
require_once(DOKU_PLUGIN.'siteexport/inc/toc.php');
require_once(DOKU_PLUGIN.'siteexport/inc/javahelp.php');

class action_plugin_siteexport_ajax extends DokuWiki_Action_Plugin
{
    /**
     * New internal variables for better structure
     */
    private $filewriter = null;
    public $functions = null;

    // List of files that have already been checked
    private $fileChecked = array();

    // Namespace of the page to export
    private $namespace = '';

    /**
     * Register Plugin in DW
     **/
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'ajax_siteexport_provider');
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'siteexport_action');
    }

    /**
     * AJAX Provider - check what is going to be done
     * @param $event
     * @param $args
     */
    function ajax_siteexport_provider(&$event, $args) {

        // If this is not a siteexport call, ignore it.
        if ( !strstr($event->data, '__siteexport' ) )
        {
            return;
        }
        
        $this->__init_functions(true);
        
        switch( $event->data ) {
            case '__siteexport_getsitelist': $this->ajax_siteexport_getsitelist( $event ); break;
            case '__siteexport_addsite': $this->ajax_siteexport_addsite( $event ); break;
            case '__siteexport_generateurl': $this->ajax_siteexport_generateurl( $event ); break;
            case '__siteexport_aggregate': $this->ajax_siteexport_aggregate( $event ); break;
        }
    }

    /**
     * Export from a URL - action
     * @param $event
     */
    function siteexport_action( &$event ) {
        global $ID;

        // Check if the 'do' was siteexport
	    $command = is_array($event->data) ? array_shift(array_keys($event->data)) : $event->data;
        if ( $command != 'siteexport' ) { return false; }
		$event->data = act_clean($event->data);

        if ( headers_sent() ) {
            msg("The siteexport function has to be called prior to any header output.", -1);
        }

        $this->__init_functions();

        $this->functions->debug->message("========================================", null, 1);
        $this->functions->debug->message("Starting export from URL call", null, 1);
        $this->functions->debug->message("----------------------------------------", null, 1);

        $event->preventDefault();
        $event->stopPropagation();

        // Fake security Token if none given
        if ( empty( $_REQUEST['sectok'] ) ) {
            $_REQUEST['sectok'] = getSecurityToken();
        }

        // The timer will be used to do redirects if needed to prevent timeouts
        $starttimer = time();
        $timerdiff = $this->getConf('max_execution_time');

        $data = $this->__get_siteexport_list_and_init_tocs($ID, !empty($_REQUEST['startcounter']));

        if ( $data === false ) {
            header("HTTP/1.0 401 Unauthorized");
            print 'Unauthorized';
            exit;
        }

        $counter = 0;

        if ( count($data) == 0 && !$this->functions->settings->hasValidCacheFile ) {
            exit( "No Data to export" );
        }

        foreach ( $data as $site ) {

			if ( intval($site['exists']) == 1 || !isset($site['exists']) ) {

	            // Skip over the amount of urls that have been exported already
	            if ( empty($_REQUEST['startcounter']) || $counter >= intval($_REQUEST['startcounter']) ) {
	                $status = $this->__siteexport_add_site($site['id']);

			        if ( $status === false ) {
				        $this->functions->debug->message("----------------------------------------", null, 1);
				        $this->functions->debug->message("Errors during export from URL call", null, 1);
				        $this->functions->debug->message("========================================", null, 1);
						print $this->functions->debug->runtimeErrors;
        			    exit(0); // We need to stop
			        }
	            }
			}

            $counter ++;
            if ( time() - $starttimer >= $timerdiff ) {
                $this->functions->debug->message("Will Redirect", null, 1);
                $this->handleRuntimeErrorOutput();
                $this->functions->startRedirctProcess($counter);
            }
        }

        $this->functions->debug->message("----------------------------------------", null, 1);
        $this->functions->debug->message("Finishing export from URL call", null, 1);
        $this->functions->debug->message("========================================", null, 1);

        $this->cleanCacheFiles();

        $URL = ml($this->functions->settings->origZipFile, array('cache' => 'nocache', 'siteexport' => $this->functions->settings->pattern, 'sectok' => getSecurityToken()), true, '&');
        $this->functions->debug->message("Redirecting to final file", $URL, 2);

        $this->handleRuntimeErrorOutput();
        send_redirect($URL);
        exit(0); // Should not be reached, but anyways
    }

    private function handleRuntimeErrorOutput()
    {
        if ( !empty($this->functions->debug->runtimeErrors) )
        {
            $this->filewriter->__moveDataToZip($this->functions->debug->runtimeErrors, '_runtime_error/' . time() . '.html');
        }
    }

    public function __init_functions($isAJAX=false)
    {
        global $conf;
        
        $conf['useslash'] = 1;
    
        $this->functions = new siteexport_functions(true, $isAJAX);
        $this->filewriter = new siteexport_zipfilewriter($this->functions);

        // Check for PDF Capabilities
        if ( $this->filewriter->canDoPDF() ) {
            $this->functions->settings->fileType = 'pdf';
        }
    }

    /**
     * Prepares the generated URL for direct download access
     * Also gives back the parameters for this URL
     * @param $event init event of the ajax request
     */
    function ajax_siteexport_prepareURL_and_POSTData( &$event ) {

        $event->preventDefault();
        $event->stopPropagation();

        // Retrieve Information for download URL
        $this->functions->debug->message("Prepared URL and POST from Request:", $_REQUEST, 2);
        $url = $this->functions->prepare_POSTData($_REQUEST);
        $combined = $this->functions->urlToPathAndParams($url);
        list($path, $query) = explode('?', $combined, 2);
        $return = array($url, $combined, $path, $query);

        $this->functions->debug->message("Prepared URL and POST data:", $return, 2);
        return $return;
    }

    /**
     * Executes a Cron Job Action
     * @param $event
     */
    function ajax_siteexport_cronaction( &$event )
    {
        $cronOverwriteExisting = intval($_REQUEST['cronOverwriteExisting']) == 1;
        list($url, $combined) = $this->ajax_siteexport_prepareURL_and_POSTData($event);

        if ( !$function =& plugin_load('cron', 'siteexport' ) )
        {
            $this->functions->debug->message("Tried to do an action with siteexport/cron, but the cron plugin is missing.", null, 4);
        }

        $status = null;
        switch( $event->data ) {
            case '__siteexport_savecron': $status = $function->saveCronDataWithParameters($combined, $cronOverwriteExisting); break;
            case '__siteexport_deletecron': $status = $function->deleteCronDataWithParameters($combined); break;
        }

        if ( !empty($status) )
        {
            $this->functions->debug->message("Tried to do an action with siteexport/cron, but failed.", $status, 4);
        }
    }

    /**
     * generate direct access URL
     **/
    function ajax_siteexport_generateurl( &$event ) {

        list($url, $combined, $path, $POSTData) = $this->ajax_siteexport_prepareURL_and_POSTData($event);

        // WGET Redirects - this is an option for wget only.
        // Calculate the maximum redirects that we want to allow. A Problem is that we don't know how long it will take to fetch one page
        // Therefore we assume it takes about 5s for each page - that gives the freedom to have anough time for redirect.
        $maxRedirectNumber = ceil( ( count($this->__get_siteexport_list($NS, true)) * 5) / $this->getConf('max_execution_time') );
        $maxRedirect = $maxRedirectNumber > 0 ? '--max-redirect=' . ($maxRedirectNumber+3) . ' ' : '';
        $maxRedirs = $maxRedirectNumber > 0 ? '--max-redirs ' . ($maxRedirectNumber+3) . ' ' : '';

        $this->functions->debug->message("Generating Direct Download URL", $url, 2);

        // If there was a Runtime Exception
        if ( !$this->functions->debug->firstRE() ) {
            $this->functions->debug->message("There have been errors while generating the download URLs.", null, 4);
            return;
        }

        echo $url;
        echo "\n";
        echo 'wget ' . $maxRedirect . '--output-document=' . array_pop(explode(":", ($this->getConf('zipfilename')))) . ' --post-data="' . $POSTData . '" ' . wl(cleanID($path), null, true) . ' --http-user=USER --http-passwd=PASSWD';
        echo "\n";
        echo 'curl -L ' . $maxRedirs . '-o ' . array_pop(explode(":", ($this->getConf('zipfilename')))) . ' -d "' . $POSTData . '" ' . wl(cleanID($path), null, true) . ' --anyauth --user USER:PASSWD';
        echo "\n";

        $this->functions->debug->message("Checking for Cron parameters: ", $combined, 1);
        if ( !$functions =& plugin_load('cron', 'siteexport' ) ||
        !$functions->hasCronJobForParameters($combined) ) {
            echo "false";
        } else
        {
            echo "true";
        }

        return;
    }

    /**
     * Get List of sites to be exported for AJAX (wrapper)
     **/
    function ajax_siteexport_getsitelist( &$event ) {

        $event->preventDefault();
        $event->stopPropagation();

        $data = $this->__get_siteexport_list_and_init_tocs($_REQUEST['ns']);
        
        // Important for reconaisance of the session

        if ( $data === false )
        {
            $this->functions->debug->runtimeException("No data generated. List of Files is 'false'.");
            return;
        }

        if ( empty($data) && !$this->functions->settings->hasValidCacheFile )
        {
            $this->functions->debug->runtimeException("Generated list is empty.");
            return;
        }

        // If there was a Runtime Exception
        if ( !$this->functions->debug->firstRE() )
        {
            $this->functions->debug->message("There have been errors while generating site list.", null, 4);
            return;
        }

        echo "{$this->functions->settings->pattern}\n";
        echo $this->functions->downloadURL() . "\n";
        foreach($data as $line ){
            echo $line['id'] . "\n";
        }

        return;
    }

    function ajax_siteexport_aggregate( &$event ) {
        
        // Quick preparations for one page only
        if ( $this->filewriter->hasValidCacheFile($_REQUEST, $data) ) {
            $this->functions->debug->message("Had a valid cache file and will use it.", null, 2);
            print $this->functions->downloadURL();
            
            $event->preventDefault();
            $event->stopPropagation();
        } else {
            // Then go for it!
            $this->functions->debug->message("Will create a new cache thing.", null, 2);
            $this->ajax_siteexport_addsite( $event );
        }
        
    }

    /**
     * Add a page to the package (for AJAX calls - Wrapper)
     **/
    function ajax_siteexport_addsite( &$event ) {

        $event->preventDefault();
        $event->stopPropagation();

        $this->functions->debug->message("========================================", null, 1);
        $this->functions->debug->message("Starting export from AJAX call", null, 1);
        $this->functions->debug->message("----------------------------------------", null, 1);

        $status = $this->__siteexport_add_site($_REQUEST['site']);
        if ( $status === false ) {
	        $this->functions->debug->message("----------------------------------------", null, 1);
	        $this->functions->debug->message("Errors during export from AJAX call", null, 1);
	        $this->functions->debug->message("========================================", null, 1);
        	return;
        }

        $this->functions->debug->message("----------------------------------------", null, 1);
        $this->functions->debug->message("Finishing export from AJAX call", null, 1);
        $this->functions->debug->message("========================================", null, 1);

        // Print the download zip-File
        $this->cleanCacheFiles();

        // If there was a Runtime Exception
        if ( !$this->functions->debug->firstRE() ) {
            $this->functions->debug->message("There have been errors during the export.", null, 4);
            return;
        }

        print $this->functions->downloadURL();
        return;
    }

    /**
     * Fetch the list of pages to be exported
     **/
    function __get_siteexport_list($NS, $overrideCache=false) {
        global $conf;

        $NS = $this->namespace = $this->functions->getNamespaceFromID($NS, $PAGE);
        $this->functions->debug->message("ROOT Namespace to export from: '{$NS}' / {$this->namespace}", null, 1);

        $depth = $this->getConf('depth');
        $query = '';
        $doSearch = 'search_allpages';

        switch( intval($_REQUEST['depthType']) ) {
            case 0:
                $query = $this->functions->cleanID(str_replace(":", "/", $NS.':'.$PAGE));
                resolve_pageid($NS, $PAGE, $exists);

                if ( $exists ) {
                    $data = array( array( 'id' => $PAGE) );
                       
                    $this->functions->debug->message("Checking for Cache, depthType:0", null, 2);
                    if ( !$overrideCache && $this->filewriter->hasValidCacheFile($_REQUEST, $data) )
                    {
                        return array();
                    }
                    
                    return $data;
                }
            case 1:	$depth = 0;
            break;
            case 2:	$depth = intval($_REQUEST['depth']);
            break;
        }

        $opts = array( 'depth' => $depth, 'skipacl' => $this->getConf('skipacl'), 'query' => $query);
        $this->functions->debug->message("Options", $opts, 2);
        
        $data = array();
        require_once (DOKU_INC.'inc/search.php');

        // Check, which TOC to take
        if ( !$this->functions->settings->useTOCFile ) {
            search($data, $conf['datadir'], $doSearch, $opts, $this->namespace);
        } else {
            $this->functions->debug->message("Using TOC for data", null, 2);

            $doSearch = 'search_pagename';

            // Create Data of the TOC File should be used instead
            $opts['query'] = 'toc.txt';
            
            $RAWdata = array();
            search($RAWdata, $conf['datadir'], $doSearch, $opts, $this->namespace);
            
            // There may be more than one toc and all of them have to be merged.
            $data = array();
            foreach( $RAWdata as $entry )
            {
                $tmpData = p_get_metadata($entry['id'], 'sitetoc siteexportTOC', true);
                
                if ( is_array($tmpData) )
                {
                    $data = array_merge($data, $tmpData);
                }
            }
        }

        $this->functions->debug->message("Checking for Cache after lookup of pages", null, 2);
        if ( !$overrideCache && $this->filewriter->hasValidCacheFile($_REQUEST, $data) )
        {
            return array();
        }
        
        $this->functions->debug->message("Exporting the following sites: ", $data, 2);
        return $data;
    }

    function __get_siteexport_list_and_init_tocs($NS, $isRedirected=false ) {

        // Clean up if not redirected
        if ( !$isRedirected && !$this->__removeOldZip() ) {
            $this->functions->debug->runtimeException("Can't remove old files.");
            return false;
        }

        $data = $this->__get_siteexport_list($NS, $isRedirected);
        if ( $isRedirected || empty($data) )
        {
            // if we have been redirected, simply return the data
            $this->functions->debug->message("List is empty I guess. Used NS: '{$NS}' ", null, 1);
            return $data;
        }

        // Create Eclipse Documentation Pages - TOC.xml, Context.xml
        if ( !empty($_REQUEST['absolutePath']) ) $this->namespace = "";
//        $this->__removeOldZip( $this->functions->settings->eclipseZipFile );

        if ( !empty($_REQUEST['eclipseDocZip']) )
        {
            $toc = new siteexport_toc($this->functions);
            $this->functions->debug->message("Generating eclipseDocZip", null, 2);
            $this->filewriter->__moveDataToZip($toc->__getTOCXML($data), 'toc.xml');
            $this->filewriter->__moveDataToZip($toc->__getContextXML($data), 'context.xml');
        } else  if ( !empty($_REQUEST['JavaHelpDocZip']) )
        {
            $toc = new siteexport_javahelp($this->functions, $this->filewriter);
            $toc->createTOCFiles($data);
            
/*            $toc = new siteexport_toc($this->functions);
            list($tocData, $mapData) = $toc->__getJavaHelpTOCXML($data);
            $this->functions->debug->message("Generating JavaHelpDocZip", null, 2);
            $this->filewriter->__moveDataToZip($tocData, 'toc.xml');
            $this->filewriter->__moveDataToZip($mapData, 'map.xml');
*/        }

        return $data;
    }

    /**
     * Add page with ID to the package
     **/
    function __siteexport_add_site( $ID ) {
        global $conf, $currentID, $currentParent;

        // Which is the current ID?
        $currentID = $ID;

        $this->functions->debug->message("========================================", null, 2);
        $this->functions->debug->message("Adding Site: '$ID'", null, 2);
        $this->functions->debug->message("----------------------------------------", $_REQUEST, 2);

        $request = $this->functions->settings->additionalParameters;
        unset($request['diPlu']); // This will not be needed for the first request.
        unset($request['diInv']); // This will not be needed for the first request.
        
        // say, what to export and Build URL
        // http://documentation:81/helpdesk/de/hds/getting-started?depthType=0&do=siteexport&ens=helpdesk%3Ade%3Ahds%3Agetting-started&pdfExport=1&renderer=siteexport_siteexportpdf&template=helpdesk
        
        $do = (intval($_REQUEST['exportbody']) == 1 ? (empty($_REQUEST['renderer']) ? $conf['renderer_xhtml'] : $_REQUEST['renderer'] ) : '' );
        
        if ($do == 'pdf' && $this->filewriter->canDoPDF() )
        {
            $do = 'export_siteexport_pdf';
            $_REQUEST['origRenderer'] = (empty($_REQUEST['renderer']) ? $conf['renderer_xhtml'] : $_REQUEST['renderer'] );
        } else if ( $_REQUEST['renderer'] == 'dw2pdf' ) {
            $do = 'pdf';
        }
        
        $do = ($do == $conf['renderer_xhtml'] && intval($_REQUEST['exportbody']) != 1) ? '' : 'export_' . $do;

        if ( $do != 'export_' && !empty($do) )
        {
            $request['do'] = $do;
        }

        // set Template
        if ( !empty( $_REQUEST['template'] ) ) {
            $request['template'] = $_REQUEST['template'];
        }

        $this->functions->debug->message("REQUEST for add_site:", $request, 2);
        
        $ID = $this->functions->cleanID($ID);
        $url = $this->functions->wl($ID, $request, true, '&');

        // Parse URI PATH and add "html"
        $currentParent = $fileName = $this->functions->getSiteName($ID, true);
        $this->functions->debug->message("Filename could be:", $fileName, 2);

        $this->fileChecked[$url] = $fileName; // 2010-09-03 - One URL to one FileName
        $this->functions->settings->depth = str_repeat('../', count(explode('/', $fileName))-1);

        // fetch URL and save it in temp file
        $tmpFile = $this->__getHTTPFile($url);
        if ( $tmpFile === false ) {
        	// return $this->functions->debug->message("Creating temporary download file failed for '$url'. See log for more information.");
        	$this->functions->debug->runtimeException("Creating temporary download file failed for '$url'. See log for more information.");
        	return false;
        }

        $dirname = dirname($fileName);
        // If a Filename was given that does not comply to the original name, use this one!
		if ( $this->filewriter->canDoPDF() ) {

			$this->functions->debug->message("Will replace old filename '{$fileName}' with {$ID}", null, 1);
			$extension = explode('.', $fileName);
        	$extension = array_pop($extension);
        	
        	// 2014-04-29 added cleanID to ensure that links are generated consistently when using [[this>...]] or another local, relativ linking
			$fileName = $dirname . '/' . $this->functions->cleanID($this->functions->getSiteTitle($ID)) . '.' . $extension;
        } else if ( !empty($tmpFile[1]) && !strstr($DATA[2], $tmpFile[1]) ) {
        
			$this->functions->debug->message("Will replace old filename '{$fileName}' with {$dirname}/{$tmpFile[1]}", null, 1);
			$fileName = $dirname . '/' . $tmpFile[1];
        }

        // Add to zip
		$this->fileChecked[$url] = $fileName;
        $status = $this->filewriter->__addFileToZip($tmpFile[0], $fileName);
        @unlink($tmpFile[0]);

        return $status;
    }

    function __preg_quote($input) {
	    return preg_quote($input, '/');
    }
     
    /**
     * Download the file via HTTP URL + recurse if this is not an image
     * The file will be saved as temporary file. The filename is the result.
     **/
    function __getHTTPFile($URL, $RECURSE=false, $newAdditionalParameters=null) {
        global $conf;

        $EXCLUDE = $this->getConf('exclude');
        if ( !empty($EXCLUDE) ) {
	        $PATTERN = "/(" . implode('|', explode(' ', preg_quote($EXCLUDE, '/'))) . ")/i";

	        $this->functions->debug->message("Checking for exclude: ", array(
	        	"pattern" => $PATTERN,
	        	"file" => $URL,
	        	"matches" => preg_match($PATTERN, $URL) ? 'match' : 'no match'
	        ), 2);
	
			if ( preg_match($PATTERN, $URL) ) { return false; }
        }

        $http = new HTTPProxy($this->functions);
        $http->max_bodysize = $conf['fetchsize'];

        // Add additional Params
        $this->functions->addAdditionalParametersToURL($URL, $newAdditionalParameters);

        $this->functions->debug->message("Fetching URL: '$URL'", null, 2);
        $getData = $http->get($URL, true); // true == sloopy, get 304 body as well.
        
        if( $getData === false ) { // || ($http->status != 200 && !$this->functions->settings->ignoreNon200) ) {
        
        	if ( $http->status != 200 && $this->functions->settings->ignoreNon200 ) {
                $this->functions->debug->message("HTTP status was '{$http->status}' - but I was told to ignore it by the settings.", $URL, 3);
	        	return true;
        	}
        
            $this->functions->debug->message("Sending request failed with error, HTTP status was '{$http->status}'.", $URL, 4);
            return false;
        } 

        if( empty($getData) ) {
            $this->functions->debug->message("No data fetched", $URL, 4);
            return false;
        }

        $this->functions->debug->message("Headers received", $http->resp_headers, 2);

        if ( !$RECURSE ) {
            // Parse URI PATH and add "html"
            $this->functions->debug->message("========================================", null, 1);
            $this->functions->debug->message("Starting to recurse file '$URL'", null , 1);
			$this->functions->debug->message("----------------------------------------", null, 1);
            $this->__getInternalLinks($getData);
			$this->functions->debug->message("----------------------------------------", null, 1);
            $this->functions->debug->message("Finished to recurse file '$URL'", null , 1);
            $this->functions->debug->message("========================================", null, 1);
        }

        $tmpFile = tempnam($this->functions->settings->tmpDir , 'siteexport__');
        $this->functions->debug->message("Temporary filename", $tmpFile, 1);

        $fp = fopen( $tmpFile, "w");
        if(!$fp) {
            $this->functions->debug->message("Can't open temporary File '$tmpFile'.", null , 4);
            return false;
        }

        fwrite($fp,$getData);
        fclose($fp);

        // plain/text; ...
        $extension = explode(';', $http->resp_headers['content-type'], 2);
        $extension = array_shift($extension);
        $extension = explode('/', $extension, 2);
        if ( $extension[0] == 'image' && preg_match("/^[a-zA-Z0-9]{3,}$/", $extension[1]) ) {
            $extension = strtolower($extension[1]);
            $this->functions->debug->message("Found new image extension:", $extension, 2);
        } else {
            unset($extension);
        }
        
        return array($tmpFile, preg_replace("/.*?filename=\"?(.*?)\"?;?$/", "$1", $http->resp_headers['content-disposition']), $extension);
    }

    /**
     * Find internal links in the currently downloaded file. This also matches inside CSS files
     **/
    function __getInternalLinks(&$DATA) {

        $PATTERN = '(href|src|action)="([^"]*)"';
        $CALLBACK = array($this, '__fetchAndReplaceLink');
        $DATA = preg_replace_callback("/$PATTERN/i", $CALLBACK, $DATA);

        $PATTERNCSS = '(url\s*?)\(([^\)]*)\)';
        $DATA = preg_replace_callback("/$PATTERNCSS/i", $CALLBACK, $DATA);
    }

    /**
     * Deep Fetch and replace of links inside the texts matched by __getInternalLinks
     **/
    function __fetchAndReplaceLink($DATA) {
        global $conf, $currentID, $currentParent;

        $noDeepReplace = true;
        $newAdditionalParameters = $this->functions->settings->additionalParameters;
        $newDepth = $this->functions->settings->depth;
        $hadBase = false;

		// Clean data[2], remote ' and "
		$DATA[2] = preg_replace("/^\s*?['\"]?(.*?)['\"]?\s*?$/", '\1', trim($DATA[2]));

        $this->functions->debug->message("Starting Link Replacement", array( 'data' => $DATA, 'additional Params' => $newAdditionalParameters, 'newDepth' => $newDepth, 'currentID' => $currentID, 'currentParent' => $currentParent), 2);

        // $DATA[2] = urldecode($DATA[2]); // Leads to problems because it does not re-encode the url
        // External and mailto links
        if ( preg_match("%^(https?://|mailto:|javascript:|data:)%", $DATA[2]) ) {
            $this->functions->debug->message("Don't like http, mailto, data or javascript links here", null, 1);
            return $this->__rebuildLink($DATA, "");
        }
        //if ( preg_match("%^(https?://|mailto:|" . DOKU_BASE . "/_export/)%", $DATA[2]) ) { return $this->__rebuildLink($DATA, ""); }
        // External media - this is deep down in the link, so we have to grep it out
        if ( preg_match("%media=(https?://.*?$)%", $DATA[2], $matches) ) {
            $DATA[2] = $matches[1];
            $this->functions->debug->message("This is an HTTP like somewhere else", $DATA, 1);
            return $this->__rebuildLink($DATA, "");
        }
        // reference only links won't have to be rewritten
        if ( preg_match("%^#.*?$%", $DATA[2]) ) {
            $this->functions->debug->message("This is a refercence only", null, 1);
            return $this->__rebuildLink($DATA, "");
        }

        // 2014-07-21: Origdata before anything else - or it will be missing some things.
        $ORIGDATA2 = $DATA;
        //        $ORIGDATA2 = $DATA[2]; // 08/10/2010 - this line required a $this->functions->wl which may mess up with the base URL
        $this->functions->debug->message("OrigDATA is:", $ORIGDATA2, 1);

        // strip all things out
        // changed Data
        $PARAMS = @parse_url($DATA[2], PHP_URL_QUERY);
        $ANCHOR = @parse_url($DATA[2], PHP_URL_FRAGMENT);
        $DATA[2] = @parse_url($DATA[2], PHP_URL_PATH);

        // 2014-05-12 - fix problem with URLs starting with a ./ or ../ ... they seem to need the current IDs root
        if ( preg_match("#^\.\.?/#", $DATA[2])) {
            $DATA[2] = getNS($currentID) . ':' . $DATA[2];
        }

        // 2010-08-25 - fix problem with relative movement in links ( "test/../test2" )
        // 2014-06-30 - what? to what will this end relatively?
        $tmpData2 = '';
        while( $tmpData2 != $DATA[2] ) {
            $tmpData2 = $DATA[2];
            $DATA[2] = preg_replace("#/(?!\.\.)[^\/]*?/\.\./#", '/', $DATA[2]);
        }

        $temp = preg_replace("%^" . preg_quote(DOKU_BASE, '%') . "%", "", $DATA[2]);
        if ( $temp != $DATA[2] ) {
            $DATA[2] = $temp;
            $hadBase = true; // 2010-08-23 Check if there has been a rewrite here that will have to be considered later on
        }

        $this->functions->debug->message("URL before rewriting option for others than 1", array($DATA, $PARAMS, $hadBase), 1);

        // Handle rewrites other than 1 - just for non-lib-files
        // if ( !preg_match('$^/?lib/$', $DATA[2]) ) {
        if ( !preg_match('$^(' . DOKU_BASE . ')?lib/$', $DATA[2]) ) {
		    $this->functions->debug->message("Did not match '$^(" . DOKU_BASE . ")?lib/$' userewrite == {$conf['userewrite']}", null, 2);
            if ( $conf['userewrite'] == 2 ) {
                $DATA[2] = $this->__getInternalRewriteURL($DATA[2]);
            } elseif ( $conf['userewrite'] == 0 ) {
                $this->__getParamsAndDataRewritten($DATA, $PARAMS);
            }
        } else {
	    	$this->functions->debug->message("This file must be inside lib ...", null, 2);
        }

        $this->functions->debug->message("URL before rewriting option", array($DATA, $PARAMS), 2);

        // Generate ID
        $DATA[2] = str_replace('/', ':', $DATA[2]);

        // If Data was empty this must be the same file!;
        if ( empty( $DATA[2] ) ) {
            $DATA[2] = $currentID;
        }

        $ID = $DATA[2];
        $MEDIAMATCHER = "#(_media(/|:)|media=|_detail(/|:)|_export(/|:)|do=export_)#i"; // 2010-10-23 added "(/|:)" for the ID may not contain slashes anymore
        $ISMEDIA = preg_match($MEDIAMATCHER, $DATA[2]);
        if ( $ISMEDIA && $conf['userewrite'] == 1) {
            //$DATA[2] = preg_replace($MEDIAMATCHER, "", $DATA[2]);
            $ID = preg_replace("#^_(detail|media)(/|:)#", "", $ID);
        }
        
        $ID = $this->functions->cleanID($DATA[2], null, $ISMEDIA );
        //        $ID = $this->functions->cleanID($DATA[2], null, strstr($DATA[2], 'media') ); // Export anpassung nun weiter unten

        //        $IDexists = page_exists($ID); // 08/10/2010 - Not needed. This will be done in the next block.
        //        $this->functions->debug->message("Current ID: '$ID' exists: '" . ($IDexists ? 'true' : 'false') . "' (will be set to 'false' anyway)", null, 1);

        $IDifIDnotExists = $ID; // 08/10/2010 - Save ID - with possible upper cases to preserve them
        $IDexists = false;

        $this->functions->debug->message("Resolving ID: '$ID'", null, 2);
        if ( $ISMEDIA ) {
            resolve_mediaid(null, $ID, $IDexists);
             
            $this->functions->debug->message("Current mediaID to filename: '" . mediaFN($ID) . "'", null, 2);
        } else {
            resolve_pageid(null, $ID, $IDexists);
            $this->functions->debug->message("Current ID to filename: '" . wikiFN($ID) . "'", null, 2);
        }

        $this->functions->debug->message("Current ID after resolvement: '$ID' the ID does exist: '" . ($IDexists ? 'true' : 'false') . "'", null, 2);
        //        $ORIGDATA2 = @parse_url($this->functions->wl($ORIGDATA2, null, true)); // What was the next 2 line for? It did mess up with links from {{jdoc>}}
        //        $this->functions->debug->message("OrigData ID after parse:", $ORIGDATA2, 1); // 08/10/2010 - The lines are obsolete when the $ORIGDATA2 = $DATA. $ORIGDATA is only for fallback

        // 08/10/2010 - If the ID does not exist, we may have a problem here with upper cases - they will all be lower by now!
        if ( !$IDexists ) {
            $ID = $IDifIDnotExists; // there may have been presevered Upper cases. We will need them!
        }

        // $this->functions->cleanID($DATA[2], null, strstr($DATA[2], 'media') || strstr($DATA[2], 'export') );
        if ( substr($ID, -1) == ':' || empty($ID) ) $ID .= $conf['start'];

        // Generate Download URL
        // $PARAMS = trim(str_replace('&amp;', '&', $PARAMS));
        $PARAMS = trim($PARAMS);
        $this->functions->removeWikiVariables($PARAMS, false, true);

        $url = $this->functions->wl($ID, null, true, null, null, true, $hadBase) . ( !empty( $ANCHOR) ? '#' . $ANCHOR : '' ) . ( !empty( $PARAMS) ? '?' . $PARAMS : '' );
        $this->functions->debug->message("URL from ID: '$url'", null, 2);

        // Parse URI PATH and add "html"
        $uri = @parse_url($url);
        $DATA[2] = $uri['path'];

        $this->functions->debug->message("DATA after parsing.", $DATA, 2);

        // Second Rewrite for UseRewrite = 2
        if ( $conf['userewrite'] == 2 && preg_match("%((/lib/exe/(fetch|detail|indexer)|feed|doku)\.php)/?(.*?)$%", $DATA[2], $matches)) {
        
        
            // The actual file in lib
            $DATA[2] = $matches[1];
            $PARAMS .= '&' . (in_array($matches[3], array('fetch', 'detail')) ? 'media' : 'id') . '=' . cleanID(str_replace('/', ':', $matches[4]));
            
/*            $DATA[2] = preg_replace( '$/lib/.*?fetch\.php$', '', $DATA[2]);
            $DATA[2] = preg_replace( '%(/lib/.*?detail\.php.*$)%', '\1' . '.' . $this->functions->settings->fileType, $DATA[2]);

            if ( preg_match( '%/(lib/.*?detail|doku)\.php%', $DATA[2])) {
                $noDeepReplace = false;
                $fileName = $this->functions->getSiteName($ID);
                $newDepth = str_repeat('../', count(explode('/', $fileName))-1);
            }
            $this->functions->debug->message("DATA after second rewrite with UseRewrite = 2", array($DATA, $noDeepReplace, $fileName, $newDepth), 1);
*/
            $this->functions->debug->message("DATA after second rewrite with UseRewrite = 2", array($DATA, $matches, $PARAMS), 1);
        }

        $DATA['ANCHOR'] = $ANCHOR;
        $DATA['PARAMS'] = $PARAMS;
        $elements = explode('/', $DATA[2]);

        switch ( array_pop($elements) ) {
            // CSS Extra Handling with extra rewrites
            case 'css.php'	:	// $DATA[2] .=  ( !$this->functions->settings->addParams || empty($PARAMS) ? '' : '.' . $this->functions->cleanID(preg_replace("/(=|\?|&amp;)/", ".", $PARAMS))) . '.css';
                $DATA[2] .=  '.' . $this->functions->cleanID(preg_replace("/(=|\?|&amp;)/", ".", $PARAMS)) . '.css'; // allways put parameters behind
                // No paramters needed since they are rewritten.
                $DATA['PARAMS'] = "";
                $noDeepReplace = false;
                $fileName = $this->functions->getSiteName($ID, true);
                
                // NewDepth has to be relative to the css file itself ...
                $newDepth = './' . str_repeat('../', count(explode('/', $fileName))-1); // it is an ID at this point.
                $newAdditionalParameters['do'] = 'siteexport';

                $this->functions->debug->message("This is CSS file", array($DATA, $noDeepReplace, $fileName, $newDepth, $newAdditionalParameters), 2);

                break;
            case 'js.php'	:	// $DATA[2] .= ( !$this->functions->settings->addParams || empty($PARAMS) ? '' : '.' . $this->functions->cleanID(preg_replace("/(=|\?|&amp;)/", ".", $PARAMS))) . '.js';
                $DATA[2] .=  '.t.' . $this->functions->cleanID($_REQUEST['template']) . '.js'; // allways put parameters behind
                // set Template
                if ( !empty( $_REQUEST['template'] ) ) {
                    $url .= ( strstr($url, '?') ? '&' : '?' ) . 'template=' . $_REQUEST['template'];
                }
                // No paramters needed since they are rewritten.
                $DATA['PARAMS'] = "";
                $newAdditionalParameters['do'] = 'siteexport';

                $this->functions->debug->message("This is JS file", array($DATA, $url, $fileName, $newAdditionalParameters), 2);

                break;
                // Detail Handling with extra Rewrites if Paramaters are available - otherwise this is just the fetch
            case 'indexer.php' :
                $this->functions->debug->message("Skipping indexer", null, 2);
                return "";
                break;
            case 'detail.php' :
                $noDeepReplace = false;

                $this->__getParamsAndDataRewritten($DATA, $PARAMS, 'media');
                $ID = $this->functions->cleanID(str_replace('/', ':', $DATA[2]), null, strstr($DATA[2], 'media'));
                $fileName = $this->functions->getSiteName($ID, true); // 2010-09-03 - rewrite with override enabled

                $newDepth = str_repeat('../', count(explode('/', $fileName))-1);
                $this->__rebuildDataForNormalFiles($DATA, $PARAMS);
                $DATA[2] .= '.detail.html';

                $this->functions->debug->message("This is detail.php file with addParams", array($DATA, $ID, $fileName, $newDepth, $newAdditionalParameters), 2);
                break;
            case 'doku.php' :

                $noDeepReplace = false;
                $this->__getParamsAndDataRewritten($DATA, $PARAMS, 'id');
                $ID = $this->functions->cleanID($DATA[2], null, strstr($DATA[2], 'id'));
                
                $this->functions->debug->message("Current ID to filename (doku.php): '" . wikiFN($ID) . "'", null, 2);

                $fileName = $this->functions->getSiteName($ID); // 2010-09-03 - rewrite with override enabled

                $newDepth = str_repeat('../', count(explode('/', $fileName))-1);
                $this->__rebuildDataForNormalFiles($DATA, $PARAMS);
                $DATA[2] .= '.' . array_pop(explode('/', $fileName));

                $this->functions->debug->message("This is doku.php file with addParams", array($DATA, $ID, $fileName, $newDepth, $newAdditionalParameters), 2);
                return $this->__rebuildLink($DATA);
                break;

                // Fetch Handling for media - rewriting everything
            case 'fetch.php':
                $this->__getParamsAndDataRewritten($DATA, $PARAMS, 'media');

                $DATA[2] = str_replace('/', ':', $DATA[2]);
                $ID = $this->functions->cleanID($DATA[2], null, strstr($DATA[2], 'media'));
                resolve_mediaid(null, $ID, $IDexists);

                $DATA[2] = $this->functions->wl($ID, null, null, null, $IDexists, true);
                $this->__rebuildDataForNormalFiles($DATA, $PARAMS);

                $DATA['PARAMS'] = "";
                $newAdditionalParameters = array();

                $this->functions->debug->message("This is fetch.php file", array($DATA, $ID, $PARAMS), 2);
                break;

                // default Handling for Pages
            case 'feed.php':
                return ""; // Ignore. Has no sense to export.
                break;
            default:
                if ( preg_match("%" . preg_quote(DOKU_BASE, '%') . "_detail/%", $DATA[2]) ) {

                    // GET ID Param from origdata2
                    preg_match("#id=(.*?)(&|\")#i", $DATA[0], $backlinkID);
                    $this->__rebuildDataForNormalFiles($DATA, $PARAMS);

                    $fileIDPart = isset($backlinkID[1]) && !empty($backlinkID[1]) ? $this->functions->cleanID(urldecode($backlinkID[1])) : 'detail';

                    $ID = preg_replace("#^_detail(/|:)#", "", $ID);
                    $DATA[2] .= ':' . $fileIDPart . '.' . $this->functions->settings->fileType; // add namespace and subpage for back button and add filetype

                    $noDeepReplace = false;
                    $fileName = $this->functions->shortenName($DATA[2]);
                    $newDepth = str_repeat('../', count(explode('/', $fileName))-1);
                    $url .= ( strstr($url, '?') ? '&' : '?' ) . 'id=' . $fileIDPart; // add id-part to URL for backlinks

                    $DATA['PARAMS'] = "";

                    $this->functions->debug->message("This is something with '_detail' file", array($DATA, $backlinkID, $newDepth, $url, $ID), 2);
                } else if ( preg_match("%" . preg_quote(DOKU_BASE, '%') . "_export/(.*?)/%", $DATA[2], $fileType) ) {
                     
                    // Fixes multiple codeblocks in one file
                    $this->__rebuildDataForNormalFiles($DATA, $PARAMS);

                    // add the Params no matter what they are. This is export. We don't mess with other files
                    // adding the "/" fixes the usage of multiple codeblocks in the same namespace
                    $DATA[2] .= (empty( $PARAMS ) ? '' : '/' . $PARAMS) . '.'. $fileType[1];
                     
                    $DATA['PARAMS'] = "";
                    $this->functions->debug->message("This is something with '_export' file", $DATA, 2);

                } else if ( $IDexists ) { // 08/10/2010 - was page_exists($ID) - but this should do as well.
                    // If this is a page ... skip it!
                    $DATA[2] .= ( !$this->functions->settings->addParams || empty($PARAMS) ? '' : '.' . $this->functions->cleanID(preg_replace("/(=|\?|&amp;)/", ".", $PARAMS)))  . '.' . $this->functions->settings->fileType;

                    $DATA[2] = $this->functions->shortenName($DATA[2]);

                    // If Parameters are to be included in the filename - they must not be added twice
                    if ( $this->functions->settings->addParams ) $DATA['PARAMS'] = "";

                    $this->functions->debug->message("This page really exists", $DATA, 1);
                    
                    return $this->__rebuildLink($DATA);
                } else {
                    $this->__rebuildDataForNormalFiles($DATA, $PARAMS, true);
                    $newAdditionalParameters = null; // 2014-06-27 - when using the "normal" files way we will not need any additional stuff.
                    // This would make problems with e.g. ditaa plugin
                }

                unset($newAdditionalParameters['diPlu']);
        }

        $this->functions->debug->message("DATA after SWITCH CASE decision", array($DATA, $noDeepReplace, $fileName, $newDepth), 1);

        if ( $this->filewriter->canDoPDF() ) {
            $this->functions->addAdditionalParametersToURL($url, $newAdditionalParameters);
            $DATA[2] = $url;
            unset($DATA['PARAMS']);
            $url = $this->__rebuildLink($DATA, '');

            $this->functions->debug->message("Creating PDF with URL '$url'", null, 2);

            return $url;
        }

        // Create Name to save the file at
        $DATA[2] = str_replace(':', '_', $DATA[2]);
        $DATA[2] = $this->functions->shortenName($DATA[2]);


        // File already loaded?
        // 2010-10-23 - changes in_array from DATA[2] to $url - to check real URLs, the DATA[2] file will be checked with fileExistsInZip
        if ( in_array($url, array_keys($this->fileChecked)) ) {
            $DATA[2] = $this->fileChecked[$url];
            $this->functions->debug->message("File has been checked before.", array($DATA, $url), 2);
            return $this->__rebuildLink($DATA);
        }

        // 2010-09-03 - second check if the file is in the ZIP already.
        if ( $this->filewriter->fileExistsInZip($DATA[2]) ) {
            $this->functions->debug->message("File with DATA exists in ZIP.", $DATA, 3);
            return $this->__rebuildLink($DATA);
        }

        // 2010-10-23 - What if this is a fetch.php? than we produced an error.
        //        $this->fileChecked[] = $DATA[2];

        // get tempFile and save it
        $origDepth = $this->functions->settings->depth;
        $this->functions->settings->depth = $newDepth;

        $tmpID = $currentID;
        $tmpParent = $currentParent;
        $tmpFile = false;

        $currentParent = $fileName;
        $this->functions->debug->message("Going to get the file", array($url, $noDeepReplace, $newAdditionalParameters), 2);
        $tmpFile = $this->__getHTTPFile($url, $noDeepReplace, $newAdditionalParameters);
        $this->functions->debug->message("The getHTTPFile result is still empty", $tmpFile === false ? 'YES' : 'NO', 2);

        $currentParent = $tmpParent;
        $currentID = $tmpID;
        $this->functions->settings->depth = $origDepth; // 2010-09-03 - Reset depth at the very end

        if ( $tmpFile === false ) {
            // Keep an potentially extra link intact

            $this->functions->debug->message("The fetched file '$url' is 'false'", null, 3);
            if ( $IDexists === false ) {
                $this->functions->debug->message("The file does not exist, fallback to ORIGDATA", $ORIGDATA2, 2);
                $DATA[2] = $this->functions->shortenName($ORIGDATA2[2]); // get Origdata Path
            }

            $this->fileChecked[$url] = $DATA[2]; // 2010-09-03 - One URL to one FileName
            $link = $this->__rebuildLink($DATA);
            $this->functions->debug->message("Final Link after empty file from '$url'", null, 2);

            return $link;
        }

        $this->functions->debug->message("The fetched file looks good.", $tmpFile, 2);
        $dirname = dirname($DATA[2]);

        // If a Filename was given that does not comply to the original name, us this one!
        // 2014-02-28 But only if we are on PDF Mode. Does this produce any other Problems?
        if ( $this->filewriter->canDoPDF() && !empty($tmpFile[1]) && !strstr($DATA[2], $tmpFile[1]) ) {
			$DATA[2] = $dirname . '/' . $tmpFile[1];
            $this->functions->debug->message("Changed filename.", $DATA[2], 2);
        }

        // Custom extension if not set already - 2014-07-02
        if ( !empty($tmpFile[2]) && !preg_match("#\.{$tmpFile[2]}$#", $DATA[2]) ) {
            $DATA[2] = preg_match("#(\.[^\.]+)$#", $DATA[2]) ? preg_replace("#(\.[^\.]+)$#", '.' . $tmpFile[2], $DATA[2]) : $DATA[2] . '.' . $tmpFile[2];
            $this->functions->debug->message("Added extension provided from Server.", $DATA[2], 2);
        }

        // Add to zip
        $this->fileChecked[$url] = $DATA[2]; // 2010-09-03 - One URL to one FileName

        $status = $this->filewriter->__addFileToZip($tmpFile[0], $DATA[2]);
        @unlink($tmpFile[0]);

        $newURL = $this->__rebuildLink($DATA);
        $this->functions->debug->message("Returning final Link to document: '$newURL'", null, 2);

        return $newURL;
    }

    /**
     * build the new link to be put in place for the donwloaded site
     **/
    function __rebuildLink($DATA, $DEPTH = null) {
        global $currentID, $currentParent;

        // depth is set, skip this one
        if ( is_null( $DEPTH ) ) $DEPTH = $this->functions->settings->depth;
        $DATA[2] .= ( !empty( $DATA['PARAMS']) && $this->functions->settings->addParams? '?' . $DATA['PARAMS'] : '' ) . ( !empty( $DATA['ANCHOR'] ) ? '#' . $DATA['ANCHOR'] : '' );

        $intermediateURL = $DEPTH . $DATA[2];

//*        
        // 2012-06-15 originally has an absolute path ... we might need a relative one if not in our namespace
        if ( empty($_REQUEST['absolutePath']) && preg_match("#^(\.\./)+#", $intermediateURL) ) {

            $this->functions->debug->message("OK, this is not to be absolute: ", array($intermediateURL, $currentParent), 1);
            // Experimental
            $intermediateURL = $this->functions->getRelativeURL($intermediateURL, $currentParent);
        }
/*/
        // Check if the URL has a ../../something/somethingelse
        // and basically goes back to our current page or something in parallel
        // 1) remove all ../ at begining
        
        $this->functions->debug->message("currentID: '{$currentID}'", null, 1);
        $checkURL = preg_replace("#^(\.\./)+#", '', $intermediateURL);
        if ( $checkURL != $intermediateURL ) {
            $this->functions->debug->message("Found ../: '$checkURL' / currentIDPart: '{$currentIDPart}'", null, 2);
            
            // 2) check if the URLs next parts match the current ENS to all NS parts of the current ID
            // $this->functions->debug->message("Found ENS: '{$this->functions->settings->exportNamespace}', currentID: {$currentID}'", null, 2);
            $currentIDPart = preg_replace("#^{$this->functions->settings->exportNamespace}/#", "", str_replace(':', '/', getNS($currentID) . '/'));

            if ( ($newURL = preg_replace("#^{$currentIDPart}#", "./", $checkURL)) != $checkURL ) {
                // 3) if so, remove these parts
                $intermediateURL = $newURL;
                $this->functions->debug->message("Found ./ URL: '$newURL'", null, 2);
            }
        }
//*/
        $newURL = $DATA[1] == 'url' ? $DATA[1] . '(' . $intermediateURL . ')' : $DATA[1] . '="' . $intermediateURL . '"';
        $this->functions->debug->message("Re-created URL: '$newURL'", $DEPTH, 2);

        return $newURL;
    }


    /**
     * remove an old zip file
     **/
    function __removeOldZip( $FILENAMEID=null, $checkForMore=true, $reauthenticated=false ) {
        global $INFO;
        global $conf;

        $returnValue = true;

        if ( empty($FILENAMEID) ) {
            $FILENAMEID = $this->functions->settings->origZipFile;
        }

        if ( !file_exists(mediaFN($FILENAMEID)) ) {
            $returnValue = true;
        } else {

            require_once( DOKU_INC . 'inc/media.php');
            if ( !media_delete($FILENAMEID, $INFO['perm']) ) {
                
                if ( !$reauthenticated ) {
                    $this->functions->authenticate();
                    return $this->__removeOldZip( $FILENAMEID, $checkForMore, true );
                }
                
                $returnValue = false;
            }
        }

        if ( $checkForMore ) {
            // Try to remove more files.
            $ns = getNS($FILENAMEID);
            $fn = $this->functions->getSpecialExportFileName(noNS($FILENAMEID), '.+');

            $data = array();
            search($data, $conf['mediadir'], 'search_media', array('pattern' => "/$fn$/i"), $ns);

            if ( count($data > 0) ) {

                // 30 Minuten Cache Zeit
                $cache = $this->functions->settings->cachetime;
                foreach ( $data as $media ) {

                    //decide if has to be deleted needed:
                    if( $media['mtime'] < time()-$cache) {
                        $this->__removeOldZip($media['id'], false, $reauthenticated);
                    }
                }
            }

        }

        return $returnValue;
    }

    /**
     * if confrewrite is set to internal rewrite, use this function - taken from a DW renderer
     **/
    function __getInternalRewriteURL($url) {
        global $conf;

        //construct page id from request URI
        if( $conf['userewrite'] != 2) { return $url; }

        //get the script URL
        if($conf['basedir']) {
            $relpath = '';
            $script = $conf['basedir'].$relpath.basename($_SERVER['SCRIPT_FILENAME']);
        } elseif($_SERVER['DOCUMENT_ROOT'] && $_SERVER['SCRIPT_FILENAME']){
            $script = preg_replace ('/^'.preg_quote($_SERVER['DOCUMENT_ROOT'],'/').'/','',
            $_SERVER['SCRIPT_FILENAME']);
            $script = '/'.$script;
        }else{
            $script = $_SERVER['SCRIPT_NAME'];
        }

        //clean script and request (fixes a windows problem)
        $script  = preg_replace('/\/\/+/','/',$script);
        $request = preg_replace('/\/\/+/','/',$url);

        //remove script URL and Querystring to gain the id
        if(preg_match('/^'.preg_quote($script,'/').'(.*)/',$request, $match)){
            $id = preg_replace ('/\?.*/','',$match[1]);
        }
        $id = urldecode($id);
        //strip leading slashes
        $id = preg_replace('!^/+!','',$id);

        return $id;
    }

    /**
     * rewrite parameter calls
     **/
    function __getParamsAndDataRewritten(&$DATA, &$PARAMS, $IDKEY='id') {

        $PARRAY = explode('&', str_replace('&amp;', '&', $PARAMS) );
        $PARAMS = array();

        foreach ( $PARRAY as $item ) {
            list($key, $value) = explode('=', $item, 2);
            if ( empty($key) || empty($value) )
            continue;

            if ( strtolower(trim($key)) == $IDKEY ) {
                $DATA[2] = preg_replace("%^" . preg_quote(DOKU_BASE, '%') . "%", "", str_replace(':', '/', $value));
                continue;
            }

            $PARAMS[] = "$key=$value";
        }
        
        sort($PARAMS);
        
        $PARAMS = implode('&', $PARAMS);
    }

    /**
     * rewrite detail.php calls
     **/
    function __rebuildDataForNormalFiles(&$DATA, &$PARAMS, $addHash=false) {
        $PARTS = explode('.', $DATA[2]);
        if ( count($PARTS) > 1 ) {
            $EXT = '.' . array_pop($PARTS);
        }

        $internalParams = $PARAMS = preg_replace("/(=|\?|&amp;)/", ".", $PARAMS);
        
        // add anyways - if on overridde
        if ( !$this->functions->settings->addParams && !empty($PARAMS) && $addHash ) {
            $internalParams = md5($PARAMS);
        } else if ( !$this->functions->settings->addParams ){
            $internalParams = null;
        }
        
        $DATA[2] = implode('.', $PARTS) . ( empty($internalParams) ? '' : '.' . $this->functions->cleanID($internalParams)) . ( $EXT == '.php' ? '.' . $this->functions->settings->fileType : $EXT );
        $DATA[2] = preg_replace("/\.+/", ".", $DATA[2]);
        $this->functions->debug->message("Rebuilding Data for normal file.", $DATA[2], 1);
    }

    /*
     * Clean JS and CSS cache files
     */
    function cleanCacheFiles() {

        $_SERVER['HTTP_HOST'] = preg_replace("/:?\d+$/", '', $_SERVER['HTTP_HOST']);
        $cache = getCacheName('scripts'.$_SERVER['HTTP_HOST'].'-siteexport-js-'.$_SERVER['SERVER_PORT'],'.js');
        $this->unlinkIfExists($cache);

        $tpl = trim(preg_replace('/[^\w-]+/','',$_REQUEST['template']));
        if($tpl)
        {
            $tplinc = DOKU_INC.'lib/tpl/'.$tpl.'/';
            $tpldir = DOKU_BASE.'lib/tpl/'.$tpl.'/';
        } else {
            $tplinc = DOKU_TPLINC;
            $tpldir = DOKU_TPL;
        }

        // The generated script depends on some dynamic options
        $cache = getCacheName('styles'.$_SERVER['HTTP_HOST'].'-siteexport-js-'.$_SERVER['SERVER_PORT'].DOKU_BASE.$tplinc.$style,'.css');
        $this->unlinkIfExists($cache);
    }

    function unlinkIfExists($cache) {
        if ( file_exists($cache) ) {
            @unlink($cache);
            if(function_exists('gzopen')) @unlink("$cache.gz");
        }
    }

    // Private unset function
    private function clear(&$variable)
    {
        if ( isset($variable) )
        {
            unset($variable);
        }
    }
}

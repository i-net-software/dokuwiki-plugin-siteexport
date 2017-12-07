<?php
/**
 * Site Export Plugin - mPDF Extension
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

if (!empty($_REQUEST['pdfExport']) && intval($_REQUEST['pdfExport']) == 1 && (plugin_load('helper', 'siteexport')::checkDW2PDF()) ) {

    require_once(DOKU_PLUGIN . 'dw2pdf/DokuPDF.class.php');
    class siteexportPDF extends DokuPDF {

        public function __construct($debug) {
            global $INPUT;

            // decide on the paper setup from param or config
            $pagesize    = $INPUT->str('pagesize', null, true);
            $orientation = $INPUT->str('orientation', null, true);

            // we're always UTF-8
            parent::__construct($pagesize, $orientation);
            $this->ignore_invalid_utf8 = true;
            $this->tabSpaces = 4;
            $this->setLogger( new SiteexportLogger( $debug ) );
            $this->shrink_tables_to_fit = 1; // Does not shrink tables by default, only in emergency
            $this->use_kwt = true; // avoids page-breaking in H1-H6 if a table follows directly
            $this->useSubstitutions = true;
        }

        public function GetFullPath(&$path,$basepath='') {

            // Full Path might return a doubled path like /~gamma/documentation/lib//~gamma/documentation/lib/tpl/clearreports/./_print-images/background-bottom.jpg

            $path = str_replace("\\","/",$path); //If on Windows
            $path = preg_replace('/^\/\//','http://',$path);    // mPDF 5.6.27
            $regexp = '|^./|';    // Inadvertently corrects "./path/etc" and "//www.domain.com/etc"
            $path = preg_replace($regexp,'',$path);

            if ( preg_match("/^.+\/\.\.\//", $path) ) {
                // ../ not at the beginning
                $newpath = array();
                $oldpath = explode('/', $path);

                foreach( $oldpath as $slice ) {
                    if ( $slice == ".." && count($newpath) > 0 ) {
                        array_pop($newpath);
                        continue;
                    }

                    $newpath[] = $slice;
                }

                $path = implode('/', $newpath);
            }

            parent::GetFullPath($path, $basepath);

            $regex = "/^(". preg_quote(DOKU_BASE, '/') .".+)\\1/";
            if ( preg_match($regex, $path, $matches) ) {
                $path = preg_replace($regex, "\\1", $path);
            }

        }

        /*
          Only when the toc is being generated  
        */
        public function MovePages($target_page, $start_page, $end_page = -1) {
            parent::MovePages($target_page, $start_page, $end_page);
        }

        public function OpenTag($tag, $attr, &$ahtml, &$ihtml) {
            switch ($tag) {
                case 'BOOKMARK':
                case 'TOCENTRY':
                    if ($attr['CONTENT']) {
                        // resolve double encoding
                        $attr['CONTENT'] = htmlspecialchars_decode($attr['CONTENT'], ENT_QUOTES);
                    }
                    break;
            }
            return parent::OpenTag($tag, $attr, $ahtml, $ihtml); 
        }
    }

    /**
     * This Logger can be used to avoid conditional log calls.
     *
     * Logging should always be optional, and if no logger is provided to your
     * library creating a NullLogger instance to have something to throw logs at
     * is a good way to avoid littering your code with `if ($this->logger) { }`
     * blocks.
     *
     * The logger has to be there if we reached this point in code.
     */
    class SiteexportLogger extends Psr\Log\AbstractLogger
    {
        private $debugObj = null;

        public function __construct($debug) {
            $this->debugObj = $debug;
        }

        /**
         * Logs with an arbitrary level.
         *
         * @param mixed  $level
         * @param string $message
         * @param array  $context
         *
         * @return void
         */
        public function log($level, $message, array $context = array())
        {
            if ($this->debugObj !== null) {
                $this->debugObj->message($message, $context, $this->logLevelToSiteexportLog( $level ));
            }
        }

        private function logLevelToSiteexportLog( $level ) {
            switch( $level ) {
                case 'error': return 4;
                case 'warning': return 3;
                case 'notice':
                case 'info': return 2;
                case 'debug': return 1;
                case 'emergency':
                case 'alert':
                case 'critical':
                default : return 5;
            }
        }
    }
}

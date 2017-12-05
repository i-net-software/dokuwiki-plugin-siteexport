<?php
/**
 * Site Export Plugin - mPDF Extension
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

if (!empty($_REQUEST['pdfExport']) && intval($_REQUEST['pdfExport']) == 1 && plugin_load('action', 'dw2pdf') ) {

    require_once(DOKU_PLUGIN . 'dw2pdf/DokuPDF.class.php');
    class siteexportPDF extends DokuPDF {

        private $debugObj = null;

        public function __construct($debug) {
            global $INPUT;

            $this->debugObj = $debug;

            // decide on the paper setup from param or config
            $pagesize    = $INPUT->str('pagesize', null, true);
            $orientation = $INPUT->str('orientation', null, true);

            // we're always UTF-8
            parent::__construct($pagesize, $orientation);
            $this->ignore_invalid_utf8 = true;
            $this->tabSpaces = 4;
            $this->debugObj = $debug;
            $this->debug = $debug !== false;
            $this->shrink_tables_to_fit = 1; // Does not shrink tables by default, only in emergency
            $this->use_kwt = true; // avoids page-breaking in H1-H6 if a table follows directly
            $this->useSubstitutions = true;
        }

        public function message($msg, $vars = null, $lvl = 1)
        {
            if ($this->debugObj !== null) {
                $this->debugObj->message($msg, $vars, $lvl);
            }
        }

        public function Error($msg)
        {
            if ($this->debugObj !== null && method_exists($this->debugObj, 'runtimeException')) {
                $this->debugObj->runtimeException($msg);
            } else {
                parent::Error($msg);
            }
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
}

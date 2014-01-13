<?php

class siteexport_debug
{
    private $debug = false;
    private $firstRE = true;

    private $debugLevel = 5;
    private $debugFile = '';
    public  $isAJAX = false;
    
    public $runtimeErrors = '';

    /**
     * Debug Level
     * the level of what should be logged during the proxied session.
     * To activate the logging, you have to enter a loglevel below 5 (see below) to log
     * to the screen. If you use the debugFile option the logstream will be rerouted
     * to this file.
     *
     * Default: 5 / No logging
     *
     * Available DEBUG Levels:
     *  5 = off      - only socket exceptions will be shown to avoid blank pages
     *  4 = ERROR    - Log errors of the proxy process
     *  3 = WARN     - Log warnings during the proxy process
     *  2 = INFO     - Log information about the ongoing connection process
     *  1 = DEBUG    - detailed log about variable states
     *  0 = VERBOSE  - Additionally logs the reponse body from the server
     *
     * @param $level
     */
    public function setDebugLevel($level = 5)
    {
        $this->debugLevel = $level;
    }

    public function debugLevel()
    {
        return $this->debugLevel;
    }

    /**
     * Set a valid and writeable filename to have the debug information written into a file
     * Set the debugLevel below 5 to enable the debugging.
     *
     * e.g. $CC->debugFile = '/temp/ccproxy.txt';
     * e.g. $CC->debugFile = 'C:\temp\ccproxy.txt';
     */
    public function setDebugFile($file = null)
    {
        if ( !$file || empty($file) )
        {
            $file = null;
        }

        $this->debugFile = $file;
    }

    public function firstRE()
    {
        return $this->firstRE;
    }

    /**
     * print debug info to file if exists
     */
    public function message($info,$var=null,$level=4){

		$ajaxCanLog = $this->isAJAX && $level == 4;
        if( $this->debugLevel > $level && !$ajaxCanLog  ) return; // only log certain Debug Levels
        
        if ( empty($this->debugFile) ) {
            $this->runtimeException("DebugFile not properly configured. Make sure, it is set, readable and writable. We suggest to use a file in the DokuWiki's media directory.", true);
            $this->debugLevel = 5; // shutdown debug
        } else {
	        $fh = @fopen($this->debugFile, "a+");
	        if ( !$fh && !$ajaxCanLog ) {
	            $this->runtimeException("Could not create/open/append logfile: '{$this->debugFile}'", true);
	            $this->debugLevel = 5; // shutdown debug
	            return;
	        }
        }

        switch($level) {
            case 4: $TYPE = "ERROR"; break;
            case 3: $TYPE = " WARN"; break;
            case 2: $TYPE = " INFO"; break;
            case 1: $TYPE = "DEBUG"; break;
            default: $TYPE = " NONE"; break;
        }

        $prepend = "[" . @date('Y-m-d H:i:s') . " $TYPE] ";
        $log = $prepend . str_replace("\n", "\n" . $prepend . "\t", trim($info)) . "\n";
        
        if ( $fh ) {
	        fwrite($fh, $log);
        }
		if ( $ajaxCanLog ) {
			if ( !headers_sent() ) {
				header("HTTP/1.0 500 Internal Server Error", true, 500);
				header("Status: 500 Internal Server Error", true, 500);
			}
	        echo $log;
		}

        if ( !empty($var) ) {

            if ( is_array($var) ) {
                ob_start();
                print_r($var);
                $content = ob_get_contents();
                ob_end_clean();
            } else {
                $content = $var;
            }

            $log = $prepend . "\t" . str_replace("\n", "\n" . $prepend . "\t", str_replace("\r\n", "\n", trim($content))) . "\n";
	        if ( $fh ) {
		        fwrite($fh, $log);
	        }
			if ( $ajaxCanLog ) {
		        echo $log;
			}
        }

		if ( $fh ) {
	        fclose($fh);
		}
    }

    function runtimeException($message, $wasDebug=false) {

        if ( empty($message) ) { return; }
        
        if ( !$this->isAJAX ) {
            ob_start();
        } else if ( !headers_sent() ) {
			header("HTTP/1.0 500 Internal Server Error", true, 500);
			header("Status: 500 Internal Server Error", true, 500);
        }

        if ( !$this->isAJAX ) {
	        if ( $this->firstRE ) {
	            print 'Runtime Error' . "\n";
	        }
	
            print '<b>'.$message.'</b><br />' . "\n";
            if ( $this->firstRE ) {
                print '<b>If this error persists, please contact the server administrator.</b><br />' . "\n";
            }
        } else {
            if ( !$wasDebug ) {
                $this->message('Runtime Error: ' . $message, null, 4);
            } else {
	            print 'Runtime Error: ' . $message . "\n";
            }
        }

        $this->firstRE = false;

        if ( !$this->isAJAX ) {
            $this->runtimeErrors .= ob_get_contents();
            ob_end_clean();
        }

        return;
    }
}

?>
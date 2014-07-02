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

require_once(DOKU_PLUGIN.'siteexport/action/ajax.php');
require_once(DOKU_PLUGIN.'siteexport/cron.php');


class action_plugin_siteexport_cron extends action_plugin_siteexport_ajax
{
    /**
     * Register Plugin in DW
     **/
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'ajax_siteexport_cron_provider');
    }

    /**
     * AJAX Provider - check what is going to be done
     * @param $event
     * @param $args
     */
    public function ajax_siteexport_cron_provider(&$event, $args) {

        // If this is not a siteexport call - and cron call, ignore it.
        if ( !(strstr($event->data, '__siteexport' ) && strstr($event->data, 'cron' )) )
        {
            return;
        }
        
        $this->__init_functions();
        
        $cronOverwriteExisting = intval($_REQUEST['cronOverwriteExisting']) == 1;
        list($url, $combined) = $this->ajax_siteexport_prepareURL_and_POSTData($event);

        if ( !$function =& plugin_load('cron', 'siteexport' ) )
        {
        	$this->functions->debug->message("Could not load Cron base", null, 4);
            return;
        }
        
        $this->functions->debug->message("Will write parameters to Cron:", $combined, 1);
        
        $status = null;
        switch( $event->data ) {
            case '__siteexport_savecron': $status = $function->saveCronDataWithParameters($combined, $cronOverwriteExisting); break;
            case '__siteexport_deletecron': $status = $function->deleteCronDataWithParameters($combined); break;
            case '__siteexport_showcron': $status = $this->printCronDataList($function->configuration); break;
            default: $this->functions->debug->message("Uhoh. You did not say the magic word.", null, 4);
        }

        if ( !empty($status) )
        {
        	$this->functions->debug->message("Tried to do an action with siteexport/cron, but failed.", "Tried to do an action with siteexport/cron, but failed. ($status)". 4);
        }
    }

    
    public function printCronDataList($configuration)
    {
        require_once(DOKU_INC.'inc/JSON.php');
        $json = new JSON();
        
        $output = array();
        foreach ( $configuration as $name => $value )
        {
            list($path, $query) = explode('?', $value, 2);
            
            $output[$name] = $this->functions->parseStringToRequestArray($query, true);
            $output[$name]['ns'] = cleanID($path);
        }
        
        print $json->encode($output);
    }
}
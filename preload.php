<?php
if (!defined('DOKU_INC')) {
    define('DOKU_INC', /** @scrutinizer ignore-type */ realpath(dirname(__FILE__) . '/../../../') . '/');
}

if ( file_exists(DOKU_INC . 'inc/plugincontroller.class.php') ) {
    include_once(DOKU_INC . 'inc/plugincontroller.class.php');
    class _preload_plugin_siteexport_controller extends Doku_Plugin_Controller {}
} else if ( file_exists(DOKU_INC . 'inc/Extension/PluginController.php') ) {
    include_once(DOKU_INC . 'inc/Extension/PluginController.php');
    class _preload_plugin_siteexport_controller extends \dokuwiki\Extension\PluginController {}
} else if ( class_exists( "\dokuwiki\Extension\PluginController", true ) ) {
    class _preload_plugin_siteexport_controller extends \dokuwiki\Extension\PluginController {}
}

class preload_plugin_siteexport {

    public $error;

    public function __register_template() {

        global $conf;
        $tempREQUEST = array();

        if (!empty($_REQUEST['q'])) {

            $tempREQUEST = (array) json_decode(stripslashes($_REQUEST['q']), true);

        } else if (array_key_exists('template', $_REQUEST)) {
            $tempREQUEST = $_REQUEST;
        } else if (preg_match("/(js|css)\.php$/", $_SERVER['SCRIPT_NAME']) && isset($_SERVER['HTTP_REFERER'])) {
            // this is a css or script, nothing before matched and we have a referrer.
            // lets asume we came from the dokuwiki page.

            // Parse the Referrer URL
            $url = parse_url($_SERVER['HTTP_REFERER']);
            if (isset($url['query'])) {
                parse_str($url['query'], $tempREQUEST);
            }
        } else {
            return;
        }

        // define Template baseURL
        $newTemplate = array_key_exists('template', $tempREQUEST) ? $tempREQUEST['template'] : null;
        // Make sure, that the template is set and the basename is equal to the template, alas there are no path definitions. see #48
        if (empty($newTemplate) || basename($newTemplate) != $newTemplate) { return; }
        $tplDir = DOKU_INC . 'lib/tpl/' . $newTemplate;
        // check if the directory is valid, has no more "../" in it and is equal to what we expect. DOKU_INC itself is absolute. see #48
        if ($tplDir != realpath($tplDir)) { return; }

        // Use fileexists, because realpath is not always right.
        if (!file_exists($tplDir)) { return; }

        // Set hint for Dokuwiki_Started event
        if (!defined('SITEEXPORT_TPL'))        define('SITEEXPORT_TPL', $tempREQUEST['template']);

        // define baseURL
        // This should be DEPRECATED - as it is in init.php which suggest tpl_basedir and tpl_incdir
        /* **************************************************************************************** */
        if (!defined('DOKU_REL')) define('DOKU_REL', getBaseURL(false));
        if (!defined('DOKU_URL')) define('DOKU_URL', getBaseURL(true));
        if (!defined('DOKU_BASE')) {
            if (isset($conf['canonical'])) {
                define('DOKU_BASE', DOKU_URL);
            } else {
                define('DOKU_BASE', DOKU_REL);
            }
        }

        // This should be DEPRECATED - as it is in init.php which suggest tpl_basedir and tpl_incdir
        if (!defined('DOKU_TPL')) define('DOKU_TPL', (empty($tempREQUEST['base']) ? DOKU_BASE : $tempREQUEST['base']) . 'lib/tpl/' . $tempREQUEST['template'] . '/');
        if (!defined('DOKU_TPLINC')) define('DOKU_TPLINC', $tplDir);
        /* **************************************************************************************** */
    }

    public function __temporary_disable_plugins() {

        // Check for siteexport - otherwise this does not matter.
        if (empty($_REQUEST['do']) || $_REQUEST['do'] != 'siteexport') {
            return;
        }

        // check for css and js  ... only disable in that case.
        if (!preg_match("/(js|css)\.php$/", $_SERVER['SCRIPT_NAME'])) {
            return;
        }

        //        print "removing plugins ";
        $_GET['purge'] = 'purge'; //activate purging
        $_POST['purge'] = 'purge'; //activate purging
        $_REQUEST['purge'] = 'purge'; //activate purging

        $_SERVER['HTTP_HOST'] = 'siteexport.js'; // fake everything in here

        // require_once(DOKU_INC.'inc/plugincontroller.class.php'); // Have to get the pluginutils already
        // require_once(DOKU_INC.'inc/pluginutils.php'); // Have to get the pluginutils already
        $this->__disablePlugins();
    }

    private function __disablePlugins() {
        global $plugin_controller_class;
        $plugin_controller_class = 'preload_plugin_siteexport_controller';    
    }

    public function __create_preload_function() {

        $PRELOADFILE = DOKU_INC . 'inc/preload.php';
        $CURRENTFILE = 'DOKU_INC' . " . 'lib/plugins/siteexport/preload.php'";
        $CONTENT = <<<OUTPUT
/* SITE EXPORT *********************************************************** */
    if ( file_exists($CURRENTFILE) ) {
        include_once($CURRENTFILE);
        \$siteexport_preload = new preload_plugin_siteexport();
        \$siteexport_preload->__register_template();
        \$siteexport_preload->__temporary_disable_plugins();
        unset(\$siteexport_preload);
    }
/* SITE EXPORT END *********************************************************** */

OUTPUT;

        if (file_exists($PRELOADFILE)) {

            if (!is_readable($PRELOADFILE)) {
                $this->error = "Preload File locked. It exists, but it can't be read.";
                msg($this->error, -1);
                return false;
            }

            if (!is_writeable($PRELOADFILE)) {
                $this->error = "Preload File locked. It exists and is readable, but it can't be written.";
                msg($this->error, -1);
                return false;
            }

            $fileContent = file($PRELOADFILE);
            if (!strstr(implode("", $fileContent ?: array()), $CONTENT)) {

                $fp = fopen($PRELOADFILE, "a");
                if ( !$fp ) { return false; }
                if (!strstr(implode("", $fileContent), "<?")) {
                    fputs($fp, "<?php\n");
                }
                fputs($fp, "\n" . $CONTENT);
                fclose($fp);
            }

            return true;

        } else if (is_writeable(DOKU_INC . 'inc/')) {

            $fp = fopen($PRELOADFILE, "w");
            if ( !$fp ) { return false; }
            fputs($fp, "<?php\n/*\n * Dokuwiki Preload File\n * Auto-generated by Site Export plugin \n * Date: " . (date('Y-m-d H:s:i') ?: "-") . "\n */\n");
            fputs($fp, $CONTENT);
            fputs($fp, "// end auto-generated content\n\n");
            fclose($fp);

            return true;
        }

        $this->error = "Could not create/modify preload.php. Please check the write permissions for your DokuWiki/inc directory.";
        msg($this->error, -1);
        return false;
    }

}

// return a custom plugin list
class preload_plugin_siteexport_controller extends _preload_plugin_siteexport_controller {

    protected $tmp_plugins = array();

    /**
     * Setup disabling
     */
    public function __construct() {
        parent::__construct();

        $disabledPlugins = array();

        // support of old syntax
        if (is_array($_REQUEST['diPlu'])) {
            $disabledPlugins = $_REQUEST['diPlu'];
        }

        if (!empty($_REQUEST['diInv']))
        {
            $allPlugins = array();
            foreach ($this->tmp_plugins as $plugin => $enabled) { // All plugins
                // check for CSS or JS
                if ($enabled == 1 && !file_exists(DOKU_PLUGIN . "$plugin/script.js") && !file_exists(DOKU_PLUGIN . "$plugin/style.css") && !file_exists(DOKU_PLUGIN . "$plugin/print.css")) { continue; }
                $allPlugins[] = $plugin;
            }
            $disabledPlugins = empty($_REQUEST['diPlu']) ? $allPlugins : array_diff($allPlugins, $_REQUEST['diPlu']);
        }

        // if this is defined, it overrides the settings made above. obviously.
        $disabledPlugins = empty($_REQUEST['disableplugin']) ? $disabledPlugins : $_REQUEST['disableplugin'];

        foreach ($disabledPlugins as $plugin) {
            $this->disable($plugin);
        }

        // always enabled - JS and CSS will be cut out later.
        $this->enable('siteexport');
    }

    /**
     * Disable the plugin
     *
     * @param string $plugin name of plugin
     * @return bool; true allways.
     */
    public function disable($plugin) {
        $this->tmp_plugins[$plugin] = 0;
        return true;
    }

    /**
     * Enable the plugin
     *
     * @param string $plugin name of plugin
     * @return bool; true allways.
     */
    public function enable($plugin) {
        $this->tmp_plugins[$plugin] = 1;
        return true;
    }

    public function hasSiteexportHeaders() {
        $headers = function_exists('getallheaders') ? getallheaders() : null;
        return is_array($headers) && array_key_exists('X-Site-Exporter', $headers) /* && $headers['X-Site-Exporter'] = getSecurityToken() */;
    }

    /**
     * Filter the List of Plugins for the siteexport plugin
     */
    private function isSiteexportPlugin($item) {
        return $item != 'siteexport';
    }

    /**
     * Get the list of plugins, bute remove Siteexport from Style and
     * JS if in export Mode
     */   
    public function getList($type = '', $all = false) {
        $plugins = parent::getList($type, $all);

        list(,, $caller) = debug_backtrace();
        if ($this->hasSiteexportHeaders() && $caller != null && preg_match("/^(js|css)_/", $caller['function']) && preg_match("/(js|css)\.php$/", $caller['file'])) {
            $plugins = array_filter($plugins, array($this, 'isSiteexportPlugin'));
        }

        return $plugins;
    }
}

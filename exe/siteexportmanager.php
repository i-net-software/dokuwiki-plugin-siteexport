<?php
/**
 * Siteexport Manager Popup
 *
 * based up on the mediamanager popup
 *
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 * @license  GPL 2 (http://www.gnu.org/licenses/gpl.html)
 */
if(!defined('DOKU_INC')) define('DOKU_INC',dirname(__FILE__).'/../../../../');
define('DOKU_SITEEXPORT_MANAGER',1);

    require_once(DOKU_INC.'inc/init.php');

    global $INFO, $JSINFO, $INPUT, $ID, $conf;
    
    $NS = cleanID($INPUT->str('ns'));
    
    if ( empty($ID) ) {
    	if ( empty($conf['basedir']) ) {
    	
    		$path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, dirname(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)).'/../../../../');
		    $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
		    $absolutes = array();
		    foreach ($parts as $part) {
		        if ('.'  == $part) continue;
		        if ('..' == $part) {
		            array_pop($absolutes);
		        } else {
		            $absolutes[] = $part;
		        }
		    }
		    $conf['basedir']='/'.implode(DIRECTORY_SEPARATOR, $absolutes);
    	}

    	$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_REFERER'];
    	$ID = $NS . ':' . getID();
    }
    
    $INFO = !empty($INFO) ? array_merge($INFO, mediainfo()) : mediainfo();
    $JSINFO = array('id' => $ID, 'namespace' => $NS);
    $AUTH = $INFO['perm'];    // shortcut for historical reasons

    // do not display the manager if user does not have read access
    if($AUTH < AUTH_READ) {
       	http_status(403);
        die($lang['accessdenied']);
    }

    header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="<?php echo $conf['lang']?>" dir="<?php echo $lang['direction'] ?>" class="popup no-js">
<head>
    <meta charset="utf-8" />
    <title>
        <?php echo hsc($lang['mediaselect'])?>
        [<?php echo strip_tags($conf['title'])?>]
    </title>
    <script>(function(H){H.className=H.className.replace(/\bno-js\b/,'js')})(document.documentElement)</script>
    <?php tpl_metaheaders()?>
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <?php echo tpl_favicon(array('favicon', 'mobile')) ?>
    <?php tpl_includeFile('meta.html') ?>
</head>

<body>
    <!--[if lte IE 7 ]><div id="IE7"><![endif]--><!--[if IE 8 ]><div id="IE8"><![endif]-->
    <div id="siteexport__manager" class="dokuwiki">
        <?php html_msgarea() ?>
        <?php        
			$functions=& plugin_load('helper', 'siteexport');
			$functions->__siteexport_addpage();	        
        ?>        
    </div>
    <!--[if ( lte IE 7 | IE 8 ) ]></div><![endif]-->
</body>
</html>

<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
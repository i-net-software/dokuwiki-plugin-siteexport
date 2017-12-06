<?php

namespace dokuwiki\plugin\siteexport;

use dokuwiki\Menu\Item\AbstractItem;

/**
 * Class MenuItem
 *
 * Implements the PDF export button for DokuWiki's menu system
 *
 * @package dokuwiki\plugin\dw2pdf
 */
class MenuItem extends AbstractItem {

    /** @var string do action for this plugin */
    protected $type = 'siteexport_addpage';

    /** @var string icon file */
    protected $svg = __DIR__ . '/images/siteexport.svg';

    /**
     * MenuItem constructor.
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Get label from plugin language file
     *
     * @return string
     */
    public function getLabel() {
        $hlp = plugin_load('helper', 'siteexport');
        return $hlp->getLang('siteexport_button');
    }
}

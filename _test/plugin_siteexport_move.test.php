<?php

/**
 * @group plugin_siteexport
 * @group plugins
 */
class SiteexportMoveTest extends DokuWikiTest {
    public function setup() {
        $this->pluginsEnabled[] = 'siteexport';
        $this->pluginsEnabled[] = 'move';
        parent::setup();
    }

    public function test_move() {
        /** @var $move helper_plugin_move_op */
        $move = plugin_load('helper', 'move_op');
        if (!$move) return; // disable the test when move is not installed
        saveWikiText('pagetomove', '<toc>
  * [[index|Index of the page]]
    * [[foo:bar|Index of the sub namespace]]
      * [[foo:index|Index of the sub/sub namespace]]
    * [[.:foo:page|Page in the sub namespace]]
</toc>', 'testcase created');
        idx_addPage('pagetomove');
        $this->assertTrue($move->movePage('pagetomove', 'test:movedpage'));
        $this->assertEquals('<toc>
  * [[:index|Index of the page]]
    * [[foo:bar|Index of the sub namespace]]
      * [[foo:index|Index of the sub/sub namespace]]
    * [[foo:page|Page in the sub namespace]]
</toc>',rawWiki('test:movedpage'));
    }
}

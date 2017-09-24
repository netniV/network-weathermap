<?php
// require_once 'PHPUnit/Framework.php';

//require_once dirname(__FILE__) . '/../lib/HTMLImagemap.php';

use Weathermap\Core\HTMLImagemapAreaRectangle;

/**
 * Test class for HTML_Imagemap_Area_Rectangle.
 * Generated by PHPUnit on 2010-04-06 at 14:29:40.
 */
class HTMLImagemapAreaRectangleTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var HTMLImagemapAreaRectangle
     */
    protected $objects;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->objects = array();
        $this->objects[] = new HTMLImagemapAreaRectangle("testarea", "testhref", array(array(50, 20, 150, 170)));
        $this->objects[] = new HTMLImagemapAreaRectangle("testarea", "testhref", array(array(150, 170, 50, 20)));
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    /**
     * @todo Implement testHitTest().
     */
    public function testHitTest()
    {
        // Remove the following lines when you implement this test.

        foreach ($this->objects as $object) {
            $this->assertTrue($object->hitTest(100, 100));

            $this->assertFalse($object->hitTest(50, 100));
            $this->assertFalse($object->hitTest(150, 20));
            $this->assertFalse($object->hitTest(150, 170));
            $this->assertFalse($object->hitTest(50, 170));

            $this->assertTrue($object->hitTest(50.1, 100));
            $this->assertTrue($object->hitTest(149.9, 20.1));
            $this->assertTrue($object->hitTest(149.9, 169.9));
            $this->assertTrue($object->hitTest(50.1, 169.9));

            $this->assertFalse($object->hitTest(151, 170));
            $this->assertFalse($object->hitTest(1, 1));
        }
    }
}

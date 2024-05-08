<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Unit\Helper;

class CountQueueHelperTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        if (file_exists(__DIR__.'/../../../Assets/json/count.json.php')) {
            unlink(__DIR__.'/../../../Assets/json/count.json.php');
        }
    }

    public function testGet(): void
    {
        $helper = new \MauticPlugin\LeuchtfeuerCompanyPointsBundle\Helper\CountQueueHelper();
        $this->assertIsArray($helper->get());
    }

    public function testGenerate(): void
    {
        $this->assertFileDoesNotExist(__DIR__.'/../../../Assets/json/count.json.php');
        $helper = new \MauticPlugin\LeuchtfeuerCompanyPointsBundle\Helper\CountQueueHelper();
        $this->assertFileExists(__DIR__.'/../../../Assets/json/count.json.php');
    }

    public function testSet(): void
    {
        $helper = new \MauticPlugin\LeuchtfeuerCompanyPointsBundle\Helper\CountQueueHelper();
        $helper->set(['batch' => 5]);
        $this->assertEquals(5, $helper->get()['batch']);
    }

    public function testGetOffset(): void
    {
        $helper = new \MauticPlugin\LeuchtfeuerCompanyPointsBundle\Helper\CountQueueHelper();
        $this->assertEquals(0, $helper->getOffset());
    }

    public function testSetOffset(): void
    {
        $helper = new \MauticPlugin\LeuchtfeuerCompanyPointsBundle\Helper\CountQueueHelper();
        $helper->setOffset(2);
        $this->assertEquals(2, $helper->getOffset());
    }

    public function testResetOffset(): void
    {
        $helper = new \MauticPlugin\LeuchtfeuerCompanyPointsBundle\Helper\CountQueueHelper();
        $helper->setOffset(2);
        $helper->resetOffset();
        $this->assertEquals(0, $helper->getOffset());
    }

}
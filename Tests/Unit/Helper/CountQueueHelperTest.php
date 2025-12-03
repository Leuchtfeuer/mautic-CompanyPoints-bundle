<?php declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Unit\Helper;

use Mautic\CoreBundle\Configurator\Configurator;
use Mautic\CoreBundle\Helper\PathsHelper;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Helper\CountQueueHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class CountQueueHelperTest extends TestCase
{

    /**
     * @var MockObject&PathsHelper
     */
    private MockObject $pathsHelper;

    private Configurator $configurator;

    private string $configFile;

    public function setUp(): void
    {
        $tmp = sys_get_temp_dir().'/CountQueueHelperTest';

        if (!is_dir($tmp.'/app/config')) {
            mkdir($tmp.'/app/config', recursive: true);
        }

        if (!is_dir($tmp.'/config')) {
            mkdir($tmp.'/config', recursive: true);
        }

        if (!is_file($tmp.'/app/config/paths.php')) {
            file_put_contents($tmp.'/app/config/paths.php', '<?php' . PHP_EOL . '$parameters = [];');
        }

        $this->configFile = $tmp.'/config/local.php';
        if (is_file($this->configFile)) {
            unlink($this->configFile);
        }

        $this->pathsHelper = $this->createMock(PathsHelper::class);
        $this->pathsHelper->method('getSystemPath')->willReturn($tmp);
        $this->configurator = new Configurator($this->pathsHelper);
    }

    public static function tearDownAfterClass(): void
    {
        $tmp = sys_get_temp_dir().'/CountQueueHelperTest';

        $filesystem = new Filesystem();
        $filesystem->remove($tmp);
    }

    public function testGetDefault(): void
    {
        $helper = new CountQueueHelper($this->configurator);
        $this->assertIsArray($helper->get());
    }

    public function testSet(): void
    {
        $this->assertFileDoesNotExist($this->configFile);
        $helper = new CountQueueHelper($this->configurator);
        $helper->set(['batch' => 5]);
        $this->assertSame(5, $helper->get()['batch']);
        $this->assertFileExists($this->configFile);
    }

    public function testGetOffset(): void
    {
        $helper = new CountQueueHelper($this->configurator);
        $this->assertSame(0, $helper->getOffset());
    }

    public function testSetOffset(): void
    {
        $helper = new CountQueueHelper($this->configurator);
        $helper->setOffset(2);
        $this->assertSame(2, $helper->getOffset());
    }

    public function testResetOffset(): void
    {
        $helper = new CountQueueHelper($this->configurator);
        $helper->setOffset(2);
        $helper->resetOffset();
        $this->assertSame(0, $helper->getOffset());
    }

    public function testSetValueAndReadConfig(): void
    {
        $helper = new CountQueueHelper($this->configurator);
        $helper->setOffset(2);
        $this->assertSame(2, $helper->getOffset());

        // Construct new configurator.
        $configurator = new Configurator($this->pathsHelper);
        $helper = new CountQueueHelper($configurator);
        $this->assertSame(2, $helper->getOffset());
    }
}

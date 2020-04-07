<?php
/**
 * 27.03.2020
 */

declare(strict_types=1);


namespace Test;

use ReflectionException;
use ReflectionMethod;
use ReflectionObject;
use TextAtAnyCost\Cfb;
use TextAtAnyCost\Doc;
use TextAtAnyCost\ServiceClasses\CfbStorage;

/**
 * Test the Cfb class through inherits.
 */
class MainCfbTest extends LocalTestCase
{
    public function testReadFileWhichNotExists(): void
    {
        $cbf = $this->getMockForAbstractClass(Cfb::class);
        $file = $this->dataDir('not-a-file.doc');
        $this->expectException(\RuntimeException::class);
        $cbf->read($file);
    }

    public function testReadNormalFile(): void
    {
        $cfb = $this->getMockForAbstractClass(Cfb::class);
        $emptyStorage = clone $cfb->getStorage();
        $cfb->read($this->dataDir('sample1.doc'));
        $initializedStorage = $cfb->getStorage();

        $this->assertNotEquals($emptyStorage, $initializedStorage);
    }

    public function testWithInitializedSedStorage(): void
    {
        $emptyStorage = $this->getMockForAbstractClass(Cfb::class)->getStorage();
        $newCfb = $this->getMockForAbstractClass(Cfb::class, [$emptyStorage]);

        $this->assertSame($emptyStorage, $newCfb->getStorage());
    }

    public function testLoadVariables(): void
    {
        $this->expectException(\RuntimeException::class);

        $cbf = $this->getMockForAbstractClass(Cfb::class);
        try {
            $loadVariables = (new ReflectionObject($cbf))->getMethod('loadVariables');
        } catch (ReflectionException $e) {
            $this->fail($e->getMessage());
        }
        $this->assertInstanceOf(ReflectionMethod::class, $loadVariables);
        $loadVariables->setAccessible(true);

        $file = __FILE__;
        $cbf->read($file);

        $loadVariables->invoke($cbf);
        $this->assertEquals($this->getExpectedExceptionMessage(), 'Data is not CFB structure');
    }
}

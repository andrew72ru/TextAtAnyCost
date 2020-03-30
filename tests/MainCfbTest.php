<?php
/**
 * 27.03.2020
 */

declare(strict_types=1);


namespace Test;

use TextAtAnyCost\Cfb;

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
}

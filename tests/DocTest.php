<?php
/**
 * 30.03.2020
 */

declare(strict_types=1);


namespace Test;


use TextAtAnyCost\Doc;

class DocTest extends LocalTestCase
{
    private $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = $this->dataDir('sample2.doc');
    }

    public function testDocParse(): void
    {
        $doc = new Doc();
        $doc->read($this->path);
        $result = $doc->parse();
        $this->assertNotNull($result);
        $this->assertStringContainsString('Hic ambiguo ludimur', $result);
        $this->assertStringContainsString('Quis istud possit, inquit, negare?', $result);
    }
}

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
        $doc->read($this->dataDir('sample2.doc'));
        $result = $doc->parse();
        $this->assertNotNull($result);
        $this->assertStringContainsString('Название', $result);
        $this->assertStringContainsString('Заголовок', $result);
    }

    public function testOtherDocParse(): void
    {
        $doc = new Doc();
        $doc->read($this->dataDir('sample1.doc'));
        $result = $doc->parse();
        $this->assertNotNull($result);
        $this->assertStringContainsString('Lorem ipsum dolor sit amet', $result);
    }
}

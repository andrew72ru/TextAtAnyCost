<?php
/**
 * 07.04.2020
 */

declare(strict_types=1);


namespace Test;


use function TextAtAnyCost\doc2text;

class FunctionTest extends LocalTestCase
{
    public function testFunctionExists(): void
    {
        $this->assertTrue(\function_exists('TextAtAnyCost\doc2text'));
    }

    public function testFunctionWorks(): void
    {
        $result = \TextAtAnyCost\doc2text($this->dataDir('sample1.doc'));
        $this->assertNotNull($result);
        $this->assertStringContainsString('Lorem ipsum dolor sit amet', $result);
    }

    public function testFunctionException(): void
    {
        $this->expectException(\RuntimeException::class);
        $file = $this->dataDir('not-a-file.doc');

        \TextAtAnyCost\doc2text($file);
    }
}

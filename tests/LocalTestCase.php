<?php
/**
 * 27.03.2020
 */

declare(strict_types=1);


namespace Test;


use PHPUnit\Framework\TestCase;

class LocalTestCase extends TestCase
{
    protected function dataDir(string $append = null): string
    {
        return \sprintf('%s/data/%s', __DIR__, $append ?? '');
    }
}

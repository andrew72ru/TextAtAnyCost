<?php
/**
 * 27.03.2020.
 */

declare(strict_types=1);

namespace TextAtAnyCost;

interface ReadFileInterface
{
    public function read(string $filename): void;
}

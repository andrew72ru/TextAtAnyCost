<?php
/**
 * 27.03.2020.
 */

declare(strict_types=1);

namespace TextAtAnyCost;

interface ConverterInterface
{
    /**
     * @param string|null $path
     *
     * @return string|null
     */
    public function parse(?string $path = null): ?string;
}

<?php
/**
 * 27.03.2020.
 */

declare(strict_types=1);

namespace TextAtAnyCost;

interface ConverterInterface
{
    public function parse(): ?string;
}

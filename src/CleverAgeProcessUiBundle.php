<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class CleverAgeProcessUiBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}

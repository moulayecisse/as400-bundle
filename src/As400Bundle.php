<?php

namespace Cisse\Bundle\As400;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class As400Bundle extends Bundle
{
    public function getPath(): string
    {
        return __DIR__;
    }
}

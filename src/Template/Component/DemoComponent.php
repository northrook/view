<?php

namespace Core\View\Template\Component;

use Core\View\Template\Component;

final class DemoComponent extends Component
{
    public function getString() : string
    {
        return __CLASS__;
    }

    public function __invoke() : static
    {
        return $this;
    }
}

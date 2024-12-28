<?php

declare(strict_types=1);

namespace Core\View\Component;

use Core\View\Interface\ViewComponentInterface;
use Core\View\Template\View;

class AbstractComponent extends View implements ViewComponentInterface
{
    public readonly string $name;

    public readonly string $uniqueID;

    public function create(
        array   $arguments,
        array   $promote = [],
        ?string $uniqueId = null,
    ) : ViewComponentInterface {
        return $this;
    }
}

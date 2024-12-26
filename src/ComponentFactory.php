<?php

namespace Core\View;

use Core\Symfony\DependencyInjection as Core;
use Core\View\Interface\{ComponentFactoryInterface};
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[Autoconfigure(
    lazy   : true,   // lazy-load using ghost
    public : false,  // private
)]
class ComponentFactory implements ComponentFactoryInterface
{
    use Core\ServiceLocator;
    /**
     * @template Component
     *
     * @param ServiceLocator<Component> $locator `view.component_locator`
     */
    public function __construct(
        private readonly ServiceLocator $locator,
    ) {}
}

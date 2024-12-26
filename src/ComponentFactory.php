<?php

namespace Core\View;

use Core\Symfony\DependencyInjection as Core;
use Core\View\Interface\{ComponentFactoryInterface};
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\ServiceLocator;
use const Cache\AUTO;

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

    /**
     * Renders a component at runtime.
     *
     * @param class-string|string  $component
     * @param array<string, mixed> $arguments
     * @param ?int                 $cache
     *
     * @return string
     */
    public function render( string $component, array $arguments = [], ?int $cache = AUTO ) : string
    {
        return $component;
    }
}

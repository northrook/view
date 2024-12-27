<?php

declare(strict_types=1);

namespace Core\View;

use Core\Symfony\DependencyInjection as Core;
use Core\View\Interface\{ComponentFactoryInterface};
use Symfony\Component\DependencyInjection\ServiceLocator;
use const Cache\AUTO;

class ComponentFactory implements ComponentFactoryInterface
{
    use Core\ServiceLocator;

    /**
     * @template Component
     *
     * @param ServiceLocator<Component> $locator `view.component_locator`
     */
    public function __construct( public readonly ServiceLocator $locator ) {}

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

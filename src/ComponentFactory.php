<?php

declare(strict_types=1);

namespace Core\View;

use Core\Symfony\DependencyInjection as Core;
use Core\View\Interface\{ComponentFactoryInterface};
use Core\View\ComponentFactory\ComponentBag;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use const Cache\AUTO;

class ComponentFactory implements ComponentFactoryInterface
{
    public const string PARAMETER_KEY = 'view.components';
    // use Core\ServiceLocator;

    /**
     * @template Component
     *
     * @param ServiceLocator<Component>                           $locator
     * @param array<string, ComponentFactory\ComponentProperties> $components
     * @param ?LoggerInterface                                    $logger
     */
    public function __construct(
        protected readonly ServiceLocator   $locator,
        protected readonly ComponentBag     $components,
        protected readonly ?LoggerInterface $logger,
    ) {
        dump( $this );
    }

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
        dump( $this->locator->getProvidedServices() );
        return $component;
    }
}

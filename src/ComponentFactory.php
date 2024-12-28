<?php

declare(strict_types=1);

namespace Core\View;

use Core\View\Interface\{ComponentFactoryInterface, ViewComponentInterface, ViewInterface};
use Core\View\ComponentFactory\{ComponentBag, ComponentProperties};
use Core\View\Exception\ComponentNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use const Cache\AUTO;
use InvalidArgumentException;

class ComponentFactory implements ComponentFactoryInterface
{
    /**
     * `[ component.name => uniqueId ]`.
     *
     * @var array<string, array<int, string>>
     */
    private array $instantiated = [];

    /**
     * @param ServiceLocator<ViewComponentInterface> $locator
     * @param ComponentBag                           $components
     * @param array<string, string>                  $tags
     * @param ?LoggerInterface                       $logger
     */
    public function __construct(
        protected readonly ServiceLocator   $locator,
        protected readonly ComponentBag     $components,
        protected readonly array            $tags = [],
        protected readonly ?LoggerInterface $logger = null,
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
     * @return ViewComponentInterface
     */
    public function render( string $component, array $arguments = [], ?int $cache = AUTO ) : ViewInterface
    {
        $properties = $this->getComponentProperties( $component );

        $viewComponent = $this->getComponent( $component );

        $viewComponent->create( $arguments, $properties->tagged );

        $this->instantiated[$properties->name][] = $viewComponent->uniqueID;

        return $viewComponent;
    }

    // :: retrieve

    /**
     * Begin the Build proccess of a component.
     *
     * @param class-string|ComponentProperties|string $component
     *
     * @return ViewComponentInterface
     */
    final public function getComponent( string|ComponentProperties $component ) : ViewComponentInterface
    {
        $component = $this->getComponentName( (string) $component );

        if ( ! $component ) {
            $message = "The component '{$component}' does not exist.";
            throw new InvalidArgumentException( $message );
        }

        if ( $this->locator->has( $component ) ) {
            $component = $this->locator->get( $component );

            \assert( $component instanceof ViewComponentInterface );

            return clone $component;
        }

        throw new ComponentNotFoundException( $component, 'Not found in the Component Container.' );
    }

    final public function getComponentProperties( string $component ) : ComponentProperties
    {
        $component = $this->getComponentName( $component );

        if ( ! $component || ! $this->components->has( $component ) ) {
            throw new ComponentNotFoundException( $component, 'Not found in the Component Container.' );
        }

        return $this->components->get( $component );
    }

    /**
     * @param string $from name or tag
     *
     * @return null|string
     */
    final public function getComponentName( string $from ) : ?string
    {
        // If the provided $value matches an array name, return it
        if ( $this->components->has( $from ) ) {
            return $from;
        }

        return $this->tags[ComponentProperties::tag( $from )] ?? null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    final public function getInstantiated() : array
    {
        return $this->instantiated;
    }

    /**
     * @return ComponentProperties[]
     */
    final public function getRegisteredComponents() : array
    {
        return $this->components->all();
    }

    // .. validation

    /**
     * Check if the provided string matches any {@see ComponentFactory::$components}.
     *
     * @param string $component
     *
     * @return bool
     */
    final public function hasComponent( string $component ) : bool
    {
        return $this->components->has( $component );
    }

    /**
     * Check if the provided string matches any {@see ComponentFactory::$tags}.
     *
     * @param string $tag
     *
     * @return bool
     */
    final public function hasTag( string $tag ) : bool
    {
        return \array_key_exists( $tag, $this->tags );
    }
}

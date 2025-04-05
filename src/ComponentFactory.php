<?php

declare(strict_types=1);

namespace Core\View;

use Core\View\ComponentFactory\{ComponentBag, ComponentProperties};
use Core\Profiler\Interface\Profilable;
use Core\Profiler\ProfilerTrait;
use Core\Interface\{LazyService};
use Core\View\Attribute\ViewComponent;
use Core\View\Template\{Component, Engine};
use Core\View\Exception\ComponentNotFoundException;
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait};
use Symfony\Component\DependencyInjection\ServiceLocator;
use function Support\str_start;
use const Support\AUTO;
use InvalidArgumentException;

class ComponentFactory implements LazyService, Profilable, LoggerAwareInterface
{
    use ProfilerTrait, LoggerAwareTrait;

    /**
     * `[ component.name => uniqueId ]`.
     *
     * @var array<string, array<int, string>>
     */
    private array $instantiated = [];

    /**
     * @param Engine                    $engine
     * @param ServiceLocator<Component> $locator
     * @param ComponentBag              $components
     * @param array<string, string>     $tags
     */
    public function __construct(
        protected readonly Engine         $engine,
        protected readonly ServiceLocator $locator,
        protected readonly ComponentBag   $components,
        protected readonly array          $tags = [],
    ) {}

    /**
     * Renders a component at runtime.
     *
     * @param class-string|string                                          $component
     * @param array<string, null|array<array-key, string|string[]>|string> $arguments
     * @param ?int                                                         $cache
     *
     * @return Component
     */
    public function render(
        string $component,
        array  $arguments = [],
        ?int   $cache = AUTO,
    ) : Component {
        $this->profiler?->event( $component );

        $properties = $this->getComponentProperties( $component );

        $viewComponent = $this->getComponent( $component );

        // @phpstan-ignore-next-line
        $viewComponent->create( $arguments, $properties->tagged );

        $this->instantiated[$properties->name][] = $viewComponent->uniqueID;

        $this->profiler?->stop( $component );
        return $viewComponent;
    }

    // :: retrieve

    /**
     * Begin the Build proccess of a component.
     *
     * @param class-string|ComponentProperties|string $component
     *
     * @return Component
     */
    final public function getComponent( string|ComponentProperties $component ) : Component
    {
        $serviceID = $this->getComponentServiceID( (string) $component );

        if ( ! $serviceID ) {
            $message = "The component '{$serviceID}'  does not exist.";
            throw new InvalidArgumentException( $message );
        }

        if ( ! $this->locator->has( $serviceID ) ) {
            throw new ComponentNotFoundException( $serviceID, 'Not found in the Component Container.' );
        }

        $viewComponent = $this->locator->get( $serviceID );

        // \assert( $viewComponent instanceof Component );

        return clone $viewComponent
            ->setDependencies(
                $this->engine,
                AUTO,
                $this->profiler,
                $this->logger,
            );
    }

    final public function getComponentProperties( string $component ) : ComponentProperties
    {
        $component = $this->getComponentServiceID( $component );

        if ( ! $component || ! $this->components->has( $component ) ) {
            $message = "The component '{$component}' not found in the Component Container.";
            throw new ComponentNotFoundException( $component, $message );
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
            return $this->components->get( $from )->name;
        }

        if ( \class_exists( $from ) && \is_subclass_of( $from, Component::class ) ) {
            return $from::viewComponentAttribute()->name;
        }

        $serviceID = $this->tags[ComponentProperties::tag( $from )] ?? null;

        return $serviceID ? $this->getComponentName( $serviceID ) : null;
    }

    /**
     * @param string $from class, name, or tag
     *
     * @return string
     */
    final public function getComponentServiceID( string $from ) : string
    {
        if (
            \str_contains( $from, '\\' )
            && \class_exists( $from )
            && \is_subclass_of( $from, Component::class )
        ) {
            return $from::viewComponentAttribute()->serviceID;
        }

        $from = str_start( $from, ViewComponent::PREFIX );

        // If the provided $value matches an array name, return it
        if ( $this->components->has( $from ) ) {
            return $from;
        }

        $tag = ComponentProperties::tag( $from );

        return $this->tags[$tag] ?? throw new InvalidArgumentException(
            "Unable to resolve Component serviceID from '{$from}'.",
        );
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

    /**
     * @return array<string, string>
     */
    final public function getTags() : array
    {
        return $this->tags;
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

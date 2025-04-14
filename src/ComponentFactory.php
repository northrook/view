<?php

declare(strict_types=1);

namespace Core\View;

use Core\View\ComponentFactory\{
    ViewComponent,
    ComponentBag,
    Properties,
};
use Core\View\Template\{
    Component,
    Engine,
};
use Core\Profiler\{
    Interface\Profilable,
    StopwatchProfiler,
};
use Symfony\Component\DependencyInjection\ServiceLocator;
use Core\Interface\LazyService;
use Psr\Log\{
    LoggerAwareInterface,
    LoggerAwareTrait,
};
use Core\View\Exception\ComponentNotFoundException;
use Symfony\Component\Stopwatch\Stopwatch;
use InvalidArgumentException;
use function Support\str_start;

class ComponentFactory implements LazyService, Profilable, LoggerAwareInterface
{
    use StopwatchProfiler, LoggerAwareTrait;

    public const string PROPERTY = 'view';

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

    final public function setProfiler(
        ?Stopwatch $stopwatch,
        ?string    $category = 'View',
    ) : void {
        $this->assignProfiler( $stopwatch, $category );
    }

    /**
     * @param string               $component
     * @param array<string, mixed> $properties
     * @param array<string, mixed> $attributes
     * @param array                $actions
     * @param array<string, mixed> $content
     *
     * @return Component
     */
    final public function render(
        string  $component,
        array   $properties = [],
        array   $attributes = [],
        array   $actions = [],
        ?string $content = null,
    ) : Component {
        $profiler = $this->profiler?->event( $component );

        $component = $this->getComponent( $component );
        $component
            ->setDependencies(
                $this->engine,
                $this->profiler,
                $this->logger,
            )
            ->create(
                $properties,
                $attributes,
                $actions,
                $content,
            );

        $this->instantiated[$component->name][] = $component->uniqueID;

        $profiler?->stop();
        return $component;
    }

    /**
     * Begin the Build proccess of a component.
     *
     * @param class-string|string $component
     *
     * @return Component
     */
    final public function getComponent( string $component ) : Component
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

        return clone $viewComponent;
    }

    final public function getComponentProperties( string $component ) : Properties
    {
        $component = $this->getComponentServiceID( $component );

        if ( ! $component || ! $this->components->has( $component ) ) {
            $message = "The component '{$component}' not found in the Component Container.";
            throw new ComponentNotFoundException( $component, $message );
        }

        return $this->components->get( $component );
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
            return $from::getViewComponentAttribute()->serviceID;
        }

        $from = str_start( $from, ViewComponent::PREFIX );

        // If the provided $value matches an array name, return it
        if ( $this->components->has( $from ) ) {
            return $from;
        }

        $tag = Properties::tag( $from );

        return $this->tags[$tag] ?? throw new InvalidArgumentException(
            "Unable to resolve Component serviceID from '{$from}'.",
        );
    }

    /**
     * @return array<string, string>
     */
    final public function getTags() : array
    {
        return $this->tags;
    }

    /**
     * @return array<string, array<int, string>>
     */
    final public function getInstantiated() : array
    {
        return $this->instantiated;
    }

    /**
     * @return Properties[]
     */
    final public function getRegisteredComponents() : array
    {
        return $this->components->all();
    }

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

<?php

declare(strict_types=1);

namespace Core\View\Compiler;

use Core\View\ComponentFactory;
use Core\View\ComponentFactory\{ComponentBag, ViewComponent};
use Core\Symfony\Console\ListReport;
use Core\Symfony\DependencyInjection\CompilerPass;
use Symfony\Component\DependencyInjection\{
    ContainerBuilder,
    Definition,
    Reference,
    Loader\Configurator\ReferenceConfigurator,
};
use Core\View\Template\Engine;
use Psr\Log\LoggerInterface;
use Support\PhpStormMeta;
use ReflectionClass, LogicException;

final class RegisterViewComponentsPass extends CompilerPass
{
    private readonly string $locatorID;

    private readonly string $factoryID;

    private readonly string $engineID;

    protected readonly Definition $locatorDefinition;

    protected readonly Definition $factoryDefinition;

    protected readonly Definition $engineDefinition;

    /**
     * @param null|ReferenceConfigurator $engine    {@see Engine}
     * @param null|ReferenceConfigurator $stopwatch {@see Stopwatch}
     * @param null|ReferenceConfigurator $logger    {@see LoggerInterface}
     */
    public function __construct(
        protected ?ReferenceConfigurator $engine = null,
        protected ?ReferenceConfigurator $stopwatch = null,
        protected ?ReferenceConfigurator $logger = null,
    ) {
        $this->engine?->nullOnInvalid();
        $this->stopwatch?->nullOnInvalid();
        $this->logger?->nullOnInvalid();
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return void
     */
    public function compile( ContainerBuilder $container ) : void
    {
        $this->locatorID = ViewComponent::LOCATOR_ID;
        $this->factoryID = ComponentFactory::class;
        $this->engineID  = 'core.view.engine';

        if ( $this->validateRequiredServices( $container ) ) {
            return;
        }

        $this->registerTaggedComponents();
    }

    /**
     * @return string[]
     */
    private function taggedViewComponents() : array
    {
        return \array_keys( $this->container->findTaggedServiceIds( $this->locatorID ) );
    }

    private function definitionViewComponentAttribute( Definition $definition ) : ?ViewComponent
    {
        $className = $definition->getClass() ?: 'invalid';

        if ( ! \class_exists( $className ) ) {
            $message = $this::class." class '{$className}' does not exist.";
            $this->console->error( $message );
            return null;
        }

        $reflectionClass = new ReflectionClass( $className );

        $viewComponentAttributes = $reflectionClass->getAttributes( ViewComponent::class );

        /** @var ViewComponent $viewComponent */
        $viewComponent = $viewComponentAttributes[0]->newInstance();
        $viewComponent->setClassName( $className );

        return $viewComponent;
    }

    protected function registerTaggedComponents() : void
    {
        $registeredServices = new ListReport( __METHOD__ );

        $serviceLocatorArguments = [];
        $componentProperties     = [];
        $componentTags           = [];
        $componentDirectories    = [];

        foreach ( $this->taggedViewComponents() as $serviceId ) {
            //
            $registeredServices->item( $serviceId );
            if ( ! $this->container->hasDefinition( $serviceId ) ) {
                $message = $this::class." missing required '{$serviceId}' definition.";

                $registeredServices->remove( $message );

                continue;
            }

            $serviceDefinition = $this->container->getDefinition( $serviceId );

            $viewComponent = $this->definitionViewComponentAttribute( $serviceDefinition )
                             ?? throw new LogicException();

            $serviceDefinition->addMethodCall(
                'setDependencies',
                [
                    new Reference( (string) $this->engine ),
                    new Reference( (string) $this->stopwatch ),
                    new Reference( (string) $this->logger ),
                ],
            );

            $properties = $viewComponent->getProperties();

            $componentTags = \array_merge( $componentTags, $properties['tags'] );
            if ( $componentDirectory = ( $properties['directory'] ?? false ) ) {
                $componentDirectories[$componentDirectory] ??= $componentDirectory;
            }

            $componentProperties[$serviceId]     = $properties;
            $serviceLocatorArguments[$serviceId] = new Reference( $serviceId );
        }

        $this->locatorDefinition->setArguments( [$serviceLocatorArguments] );

        $componentBag = new Definition( ComponentBag::class );
        $componentBag->setArguments( [$componentProperties] );

        $this->factoryDefinition->replaceArgument( '$components', $componentBag );
        $this->factoryDefinition->replaceArgument( '$tags', $componentTags );

        foreach ( $componentDirectories as $directory ) {
            dump( $directory );
            $this->engineDefinition->addMethodCall( 'addTemplateDirectory', [$directory] );
        }

        $meta = new PhpStormMeta( $this->projectDirectory );

        $meta->registerArgumentsSet(
            'view_component_keys',
            ...\array_keys( $componentProperties ),
        );

        $generateReferences = \array_merge(
            [
                [ComponentBag::class, 'has'],
                [ComponentBag::class, 'get'],
                [ComponentFactory::class, 'render'],
                [ComponentFactory::class, 'has'],
            ],
        );

        foreach ( $generateReferences as $generateReference ) {
            $meta->expectedArguments( $generateReference, [0 => 'view_component_keys'] );
        }

        $meta->save( 'view_components' );

        $registeredServices->output();
    }

    private function validateRequiredServices( ContainerBuilder $container ) : bool
    {
        if ( ! $container->hasDefinition( $this->locatorID ) ) {
            $message = $this::class." cannot find required '{$this->locatorID}' definition.";
            $this->console->error( $message );
            return true;
        }

        $this->locatorDefinition = $container->getDefinition( $this->locatorID );

        if ( ! $container->hasDefinition( $this->factoryID ) ) {
            $message = $this::class." cannot find required '{$this->factoryID}' definition.";
            $this->console->error( $message );
            return true;
        }

        $this->factoryDefinition = $container->getDefinition( $this->factoryID );

        if ( ! $container->hasDefinition( $this->engineID ) ) {
            $message = $this::class." cannot find required '{$this->engineID}' definition.";
            $this->console->error( $message );
            return true;
        }

        $this->engineDefinition = $container->getDefinition( $this->engineID );

        return false;
    }
}

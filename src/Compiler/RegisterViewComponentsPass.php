<?php

declare(strict_types=1);

namespace Core\View\Compiler;

use Symfony\Component\DependencyInjection\{
    ContainerBuilder,
    Definition,
    Reference,
};
use Core\Symfony\Console\ListReport;
use Core\Symfony\DependencyInjection\CompilerPass;
use Core\View\Attribute\ViewComponent;
use Core\View\ComponentFactory;
use Core\View\ComponentFactory\ComponentBag;
use ReflectionClass, LogicException;
use Support\PhpStormMeta;

final class RegisterViewComponentsPass extends CompilerPass
{
    private readonly string $locatorID;

    private readonly string $factoryID;

    protected readonly Definition $locatorDefinition;

    protected readonly Definition $factoryDefinition;

    /**
     * @param ContainerBuilder $container
     *
     * @return void
     */
    public function compile( ContainerBuilder $container ) : void
    {
        $this->locatorID = ViewComponent::LOCATOR_ID;
        $this->factoryID = ComponentFactory::class;

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

        foreach ( $this->taggedViewComponents() as $serviceId ) {
            //
            $registeredServices->item( $serviceId );
            if ( ! $this->container->hasDefinition( $serviceId ) ) {
                $message = $this::class." missing required '{$serviceId}' definition.";

                $registeredServices->remove( $message );

                continue;
            }

            $viewComponent = $this->definitionViewComponentAttribute(
                $this->container->getDefinition( $serviceId ),
            ) ?? throw new LogicException();

            $properties = $viewComponent->getProperties();

            $componentTags = \array_merge( $componentTags, $properties['tags'] );

            $componentProperties[$serviceId]     = $viewComponent->getProperties();
            $serviceLocatorArguments[$serviceId] = new Reference( $serviceId );
        }

        $this->locatorDefinition->setArguments( [$serviceLocatorArguments] );

        $componentBag = new Definition( ComponentBag::class );
        $componentBag->setArguments( [$componentProperties] );

        $this->factoryDefinition->replaceArgument( '$components', $componentBag );
        $this->factoryDefinition->replaceArgument( '$tags', $componentTags );

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

        return false;
    }
}

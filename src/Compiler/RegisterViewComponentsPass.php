<?php

declare(strict_types=1);

namespace Core\View\Compiler;

use Symfony\Component\DependencyInjection\{ContainerBuilder, Definition, Reference};
use Core\Symfony\DependencyInjection\CompilerPass;
use Core\View\Attribute\ViewComponent;
use Core\View\ComponentFactory;
use ReflectionClass;

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
        $className = $definition->getClass();

        if ( ! \class_exists( $className ) ) {
            $message = $this::class." class '{$className}' does not exist.";
            $this->console->error( $message );
            return null;
        }

        $reflectionClass = new ReflectionClass( $className );

        $viewComponentAttributes = $reflectionClass->getAttributes( ViewComponent::class );

        return $viewComponentAttributes[0]?->newInstance() ?? null;
    }

    protected function registerTaggedComponents() : void
    {
        $serviceLocatorArguments = [];

        foreach ( $this->taggedViewComponents() as $serviceId ) {
            //
            if ( ! $this->container->hasDefinition( $serviceId ) ) {
                $message = $this::class." missing required '{$serviceId}' definition.";
                $this->console->error( $message );

                continue;
            }

            $viewComponent = $this->definitionViewComponentAttribute(
                $this->container->getDefinition( $serviceId ),
            );

            dump(
                $viewComponent,
            );

            $serviceLocatorArguments[$serviceId] = new Reference( $serviceId );
        }

        $this->locatorDefinition->setArguments( [$serviceLocatorArguments] );
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

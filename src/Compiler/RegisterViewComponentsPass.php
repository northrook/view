<?php

namespace Core\View\Compiler;

use Core\Symfony\DependencyInjection\CompilerPass;
use Core\View\Attribute\ViewComponent;
use Support\Reflect;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RegisterViewComponentsPass extends CompilerPass
{
    /**
     * Find classes annotated with {@see ViewComponent} and implementing the {@see ViewComponentInterface}.
     *
     * Register them as Components:
     * - private
     * - register with 'view_components.service_locator'
     *
     * @param ContainerBuilder $container
     *
     * @return void
     */
    public function compile( ContainerBuilder $container ) : void
    {
        foreach ( $this->getDeclaredClasses() as $classId ) {
            $this->console->info( $classId );
        }
        // Reflect::getAttribute( $this->component->reflect(), ViewComponent::class );
    }
}

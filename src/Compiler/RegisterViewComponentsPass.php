<?php

namespace Core\View\Compiler;

use Core\Symfony\DependencyInjection\CompilerPass;
use Core\View\Attribute\ViewComponent;
use Support\{FileInfo};
use Symfony\Component\DependencyInjection\ContainerBuilder;
use InvalidArgumentException;

final class RegisterViewComponentsPass extends CompilerPass
{
    /** @var FileInfo[] */
    private array $directories;

    /**
     * @param string[] $scan
     */
    public function __construct( string ...$scan )
    {
        foreach ( $scan as $directory ) {
            $fileInfo = new FileInfo( $directory );
            if ( $fileInfo->isDir() && $fileInfo->isReadable() ) {
                $this->directories[] = $fileInfo;
            }
            else {
                throw new InvalidArgumentException();
            }
        }
    }

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
    public function compile( ContainerBuilder $container ) : void {}
}

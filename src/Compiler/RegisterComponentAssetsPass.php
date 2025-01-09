<?php

namespace Core\View\Compiler;

use Core\Assets\AssetManifest;
use Core\Symfony\DependencyInjection\CompilerPass;
use Core\View\Attribute\ViewComponent;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RegisterComponentAssetsPass extends CompilerPass
{
    private readonly string $locatorID;

    private readonly string $manifestID;

    private readonly AssetManifest $assetManifest;

    public function compile( ContainerBuilder $container ) : void
    {
        $this->manifestID = AssetManifest::class;
        $this->locatorID  = ViewComponent::LOCATOR_ID;

        if ( $this->validateRequiredServices( $container ) ) {
            return;
        }

        $this->registerComponentAssets();
    }

    protected function registerComponentAssets() : void
    {
        dump( $this->container->findTaggedServiceIds( $this->locatorID ) );
    }

    private function validateRequiredServices( ContainerBuilder $container ) : bool
    {
        if ( ! $container->hasDefinition( $this->manifestID ) ) {
            $this->console->error(
                $this::class." cannot find required '{$this->manifestID}' definition.",
            );
            return true;
        }

        $manifestArguments = $container->getDefinition( $this->manifestID )->getArguments();

        if ( ! empty( $manifestArguments ) ) {
            $message = [
                $this::class." cannot find required '{$this->manifestID}' arguments.",
                "\nEnsure this CompilerPass is TYPE_OPTIMIZE or later.",
            ];
            $this->console->error( $message );
            return true;
        }

        $this->assetManifest = new AssetManifest( ...$manifestArguments );

        return false;
    }
}

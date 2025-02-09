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

    protected readonly AssetManifest $assetManifest;

    public function compile( ContainerBuilder $container ) : void
    {
        $this->manifestID = AssetManifest::class;
        $this->locatorID  = ViewComponent::LOCATOR_ID;

        if ( $this->validateRequiredServices( $container ) ) {
            return;
        }
        // $this->registerComponentAssets();
    }

    protected function registerComponentAssets() : void
    {
        foreach ( \array_keys( $this->container->findTaggedServiceIds( $this->locatorID ) ) as $serviceId ) {
            $componentDefinition = $this->container->getDefinition( $serviceId );
        }
    }

    // protected function registerComponentAssets() : void
    // {
    //     foreach ( \array_keys( $this->container->findTaggedServiceIds( $this->locatorID ) ) as $serviceId ) {
    //         $componentDefinition = $this->container->getDefinition( $serviceId );
    //
    //         $classInfo = new ClassInfo( $componentDefinition->getClass() );
    //         $fileInfo  = $classInfo->fileInfo;
    //         $dirInfo   = new FileInfo( $fileInfo->getPath() );
    //
    //         $componentAssetFiles = $dirInfo->glob( [ '/*.css', '/*.js' ], asFileInfo : true );
    //
    //         foreach ( $componentAssetFiles as $assetFile ) {
    //             // $assetReference = $this->assetManifest->getReference( $assetFile );
    //
    //             // $type = Type::from( $assetFile->getExtension() );
    //             // $path = $assetFile->getPathname();
    //
    //             dump(
    //                     AssetReference::generateName( $assetFile ),
    //                     AssetReference::generateName( 'C:\laragon\www\symfony-framework\assets\styles\core\color.intent.css' ),
    //             // $type,
    //             // $path,
    //             // $assetReference,
    //             );
    //         }
    //     }
    // }

    private function validateRequiredServices( ContainerBuilder $container ) : bool
    {
        if ( ! $container->hasDefinition( $this->manifestID ) ) {
            $this->console->error(
                $this::class." cannot find required '{$this->manifestID}' definition.",
            );
            return true;
        }

        $manifestArguments = $container->getDefinition( $this->manifestID )->getArguments();

        if ( empty( $manifestArguments ) ) {
            $message = [
                $this::class." cannot find required '{$this->manifestID}' arguments.",
                "\nEnsure this CompilerPass is TYPE_OPTIMIZE or later.",
            ];
            $this->console->error( $message );
            return true;
        }

        $assetManifestPath = $manifestArguments[0] ?? null;

        \assert( \is_string( $assetManifestPath ) );

        $this->assetManifest = new AssetManifest( $assetManifestPath );

        return false;
    }
}

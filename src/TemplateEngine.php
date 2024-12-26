<?php

declare(strict_types=1);

namespace Core\View;

use BadMethodCallException;
use Core\PathfinderInterface;
use Core\View\Exception\TemplateCompilerException;
use Core\View\Latte\PreformatterExtension;
use Core\View\Template\Engine\Configuration;
use Latte\{Engine, Loader, Loaders\FileLoader};
use Support\{FileInfo};
use Core\View\Interface\{TemplateEngineInterface};
use Psr\Log\LoggerInterface;
use RuntimeException;
use function String\hashKey;

/**
 * Provides the Template Manager Service to the Framework.
 *
 * Get Asset:
 * - HtmlData `get`
 * - FactoryModel `getModel`
 * - ManifestReference `getReference`
 *
 * Public access:
 * - Locator
 * - Factory
 */
abstract class TemplateEngine implements TemplateEngineInterface
{
    protected readonly Configuration $configuration;

    /** @var array<string, Engine> */
    private array $engine = [];

    /** @var array<string, mixed> */
    private array $globalParameters = [];

    /**
     * @param PathfinderInterface  $pathfinder
     * @param array<int, mixed>    $configuration
     * @param null|LoggerInterface $logger
     * @param array<string, mixed> $globalParameters `$var: $value`
     * @param \Latte\Extension[]   $engineExtensions
     */
    public function __construct(
        protected readonly PathfinderInterface $pathfinder,
        array                                  $configuration,
        protected readonly ?LoggerInterface    $logger = null,
        array                                  $globalParameters = [],
        private readonly array                 $engineExtensions = [],
    ) {
        $this->configuration = new Configuration( ...$configuration );

        foreach ( $globalParameters as $key => $value ) {
            $this->addGlobalParameter( $key, $value );
        }
    }

    public function render(
        string       $view,
        object|array $parameters = [],
        bool         $cache = true,
        ?Loader      $loader = null,
    ) : string {
        $engine = $this->getEngine( $loader );

        if ( ! $cache ) {
            // Temporarily clear the assigned cache directory
            $engine->setTempDirectory( null );
        }

        $render = $engine->renderToString(
            $this->resolveTemplate( $view ),
            $this->injectParameters( $parameters ),
        );

        if ( ! $cache ) {
            // Reassign the cache directory
            $engine->setTempDirectory( $this->cacheDirectory() );
        }

        return $render;
    }

    public function clearTemplateCache() : bool
    {
        return $this->cacheDirectory( true );
    }

    public function pruneTemplateCache() : array
    {
        throw new BadMethodCallException( __METHOD__.' not implemented yet.' );
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    final public function addGlobalParameter( string $key, mixed $value ) : void
    {
        $this->globalParameters[$key] = $value;
    }

    // .. Engine

    final public function getEngine(
        ?Loader $loader = null,
        bool    $preformatter = true,
        bool    $elements = true,
        bool    $components = true,
    ) : Engine {
        $loader ??= new FileLoader();
        $cacheKey = hashKey( [$loader::class, $preformatter, $elements, $components], 'implode' );

        if ( isset( $this->engine[$cacheKey] ) ) {
            $this->logger?->debug(
                'Engine {cacheKey} using {loader} returned from cache.',
                ['cacheKey' => $cacheKey, 'loader' => $loader::class],
            );
        }

        // Otherwise start and cache the main Engine
        return $this->engine[$cacheKey] ??= $this->startEngine( $loader, $preformatter, $elements, $components );
    }

    final protected function startEngine(
        Loader $loader,
        bool   $preformatter = true,
        bool   $elements = true,
        bool   $components = true,
    ) : Engine {
        // Initialize the Engine.
        $engine = new Engine();

        if ( $preformatter ) {
            $engine->addExtension( new PreformatterExtension( $this->logger ) );
        }

        // Add all registered extensions to the Engine.
        \array_map( [$engine, 'addExtension'], $this->engineExtensions );

        $engine
            ->setTempDirectory( $this->cacheDirectory() )
            ->setAutoRefresh( $this->configuration->debug )
            ->setLoader( $loader )
            ->setLocale( $this->configuration->locale );

        $this->logger?->info(
            'Started Latte Engine {id} using '.\strchr( $loader::class, '\\' ),
            [
                'id'     => \spl_object_id( $engine ),
                'engine' => $engine,
            ],
        );

        return $engine;
    }

    // :::: Internal

    /**
     * @param bool $purgeCacheDirectory
     *
     * @return ($purgeCacheDirectory is true ? bool : string)
     */
    final protected function cacheDirectory( bool $purgeCacheDirectory = false ) : bool|string
    {
        $cacheDirectory = $this->pathfinder->getFileInfo(
            path      : $this->configuration->cacheDirectory,
            assertive : true,
        );

        if ( $purgeCacheDirectory ) {
            return $cacheDirectory->remove();
        }

        if ( ! $cacheDirectory->exists() ) {
            $cacheDirectory->mkdir();
        }

        return $cacheDirectory->getRealPath()
                ?: throw new RuntimeException( 'Cache directory does not exist.' );
    }

    final protected function resolveTemplate( string $view ) : string
    {
        // Return string views
        if ( ! \str_ends_with( $view, '.latte' ) ) {
            return $view;
        }

        // Return full valid paths early
        if ( \is_readable( $view ) && \is_file( $view ) ) {
            dump( $view, \is_file( $view ) );
            return $view;
        }

        if ( \str_starts_with( $view, '@' ) ) {
            if ( ! \str_contains( $view, '/' ) ) {
                $message = 'Namespaced view calls must use the forward slash separator.';
                throw new TemplateCompilerException( $message );
            }

            [$namespace, $view] = \explode( '/', $view, 2 );

            $directory = $this->templateDirectories[$namespace] ?? null;

            if ( ! $directory ) {
                $message = '';
                throw new TemplateCompilerException( $message );
            }

            $fileInfo = new FileInfo( "{$directory}/{$view}" );

            return $fileInfo->getPathname();
        }

        foreach ( $this->configuration->templateDirectories as $directoryKey ) {
            $fileInfo = $this->pathfinder->getFileInfo( "{$directoryKey}/{$view}" );

            if ( $fileInfo && $fileInfo->isReadable() ) {
                return $fileInfo->getPathname();
            }
        }
        throw new TemplateCompilerException( 'Unable to load view: '.$view );
    }

    /**
     * Adds {@see Latte::$globalVariables} to all templates.
     *
     * - {@see $globalVariables} are not available when using Latte `templateType` objects.
     *
     * @param array<array-key,mixed>|object $parameters
     *
     * @return array<array-key,mixed>|object
     */
    final protected function injectParameters( object|array $parameters ) : object|array
    {
        if ( \is_object( $parameters ) ) {
            return $parameters;
        }

        return $this->globalParameters + $parameters;
    }
}

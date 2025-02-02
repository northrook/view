<?php

declare(strict_types=1);

namespace Core\View;

use Core\Interface\PathfinderInterface;
use Core\View\Exception\TemplateCompilerException;
use Core\View\Latte\{PreformatterExtension, StyleSystemExtension};
use Latte\{Engine, Loader, Loaders\FileLoader};
use Support\FileInfo;
use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Core\View\Interface\TemplateEngineInterface;
use Psr\Log\LoggerInterface;
use RuntimeException, BadMethodCallException;
use function String\hashKey;

#[Lazy]
class TemplateEngine implements TemplateEngineInterface
{
    public readonly Parameters $parameters;

    /** @var array<string, Engine> */
    private array $engine = [];

    /**
     * @param string               $cacheDirectory
     * @param Parameters           $parameters
     * @param PathfinderInterface  $pathfinder
     * @param null|LoggerInterface $logger
     * @param array                $templateDirectories
     * @param \Latte\Extension[]   $engineExtensions
     * @param string               $locale
     * @param bool                 $debug
     */
    public function __construct(
        public string                          $cacheDirectory,
        ?Parameters                            $parameters,
        protected readonly PathfinderInterface $pathfinder,
        protected readonly ?LoggerInterface    $logger = null,
        protected readonly array               $templateDirectories = [],
        protected readonly array               $engineExtensions = [],
        protected string                       $locale = 'en',
        public bool                            $debug = true,
    ) {
        $this->parameters = $parameters ?? new Parameters();
    }

    final public function render(
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

    final public function clearTemplateCache() : bool
    {
        return $this->cacheDirectory( true );
    }

    final public function pruneTemplateCache() : array
    {
        throw new BadMethodCallException( __METHOD__.' not implemented yet.' );
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

        $engine->addExtension( new StyleSystemExtension( $this->logger ) );

        $engine
            ->setTempDirectory( $this->cacheDirectory() )
            ->setAutoRefresh( $this->debug )
            ->setLoader( $loader )
            ->setLocale( $this->locale );

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
        $cacheDirectory = $this->pathfinder->getFileInfo( $this->cacheDirectory );

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
            // dump( $view, \is_file( $view ) );
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

        foreach ( $this->templateDirectories as $directoryKey ) {
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

        foreach ( $parameters as $key => $value ) {
            if ( $this->parameters->has( $key ) ) {
                $this->logger?->warning(
                    'Parameter {key} exists in {parameters}.',
                    ['key' => $key, 'parameters' => $this->parameters],
                );
            }
            $this->parameters->set( $key, $value );
        }

        return $this->parameters->getParameters();
    }
}

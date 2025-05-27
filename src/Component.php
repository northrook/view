<?php

declare(strict_types=1);

namespace Core\View;

use Cache\CacheHandler;
use Core\Autowire\{Logger, Profiler, SettingsProvider};
use Core\Interface\{Loggable};
use Core\View\Component\{Arguments, Properties};
use Psr\Cache\CacheItemPoolInterface;
use Core\View\ComponentFactory\{ViewComponent};
use Core\View\Element\Attributes;
use Core\View\Exception\{ViewException};
use Core\View\Template\{Compiler\Nodes\Html\ElementNode, Engine};
use Stringable;
use BadMethodCallException;
use InvalidArgumentException;
use Exception;
use RuntimeException;
use function Support\slug;

/**
 * Base class for a view component.
 *
 * @require-method self __invoke()
 */
abstract class Component implements Stringable, Loggable
{
    use Logger, Profiler, SettingsProvider;

    /** @var ?string Manually define a name for this component */
    protected const ?string NAME = null;

    public const string TEMPLATE_DIRECTORY = 'templates/component';

    private ?Engine $engine = null;

    protected readonly ?CacheHandler $cache;

    public readonly string $name;

    public readonly string $uniqueId;

    public readonly Attributes $attributes;

    final public static function getNodeArguments(
        ElementNode $from,
        Properties  $componentProperties,
    ) : Arguments {
        return new Arguments( $from, $componentProperties );
    }

    public static function prepareArguments( Arguments $arguments ) : void {}

    /**
     * @param null|Engine                 $engine
     * @param null|CacheItemPoolInterface $cache
     *
     * @return $this
     */
    final public function setDependencies(
        ?Engine                 $engine,
        ?CacheItemPoolInterface $cache = null,
    ) : self {
        $this->engine ??= $engine;
        $this->cache = new CacheHandler(
            adapter     : $cache,
            expiration  : $this->getSetting( 'cache.expiration', 14_400 ),
            deferCommit : $this->getSetting( 'cache.defer', true ),
        );

        return $this;
    }

    /**
     * @param array<string, mixed> $arguments
     * @param null|string          $uniqueId
     *
     * @return $this
     */
    final public function create(
        array   $arguments = [],
        ?string $uniqueId = null,
    ) : self {
        $this->initializeComponent( $uniqueId ?? \get_defined_vars() );
        $this->profiler->start( "{$this->name}.{$this->uniqueId}" );

        if ( isset( $arguments['__attributes'] ) ) {
            \assert( \is_array( $arguments['__attributes'] ) );
            $this->attributes = new Attributes( ...$arguments['__attributes'] );
            unset( $arguments['__attributes'] );
        }
        else {
            $this->attributes = new Attributes();
        }

        $this->attributes->set( 'component-id', $this->uniqueId );

        foreach ( $arguments as $key => $value ) {
            // Autoset `__properties`
            if ( $key[0] === '_' && \property_exists( $this, \substr( $key, $key[1] === '_' ? 2 : 1 ) ) ) {
                $this->{$key} = $value;
                unset( $arguments[$key] );
            }
        }

        \assert(
            \method_exists( $this, '__invoke' ),
            "Required method '".$this::class."::__invoke()' does not exist.",
        );

        return $this->__invoke( ...$arguments );
    }

    final public function __toString() : string
    {
        $engine   = $this->getEngine();
        $template = $this->getTemplatePath();

        if ( $template === false ) {
            $string = $this->getString();
        }
        else {
            $string = $engine->loader->templateExists( $template )
                    ? $engine->renderToString(
                        name       : $template,
                        parameters : $this,
                    )
                    : $this->getString();
        }

        if ( ! $string ) {
            throw new ViewException( $template ?: $this::class );
        }

        $this->profiler->stop( "{$this->name}.{$this->uniqueId}" );
        return \trim( $string );
    }

    protected function getString() : false|string
    {
        return false;
    }

    protected function getTemplatePath() : false|string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>|object
     */
    protected function getTemplateParameters() : array|object
    {
        return $this;
    }

    final public function getEngine() : Engine
    {
        if ( $this->engine === null ) {
            $this->logger?->notice( 'Returning internal fallback Engine.' );
        }

        return $this->engine ??= new Engine( cache : false );
    }

    final public static function getComponentName() : string
    {
        $name = static::NAME ?? ViewComponent::from( static::class )->name;

        if ( ! $name ) {
            throw new BadMethodCallException( static::class.' name is not defined.' );
        }

        if ( ! \ctype_alnum( \str_replace( ':', '', $name ) ) ) {
            $message = static::class." name '{$name}' must be lower-case alphanumeric.";

            if ( \is_numeric( $name[0] ) ) {
                $message = static::class." name '{$name}' cannot start with a number.";
            }

            if ( \str_starts_with( $name, ':' ) || \str_ends_with( $name, ':' ) ) {
                $message = static::class." name '{$name}' must not start or end with a separator.";
            }

            throw new InvalidArgumentException( $message );
        }

        return $name;
    }

    /**
     * @param array<array-key,mixed>|string $uniqueId
     *
     * @return self
     */
    private function initializeComponent( string|array $uniqueId ) : self
    {
        if ( $this->engine === null ) {
            $this->logger?->warning(
                '{component} initialized before setDependencies has been called.',
                ['component' => $this::class],
            );
        }

        $this->name = $this->getComponentName();

        if ( \is_array( $uniqueId ) ) {
            try {
                $uniqueId = \serialize( [$this::class => \spl_object_id( $this ), ...$uniqueId] );
            }
            catch ( Exception $e ) {
                throw new RuntimeException( $e->getMessage() );
            }
        }

        // Set a predefined hash
        if ( \strlen( $uniqueId ) === 8 ) {
            \assert(
                \ctype_alnum( $uniqueId ) && \strtolower( $uniqueId ) === $uniqueId,
                "Invalid component unique ID '{$uniqueId}'. Expected 8 characters of alphanumeric, lowercase.",
            );
            $this->uniqueId = $uniqueId;
        }
        else {
            $this->uniqueId = \hash( 'xxh32', $uniqueId );
        }

        return $this;
    }

    /**
     * @template Setting of null|array<array-key, scalar>|scalar
     *
     * @param string  $key
     * @param Setting $default
     *
     * @return Setting
     */
    final protected function getSetting(
        string $key,
        mixed  $default,
    ) : mixed {
        return isset( $this->settings )
                ? $this->settings->get( slug( "component.{$key}", '.' ), $default )
                : $default;
    }

    // /**
    //  * @param array<string, mixed> $actions
    //  *
    //  * @return self
    //  */
    // private function actionCalls( array $actions ) : self
    // {
    //     foreach ( $actions as $action ) {
    //         if ( \is_string( $action ) && \method_exists( $this, $action ) ) {
    //             $this->{$action}();
    //         }
    //         if ( \is_callable( $action ) ) {
    //             \call_user_func( $action, $this );
    //         }
    //     }
    //
    //     return $this;
    // }
}

<?php

namespace Core\View;

use Core\Profiler\ClerkProfiler;
use Core\View\Template\{Engine, ViewComponentExtension};
use Core\View\ComponentFactory\{Properties, ViewComponent};
use Core\View\Element\Attributes;
use Core\View\Template\Compiler\{NodeAttributes, NodeHelpers};
use Core\View\Template\Compiler\Nodes\Html\ElementNode;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Stringable;
use ReflectionClass;
use BadMethodCallException;
use LogicException;
use Symfony\Component\Stopwatch\Stopwatch;
use CompileError;
use TypeError;
use function Support\{match_property_type, normalize_path, slug, str_end};

/**
 * @method self __invoke()
 */
abstract class Component implements Stringable
{
    /** @var ?string Manually define a name for this component */
    protected const ?string NAME = null;

    protected const ?string TEMPLATE_DIRECTORY = null;

    private ?Engine $engine = null;

    protected ?string $templateFilename = null;

    private ?ClerkProfiler $clerkProfiler = null;

    protected ?LoggerInterface $logger = null;

    public readonly string $name;

    public readonly string $uniqueID;

    public readonly Attributes $attributes;

    final public function setDependencies(
        ?Engine                      $engine,
        null|Stopwatch|ClerkProfiler $profiler,
        ?LoggerInterface             $logger = null,
    ) : self {
        $this->engine        ??= $engine;
        $this->clerkProfiler ??= ClerkProfiler::from( $profiler, 'View' );
        $this->logger        ??= $logger;

        return $this;
    }

    final public function __toString() : string
    {
        $this->clerkProfiler?->stop( "{$this->name}.{$this->uniqueID}" );
        return $this->getString();
    }

    public function getString() : string
    {
        return $this->getTemplateString();
    }

    protected function onCreation( ?string &$content ) : void {}

    /**
     * @param array<string, mixed> $properties
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $actions
     * @param ?string              $content
     * @param null|string          $uniqueId
     *
     * @return $this
     */
    final public function create(
        array   $properties = [],
        array   $attributes = [],
        array   $actions = [],
        ?string $content = null,
        ?string $uniqueId = null,
    ) : self {
        // dump( get_defined_vars() );
        $this->name     = $this::getComponentName();
        $this->uniqueID = $this->componentUniqueID( $uniqueId ?? \serialize( \get_defined_vars() ) );
        $this->clerkProfiler?->event( "{$this->name}.{$this->uniqueID}" );

        $this->attributes = new Attributes( ...$attributes );
        $this->attributes->set( 'component-id', $this->uniqueID );

        foreach ( $properties as $property => $value ) {
            if ( \property_exists( $this, $property ) ) {
                if ( ! isset( $this->{$property} ) ) {
                    $this->{$property} = $value;
                }
                else {
                    $this->{$property} = match ( \gettype( $this->{$property} ) ) {
                        'boolean' => (bool) $value,
                        'integer' => (int) $value,
                        default   => $value,
                    };
                }

                continue;
            }

            \assert( \is_string( $value ), 'All remaining arguments should be method calls at this point.' );

            $method = 'do'.\ucfirst( $value );

            if ( \method_exists( $this, $method ) ) {
                $this->{$method}();
            }
            else {
                $this->logger?->error(
                    'The {component} was provided with undefined property {property}.',
                    ['component' => $this->name, 'property' => $property],
                );
            }
        }

        $this->onCreation( $content );

        foreach ( $actions as $action ) {
            if ( \is_string( $action ) && \method_exists( $this, $action ) ) {
                $this->{$action}();
            }
            if ( \is_callable( $action ) ) {
                \call_user_func( $action, $this );
            }
        }

        return $this;
    }

    final public static function getComponentName() : string
    {
        $name = self::NAME ?? self::getViewComponentAttribute()->name;

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

    final public static function getViewComponentAttribute() : ViewComponent
    {
        $viewComponentAttributes = ( new ReflectionClass( static::class ) )->getAttributes( ViewComponent::class );

        if ( empty( $viewComponentAttributes ) ) {
            $message = 'This Component is missing the required '.ViewComponent::class.' attribute.';
            throw new BadMethodCallException( $message );
        }

        $viewAttribute = $viewComponentAttributes[0]->newInstance();
        $viewAttribute->setClassName( static::class );

        return $viewAttribute;
    }

    /**
     * @param ?string $filename
     *
     * @return string
     */
    final protected function getTemplatePath( ?string $filename = null ) : string
    {
        $this->templateFilename ??= $this::getComponentName();

        $filename  = str_end( $this->templateFilename, '.latte' );
        $directory = self::TEMPLATE_DIRECTORY ?? ( new ReflectionClass( static::class ) )->getFileName();

        if ( ! $directory ) {
            throw new LogicException( 'Template directory is not defined for '.static::class.'.' );
        }

        $path = normalize_path( $directory );

        if ( \str_ends_with( $path, '.php' ) ) {
            $path = \strrchr( $path, DIR_SEP, true ) ?: $path;
        }

        if ( \file_exists( "{$path}/{$filename}" ) ) {
            return "{$path}/{$filename}";
        }

        $directory = ( \strrchr( $path, DIR_SEP.'src', true ) ?: $path ).DIR_SEP.'templates';
        $namespace = slug( \strrchr( $this::class, '\\', true ) ?: "component.{$this->name}", '.' );

        if ( \file_exists( "{$directory}/component/{$filename}" ) ) {
            $this->getEngine()->addTemplateDirectory( $directory, $namespace );
            return "component/{$filename}";
        }

        throw new LogicException(
            'Unable to resolve template directory for component '.static::class.'.',
        );
    }

    final protected function getEngine() : Engine
    {
        return $this->engine ??= new Engine( cache : false );
    }

    protected function getTemplate() : string
    {
        return $this->getTemplatePath();
    }

    protected function getTemplateString() : string
    {
        return \trim(
            $this->getEngine()->renderToString(
                $this->getTemplatePath(),
                $this->getParameters(),
                // TOOD: DEBUG
                preserveCacheKey  : true,
                suppressExtension : ViewComponentExtension::class,
            ),
        );
    }

    /**
     * @return array<string, mixed>|object
     */
    protected function getParameters() : array|object
    {
        return $this;
    }

    private function componentUniqueID( string $set ) : string
    {
        // Set a predefined hash
        if ( \strlen( $set ) === 8 ) {
            if ( \ctype_alnum( $set ) || \strtolower( $set ) === $set ) {
                return $set;
            }

            $this->logger?->error(
                'Invalid component unique ID {set}. Expected 8 characters of alphanumeric, lowercase.',
                ['set' => $set],
            );
        }

        return \hash( 'xxh32', $set );
    }

    /**
     * @param ElementNode $from
     * @param Properties  $componentProperties
     *
     * @return array<string,mixed>
     */
    public function getArguments(
        ElementNode $from,
        Properties  $componentProperties,
    ) : array {
        $arguments = [
            'properties' => [],
            'attributes' => ( new NodeAttributes( $from ) )->getArray(),
            'actions'    => [],
            'content'    => null,
        ];

        /** @var array<int, string> $tagged */
        $tagged = \explode( ':', $from->name );
        $tag    = $tagged[0] ?? null;

        $arguments['properties']['tag'] = $tag;

        foreach ( $componentProperties->tagged[$tag] ?? [] as $position => $property ) {
            $value = $tagged[$position] ?? null;
            $value = match ( true ) {
                \is_numeric( $value ) => (int) $value,
                default               => $value,
            };

            if ( ! \property_exists( $this, $property ) ) {
                throw new CompileError(
                    \sprintf(
                        'Property "%s" does not exist in %s.',
                        $property,
                        $this::class,
                    ),
                );
            }

            if ( ! match_property_type( $this, $property, from : $value ) ) {
                throw new TypeError(
                    \sprintf(
                        'Invalid property type: "%s" does not allow %s.',
                        $this::class."->{$property}",
                        \gettype( $value ),
                    ),
                );
            }

            $arguments['properties'][$property] = $value;
        }

        $content = [];

        foreach ( $from->content ?? [] as $contentNode ) {
            $content[] = NodeHelpers::toText( $contentNode );
            // dump( $contentNode );
        }

        $arguments['content'] = \implode( '', $content );

        $this->prepareArguments( ...$arguments );

        // echo '<xmp>';
        // print_r( $arguments );
        // echo '</xmp>';
        //
        // dd(
        //     $arguments,
        //     \array_values( \array_filter( $arguments ) ),
        // );
        return \array_filter( $arguments );
    }

    /**
     * @param array<string, null|scalar>            $properties
     * @param array<string, null|scalar>            $attributes
     * @param callable-string[]|callable[]|string[] $actions
     * @param ?string                               $content
     *
     * @return void
     */
    protected function prepareArguments(
        array &   $properties,
        array &   $attributes,
        array &   $actions,
        ?string & $content,
    ) : void {}
}

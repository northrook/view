<?php

declare(strict_types=1);

namespace Core\View\ComponentFactory;

use Attribute, Override;
use Core\Compiler\Autodiscover;
use Core\Exception\CompilerException;
use Core\View\Component;
use ReflectionException;
use LogicException;
use ReflectionClass;
use InvalidArgumentException;
use Throwable;
use ValueError;
use ReflectionAttribute;
use RuntimeException;
use function Support\normalize_path;
use const Support\INFER;

/**
 * {@see Component} annotated with {@see ViewComponent} will be autoconfigured as a `service`.
 *
 * @template T of object
 * @used-by ComponentFactory
 * @extends Autodiscover<Component>
 *
 * @author  Martin Nielsen
 */
#[Attribute( Attribute::TARGET_CLASS )]
final class ViewComponent extends Autodiscover
{
    public const string PREFIX = 'view.component.';

    public const string LOCATOR_ID = 'view.component_locator';

    /** @var array<string,ViewComponent<Component>> */
    private static array $instances = [];

    /** @var class-string<Component> */
    public readonly string $className;

    /** @var string[] */
    public readonly array $nodeTags;

    public readonly string $name;

    public readonly string $directory;

    /**
     * Configure how this {@see Component} is handled.
     *
     * ### `Tag`
     * Assign one or more HTML tags to trigger this component.
     *
     * Use the `:` separator to indicate a component subtype,
     * which will call a method of the same name.
     *
     * @param string|string[] $tag       [optional]
     * @param ?string         $name
     * @param ?string         $serviceId
     */
    public function __construct(
        string|array $tag = [],
        ?string      $name = null,
        ?string      $serviceId = INFER,
    ) {
        if ( $name ) {
            $this->name = \strtolower( \trim( $name, " \n\r\t\v\0." ) );
        }

        $tags = [];

        foreach ( \is_array( $tag ) ? $tag : [$tag] as $value ) {
            if ( ! \is_string( $value ) ) {
                throw new InvalidArgumentException( 'Invalid source: '.\gettype( $value ) );
            }

            $tags[] = \trim( $value, " \n\r\t\v\0." );
        }
        $this->nodeTags = $tags;

        parent::__construct(
            serviceId : $serviceId,
            tag       : [
                'view.component_locator',
                'monolog.logger' => ['channel' => 'view_component'],
            ],
            lazy      : false,
            public    : false,
            autowire  : true,
        );
    }

    /**
     * @return ViewComponent<T>[]
     */
    public static function getInstances() : array
    {
        return self::$instances;
    }

    /**
     * @param class-string<Component> $component
     *
     * @return self<T>
     */
    public static function from( string $component ) : self
    {
        if ( \array_key_exists( $component, self::$instances ) ) {
            return self::$instances[$component];
        }

        try {
            $attribute = ( new ReflectionClass( $component ) )
                ->getAttributes(
                    self::class,
                    ReflectionAttribute::IS_INSTANCEOF,
                );
        }
        catch ( ReflectionException $exception ) {
            throw new RuntimeException(
                message  : "'{$component}' is missing the required ".self::class.' attribute.',
                previous : $exception,
            );
        }

        /** @var static $self */
        $self = $attribute[0]->newInstance();

        $self->configure( $component );

        $self->getDirectory();

        return self::$instances[$component] ??= $self;
    }

    #[Override]
    protected function serviceID() : string
    {
        if ( ! isset( $this->name ) ) {
            if ( ! isset( $this->className ) ) {
                $message = "Could not generate ViewComponent->name: ViewComponent->className is not defined.\n";
                $message .= 'Call ViewComponent->setClassName( .. ) when registering the component.';
                throw new LogicException( $message );
            }

            $namespaced = \explode( '\\', $this->className );
            $className  = \strtolower( \end( $namespaced ) );

            if ( \str_ends_with( $className, 'component' ) ) {
                $className = \substr( $className, 0, -\strlen( 'component' ) );
            }
            $this->name = $className;
        }
        return \strtolower( $this::PREFIX.$this->name );
    }

    private function getDirectory() : string
    {
        if ( isset( $this->directory ) ) {
            return $this->directory;
        }

        try {
            $reflect   = ( new ReflectionClass( $this->className ) );
            $fileName  = $reflect->getFileName() ?: throw new ValueError();
            $classDir  = normalize_path( \pathinfo( $fileName, PATHINFO_DIRNAME ) );
            $directory = $reflect->getConstant( 'TEMPLATE_DIRECTORY' ) ?? '';

            if ( \str_ends_with( $classDir, 'src' ) ) {
                $classDir = \substr( $classDir, 0, -\strlen( 'src' ) );
            }

            \assert( \is_string( $directory ) );
            return $this->directory = normalize_path( [$classDir, $directory] );
        }
        catch ( Throwable $exception ) {
            throw new InvalidArgumentException(
                message  : "Could not derive directory path from '{$this->className}'.\n {$exception->getMessage()}.",
                previous : $exception,
            );
        }
    }

    /**
     * @return array{name: string, class: class-string<Component>, directory: string, tags: string[], tagged: array<string, array<int, null|string>>}
     */
    public function getProperties() : array
    {
        return [
            'name'      => $this->name,
            'class'     => $this->className,
            'directory' => $this->getDirectory(),
            'tags'      => $this->componentNodeTags(),
            'tagged'    => $this->taggedNodeTags(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function componentNodeTags() : array
    {
        $set = [];

        foreach ( $this->nodeTags as $tag ) {
            if ( ! $tag || \preg_match( '#[^a-z]#', $tag[0] ) ) {
                $reason = $tag ? null : 'Tags cannot be empty.';
                $reason ??= $tag[0] === ':'
                        ? 'Tags cannot start with a separator.'
                        : 'Tags must start with a letter.';

                CompilerException::error(
                    message  : "Invalid component tag '{$tag}'. {$reason}",
                    continue : true,
                );
            }

            if ( \str_contains( $tag, ':' ) ) {
                $fragments      = \explode( ':', $tag );
                $tag            = \array_shift( $fragments );
                $taggedFragment = false;

                foreach ( $fragments as $index => $fragment ) {
                    if ( \preg_match( '{[a-z]+}', $fragment ) ) {
                        $taggedFragment = true;
                    }

                    if ( $taggedFragment ) {
                        unset( $fragments[$index] );
                    }
                }
                $tag .= ':'.\implode( ':', $fragments );
            }

            $set[$tag] = $this->serviceId;
        }

        return $set;
    }

    /**
     * @return array<string, array<int, null|string>>
     */
    protected function taggedNodeTags() : array
    {
        $properties = [];

        foreach ( $this->nodeTags as $tag ) {
            $tags = \explode( ':', $tag );
            $tag  = $tags[0];

            if ( ! \ctype_alnum( \str_replace( [':', '{', '}'], '', $tag ) ) ) {
                CompilerException::error(
                    message  : "{$this->className} contains invalid characters in tag: {$tag}",
                    continue : true,
                );
            }

            foreach ( $tags as $position => $argument ) {
                if ( $position === 0 ) {
                    unset( $tags[$position] );

                    continue;
                }

                $property        = \trim( $argument, " \t\n\r\0\x0B{}" );
                $tags[$position] = $property;
            }
            $properties[$tag] = $tags;
        }

        return $properties;
    }
}

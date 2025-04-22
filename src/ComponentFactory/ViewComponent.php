<?php

declare(strict_types=1);

namespace Core\View\ComponentFactory;

use Attribute, Override;
use Core\Symfony\Console\Output;
use Core\Symfony\DependencyInjection\Autodiscover;
use Core\View\Component;
use Northrook\Logger\Log;
use ReflectionException;
use LogicException;
use ReflectionClass;
use InvalidArgumentException;
use Throwable;
use ValueError;
use ReflectionAttribute;
use RuntimeException;
use function Support\normalize_path;

/**
 * Classing annotated with {@see Component} will be autoconfigured as a `service`.
 *
 * @used-by ComponentFactory, ComponentParser
 *
 * @author  Martin Nielsen
 */
#[Attribute( Attribute::TARGET_CLASS )]
final class ViewComponent extends Autodiscover
{
    public const string PREFIX = 'view.component.';

    public const string LOCATOR_ID = 'view.component_locator';

    /** @var array<string,ViewComponent> */
    private static array $instances = [];

    /** @var class-string<Component> */
    public readonly string $className;

    /** @var string[] */
    public readonly array $nodeTags;

    public readonly string $name;

    public readonly string $directory;

    /**
     * Configure how this {@see AbstractComponent} is handled.
     *
     * ### `Tag`
     * Assign one or more HTML tags to trigger this component.
     *
     * Use the `:` separator to indicate a component subtype,
     * which will call a method of the same name.
     *
     * ### `Static`
     * Components will by default be rendered at runtime,
     * but static components will render into the template cache as HTML.
     *
     * @param string[] $tag       [optional]
     * @param ?string  $name
     * @param ?string  $serviceId
     */
    public function __construct(
        string|array $tag = [],
        ?string      $name = null,
        ?string      $serviceId = null,
    ) {
        if ( $name ) {
            $this->name = \strtolower( \trim( $name, " \n\r\t\v\0." ) );
        }

        $this->setTags( (array) $tag );

        parent::__construct(
            serviceID : $serviceId ?? '',
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
     * @return ViewComponent[]
     */
    public static function getInstances() : array
    {
        return self::$instances;
    }

    /**
     * @param class-string<Component> $component
     *
     * @return self
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
        $self->setClassName( $component );
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

    /**
     * @param string[] $tags
     *
     * @return void
     */
    private function setTags( array $tags ) : void
    {
        foreach ( $tags as $tag ) {
            if ( ! \ctype_alpha( \str_replace( [':', '{', '}'], '', $tag ) ) ) {
                Log::error( 'Tag {tag} contains invalid characters.', ['tag' => $tag] );
            }
        }

        $this->nodeTags = \array_values( $tags );
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
                Output::error( 'Invalid component tag.', 'Value: '.$tag, $reason );

                continue;
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

            $set[$tag] = $this->serviceID;
        }

        return $set;
    }

    /**
     * @return array<string, array<int, null|string>>
     */
    protected function taggedNodeTags() : array
    {
        $properties = [];

        dump( $this->nodeTags );

        foreach ( $this->nodeTags as $tag ) {
            $tags = \explode( ':', $tag );
            $tag  = $tags[0];

            foreach ( $tags as $position => $argument ) {
                if ( $position === 0 ) {
                    unset( $tags[$position] );

                    continue;
                }

                $property        = \trim( $argument, " \t\n\r\0\x0B{}" );
                $tags[$position] = $property;
                // if ( \str_contains( $argument, '{' ) ) {
                //
                //     // if ( Reflect::class( $this->className )->hasProperty( $property ) ) {
                //     //     $tags[ $position ] = $property;
                //     // }
                //     // else {
                //     //     Output::error( "Property '{$property}' not found in component '{$this->name}'" );
                //     // }
                //
                //     continue;
                // }

                // if ( !Reflect::class( $this->className )->hasMethod( $argument ) ) {
                //     Output::error( "Method {$this->className}::{$argument}' not found in component '{$this->name}'" );
                // }
            }
            $properties[$tag] = $tags;
        }

        return $properties;
    }
}

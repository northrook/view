<?php

declare(strict_types=1);

namespace Core\View\ComponentFactory;

use Attribute, Override;
use Core\Symfony\Console\Output;
use Core\Symfony\DependencyInjection\Autodiscover;
use Core\View\Template\Component;
use Northrook\Logger\Log;
use Support\Reflect;
use LogicException;

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

    /** @var class-string<Component> */
    public readonly string $className;

    /** @var string[] */
    public readonly array $nodeTags;

    public readonly string $name;

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
     * @param bool     $static    [false]
     * @param ?string  $name
     * @param ?string  $serviceId
     */
    public function __construct(
        string|array $tag = [],
        public bool  $static = false,
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

    /**
     * @return array{name: string, class: class-string<Component>, static: bool, tags: string[], tagged: array<string, array<int, null|string>>}
     */
    public function getProperties() : array
    {
        return [
            'name'   => $this->name,
            'class'  => $this->className,
            'static' => $this->static,
            'tags'   => $this->componentNodeTags(),
            'tagged' => $this->taggedNodeTags(),
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

        foreach ( $this->nodeTags as $tag ) {
            $tags = \explode( ':', $tag );
            $tag  = $tags[0];

            foreach ( $tags as $position => $argument ) {
                if ( $position === 0 ) {
                    unset( $tags[$position] );

                    continue;
                }

                if ( \str_contains( $argument, '{' ) ) {
                    $property = \trim( $argument, " \t\n\r\0\x0B{}" );

                    if ( Reflect::class( $this->className )->hasProperty( $property ) ) {
                        $tags[$position] = $property;
                    }
                    else {
                        Output::error( "Property '{$property}' not found in component '{$this->name}'" );
                    }

                    continue;
                }

                if ( ! Reflect::class( $this->className )->hasMethod( $argument ) ) {
                    Output::error( "Method {$this->className}::{$argument}' not found in component '{$this->name}'" );
                }
            }
            $properties[$tag] = $tags;
        }

        return $properties;
    }
}

<?php

declare(strict_types=1);

namespace Core\View\Attribute;

use Attribute, Override;
use Core\Symfony\Console\Output;
use Core\Symfony\DependencyInjection\Autodiscover;
use Core\View\Interface\ViewComponentInterface;
use Core\View\Component\AbstractComponent;
use Core\View\Html\Tag;
use Northrook\Logger\Log;
use Support\Reflect;
use function Support\classBasename;

/**
 * Classing annotated with {@see AbstractComponent} and implementing the {@see ViewComponentInterface}, will be autoconfigured as a `service`.
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

    /** @var class-string<ViewComponentInterface> */
    public readonly string $className;

    /** @var string[] */
    public readonly array $nodeTags;

    public readonly string $name;

    /**
     * Configure how this {@see ViewComponentInterface} is handled.
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
     * ### `Priority`
     * The higher the number, the earlier the Component is parsed.
     *
     * @param string[] $tag       [optional]
     * @param bool     $static    [false]
     * @param int      $priority  [0]
     * @param ?string  $name
     * @param ?string  $serviceId
     */
    public function __construct(
        string|array $tag = [],
        public bool  $static = false,
        public int   $priority = 0,
        ?string      $name = null,
        ?string      $serviceId = null,
    ) {
        if ( $name ) {
            $this->name = \strtolower( \trim( $name, " \n\r\t\v\0." ) );
        }

        $this->setTags( (array) $tag );

        parent::__construct(
            serviceID : $serviceId ?? '',
            tag       : ['view.component_locator', 'controller.service_arguments'],
            lazy      : false,
            public    : false,
            autowire  : true,
        );
    }

    #[Override]
    protected function serviceID() : string
    {
        $this->name ??= \strtolower( classBasename( $this->className ) );
        return \strtolower( $this::PREFIX.$this->name );
    }

    /**
     * @param string[] $tags
     *
     * @return void
     */
    private function setTags( array $tags ) : void
    {
        // TODO : Will only match stand-alone tags if strictly specified

        foreach ( $tags as $tag ) {
            $tag = \strtolower( \trim( $tag ) );

            if ( ! \in_array( \strstr( $tag, ':', true ), Tag::TAGS, true ) ) {
                Log::warning( 'Unknown tag: '.$tag );
            }

            if ( ! \preg_match( '/^[a-zA-Z][a-zA-Z0-9._:-]*$/', $tag ) ) {
                Log::error( 'Tag {tag} contains invalid characters.', ['tag' => $tag] );
            }
        }

        $this->nodeTags = \array_values( $tags );
    }

    /**
     * @return array{name: string, class: class-string<ViewComponentInterface>, static: bool, priority: int, tags: string[], tagged: array<string, array<int, null|string>>}
     */
    public function getProperties() : array
    {
        return [
            'name'     => $this->name,
            'class'    => $this->className,
            'static'   => $this->static,
            'priority' => $this->priority,
            'tags'     => $this->componentNodeTags(),
            'tagged'   => $this->taggedNodeTags(),
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
                $reason ??= ':' === $tag[0] ? 'Tags cannot start with a separator.'
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

                if ( $position && ! Reflect::class( $this->className )->hasMethod( $argument ) ) {
                    Output::error( "Method {$this->className}::{$argument}' not found in component '{$this->name}'" );
                }

                $tags[$position] = null;
            }

            $properties[$tag] = $tags;
        }

        return $properties;
    }
}

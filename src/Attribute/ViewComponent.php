<?php

declare(strict_types=1);

namespace Core\View\Attribute;

use Attribute;
use Core\Symfony\Console\Output;
use Core\Symfony\DependencyInjection\Autodiscover;
use Core\View\Html\Tag;
use Core\View\Interface\ViewComponentInterface;
use Northrook\Logger\Log;
use function Support\classBasename;
use Override;

/**
 * Classing annotated with {@see ViewComponent} and implementing the {@see ViewComponentInterface}, will be autoconfigured as a `service`.
 *
 * @used-by ComponentFactory, ComponentParser
 *
 * @author  Martin Nielsen
 */
#[Attribute( Attribute::TARGET_CLASS )]
final class ViewComponent extends Autodiscover
{
    public const string LOCATOR_ID = 'view.component_locator';

    /** @var class-string<ViewComponentInterface> */
    protected readonly string $className;

    /** @var string[] */
    public readonly array $nodeTags;

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
     * @param ?string  $serviceId
     * @param bool     $autowire
     */
    public function __construct(
        string|array $tag = [],
        public bool  $static = false,
        public int   $priority = 0,
        ?string      $serviceId = null,
        bool         $autowire = false,
    ) {
        $this->setTags( (array) $tag );

        parent::__construct(
            serviceID : $serviceId ?? '',
            tags      : ['view.component_locator'],
            lazy      : false,
            autowire  : $autowire,
        );
    }

    #[Override]
    protected function serviceID() : string
    {
        return \strtolower( 'view.component.'.classBasename( $this->className ) );
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
     * @return array{name: string, class: class-string<ViewComponentInterface>, static: bool, priority: int, tags: string[], tagged: array<string, string>}
     */
    public function getProperties() : array
    {
        return [
            'name'     => $this->serviceID,
            'class'    => $this->className,
            'static'   => $this->static,
            'priority' => $this->priority,
            'tags'     => $this->nodeTags,
            'tagged'   => $this->taggedNodeTags(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function taggedNodeTags() : array
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
}

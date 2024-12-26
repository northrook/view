<?php

declare(strict_types=1);

namespace Core\View\Attribute;

use Attribute;
use Core\Symfony\DependencyInjection\Autodiscover;
use Core\View\Html\Tag;
use Northrook\Logger\Log;

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
    /** @var string[] */
    public readonly array $tags;

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
     */
    public function __construct(
        string|array $tag = [],
        public bool  $static = false,
        public int   $priority = 0,
        ?string      $serviceId = null,
    ) {
        $this->setTags( (array) $tag );

        parent::__construct( $serviceId );
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

        $this->tags = \array_values( $tags );
    }
}

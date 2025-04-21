<?php

declare(strict_types=1);

namespace Core\View\Component;

use Core\Interface\DataObject;
use Core\View\Component;
use Stringable;

/**
 * @internal
 * @author Martin Nielsen <mn@northrook.com>
 */
final readonly class Properties extends DataObject implements Stringable
{
    /**
     * @param string                   $name
     * @param class-string<Component>  $class
     * @param string                   $directory
     * @param string[]                 $tags
     * @param array<string, ?string[]> $tagged
     */
    public function __construct(
        public string $name,
        public string $class,
        public string $directory,
        public array  $tags = [],
        public array  $tagged = [],
    ) {}

    public function __toString() : string
    {
        return $this->name;
    }

    public function targetTag( string $tag ) : bool
    {
        return \array_key_exists( $this::tag( $tag ), $this->tags );
    }

    public static function tag( string $tag ) : string
    {
        // Parsed namespaced $tag
        if ( \str_contains( $tag, ':' ) ) {
            // Always parse tags passed using a view:tag.. namespace
            if ( \str_starts_with( $tag, 'view:' ) ) {
                return \explode( ':', $tag )[1];
            }

            $tag = \strstr( $tag, ':', true ).':';
        }

        return $tag;
    }
}

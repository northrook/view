<?php

namespace Core\View;

use InvalidArgumentException;

use Core\View\HTML\{Element, Tag};
use Support\Escape;

final class HTML
{
    /**
     * @param string                                    $href
     * @param null|array<array-key, string>|bool|string ...$attributes
     *
     * @return string
     */
    public static function link(
        string                    $href,
        string|bool|array|null ...$attributes,
    ) : string {
        $attributes['href'] = Escape::url( $href );
        return Tag::from( 'link' )->getOpeningTag( $attributes );
    }

    /**
     * @param ?string                                   $src
     * @param false|string                              $inline
     * @param null|array<array-key, string>|bool|string ...$attributes
     *
     * @return string
     */
    public static function script(
        ?string                   $src = null,
        false|string              $inline = false,
        string|bool|array|null ...$attributes,
    ) : string {
        if ( $src && ! $inline ) {
            $attributes['src'] = Escape::url( $src );
            return Tag::from( 'script' )->getOpeningTag( $attributes );
        }

        if ( $inline ) {
            unset( $attributes['src'] );
            return (string) new Element( 'script', $attributes, $inline );
        }

        throw new InvalidArgumentException();
    }

    /**
     * @param ?string                                   $href
     * @param false|string                              $inline
     * @param null|array<array-key, string>|bool|string ...$attributes
     *
     * @return string
     */
    public static function style(
        ?string                   $href = null,
        false|string              $inline = false,
        string|bool|array|null ...$attributes,
    ) : string {
        if ( $href && ! $inline ) {
            $attributes['href'] = Escape::url( $href );
            $attributes['rel']  = 'stylesheet';
            return Tag::from( 'link' )->getOpeningTag( $attributes );
        }

        if ( $inline ) {
            unset( $attributes['href'] );
            return (string) new Element( 'style', $attributes, $inline );
        }

        throw new InvalidArgumentException();
    }
}

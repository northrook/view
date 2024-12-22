<?php

declare(strict_types=1);

namespace Core\View\HTML;

use Stringable;

final class Tag implements Stringable
{
    public const array TAGS = [
        'div',
        'body',
        'html',
        'li',
        'dropdown',
        'menu',
        'modal',
        'field',
        'fieldset',
        'legend',
        'label',
        'option',
        'script',
        'style',
        'select',
        'input',
        'textarea',
        'form',
        'tooltip',
        'section',
        'main',
        'header',
        'footer',
        'div',
        'span',
        'p',
        'ul',
        'a',
        'img',
        'button',
        'i',
        'strong',
        'em',
        'sup',
        'sub',
        'br',
        'hr',
        'hgroup',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
    ];

    public const array HEADING = ['hgroup', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    /** @link https://developer.mozilla.org/en-US/docs/Web/HTML/Content_categories#flow_content MDN */
    public const array INLINE = [
        'a',
        'b',
        'strong',
        'cite',
        'code',
        'em',
        'i',
        'kbd',
        'mark',
        'span',
        's',
        'small',
        'wbr',
    ];

    public const array SELF_CLOSING = [
        'meta',
        'link',
        'img',
        'input',
        'wbr',
        'hr',
        'br',
        'col',
        'area',
        'base',
        'source',
        'param',
        'embed',
        'track',
        'keygen',
    ];

    private function __construct( private string $name )
    {
    }

    public static function from( null|Tag|string $tag, false|string $fallback = 'div' ) : self
    {
        if ( ! $tag && $fallback ) {
            $tag = $fallback;
        }

        return new self( (string) $tag );
    }

    /**
     * @return string
     */
    public function __toString() : string
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public function __invoke( string $name ) : self
    {
        return $this->set( $name );
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public function set( string $name ) : Tag
    {
        $this->name = \strtolower( \trim( $name ) );
        return $this;
    }

    /**
     * @param 'heading'|'inline'|'self-closing'|string $name
     *
     * @return bool
     */
    public function is( string $name ) : bool
    {
        return match ( $name ) {
            'heading'      => \in_array( $this->name, Tag::HEADING ),
            'inline'       => \in_array( $this->name, Tag::INLINE ),
            'self-closing' => \in_array( $this->name, Tag::SELF_CLOSING ),
            default        => $this->name === $name,
        };
    }

    /**
     * @param null|array<string, null|array<array-key, ?string>|string>|Attributes $attributes
     *
     * @return string
     */
    public function getOpeningTag( null|array|Attributes $attributes = null ) : string
    {
        if ( \is_array( $attributes ) ) {
            $attributes = new Attributes( $attributes );
        }

        return "<{$this->name}{$attributes}>";
    }

    /**
     * @return null|string
     */
    public function getClosingTag() : ?string
    {
        return \in_array( $this->name, $this::SELF_CLOSING ) ? null : "</{$this->name}>";
    }

    /**
     * Check if the provided tag is a valid HTML tag.
     *
     * - Only checks native HTML tags.
     *
     * @param null|string|Tag $name
     *
     * @return bool
     */
    public static function isValidTag( null|string|Tag $name ) : bool
    {
        if ( ! $name ) {
            return false;
        }

        return \in_array( \strtolower( $name ), [...Tag::TAGS, ...Tag::SELF_CLOSING], true );
    }

    public static function isContent( Tag|string $name ) : bool
    {
        return \in_array( \strtolower( (string) $name ), [...Tag::HEADING, ...Tag::INLINE, 'p'], true );
    }

    public static function isInline( Tag|string $name ) : bool
    {
        return \in_array( \strtolower( (string) $name ), Tag::INLINE, true );
    }

    public static function isSelfClosing( Tag|string $name ) : bool
    {
        return \in_array( \strtolower( (string) $name ), Tag::SELF_CLOSING, true );
    }
}

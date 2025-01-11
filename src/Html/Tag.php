<?php

declare(strict_types=1);

namespace Core\View\Html;

use Stringable;
use BadMethodCallException;

/**
 */
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

    private function __construct( private string $name ) {}

    public static function from( null|Tag|string $value, false|string $fallback = 'div' ) : self
    {
        if ( ! $value && $fallback ) {
            $value = $fallback;
        }

        return new self( (string) $value );
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
     * @param string            $name
     * @param array{string|Tag} $arguments
     *
     * @return bool
     */
    public function __call( string $name, array $arguments ) : bool
    {
        return match ( $name ) {
            'isValidTag'    => $this::isValidTag( $this->name ),
            'isContent'     => $this::isContent( $this->name ),
            'isHeading'     => $this::isHeading( $this->name ),
            'isInline'      => $this::isInline( $this->name ),
            'isSelfClosing' => $this::isSelfClosing( $this->name ),
            default         => throw new BadMethodCallException(
                'Warning: Undefined method: '.$this::class."::\${$name}",
            ),
        };
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function is( string $name ) : bool
    {
        return $this->name === $name;
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

    public function getTagName() : string
    {
        return $this->name;
    }

    /**
     * @param null|array<array-key, null|array<array-key, string>|bool|string>|Attributes $attributes
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
     * - Instanced calls checks `$this->name`.
     *
     * @param null|self|string $name
     *
     * @return bool
     */
    public static function isValidTag( null|string|Tag $name = null ) : bool
    {
        if ( ! $name ) {
            return false;
        }

        return \in_array( \strtolower( (string) $name ), [...Tag::TAGS, ...Tag::SELF_CLOSING], true );
    }

    /**
     * Instanced calls checks `$this->name`.
     *
     * @param null|self|string $name
     *
     * @return bool
     */
    public static function isContent( null|string|self $name = null ) : bool
    {
        if ( ! $name ) {
            return false;
        }
        return \in_array( \strtolower( (string) $name ), [...Tag::HEADING, ...Tag::INLINE, 'p'], true );
    }

    /**
     * Instanced calls checks `$this->name`.
     *
     * @param null|self|string $name
     *
     * @return bool
     */
    public static function isHeading( null|string|self $name = null ) : bool
    {
        if ( ! $name ) {
            return false;
        }
        return \in_array( \strtolower( (string) $name ), Tag::HEADING );
    }

    /**
     * Instanced calls checks `$this->name`.
     *
     * @param null|self|string $name
     *
     * @return bool
     */
    public static function isInline( null|string|self $name = null ) : bool
    {
        if ( ! $name ) {
            return false;
        }
        return \in_array( \strtolower( (string) $name ), Tag::INLINE, true );
    }

    /**
     * Instanced calls checks `$this->name`.
     *
     * @param null|self|string $name
     *
     * @return bool
     */
    public static function isSelfClosing( null|string|self $name = null ) : bool
    {
        if ( ! $name ) {
            return false;
        }
        return \in_array( \strtolower( (string) $name ), Tag::SELF_CLOSING, true );
    }
}

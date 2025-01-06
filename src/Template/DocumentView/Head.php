<?php

declare(strict_types=1);

namespace Core\View\Template\DocumentView;

use Stringable;
use InvalidArgumentException;

/**
 * @internal
 * @author Martin Nielsen <mn@northrook.com>
 */
final class Head implements Stringable
{
    /** @var array<string, string> */
    private array $head = [];

    public function __construct() {}

    public function title( string $value ) : self
    {
        $this->head['title'] ??= "<title>{$value}</title>";
        return $this;
    }

    public function description( string $value ) : self
    {
        $this->head['description'] ??= "<meta name=\"description\" content=\"{$value}\"/>";
        return $this;
    }

    /**
     * @param array<array-key, string>|string $value
     *
     * @return $this
     */
    public function keywords( string|array $value ) : self
    {
        $value = \implode( ', ', (array) $value );

        $this->head['keywords'] ??= "<meta name=\"keywords\" content=\"{$value}\"/>";
        return $this;
    }

    public function author( string $set ) : self
    {
        $this->head['author'] ??= "<meta name=\"author\" content=\"{$set}\"/>";
        return $this;
    }

    public function meta( ?string $name = null, string ...$set ) : void
    {
        $key  = $name;
        $meta = '<meta';

        if ( $name ) {
            $meta .= " name=\"{$name}\"";
        }

        // if ( $content ) {
        //     $meta .= " content=\"{$content}\"";
        // }

        foreach ( $set as $property => $content ) {
            if ( \is_int( $property ) ) {
                if ( 0 === $property && ! \array_key_exists( 'content', $set ) ) {
                    $property = 'content';
                }
                else {
                    throw new InvalidArgumentException( 'Named arguments only' );
                }
            }

            $property = \str_replace( '_', '-', $property );

            $key  .= ".{$property}";
            $meta .= " {$property}=\"{$content}\"";
        }
        $meta .= '/>';

        $key = \trim( (string) $key, " \n\r\t\v\0." );

        $this->head[$key] = $meta;
    }

    // public function robots()
    // {
    //
    // }

    public function injectHtml( string|Stringable $html ) : self
    {
        $key = $html instanceof Stringable ? $html::class : $html;
        $this->head[$key] ??= (string) $html;
        return $this;
    }

    /**
     * @return string[]
     */
    public function array() : array
    {
        return $this->head;
    }

    public function render() : string
    {
        return "<head>\n\t".\implode( "\n\t", $this->head )."\n</head>";
    }

    public function __toString() : string
    {
        // TODO : Sort before dump
        return $this->render();
    }
}

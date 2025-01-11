<?php

declare(strict_types=1);

namespace Core\View\Html;

use JetBrains\PhpStorm\Language;
use DOMDocument;
use Exception;
use Northrook\Logger\Log;
use Support\Str;
use ErrorException;
use DOMNodeList;
use DOMNode;

final class HtmlNode
{
    public DOMDocument $dom;

    public function __construct(
        ?string $html = null,
    ) {
        $this->dom = new DOMDocument( '1.0', 'UTF-8' );
        if ( $html ) {
            $this->load( $html );
        }
    }

    public function load(
        #[Language( 'HTML' )]
        string $string,
    ) : self {
        try {
            $html = Str::normalize( $string );
            $this->dom->loadHTML(
                source  : "<div>{$html}</div>",
                options : LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
            );
            $this->dom->encoding = 'UTF-8';
        }
        catch ( Exception $exception ) {
            $this->errorHandler( $exception );
        }

        return $this;
    }

    /**
     * @return DOMNode[]
     */
    public function getChildNodes() : array
    {
        return \iterator_to_array( $this->iterateChildNodes() );
    }

    /**
     * @return DOMNodeList<DOMNode>
     */
    public function iterateChildNodes() : DOMNodeList
    {
        return $this->dom->documentElement->childNodes ?? new DOMNodeList();
    }

    /**
     * @param string $html
     * @param bool   $asArray
     *
     * @return ($asArray is true ? array<string, null|array<array-key, string>|bool|string> : Attributes)
     */
    public static function extractAttributes( string $html, bool $asArray = false ) : array|Attributes
    {
        // Trim whitespace, bail early if empty
        if ( ! $html = \preg_replace( '# +#', '', $html ) ) {
            return $asArray ? [] : new Attributes();
        }

        if ( ! ( \str_starts_with( $html, '<' ) && \str_starts_with( $html, '>' ) )
        ) {
            $html = "<div {$html}>";
        }
        else {
            $html = \strstr( $html, '>', true ).'>';
            $html = \preg_replace(
                pattern     : '/^<(\w.+):\w+? /',
                replacement : '<$1 ',
                subject     : $html,
            );
        }

        return ( new HtmlNode( $html ) )->getAttributes( $asArray );
    }

    public static function unwrap( string $html, string ...$tags ) : string
    {
        $proceed = false;

        $string = \trim( $html );

        // Bail early if the html isn't wrapped
        if ( ! ( \str_starts_with( $string, '<' ) && \str_ends_with( $string, '>' ) ) ) {
            return $html;
        }

        // Check for target tags if provided
        if ( $tags ) {
            foreach ( $tags as $tag ) {
                if ( \str_starts_with( $string, "<{$tag}" ) ) {
                    $proceed = true;
                }
            }
        }
        // Otherwise ensure the string starts with a <tag
        else {
            if ( \ctype_alpha( $string[1] ?? false ) ) {
                $proceed = true;
            }
        }

        if ( ! $proceed ) {
            return $html;
        }

        // return __METHOD__;
        $element = new self( $html );

        // $element->d

        foreach ( $element->iterateChildNodes() as $childNode ) {
            // if ( ! \in_array( $childNode->nodeName, $tags ) ) {
            //     continue;
            // }

            foreach ( $childNode->childNodes as $nestedChild ) {
                $childNode->parentNode?->insertBefore( $nestedChild->cloneNode( true ), $childNode );
            }
            $childNode->parentNode?->removeChild( $childNode );
        }

        return $element->getHtml();
    }

    public function getHtml() : string
    {
        $content = '';

        foreach ( $this->getChildNodes() as $node ) {
            $content .= $this->dom->saveXML( $node, options : LIBXML_NOXMLDECL );
        }

        if ( ! \str_contains( $content, "\t" ) ) {
            return $content;
        }

        $lines = \array_filter( \explode( "\n", $content ) );

        $leadingTabs = \strspn( $lines[\array_key_first( $lines )], "\t" );

        foreach ( $lines as $index => $line ) {
            if ( \str_starts_with( $line, "\t" ) ) {
                $lines[$index] = \substr( $line, $leadingTabs );
            }
        }

        return \implode( "\n", $lines );
    }

    /**
     * @param bool $asArray
     *
     * @return ($asArray is true ? array<string, null|array<array-key, string>|bool|string> : Attributes)
     */
    public function getAttributes( bool $asArray = false ) : array|Attributes
    {
        $attributes = [];

        $node = $this->dom->firstElementChild;

        if ( ! $node ) {
            return $attributes;
        }

        foreach ( $node->attributes as $attribute ) {
            $attributes[$attribute->nodeName] = $attribute->nodeValue;
        }

        return $asArray ? $attributes : Attributes::from( $attributes );
    }

    private function errorHandler( Exception $exception ) : void
    {
        if ( $exception instanceof ErrorException ) {
            // $severity = $exception->getSeverity();
            $message = $exception->getMessage();

            if ( \str_contains( $message, ' invalid in Entity, ' ) ) {
                return;
            }

            // : We will likely downright skip all down the line
            // if ( $severity === E_WARNING ) {
            //     return;
            // }
        }

        Log::exception( $exception );
    }
}

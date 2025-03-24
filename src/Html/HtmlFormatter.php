<?php

declare(strict_types=1);

namespace Core\View\Html;

use Core\Interface\Printable;
use Core\View\Element\{Attributes, Tag};
use Core\View\Element;
use DOMDocument;
use DOMElement;
use DOMNode;
use ErrorException;
use LogicException;
use Northrook\Logger\Log;
use Stringable;
use DOMText;
use DOMAttr;
use Exception;
use function Support\{
    key_hash,
    normalizeNewline,
    str_after,
    str_bisect,
    str_extract,
    str_replace_each,
    str_starts_with_any
};
use const Support\{TAG_INLINE, TAG_SELF_CLOSING};

final class HtmlFormatter implements Printable
{
    private const string OPERATOR = '[%OPERATOR%]';

    private const string FUSE = '[%FUSE%]';

    /** @var string[] */
    private array $selfClosing = [...TAG_SELF_CLOSING, 'css', 'js'];

    /** @var string[] */
    private array $inline = [
        ...TAG_INLINE,
        'title',
        'br',
        'hr',
        'img',
        'input',
        'link',
        'meta',
        'param',
        'source',
        'track',
        'wbr',
        'circle',
        'path',
        'rect',
        'line',
        'polyline',
        // 'span',
    ];

    /** @var array<array-key, mixed> */
    private array $ast;

    /** @var array<string, string> */
    private array $safe = [];

    protected ?string $doctype = null;

    protected string $docHtml;

    protected string $docHead;

    protected string $docBody;

    protected string $html;

    /**
     * @param null|array<array-key, mixed>|string|Stringable $source
     */
    public function __construct(
        null|string|Stringable|array $source,
    ) {
        if ( \is_array( $source ) ) {
            $this->ast = $source;
        }
        else {
            $this->html = \trim( (string) $source );
            $this->doctype();
            $this->ast = $this->htmlToAst();
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getAst() : array
    {
        return $this->ast;
    }

    public function __toString() : string
    {
        return $this->toString() ?: '';
    }

    /**
     * @param bool $indent
     *
     * @return null|string
     */
    public function toString( bool $indent = false ) : ?string
    {
        $this->html = $this->doctype.$this->parseSyntaxTree();

        if ( $indent ) {
            $this
                ->doctype()
                ->documentHtml()
                ->documentHead()
                ->documentBody();

            $this
                ->parse( $this->docHead )
                ->parse( $this->docBody );

            $html = [
                $this->doctype,
                $this->docHtml,
                $this->docHead,
                $this->docBody,
                $this->docHtml ? '</html>' : null,
            ];

            $this->html = \implode( PHP_EOL, $html );
        }

        if ( ! $this->html ) {
            return null;
        }

        return normalizeNewline( $this->html );
    }

    /**
     * @param string $string
     *
     * @return Node[]
     */
    private function parseDocumentElements( string $string ) : array
    {
        $nodes = [];

        foreach ( $this->explode( $string ) as $key => $value ) {
            $nodes[] = new Node( $key, $value );
        }

        return $nodes;
    }

    /**
     * @param string           $string
     * @param non-empty-string $fuse
     *
     * @return string[]
     */
    private function explode(
        string $string,
        string $fuse = self::FUSE,
    ) : array {
        $string = str_replace_each(
            [
                '>'               => '>'.$fuse,
                '<'               => $fuse.'<',
                "{$fuse} {$fuse}" => $fuse,
                "{$fuse}{$fuse}"  => $fuse,
                ' />'             => '/>',
            ],
            $this->protectPassedVariables( $string ),
        );
        $array = \explode( $fuse, $string );
        return \array_values( \array_filter( $array ) );
    }

    protected function parse( string &$string ) : self
    {
        $this->protectDataElements( $string );

        $parse = $this->parseDocumentElements( $string );
        $write = '';
        $level = 1;

        foreach ( $parse as $node ) {
            $node->setNodtList( $parse );
            $inline      = $node->tag( ...$this->inline );
            $selfClosing = $node->tag( ...$this->selfClosing );

            if ( $node->tag( 'DOCTYPE', 'html', 'head', 'body' ) ) {
                if ( ! $node->isFirst() && ! \str_ends_with( $write, PHP_EOL ) ) {
                    $write .= PHP_EOL;
                }
                $write .= $node->content().PHP_EOL;

                continue;
            }

            if ( $node->tag( 'title' ) || ( $node->type( 'text' ) && $node->previous()?->tag( 'title' ) ) ) {
                $write .= match ( $node->type() ) {
                    'opening' => $this->indent( $level ).$node->content,
                    'closing' => $node->content.PHP_EOL,
                    default   => $node->content,
                };

                continue;
            }

            if ( $selfClosing ) {
                $write .= $this->indent( $level ).$node->content.PHP_EOL;

                continue;
            }

            if ( $node->type( 'opening' ) && ! $inline ) {
                if ( ! \str_ends_with( $write, PHP_EOL ) ) {
                    $write .= PHP_EOL;
                }
                $write .= $this->indent( $level ).$node->content;
                $level++;

                continue;
            }
            if ( $node->type( 'closing' ) && ! $inline ) {
                // dump( $node );
                $level--;
                $write .= PHP_EOL.$this->indent( $level ).$node->content;

                continue;
            }

            if ( $node->type( 'text' ) ) {
                if ( $node->previous()?->tag( ...$this->inline ) ) {
                    $write .= $node->content;
                    // dump( 'previous is inline',$node->previous() );
                }
                else {
                    $write .= PHP_EOL.$this->indent( $level ).$node->content;
                }

                // if ( $node->previous()->type( 'opening' ) &&  ) {
                //     $write .= PHP_EOL.$this->indent( $level ).$node->content;
                // }
                // else {
                //     $write .= $node->content;
                // }

                // dump( [$write, $node] );
                // dump( [$write, $node] );

                continue;
            }

            if ( $inline ) {
                if ( $node->previous()?->type( 'text' ) ) {
                    $write .= $node->content;

                    continue;
                }
                if ( ! $node->previous()?->tag( ...$this->inline ) ) {
                    $write .= PHP_EOL.$this->indent( $level );
                }
                // else {
                $write .= $node->content;
                // }
                // dump(
                //     [
                //         $index => $write,
                //         'tag'  => $node->tag?->getTagName() ?? $node->type(),
                //         'node' => $node,
                //         // 'next' => $node->next(),
                //         // 'prev' => $node->previous(),
                //     ],
                // );

                // dump(
                //     [
                //         $index => $write,
                //         'tag'  => $node->tag?->getTagName() ?? $node->type(),
                //         'node' => $node,
                //         'next' => $node->next(),
                //         'prev' => $node->previous(),
                //     ],
                // );

                continue;
            }

            throw new LogicException( 'Unhandled node: '.$node->type() );
        }

        $string = $this->restorePassedVariables( \trim( $write ) );
        //
        // echo "<xmp>{$string}</xmp>";

        return $this;
    }

    private function indent( int $level ) : string
    {
        $indent = ( $level <= 0 ) ? 0 : $level;

        return \str_repeat( "\t", $indent );
    }

    // ::: PROTECT

    protected function protectPassedVariables( string $html ) : string
    {
        return (string) \preg_replace_callback(
            "/\\\$[a-zA-Z?>._':$\s\-]*/m",
            static fn( array $m ) => \str_replace( '->', self::OPERATOR, $m[0] ),
            $html,
        );
    }

    protected function restorePassedVariables( string $html ) : string
    {
        $html = \str_ireplace( self::OPERATOR, '->', $html );

        foreach ( $this->safe as $key => $value ) {
            if ( \str_contains( $html, $key ) ) {
                $html = \str_replace( $key, $value, $html );
            }
        }

        return $html;
    }

    private function protectDataElements( string &$html ) : void
    {
        $start = $this->next( '<style', $html );

        if ( $start && $closing = $this->next( '</style>', $html ) ) {
            $closing += \strlen( '</style>' );
            $extracted  = str_extract( $html, $start, $closing );
            $attributes = Attributes::extract( $extracted );

            $key = $attributes->get( 'asset-id' ) ?? key_hash( 'xxh32', $extracted );

            $placehoder = "<css:[{$key}]>";

            $this->safe[$placehoder] = $extracted;

            $html = str_extract( $html, $start, $closing, $placehoder );

            $this->protectDataElements( $html );
            return;
        }

        $start = $this->next( '<script', $html );

        if ( $start && $closing = $this->next( '</script>', $html ) ) {
            $closing += \strlen( '</script>' );
            $extracted  = str_extract( $html, $start, $closing );
            $attributes = Attributes::extract( $extracted );

            $key = $attributes->get( 'asset-id' ) ?? key_hash( 'xxh32', $extracted );

            $placehoder = "<js:[{$key}]>";

            $this->safe[$placehoder] = $extracted;

            $html = str_extract( $html, $start, $closing, $placehoder );

            $this->protectDataElements( $html );
        }
    }
    // ::: PROTECT

    /**
     * @param bool $indent
     *
     * @return void
     */
    public function print( bool $indent = false ) : void
    {
        echo $this->toString( $indent );
    }

    /**
     * @param null|array<array-key, mixed> $array
     *
     * @return string
     */
    public function astToHtml( ?array $array = null ) : string
    {
        return $this->parseSyntaxTree( $array );
    }

    /**
     * @internal
     *
     * String is returned during recursion.
     * Array returned upon completion.
     *
     * @param null|array<array-key, mixed> $ast 🔁 recursive
     * @param null|int|string              $key 🔂 recursive
     *
     * @return string
     */
    private function parseSyntaxTree( ?array $ast = null, null|string|int $key = null ) : string
    {
        // Grab $this->ast for initial loop
        $ast ??= $this->ast;
        $tag        = null;
        $attributes = [];

        // If $key is string, this iteration is an element
        if ( \is_string( $key ) ) {
            $tag        = \trim( str_after( $key, ':' ), ':' );
            $attributes = $ast['attributes'];
            $ast        = $ast['content'];

            // if ( \str_ends_with( $tag, 'icon' ) && $get = $attributes['get'] ?? null ) {
            //     unset( $attributes['get'] );
            //     return (string) new Icon( $tag, $get, $attributes );
            // }
        }

        $content = [];

        \assert( \is_array( $ast ) && \is_array( $attributes ) );

        foreach ( $ast as $elementKey => $value ) {
            $elementKey = $this->nodeKey( $elementKey, \gettype( $value ) );

            if ( \is_array( $value ) ) {
                // dump( 'Loop' );
                // dump( ['Set' => $value, 'content' => $content] );
                $content[$elementKey] = $this->parseSyntaxTree( $value, $elementKey );
            }
            elseif ( \is_string( $value ) ) {
                self::appendByReference( $value, $content, $elementKey, $ast );
            }
            else {
                Log::warning(
                    '{method} encountered unexpected value type {type}.',
                    ['method' => __METHOD__, 'type' => \gettype( $value )],
                );
            }
        }

        /** @var string[] $content */
        if ( $tag ) {
            // @phpstan-ignore-next-line
            $element = new Element( $tag, $content, ...$attributes );

            return $element->__toString();
        }

        return \implode( '', $content );
    }

    /**
     * @internal
     *
     * @param int|string $node
     * @param string     $valueType
     *
     * @return int|string
     */
    private function nodeKey( string|int $node, string $valueType ) : string|int
    {
        if ( \is_int( $node ) ) {
            return $node;
        }

        $index = \strrpos( $node, ':' );

        // Treat parsed string variables as simple strings
        if ( $index !== false && $valueType === 'string' && \str_starts_with( $node, '$' ) ) {
            return (int) \substr( $node, $index + 1 );
        }

        return $node;
    }

    /**
     * @param string                  $string
     * @param array<array-key, mixed> $content
     * @param int|string              $key
     * @param array<array-key, mixed> $ast
     *
     * @return void
     */
    private function appendByReference(
        string     $string,
        array &      $content,
        int|string $key,
        array      $ast,
    ) : void {
        // Trim $value, and bail early if empty
        if ( ! $string = \trim( $string ) ) {
            return;
        }

        $ast = \array_values( $ast );
        $key = (int) $key;

        $next = $ast[$key + 1] ?? null;
        $prev = $ast[$key - 1] ?? null;

        $lastIndex = \array_key_last( $content );
        $index     = \count( $content );

        if ( \is_int( $lastIndex ) ) {
            if ( $index > 0 ) {
                $index--;
            }
        }

        $nextTag = \is_array( $next ) ? $next['tag'] ?? null : null;
        $prevTag = \is_array( $prev ) ? $prev['tag'] ?? null : null;

        if ( \is_string( $nextTag ) && Tag::isInline( $nextTag ) ) {
            $string = "{$string} ";
        }
        elseif ( \is_string( $prevTag ) && Tag::isInline( $prevTag ) ) {
            if ( ! str_starts_with_any( $string, ',', ';' ) ) {
                $string = " {$string}";
            }
        }
        if ( isset( $content[$index] ) && \is_string( $content[$index] ) ) {
            $content[$index] .= $string;
        }
        else {
            $content[$index] = $string;
        }
    }

    // :::::

    /**
     * @param null|string $html
     *
     * @return array<array-key, mixed>
     */
    public function htmlToAst( ?string $html = null ) : array
    {
        if ( ! $html && isset( $this->html ) ) {
            $html = $this->html;
            unset( $this->html );
        }

        if ( ! $html ) {
            return [];
        }

        if ( ! \str_starts_with( $html, '<html' ) && ! \str_ends_with( $html, '</html>' ) ) {
            $html = "<html>{$html}</html>";
        }

        try {
            $dom = new DOMDocument();
            $dom->loadHTML(
                source  : $html,
                options : LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOCDATA,
            );
            $dom->encoding = 'UTF-8';
            return $this->traverseNodes( $dom );
        }
        catch ( Exception $exception ) {
            $this->errorHandler( $exception );
        }

        return [];
    }

    /**
     * @param DOMNode $dom
     *
     * @return array<array-key, mixed>
     */
    private function traverseNodes( DOMNode $dom ) : array
    {
        $ast = [];

        foreach ( $dom->childNodes as $key => $node ) {
            if ( $node instanceof DOMText && $value = \trim( $node->textContent ) ) {
                $ast[$key] = $value;

                continue;
            }

            if ( $node instanceof DOMElement ) {
                $ast["{$key}:{$node->nodeName}"] = [
                    'tag'        => $node->nodeName,
                    'attributes' => $this->nodeAttributes( $node ),
                    'content'    => $this->traverseNodes( $node ),
                ];
            }
        }

        return $ast;
    }

    /**
     * @param DOMElement $node
     *
     * @return array<string, string>
     */
    private function nodeAttributes( DOMElement $node ) : array
    {
        $attributes = [];

        foreach ( $node->attributes as $attribute ) {
            \assert( $attribute instanceof DOMAttr );
            $attributes[$attribute->name] = $attribute->nodeValue;
        }

        return Attributes::from( ...$attributes )->resolveAttributes( true );
    }

    // .. HTML String

    protected function doctype() : self
    {
        $position = \mb_stripos( $this->html, '<!doctype' );

        if ( \mb_stripos( $this->html, '<' ) < $position ) {
            throw new LogicException();
        }

        [$doctype, $html] = str_bisect( $this->html, '>' );

        $this->doctype = \trim( $doctype );
        $this->html    = \trim( $html );

        return $this;
    }

    protected function documentHtml() : self
    {
        $position = \mb_stripos( $this->html, '<html' );
        if ( $this->next( '<' ) < $position ) {
            throw new LogicException();
        }
        [$tag, $html] = str_bisect( $this->html, '>' );
        if ( ! \str_ends_with( $this->html, '</html>' ) ) {
            throw new LogicException();
        }
        $html          = \substr( $html, 0, -7 );
        $this->docHtml = \trim( $tag );
        $this->html    = \trim( $html );

        return $this;
    }

    protected function documentHead() : self
    {
        $position = \mb_stripos( $this->html, '<head' );
        if ( $this->next( '<' ) < $position ) {
            throw new LogicException();
        }
        if ( ! \str_contains( $this->html, '</head>' ) ) {
            throw new LogicException();
        }
        [$head, $html] = str_bisect( $this->html, '</head>' );
        $this->docHead = \trim( $head );
        $this->html    = \trim( $html );
        return $this;
    }

    protected function documentBody() : self
    {
        $position = \mb_stripos( $this->html, '<body' );
        if ( $this->next( '<' ) < $position ) {
            throw new LogicException();
        }
        if ( ! \str_ends_with( $this->html, '</body>' ) ) {
            throw new LogicException();
        }
        [$body, $html] = str_bisect( $this->html, '</body>' );
        $this->docBody = \trim( $body );
        $this->html    = \trim( $html );
        return $this;
    }

    private function next( string $string, ?string $in = null ) : false|int
    {
        return \mb_stripos( $in ?? $this->html, $string );
    }

    /**
     * @param Exception $exception
     */
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

        // throw $exception;
        Log::exception( $exception );
    }
}

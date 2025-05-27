<?php

declare(strict_types=1);

namespace Core\View\Html;

use Core\Interface\DataInterface;
use Core\View\Element\Tag;
use Stringable;
use function Support\{str_before, str_includes_any};

/**
 * @internal
 */
final class HtmlNode implements DataInterface, Stringable
{
    public const string TYPE_INLINE = 'inline';

    public const string TYPE_TEXT = 'text';

    public const string TYPE_DATA = 'data';

    public const string TYPE_COMMENT = 'comment';

    public const string TYPE_OPENING = 'opening';

    public const string TYPE_CLOSING = 'closing';

    /** @var self[] */
    private readonly array $nodtList;

    public readonly ?Tag $tag;

    /** @var 'closing'|'comment'|'data'|'inline'|'opening'|'text' */
    public readonly string $type;

    /**
     * @param int    $index
     * @param string $content
     */
    public function __construct(
        public readonly int    $index,
        public readonly string $content,
    ) {
        $tag        = $this::tagFrom( $this->content );
        $this->tag  = $tag ? Tag::from( $tag ) : null;
        $this->type = match ( true ) {
            $this::isTextString( $this->content )       => $this::TYPE_TEXT,
            $this::isOpeningTag( $this->content, $tag ) => $this::TYPE_OPENING,
            $this::isClosingTag( $this->content, $tag ) => $this::TYPE_CLOSING,
            ! $tag                                      => $this::TYPE_COMMENT,

            default => $this::TYPE_INLINE,
        };
    }

    public function __toString() : string
    {
        return $this->content;
    }

    /**
     * @param self[] $nodtList
     *
     * @return void
     */
    public function setNodtList( array $nodtList ) : void
    {
        $this->nodtList = $nodtList;
    }

    public function next() : ?self
    {
        return $this->nodtList[$this->index + 1] ?? null;
    }

    public function previous() : ?self
    {
        return $this->nodtList[$this->index - 1] ?? null;
    }

    public function nextIndex() : false|int
    {
        if ( $this->isLast() ) {
            return false;
        }

        return $this->index + 1;
    }

    public function previousIndex() : false|int
    {
        if ( $this->isFirst() ) {
            return false;
        }

        return $this->index - 1;
    }

    public function isFirst() : bool
    {
        return $this->index === 0;
    }

    public function isLast() : bool
    {
        return $this->index === \count( $this->nodtList );
    }

    public function content( ?int $index = null ) : string
    {
        if ( $index === null ) {
            return $this->content;
        }

        return $this->nodtList[$index]->content();
    }

    public function tag( string ...$is ) : bool|null|Tag
    {
        return empty( $is )
                ? $this->tag
                : ( $this->tag && \in_array( (string) $this->tag, $is, true ) );
    }

    /**
     * @param null|'closing'|'comment'|'data'|'inline'|'opening'|'text' $is
     *
     * @return 'closing'|'comment'|'data'|'inline'|'opening'|'text'|bool
     */
    public function type( ?string $is = null ) : bool|string
    {
        if ( $is === null ) {
            return $this->type;
        }

        return $this->type === \strtolower( $is );
    }

    public function selfClosing( ?string $is = null ) : bool
    {
        return Tag::isSelfClosing( $is ?? $this->tag );
    }

    public static function tagFrom( string $string ) : string
    {
        if ( ! str_includes_any( $string, '<>' ) ) {
            return '';
        }

        $string = str_before( $string, ':' );
        return \trim( str_before( $string, ' ' ), " \n\r\t\v\0<>/" );
    }

    public static function isTextString( string $string ) : bool
    {
        $tag = \mb_strpos( $string, '<' );

        if ( $tag === false ) {
            return true;
        }

        if ( ! $next = $string[$tag + 1] ?? null ) {
            return true;
        }

        return ! ( $next === '/' || \ctype_alpha( $next ) );
    }

    public static function isOpeningTag( string $string, string $tag ) : bool
    {
        return \str_contains( $string, "<{$tag}" );
    }

    public static function isClosingTag( string $line, string $tag ) : bool
    {
        return \str_contains( $line, "</{$tag}>" );
    }
}

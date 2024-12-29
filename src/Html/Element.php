<?php

declare(strict_types=1);

namespace Core\View\Html;

use Core\View\Interface\ViewInterface;
use Stringable;
use Latte\Runtime as Latte;

class Element implements ViewInterface
{
    use StaticElements;

    private ?string $html = null;

    public readonly Tag $tag;

    public readonly Attributes $attributes;

    public readonly Content $content;

    /**
     * @param string|Tag                                                             $tag
     * @param array<array-key, null|array<array-key, string>|bool|string>|Attributes $attributes
     * @param string|Stringable                                                      ...$content
     */
    public function __construct(
        string|Tag           $tag = 'div',
        array|Attributes     $attributes = [],
        string|Stringable ...$content,
    ) {
        $this->tag        = $tag instanceof Tag ? $tag : Tag::from( $tag );
        $this->attributes = $attributes instanceof Attributes ? $attributes : new Attributes( $attributes );
        $this->content    = new Content( ...$content );
    }

    protected function build() : string
    {
        if ( $this->tag->isSelfClosing() ) {
            return $this->tag->getOpeningTag( $this->attributes );
        }
        return \implode(
            '',
            [
                $this->tag->getOpeningTag( $this->attributes ),
                $this->content,
                $this->tag->getClosingTag(),
            ],
        );
    }

    final public function render( bool $rebuild = false ) : string
    {
        if ( $rebuild ) {
            $this->html = null;
        }

        return $this->html ??= $this->build();
    }

    public function __toString() : string
    {
        return $this->render();
    }

    /**
     * Return a {@see ViewInterface} as {@see Stringable} or `string`.
     *
     * Pass `true` to return as `string`.
     *
     * @param bool $string [false]
     *
     * @return ($string is true ? string : Stringable)
     */
    final public function getHtml( bool $string = false ) : string|Stringable
    {
        if ( \class_exists( Latte\Html::class ) ) {
            return new Latte\Html( $this->__toString() );
        }
        return $string ? $this->__toString() : $this;
    }

    final public function tag( string $set ) : self
    {
        $this->tag->set( $set );
        return $this;
    }

    /**
     * Add attributes using named arguments.
     *
     * Underscores get converted to hyphens.
     *
     * @param null|array<array-key, string>|bool|string ...$set
     *
     * @return $this
     */
    final public function attributes( string|bool|array|null ...$set ) : self
    {
        /** @var array<string, null|array<array-key, null|bool|string>|string> $set */
        $this->attributes->add( $set );

        return $this;
    }

    /**
     * @param null|array<array-key, string|Stringable>|string|Stringable $content
     * @param bool                                                       $prepend
     *
     * @return $this
     */
    final public function content( string|array|Stringable|null $content, bool $prepend = false ) : self
    {
        if ( null === $content ) {
            return $this;
        }

        if ( ! \is_array( $content ) ) {
            $content = [$content];
        }

        if ( $prepend ) {
            $this->content->prepend( ...$content );
        }
        else {
            $this->content->append( ...$content );
        }

        return $this;
    }
}

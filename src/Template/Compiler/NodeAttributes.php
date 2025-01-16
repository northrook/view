<?php

namespace Core\View\Template\Compiler;

use Latte\Compiler\Nodes\FragmentNode;
use Latte\Compiler\Nodes\Html\ElementNode;

final readonly class NodeAttributes
{
    protected FragmentNode $attributeFragmentNode;

    public function __construct( ElementNode|FragmentNode $from = null )
    {
        $this->attributeFragmentNode = match ( true ) {
            $from instanceof ElementNode  => $from->attributes ?? new FragmentNode(),
            $from instanceof FragmentNode => $from,
            default                       => new FragmentNode(),
        };

        foreach ( $this->attributeFragmentNode as $index => $attribute ) {
            dump( [$index => $attribute] );
        }
    }

    public function __invoke() : FragmentNode
    {
        return $this->getNode();
    }

    public function getNode() : FragmentNode
    {
        return $this->attributeFragmentNode;
    }

    public function has( string $attribute, bool|int|string ...$value ) : bool
    {
        return true;
    }

    public function set( string $attribute, bool|int|string $value ) : self
    {
        return $this;
    }

    public function add( string $attribute, bool|int|string $value ) : self
    {
        return $this;
    }

    public function addClass( string $string, bool $prepend = false ) : self
    {
        return $this;
    }

    public function addStyle( string $string, bool $prepend = false ) : self
    {
        return $this;
    }

    public function remove( string $attribute ) : self
    {
        return $this;
    }
}

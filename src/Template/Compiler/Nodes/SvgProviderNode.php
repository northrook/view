<?php

declare(strict_types=1);

namespace Core\View\Template\Compiler\Nodes;

use Core\View\Template\Exception\CompileException;
use Generator;
use Core\View\Template\Compiler\{
    Nodes\Php\Expression\ArrayNode,
    PrintContext,
    Tag
};

/**
 * Renders a raw `<svg $get..>` element from the global {@see IconProviderInterface}.
 *
 * {svg [$get] [...$attributes]}
 */
final class SvgProviderNode extends StatementNode
{
    public ArrayNode $arguments;

    /**
     * @param Tag $tag
     *
     * @throws CompileException
     */
    public function __construct( Tag $tag )
    {
        $tag->outputMode = $tag::OutputRemoveIndentation;
        $this->arguments = $tag->parser->parseArguments();
    }

    /**
     * @param Tag $tag
     *
     * @return static
     * @throws CompileException
     */
    public static function create( Tag $tag ) : static
    {
        return new self( $tag );
    }

    public function print( PrintContext $context ) : string
    {
        return $context->format(
            'echo $this->global->icon->getSvg( %args );',
            $this->arguments,
        );
    }

    public function &getIterator() : Generator
    {
        yield $this->arguments;
    }
}

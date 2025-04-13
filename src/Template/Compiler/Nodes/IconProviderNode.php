<?php

declare(strict_types=1);

namespace Core\View\Template\Compiler\Nodes;

use Core\View\Template\Exception\CompileException;
use Generator;
use Core\View\Template\Compiler\{Nodes\Php\ExpressionNode, PrintContext, Tag};

/**
 * {icon [$get] [...$attributes]}
 */
final class IconProviderNode extends StatementNode
{
    public readonly string $name;

    public ?ExpressionNode $expression = null;

    /**
     * @param Tag $tag
     *
     * @throws CompileException
     */
    public function __construct( Tag $tag )
    {
        $this->expression = $tag->parser->isEnd()
                ? null
                : $tag->parser->parseExpression();
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
        if ( ! \function_exists( 'dump' ) ) {
            return '/* '.\implode(
                ' ',
                [
                    "dump( {$this->expression?->print( $context )} )",
                    $this->position ? "line {$this->position->line}" : '',
                ],
            ).' */';
        }

        return $this->expression
                ? $context->format(
                    'dump( %node ) %line;',
                    $this->expression,
                    $this->position,
                )
                : $context->format(
                    "dump( ['\$this->global' => \$this->global, ...get_defined_vars()] ) %line;",
                    $this->position,
                );
    }

    public function &getIterator() : Generator
    {
        if ( $this->expression ) {
            yield $this->expression;
        }
    }
}

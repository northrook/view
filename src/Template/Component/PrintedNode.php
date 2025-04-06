<?php

declare(strict_types=1);

namespace Core\View\Template\Component;

use JetBrains\PhpStorm\Language;
use Core\View\Template\Compiler\{Node, PrintContext};
use Core\View\Template\Compiler\Nodes\PrintNode;
use Northrook\Logger\Log;
use Stringable, Exception;

final class PrintedNode implements Stringable
{
    public readonly string $value;

    public readonly bool $isExpression;

    public readonly ?string $variable;

    public readonly ?string $expression;

    public function __construct(
        private readonly Node $node,
        private ?PrintContext $context = null,
    ) {
        $this->context ??= new PrintContext();
        match ( true ) {
            $node instanceof PrintNode => $this->parsePrintNode(),
            default                    => $this->parseNode(),
        };
    }

    private function parseNode() : void
    {
        $this->value        = \str_ireplace( ["echo '", "';"], EMPTY_STRING, $this->print() );
        $this->isExpression = false;
        $this->variable     = null;
    }

    private function parsePrintNode() : void
    {
        $this->value = \trim( (string) \preg_replace( '#echo (.+) /\*.+?;#', '$1 ', $this->print() ) );
        // dump( $this->value );

        // We may want to capture the whole {$variable ?: $withAny ?? [$rules]}
        // Parse and ensure it has a null-coalescing fallback
        $this->variable     = $this->extract( '#\$(\w+)(?=\s*|:|\?|$)#', $this->value );
        $this->expression   = $this->extract( '#\$(.+?)(?=\)|$)#', $this->value );
        $this->isExpression = true;
    }

    public function __toString() : string
    {
        return $this->value;
    }

    private function print( ?Node $node = null, ?PrintContext $context = null ) : string
    {
        try {
            $node    ??= $this->node;
            $context ??= $this->context;
            return $node->print( $context ?? new PrintContext() );
        }
        catch ( Exception $exception ) {
            Log::exception( $exception );
        }
        return EMPTY_STRING;
    }

    private function extract(
            #[Language( 'RegExp' )]
            string $pattern,
            string $string,
    ) : ?string {
        if ( \preg_match_all( $pattern, $string, $matches, PREG_SET_ORDER ) === false ) {
            return null;
        }

        return $matches[0][0] ?? null;
    }
}

<?php

declare(strict_types=1);

namespace Core\View\Exception;

use Core\Interface\View;
use RuntimeException;
use Throwable;
use const Support\AUTO;

final class RenderException extends RuntimeException
{
    /** @var array{'file': ?string, 'line': ?int, 'function': string} */
    public readonly array $caller;

    /**
     * @param class-string<View>|string $render
     * @param string                    $message
     * @param null|Throwable            $previous
     */
    public function __construct(
        public readonly string $render,
        ?string                $message = AUTO,
        ?Throwable             $previous = null,
    ) {
        $backtrace    = \debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 1 );
        $this->caller = [
            'file'     => $backtrace[0]['file'] ?? null,
            'line'     => $backtrace[0]['line'] ?? null,
            'function' => $backtrace[0]['function'],
        ];

        parent::__construct(
            $message
                   ?? $previous?->getMessage()
                   ?? "Error rendering '{$this->render}'.",
            E_RECOVERABLE_ERROR,
            $previous,
        );
    }
}

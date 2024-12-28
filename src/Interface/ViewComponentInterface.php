<?php

declare(strict_types=1);

namespace Core\View\Interface;

/**
 * @phpstan-require-extends \Core\View\Component\AbstractComponent
 */
interface ViewComponentInterface extends ViewInterface
{
    /**
     * Creates a new {@see ComponentInterface} object using the provided `$arguments`.
     *
     * @param array<string, null|array<string,bool|string|string[]>|string|string[]> $arguments
     * @param array<string, string>                                                  $promote
     * @param ?string                                                                $uniqueId  [optional]
     *
     * @return self
     */
    public function create(
        array   $arguments,
        array   $promote = [],
        ?string $uniqueId = null,
    ) : self;
}

<?php

declare(strict_types=1);

namespace Core\View\Template\Engine;

use Support\Interface\DataObject;

final readonly class Configuration extends DataObject
{
    /**
     * @param string   $cacheDirectory
     * @param string[] $templateDirectories
     * @param string   $locale
     * @param bool     $debug
     */
    private function __construct(
        public string $cacheDirectory,
        public array  $templateDirectories,
        public string $locale,
        public bool   $debug,
    ) {}

    /**
     * @param array<string, bool|string|string[]> $configuration
     *
     * @return self
     */
    public static function set( array $configuration ) : self
    {
        /** @var array{cacheDirectory: string, templateDirectories: string[], locale: string, debug: bool} $configuration */
        return new self( ...$configuration );
    }
}

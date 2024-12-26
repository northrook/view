<?php

declare(strict_types=1);

namespace Core\View\Template\Engine;

use Support\FileInfo;
use Support\Interface\DataObject;

final readonly class Configuration extends DataObject
{
    /**
     * @param string   $cacheDirectory
     * @param string[] $templateDirectories `key: FileInfo`
     * @param string   $locale
     * @param bool     $debug
     */
    public function __construct(
        public string $cacheDirectory,
        public array  $templateDirectories,
        public string $locale,
        public bool   $debug,
    ) {}
}

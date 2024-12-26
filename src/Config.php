<?php

declare(strict_types=1);

namespace Core\View;

use Core\Symfony\DependencyInjection\ServiceConfigurator;

final class Config extends ServiceConfigurator
{
    /**
     * @param string   $cacheDirectory
     * @param string[] $templateDirectories
     * @param string   $locale
     * @param bool     $debug
     *
     * @return array<string, bool|float|int|string|string[]>
     */
    public static function templateEngine(
        string $cacheDirectory,
        array  $templateDirectories,
        string $locale = 'en',
        bool   $debug = true,
    ) : array {
        $config = new self( \get_defined_vars() );
        $config->normalizePathParameters();
        return $config->toArray();
    }
}

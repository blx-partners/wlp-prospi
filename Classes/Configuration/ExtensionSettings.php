<?php

declare(strict_types=1);

namespace Blx\WlpProspi\Configuration;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Typisierter Zugriff auf die Extension-Konfiguration (ext_conf_template.txt).
 */
final class ExtensionSettings
{
    public const EXTENSION_KEY = 'wlp_prospi';

    private const DEFAULT_API_BASE_URL = 'https://www.spi-monitoring.de/orientierungsplan/trainercourses';
    private const DEFAULT_CACHE_LIFETIME = 900;
    private const DEFAULT_TIMEOUT = 10;

    public function __construct(private readonly ExtensionConfiguration $extensionConfiguration) {}

    public function getApiBaseUrl(): string
    {
        $value = trim((string)$this->get('apiBaseUrl'));

        return rtrim($value !== '' ? $value : self::DEFAULT_API_BASE_URL, '/');
    }

    public function getCacheLifetime(): int
    {
        $value = $this->get('cacheLifetime');

        return is_numeric($value) ? max(0, (int)$value) : self::DEFAULT_CACHE_LIFETIME;
    }

    public function getTimeout(): int
    {
        $value = (int)$this->get('timeout');

        return $value > 0 ? $value : self::DEFAULT_TIMEOUT;
    }

    private function get(string $path): mixed
    {
        try {
            return $this->extensionConfiguration->get(self::EXTENSION_KEY, $path);
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
            return null;
        }
    }
}

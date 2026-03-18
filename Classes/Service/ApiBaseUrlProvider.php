<?php
declare(strict_types=1);

namespace Blx\WlpProspi\Service;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final class ApiBaseUrlProvider
{
    private const DEFAULT_URL = 'https://prospi-testing.intevation.de/orientierungsplan/trainercourses';

    public function __construct(private readonly ExtensionConfiguration $extensionConfiguration)
    {
    }

    public function get(): string
    {
        $value = $this->getConfigValue('wlp-prospi');
        if ($value === null) {
            $value = $this->getConfigValue('wlp_prospi');
        }
        $configured = trim((string) ($value ?? ''));
        return $configured !== '' ? $configured : self::DEFAULT_URL;
    }

    private function getConfigValue(string $extensionKey): ?string
    {
        try {
            return (string) $this->extensionConfiguration->get($extensionKey, 'apiBaseUrl');
        } catch (ExtensionConfigurationExtensionNotConfiguredException $e) {
            return null;
        }
    }
}

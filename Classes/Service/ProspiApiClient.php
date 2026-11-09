<?php

declare(strict_types=1);

namespace Blx\WlpProspi\Service;

use Blx\WlpProspi\Configuration\ExtensionSettings;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Lädt die Rohdaten der Kurse von der ProSPI-API und hält sie im Cache vor.
 */
final class ProspiApiClient
{
    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly FrontendInterface $cache,
        private readonly ExtensionSettings $settings,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchCourses(): array
    {
        $url = $this->settings->getApiBaseUrl();
        $cacheIdentifier = 'courses_' . sha1($url);

        $cached = $this->cache->get($cacheIdentifier);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $courses = $this->request($url);
        } catch (\Throwable $exception) {
            $this->logger->error('ProSPI-API nicht erreichbar', ['url' => $url, 'exception' => $exception]);
            return [];
        }

        $this->cache->set($cacheIdentifier, $courses, [], $this->settings->getCacheLifetime());

        return $courses;
    }

    public function getBookingUrl(string $uuid): ?string
    {
        $uuid = trim($uuid);
        if ($uuid === '') {
            return null;
        }

        return sprintf('%s/book?code=%s', $this->settings->getApiBaseUrl(), rawurlencode($uuid));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function request(string $url): array
    {
        $response = $this->requestFactory->request($url, 'GET', [
            'headers' => ['Accept' => 'application/json'],
            'timeout' => $this->settings->getTimeout(),
            'allow_redirects' => ['max' => 5],
            'http_errors' => true,
        ]);

        $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \UnexpectedValueException('Unerwartetes Antwortformat der ProSPI-API', 1762679401);
        }

        return array_values(array_filter($data, 'is_array'));
    }
}

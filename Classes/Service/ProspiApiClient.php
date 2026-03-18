<?php
//declare(strict_types=1);
namespace Blx\WlpProspi\Service;

use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Cache\CacheManager;

final class ProspiApiClient
{
    use LoggerAwareTrait;

    private string $baseUrl;

    public function __construct(
        CacheManager $deprecatedCacheManager = null, // kept for backwards compatibility with cached DI container
        string $baseUrl = '',
        private readonly int $timeout = 10
    ) {
        unset($deprecatedCacheManager); // intentionally unused
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function fetchCourses(): array
    {
        $url = $this->baseUrl; // already points to .../trainercourses

        try {
            $data = $this->getJson($url);
        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->error('SPI API request failed', ['exception' => $e, 'url' => $url]);
            }
            return [];
        }

        return array_map([$this, 'normalizeCourse'], $data);
    }

    /**
     * Fetches JSON via cURL (follows redirects) and returns decoded array.
     */
    private function getJson(string $url): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('cURL init failed');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'wlp-prospi/1.0',
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException('cURL request failed: ' . $error);
        }
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(sprintf('Unexpected HTTP status %d', $status));
        }

        return json_decode($body, true, 512, JSON_THROW_ON_ERROR) ?? [];
    }

    private function normalizeCourse(array $c): array
    {
        $limit = (int) ($c['limitTeilnehmende'] ?? 0);
        $count = (int) ($c['teilnehmendenZahl'] ?? 0);
        $free = $limit > 0 ? max($limit - $count, 0) : null;
        $plzRaw = $this->extractPlzValue($c) ?? '';
        if ($plzRaw === '' && isset($c['termine']) && is_array($c['termine'])) {
            foreach ($c['termine'] as $t) {
                $candidate = $this->extractPlzValue(is_array($t) ? $t : []);
                if ($candidate !== null) {
                    $plzRaw = $candidate;
                    break;
                }
            }
        }
        $plzField = $this->normalizeZipString($plzRaw);
        $plzFromOrt = $this->extractZip((string) ($c['ort'] ?? ''));
        $plz = $plzField ?: $plzFromOrt;
        $displayPlz = $plz ?? ($plzRaw !== '' ? $plzRaw : null);

        $courseCity = trim((string) ($c['ort'] ?? ''));
        $terminCity = '';
        if ($courseCity === '' && isset($c['termine']) && is_array($c['termine'])) {
            foreach ($c['termine'] as $t) {
                $candidateCity = trim((string) (($t['ort'] ?? '') ?: ''));
                if ($candidateCity !== '') {
                    $terminCity = $candidateCity;
                    break;
                }
            }
        }
        $city = $courseCity !== '' ? $courseCity : $terminCity;
        $plzDisplay = trim(($displayPlz ? $displayPlz . ' ' : '') . $city);

        $modes = array_values(array_unique(array_map(static fn($t) => $t['typ'] ?? '', $c['termine'] ?? [])));

        $toIso = static function (?string $d) {
            if (!$d)
                return null;
            $dt = \DateTime::createFromFormat('d.m.Y', $d);
            return $dt ? $dt->format('Y-m-d') : $d;
        };

        $c['freeSlots'] = $free;
        $c['isFull'] = $limit > 0 && $free === 0;
        $c['isClosedOrFull'] = (($c['kurstyp'] ?? '') === 'CLOSED') || $c['isFull'];
        $c['plz'] = $plz;
        $c['plzRaw'] = $plzRaw !== '' ? $plzRaw : null;
        $c['plzDisplay'] = $plzDisplay !== '' ? $plzDisplay : ($city !== '' ? $city : null);
        $c['modes'] = $modes;
        $c['modeBadge'] = $this->buildModeBadge($modes, $c['kurstyp'] ?? '');
        $c['vonIso'] = $toIso($c['von'] ?? null);
        $c['bisIso'] = $toIso($c['bis'] ?? null);
        $c['bookingUrl'] = $this->buildBookingUrl($c['uuid'] ?? null);
        $c['termCount'] = $this->determineTermCount($c);

        foreach ($c['termine'] ?? [] as $i => $t) {
            $c['termine'][$i]['terminIso'] = $toIso($t['termin'] ?? null);
        }
        return $c;
    }

    private function determineTermCount(array $course): ?int
    {
        $termine = array_filter(
            $course['termine'] ?? [],
            static fn($t) => trim((string) ($t['termin'] ?? '')) !== ''
        );
        $count = count($termine);
        if ($count > 0) {
            return $count;
        }
        $from = isset($course['vonIso']) ? \DateTime::createFromFormat('Y-m-d', (string) $course['vonIso']) : null;
        $to = isset($course['bisIso']) ? \DateTime::createFromFormat('Y-m-d', (string) $course['bisIso']) : null;
        if ($from && $to) {
            $diff = (int) $from->diff($to)->format('%a');
            return $diff >= 0 ? $diff + 1 : null;
        }
        return null;
    }

    private function buildModeBadge(array $modes, string $fallbackType): string
    {
        $hasOnline = in_array('ONLINE', $modes, true);
        $hasPraesenz = in_array('PRAESENZ', $modes, true);

        if ($hasOnline && $hasPraesenz) {
            return 'PRÄSENZ + ONLINE';
        }
        if ($hasPraesenz) {
            return 'PRÄSENZ';
        }
        if ($hasOnline) {
            return 'ONLINE';
        }
        return $fallbackType ?: 'KURS';
    }

    private function buildBookingUrl(?string $uuid): ?string
    {
        $cleanUuid = trim((string) $uuid);
        if ($cleanUuid === '' || $this->baseUrl === '') {
            return null;
        }
        return sprintf('%s/book?code=%s', $this->baseUrl, rawurlencode($cleanUuid));
    }

    private function extractZip(string $address): ?string
    {
        if (preg_match('/\b(\d{5})\b/', $address, $m)) {
            return $m[1];
        }
        return null;
    }

    private function normalizeZipString(?string $zip): ?string
    {
        if ($zip === null) {
            return null;
        }
        $zip = trim($zip);
        if ($zip === '') {
            return null;
        }
        if (preg_match('/\b(\d{5})\b/', $zip, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractPlzValue(array $data): ?string
    {
        if (!$data) {
            return null;
        }
        $lower = array_change_key_case($data, CASE_LOWER);
        foreach (['plz', 'postalcode', 'postal_code', 'postleitzahl', 'zip', 'zip_code', 'zipcode'] as $key) {
            if (array_key_exists($key, $lower)) {
                $value = trim((string) $lower[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return null;
    }
}

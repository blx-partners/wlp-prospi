<?php

declare(strict_types=1);

namespace Blx\WlpProspi\Service;

use TYPO3\CMS\Core\Context\Context;

/**
 * Ergänzt die Rohdaten eines Kurses um abgeleitete Felder für Filter und Ausgabe.
 */
final class CourseNormalizer
{
    public const MODE_ONLINE = 'ONLINE';
    public const MODE_PRESENCE = 'PRAESENZ';
    public const MODE_HYBRID = 'HYBRID';

    public const TYPE_OPEN = 'OPEN';
    public const TYPE_CLOSED = 'CLOSED';

    private const ZIP_KEYS = ['plz', 'postalcode', 'postal_code', 'postleitzahl', 'zip', 'zip_code', 'zipcode'];

    public function __construct(
        private readonly ProspiApiClient $apiClient,
        private readonly Context $context,
    ) {}

    /**
     * @param array<string, mixed> $course
     * @return array<string, mixed>
     */
    public function normalize(array $course): array
    {
        $termine = $this->getTermine($course);

        $limit = (int)($course['limitTeilnehmende'] ?? 0);
        $participants = (int)($course['teilnehmendenZahl'] ?? 0);
        $freeSlots = $limit > 0 ? max($limit - $participants, 0) : null;

        $zipRaw = $this->extractZipValue($course) ?? '';
        if ($zipRaw === '') {
            foreach ($termine as $termin) {
                $candidate = $this->extractZipValue($termin);
                if ($candidate !== null) {
                    $zipRaw = $candidate;
                    break;
                }
            }
        }
        $zip = $this->findZip($zipRaw) ?? $this->findZip((string)($course['ort'] ?? ''));
        $displayZip = $zip ?? ($zipRaw !== '' ? $zipRaw : null);

        $city = trim((string)($course['ort'] ?? ''));
        if ($city === '') {
            foreach ($termine as $termin) {
                $candidate = trim((string)($termin['ort'] ?? ''));
                if ($candidate !== '') {
                    $city = $candidate;
                    break;
                }
            }
        }
        $location = trim(($displayZip !== null ? $displayZip . ' ' : '') . $city);

        $modes = array_values(array_unique(array_map(
            static fn(array $termin): string => (string)($termin['typ'] ?? ''),
            $termine
        )));

        foreach ($termine as $index => $termin) {
            $termine[$index]['terminIso'] = $this->toIsoDate($termin['termin'] ?? null);
        }

        $type = (string)($course['kurstyp'] ?? '');
        $isFull = $limit > 0 && $freeSlots === 0;

        $course['termine'] = $termine;
        $course['freeSlots'] = $freeSlots;
        $course['isFull'] = $isFull;
        $course['isClosedOrFull'] = $type === self::TYPE_CLOSED || $isFull;
        $course['plz'] = $zip;
        $course['plzRaw'] = $zipRaw !== '' ? $zipRaw : null;
        $course['plzDisplay'] = $location !== '' ? $location : null;
        $course['modes'] = $modes;
        $course['modeBadge'] = $this->resolveModeBadge($modes);
        $course['vonIso'] = $this->toIsoDate($course['von'] ?? null);
        $course['bisIso'] = $this->toIsoDate($course['bis'] ?? null);
        $course['bookingUrl'] = $this->apiClient->getBookingUrl((string)($course['uuid'] ?? ''));
        $course['termCount'] = $this->determineTermCount($course);
        $course['hasFutureFirstStartDate'] = $this->hasFutureFirstStartDate($course);

        return $course;
    }

    /**
     * @param array<string, mixed> $course
     * @return array<int, array<string, mixed>>
     */
    private function getTermine(array $course): array
    {
        $termine = $course['termine'] ?? [];

        return is_array($termine) ? array_filter($termine, 'is_array') : [];
    }

    /**
     * Schlüssel für die Übersetzung des Format-Badges (mode.*), leer ohne Termine.
     *
     * @param list<string> $modes
     */
    private function resolveModeBadge(array $modes): string
    {
        $online = in_array(self::MODE_ONLINE, $modes, true);
        $presence = in_array(self::MODE_PRESENCE, $modes, true);

        return match (true) {
            $online && $presence => self::MODE_HYBRID,
            $presence => self::MODE_PRESENCE,
            $online => self::MODE_ONLINE,
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $course
     */
    private function determineTermCount(array $course): ?int
    {
        $count = count(array_filter(
            $course['termine'],
            static fn(array $termin): bool => trim((string)($termin['termin'] ?? '')) !== ''
        ));
        if ($count > 0) {
            return $count;
        }

        $from = $course['vonIso'] !== null ? \DateTimeImmutable::createFromFormat('!Y-m-d', $course['vonIso']) : false;
        $to = $course['bisIso'] !== null ? \DateTimeImmutable::createFromFormat('!Y-m-d', $course['bisIso']) : false;
        if ($from === false || $to === false || $to < $from) {
            return null;
        }

        return (int)$from->diff($to)->days + 1;
    }

    /**
     * @param array<string, mixed> $course
     */
    private function hasFutureFirstStartDate(array $course): bool
    {
        $today = $this->today();

        if ($course['termine'] !== []) {
            $startDates = array_filter(array_map(
                static fn(array $termin): string => trim((string)($termin['terminIso'] ?? '')),
                $course['termine']
            ));
            if ($startDates === []) {
                return false;
            }
            sort($startDates);

            return $startDates[0] > $today;
        }

        $courseStart = trim((string)($course['vonIso'] ?? ''));

        return $courseStart !== '' && $courseStart > $today;
    }

    private function today(): string
    {
        /** @var \DateTimeImmutable $now */
        $now = $this->context->getPropertyFromAspect('date', 'full');

        return $now->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('Y-m-d');
    }

    private function toIsoDate(mixed $date): ?string
    {
        if (!is_string($date) || $date === '') {
            return null;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!d.m.Y', $date);

        return $parsed !== false ? $parsed->format('Y-m-d') : $date;
    }

    private function findZip(string $value): ?string
    {
        return preg_match('/\b(\d{5})\b/', $value, $matches) === 1 ? $matches[1] : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractZipValue(array $data): ?string
    {
        $lower = array_change_key_case($data, CASE_LOWER);
        foreach (self::ZIP_KEYS as $key) {
            $value = trim((string)($lower[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}

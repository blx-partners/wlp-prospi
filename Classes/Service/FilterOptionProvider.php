<?php

declare(strict_types=1);

namespace Blx\WlpProspi\Service;

/**
 * Baut die Auswahllisten des Filterformulars aus den vorhandenen Kursen.
 */
final class FilterOptionProvider
{
    /**
     * @param list<array<string, mixed>> $courses
     * @return array{trainers: list<string>, topics: list<string>, cities: list<string>, zips: list<string>, termCounts: list<int>}
     */
    public function build(array $courses): array
    {
        $trainers = $topics = $cities = $zips = $termCounts = [];

        foreach ($courses as $course) {
            $this->collect($trainers, $course['trainer'] ?? null);
            $this->collect($cities, $course['ort'] ?? null);
            $this->collect($zips, $course['plz'] ?? null);
            foreach ((array)($course['stichworte'] ?? []) as $topic) {
                $this->collect($topics, $topic);
            }
            foreach ($course['termine'] ?? [] as $termin) {
                $this->collect($cities, $termin['ort'] ?? null);
                $this->collect($zips, $termin['plz'] ?? null);
            }
            $termCount = (int)($course['termCount'] ?? 0);
            if ($termCount > 0) {
                $termCounts[$termCount] = $termCount;
            }
        }

        ksort($termCounts, SORT_NUMERIC);

        return [
            'trainers' => $this->sorted($trainers),
            'topics' => $this->sorted($topics),
            'cities' => $this->sorted($cities),
            'zips' => $this->sorted($zips),
            'termCounts' => array_values($termCounts),
        ];
    }

    /**
     * @param array<string, true> $bucket
     */
    private function collect(array &$bucket, mixed $value): void
    {
        if (is_scalar($value) && ($value = trim((string)$value)) !== '') {
            $bucket[$value] = true;
        }
    }

    /**
     * @param array<string, true> $bucket
     * @return list<string>
     */
    private function sorted(array $bucket): array
    {
        $values = array_map('strval', array_keys($bucket));
        sort($values, SORT_STRING | SORT_FLAG_CASE);

        return $values;
    }
}

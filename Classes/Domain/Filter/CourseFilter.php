<?php

declare(strict_types=1);

namespace Blx\WlpProspi\Domain\Filter;

use Blx\WlpProspi\Service\CourseNormalizer;

/**
 * Die im Formular gewählten Filter, bereinigt und als Prüfregel auf einen Kurs anwendbar.
 */
final class CourseFilter
{
    public const FIELDS = ['mode', 'trainer', 'plz', 'city', 'from', 'to', 'topic', 'kurstyp', 'free', 'termCount'];

    private const PUBLISHED = 'PUBLISHED';

    private function __construct(
        public readonly ?string $mode,
        public readonly ?string $trainer,
        public readonly ?string $plz,
        public readonly ?string $city,
        public readonly ?string $from,
        public readonly ?string $to,
        public readonly ?string $topic,
        public readonly ?string $kurstyp,
        public readonly bool $free,
        public readonly ?int $termCount,
    ) {}

    /**
     * @param array<string, mixed> $arguments
     */
    public static function fromArguments(array $arguments): self
    {
        return new self(
            mode: self::oneOf($arguments['mode'] ?? null, [CourseNormalizer::MODE_PRESENCE, CourseNormalizer::MODE_HYBRID, CourseNormalizer::MODE_ONLINE]),
            trainer: self::text($arguments['trainer'] ?? null),
            plz: self::zip($arguments['plz'] ?? null),
            city: self::text($arguments['city'] ?? null),
            from: self::date($arguments['from'] ?? null),
            to: self::date($arguments['to'] ?? null),
            topic: self::text($arguments['topic'] ?? null),
            kurstyp: self::oneOf($arguments['kurstyp'] ?? null, [CourseNormalizer::TYPE_OPEN, CourseNormalizer::TYPE_CLOSED]),
            free: (string)($arguments['free'] ?? '') === '1',
            termCount: self::positiveInt($arguments['termCount'] ?? null),
        );
    }

    public function isActive(): bool
    {
        return array_filter($this->toArray()) !== [];
    }

    /**
     * Werte für Formular und Chips im Template.
     *
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'trainer' => $this->trainer,
            'plz' => $this->plz,
            'city' => $this->city,
            'from' => $this->from,
            'to' => $this->to,
            'topic' => $this->topic,
            'kurstyp' => $this->kurstyp,
            'free' => $this->free ? '1' : null,
            'termCount' => $this->termCount !== null ? (string)$this->termCount : null,
        ];
    }

    /**
     * @param array<string, mixed> $course
     */
    public function matches(array $course): bool
    {
        if (($course['status'] ?? '') !== self::PUBLISHED) {
            return false;
        }

        if ($this->mode !== null) {
            $modes = $course['modes'] ?? [];
            $matchesMode = $this->mode === CourseNormalizer::MODE_HYBRID
                ? in_array(CourseNormalizer::MODE_ONLINE, $modes, true) && in_array(CourseNormalizer::MODE_PRESENCE, $modes, true)
                : in_array($this->mode, $modes, true);
            if (!$matchesMode) {
                return false;
            }
        }

        if ($this->trainer !== null && mb_stripos((string)($course['trainer'] ?? ''), $this->trainer) === false) {
            return false;
        }

        if ($this->kurstyp !== null && ($course['kurstyp'] ?? '') !== $this->kurstyp) {
            return false;
        }

        if ($this->topic !== null) {
            $topics = array_map('mb_strtolower', array_map('strval', $course['stichworte'] ?? []));
            if (!in_array(mb_strtolower($this->topic), $topics, true)) {
                return false;
            }
        }

        if ($this->termCount !== null && (int)($course['termCount'] ?? 0) !== $this->termCount) {
            return false;
        }

        if ($this->free && (
            ($course['kurstyp'] ?? '') !== CourseNormalizer::TYPE_OPEN
            || ($course['isFull'] ?? false)
            || !($course['hasFutureFirstStartDate'] ?? false)
        )) {
            return false;
        }

        if ($this->plz !== null && ($course['plz'] ?? '') !== $this->plz) {
            return false;
        }

        if ($this->city !== null && mb_stripos((string)($course['ort'] ?? ''), $this->city) === false) {
            return false;
        }

        if ($this->from !== null || $this->to !== null) {
            $start = $course['vonIso'] ?? null;
            $end = $course['bisIso'] ?? null;
            if (!$start || !$end) {
                return false;
            }
            if ($this->from !== null && $end < $this->from) {
                return false;
            }
            if ($this->to !== null && $start > $this->to) {
                return false;
            }
        }

        return true;
    }

    private static function text(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param list<string> $allowed
     */
    private static function oneOf(mixed $value, array $allowed): ?string
    {
        $value = self::text($value);

        return in_array($value, $allowed, true) ? $value : null;
    }

    private static function zip(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string)self::text($value));

        return is_string($digits) && preg_match('/^\d{5}$/', $digits) === 1 ? $digits : null;
    }

    /**
     * Akzeptiert TT.MM.JJJJ und JJJJ-MM-TT, liefert JJJJ-MM-TT.
     */
    private static function date(mixed $value): ?string
    {
        $value = self::text($value);
        if ($value === null) {
            return null;
        }
        foreach (['!d.m.Y', '!Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date !== false && $date->format(substr($format, 1)) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private static function positiveInt(mixed $value): ?int
    {
        $value = self::text($value);
        if ($value === null || !ctype_digit($value)) {
            return null;
        }

        return (int)$value > 0 ? (int)$value : null;
    }
}

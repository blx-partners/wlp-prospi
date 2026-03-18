<?php
declare(strict_types=1);

namespace Blx\WlpProspi\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Blx\WlpProspi\Service\ProspiApiClient;
use Psr\Http\Message\ServerRequestInterface;

final class ProspiController extends ActionController
{
    public function __construct(private readonly ProspiApiClient $api)
    {
    }

    private function normalizeInputDate(?string $d): ?string
    {
        if ($d === null) {
            return null;
        }
        $d = trim($d);
        if ($d === '' || $d === 'tt.mm.jjjj') {
            return null;
        }

        // dd.mm.yyyy -> yyyy-mm-dd
        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $d)) {
            $dt = \DateTime::createFromFormat('d.m.Y', $d);
            return $dt ? $dt->format('Y-m-d') : null;
        }
        // yyyy-mm-dd passt
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return $d;
        }
        return null; // Unbekanntes Format ignorieren
    }

    private function normalizeZip(?string $zip): ?string
    {
        if ($zip === null) {
            return null;
        }
        $zip = trim($zip);
        if ($zip === '') {
            return null;
        }
        $numeric = preg_replace('/\D/', '', $zip);
        if ($numeric === null) {
            return null;
        }
        if (preg_match('/^\d{5}$/', $numeric)) {
            return $numeric;
        }
        return null;
    }

    private function todayIso(): string
    {
        return (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Berlin')))->format('Y-m-d');
    }

    private function hasFutureFirstStartDate(array $course): bool
    {
        $today = $this->todayIso();
        $termine = is_array($course['termine'] ?? null) ? $course['termine'] : [];

        if ($termine !== []) {
            $startDates = [];
            foreach ($termine as $termin) {
                if (!is_array($termin)) {
                    continue;
                }

                $start = trim((string) ($termin['terminIso'] ?? ''));
                if ($start !== '') {
                    $startDates[] = $start;
                }
            }

            if ($startDates === []) {
                return false;
            }

            sort($startDates);
            return $startDates[0] > $today;
        }

        $courseStart = trim((string) ($course['vonIso'] ?? ''));
        return $courseStart !== '' && $courseStart > $today;
    }

    private function enrichCourse(array $course): array
    {
        $course['hasFutureFirstStartDate'] = $this->hasFutureFirstStartDate($course);
        return $course;
    }

    public function listAction(): ResponseInterface
    {
        $serverRequest = $this->resolveServerRequest();

        $query = (array) $serverRequest->getQueryParams();
        $body = (array) $serverRequest->getParsedBody();

        // 1) Namespacete Felder aus GET/POST
        $args = [];
        foreach (['tx_wlpprospi_list', 'tx_spicourses_list'] as $namespace) {
            $args = array_replace(
                $args,
                (array) ($query[$namespace] ?? []),
                (array) ($body[$namespace] ?? [])
            );
        }

        // 2) Fallback: „flache“ Namen (nur falls 1) leer ist)
        if (!$args) {
            $flat = array_replace($query, $body);
            $allowed = ['mode', 'trainer', 'plz', 'city', 'from', 'to', 'topic', 'kurstyp', 'target', 'free', 'termCount'];
            $args = array_intersect_key($flat, array_flip($allowed));
        }

        $filters = [
            'mode' => $args['mode'] ?? null,         // 'ONLINE' | 'PRAESENZ'
            'trainer' => $args['trainer'] ?? null,
            'plz' => $this->normalizeZip($args['plz'] ?? null),
            'city' => $args['city'] ?? null,
            'from' => $this->normalizeInputDate($args['from'] ?? null),
            'to' => $this->normalizeInputDate($args['to'] ?? null),
            'topic' => $args['topic'] ?? null,         // stichwort
            'kurstyp' => $args['kurstyp'] ?? null,         // OPEN | CLOSED
            'target' => $args['target'] ?? null,         // optional
            'free' => $args['free'] ?? null,         // '1' = nur freie Plätze
            'termCount' => $this->normalizePositiveInt(isset($args['termCount']) ? (string) $args['termCount'] : null), // z. B. "2" Termine
            'status' => 'PUBLISHED',
        ];

        // Kurse holen + filtern
        $allCourses = array_map(fn($course) => $this->enrichCourse($course), $this->api->fetchCourses());
        $courses = array_values(array_filter($allCourses, fn($c) => $this->matches($c, $filters)));

        // Auswahllisten
        $trainers = array_values(array_unique(array_filter(array_map(fn($c) => $c['trainer'] ?? '', $allCourses))));
        sort($trainers);

        $topics = [];
        foreach ($allCourses as $c) {
            foreach (($c['stichworte'] ?? []) as $s) {
                $topics[$s] = true;
            }
        }
        $topics = array_keys($topics);
        sort($topics);

        $cities = [];
        $zips = [];
        foreach ($allCourses as $c) {
            $city = trim((string) ($c['ort'] ?? ''));
            if ($city !== '') {
                $cities[$city] = true;
            }
            if (!empty($c['termine']) && is_array($c['termine'])) {
                foreach ($c['termine'] as $t) {
                    $tCity = trim((string) ($t['ort'] ?? ''));
                    if ($tCity !== '') {
                        $cities[$tCity] = true;
                    }
                    $tZip = trim((string) ($t['plz'] ?? ''));
                    if ($tZip !== '') {
                        $zips[$tZip] = true;
                    }
                }
            }
            $zip = trim((string) ($c['plz'] ?? ''));
            if ($zip !== '') {
                $zips[$zip] = true;
            }
        }
        $cities = array_keys($cities);
        sort($cities);
        $zips = array_keys($zips);
        sort($zips);

        $termCounts = [];
        foreach ($allCourses as $c) {
            $count = (int) ($c['termCount'] ?? 0);
            if ($count > 0) {
                $termCounts[$count] = true;
            }
        }
        $termCounts = array_keys($termCounts);
        sort($termCounts, SORT_NUMERIC);

        $termCountOptions = ['' => 'Alle'];
        foreach ($termCounts as $count) {
            $label = $count === 1 ? '1 Termin' : sprintf('%d Termine', $count);
            $termCountOptions[(string) $count] = $label;
        }

        // Optionen für f:form.select (NICHT inline bauen → sonst String!)
        $modeOptions = [
            '' => 'Alle',
            'PRAESENZ' => 'Präsenz',
            'HYBRID' => 'Präsenz + Online',
            'ONLINE' => 'Online',
        ];
        $typeOptions = [
            '' => 'Alle',
            'OPEN' => 'Offen',
            'CLOSED' => 'Geschlossen',
        ];
        $topicOptions = ['' => 'Alle'] + array_combine($topics, $topics);

        $this->view->assignMultiple([
            'courses' => $courses,
            'filters' => $filters,
            'trainers' => $trainers,
            'topics' => $topics,        // nur falls du sie woanders brauchst
            'modeOptions' => $modeOptions,   // für f:form.select
            'typeOptions' => $typeOptions,   // für f:form.select
            'topicOptions' => $topicOptions,  // für f:form.select
            'termCountOptions' => $termCountOptions,
            'cities' => $cities,
            'zips' => $zips,
            'hasActiveFilters' => $this->hasActiveFilters($filters),
        ]);

        return $this->htmlResponse();
    }

    public function detailAction(string $uuid = ''): ResponseInterface
    {
        $course = null;
        foreach ($this->api->fetchCourses() as $c) {
            if (($c['uuid'] ?? null) === $uuid) {
                $course = $c;
                break;
            }
        }
        if (!$course) {
            $this->throwStatus(404, 'Course not found');
        }

        $this->view->assign('course', $course);
        return $this->htmlResponse();
    }

    private function matches(array $c, array $f): bool
    {
        if (($f['status'] ?? null) && (($c['status'] ?? '') !== $f['status']))
            return false;

        if ($f['mode']) {
            $modes = $c['modes'] ?? [];
            if ($f['mode'] === 'HYBRID') {
                if (!(in_array('ONLINE', $modes, true) && in_array('PRAESENZ', $modes, true)))
                    return false;
            } elseif (!in_array($f['mode'], $modes, true)) {
                return false;
            }
        }

        if ($f['trainer'] && stripos((string) ($c['trainer'] ?? ''), (string) $f['trainer']) === false)
            return false;

        if ($f['kurstyp'] && (($c['kurstyp'] ?? '') !== $f['kurstyp']))
            return false;

        if ($f['topic']) {
            $topics = array_map('mb_strtolower', $c['stichworte'] ?? []);
            if (!in_array(mb_strtolower((string) $f['topic']), $topics, true))
                return false;
        }

        if ($f['termCount']) {
            $termCount = (int) ($c['termCount'] ?? 0);
            if ($termCount !== (int) $f['termCount'])
                return false;
        }

        if ($f['free'] === '1') {
            if (
                (string) ($c['kurstyp'] ?? '') !== 'OPEN'
                || ($c['isFull'] ?? false)
                || !($c['hasFutureFirstStartDate'] ?? false)
            ) {
                return false;
            }
        }

        if ($f['plz'] && (($c['plz'] ?? '') !== $f['plz']))
            return false;
        if ($f['city'] && stripos((string) ($c['ort'] ?? ''), (string) $f['city']) === false)
            return false;

        $from = $f['from'] ?? null;
        $to = $f['to'] ?? null;
        if ($from || $to) {
            $cv = $c['vonIso'] ?? null;
            $cb = $c['bisIso'] ?? null;
            if (!$cv || !$cb)
                return false;
            if ($from && $cb < $from)
                return false;
            if ($to && $cv > $to)
                return false;
        }

        return true;
    }

    private function hasActiveFilters(array $filters): bool
    {
        foreach (['mode', 'trainer', 'plz', 'city', 'from', 'to', 'topic', 'kurstyp', 'target', 'free', 'termCount'] as $key) {
            if (!empty($filters[$key])) {
                return true;
            }
        }
        return false;
    }

    private function normalizePositiveInt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? (string) $int : null;
    }

    private function resolveServerRequest(): ServerRequestInterface
    {
        if (method_exists($this->request, 'getHttpRequest')) {
            $httpRequest = $this->request->getHttpRequest();
            if ($httpRequest instanceof ServerRequestInterface) {
                return $httpRequest;
            }
        }

        $global = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($global instanceof ServerRequestInterface) {
            return $global;
        }

        throw new \RuntimeException('Kein ServerRequest verfügbar.');
    }
}

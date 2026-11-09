<?php

declare(strict_types=1);

namespace Blx\WlpProspi\Service;

/**
 * Lesezugriff auf die normalisierten Kurse der ProSPI-API.
 */
final class CourseRepository
{
    /** @var list<array<string, mixed>>|null */
    private ?array $courses = null;

    public function __construct(
        private readonly ProspiApiClient $apiClient,
        private readonly CourseNormalizer $normalizer,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        return $this->courses ??= array_map(
            fn(array $course): array => $this->normalizer->normalize($course),
            $this->apiClient->fetchCourses()
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUuid(string $uuid): ?array
    {
        if ($uuid === '') {
            return null;
        }
        foreach ($this->findAll() as $course) {
            if (($course['uuid'] ?? null) === $uuid) {
                return $course;
            }
        }

        return null;
    }
}

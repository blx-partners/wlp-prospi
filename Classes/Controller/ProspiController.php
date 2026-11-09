<?php

declare(strict_types=1);

namespace Blx\WlpProspi\Controller;

use Blx\WlpProspi\Domain\Filter\CourseFilter;
use Blx\WlpProspi\Service\CourseRepository;
use Blx\WlpProspi\Service\FilterOptionProvider;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class ProspiController extends ActionController
{
    public function __construct(
        private readonly CourseRepository $courseRepository,
        private readonly FilterOptionProvider $filterOptionProvider,
    ) {}

    public function listAction(): ResponseInterface
    {
        $filter = CourseFilter::fromArguments($this->resolveFilterArguments());
        $allCourses = $this->courseRepository->findAll();

        $this->view->assignMultiple([
            'courses' => array_values(array_filter($allCourses, $filter->matches(...))),
            'filters' => $filter->toArray(),
            'hasActiveFilters' => $filter->isActive(),
            'options' => $this->filterOptionProvider->build($allCourses),
        ]);

        return $this->htmlResponse();
    }

    public function detailAction(string $uuid = ''): ResponseInterface
    {
        $course = $this->courseRepository->findByUuid($uuid);
        if ($course === null) {
            $this->throwStatus(404);
        }

        $this->view->assign('course', $course);

        return $this->htmlResponse();
    }

    /**
     * Filter kommen aus dem Plugin-Namespace (tx_wlpprospi_list[...]);
     * für verlinkte Einstiege werden auch flache GET-Parameter (?plz=...) akzeptiert.
     *
     * @return array<string, mixed>
     */
    private function resolveFilterArguments(): array
    {
        $arguments = array_intersect_key($this->request->getArguments(), array_flip(CourseFilter::FIELDS));
        if ($arguments !== []) {
            return $arguments;
        }

        return array_intersect_key($this->request->getQueryParams(), array_flip(CourseFilter::FIELDS));
    }
}

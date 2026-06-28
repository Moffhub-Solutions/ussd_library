<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Menus;

use Moffhub\Ussd\Interfaces\DataProviderInterface;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class SearchablePaginatedMenu extends PaginatedMenu
{
    protected bool $searchable;

    protected array $searchFields;

    public function __construct(string $title, DataProviderInterface $dataProvider, array $options = [])
    {
        parent::__construct($title, $dataProvider, $options);

        $this->searchable = $options['searchable'] ?? true;
        $this->searchFields = $options['search_fields'] ?? ['name', 'title', 'description'];
    }

    #[\Override]
    protected function showInitial(UssdSession $session): UssdResponse
    {
        $session->setFormData('search_query', '');

        return parent::showInitial($session);
    }

    #[\Override]
    protected function handleNavigationCommand(string $command, UssdSession $session): UssdResponse
    {
        $navConfig = $this->config['navigation'];

        if ($command === $navConfig['search'] && $this->searchable) {
            return $this->initiateSearch($session);
        }

        return parent::handleNavigationCommand($command, $session);
    }

    #[\Override]
    protected function processStep(string $input, int $step, UssdSession $session): UssdResponse
    {
        $searchQuery = $session->getFormData('search_query');

        if ($this->searchable && $searchQuery === '' && ! $this->isNavigationCommand($input) && ! is_numeric($input)) {
            $session->setFormData('search_query', $input);

            return $this->showSearchResults($input, $session);
        }

        return parent::processStep($input, $step, $session);
    }

    protected function initiateSearch(UssdSession $session): UssdResponse
    {
        return UssdResponse::continue('Enter search term:');
    }

    protected function showSearchResults(string $query, UssdSession $session): UssdResponse
    {
        $allData = $this->getData($session);

        // A data provider may return either a bare item list or a paginator
        // wrapper (['data' => items, 'total' => ...]). Filter the actual item
        // list, then hand showPage() that list directly (showPage paginates the
        // list and renders the empty message when it is empty).
        $items = $allData['data'] ?? $allData;
        $filteredItems = $this->filterData($items, $query);

        $originalProvider = $this->dataProvider;
        $this->dataProvider = $filteredItems;

        $response = $this->showPage(1, $session);

        $this->dataProvider = $originalProvider;

        return $response;
    }

    #[\Override]
    protected function filterData(array $data, string $query): array
    {
        $query = strtolower(trim($query));

        return array_filter($data, function ($item) use ($query): bool {
            if (is_array($item)) {
                if (array_any($this->searchFields,
                    fn ($field): bool => isset($item[$field]) && str_contains(strtolower((string) $item[$field]), $query))) {
                    return true;
                }
            } else {
                return str_contains(strtolower((string) $item), $query);
            }

            return false;
        });
    }

    #[\Override]
    protected function getNavigationOptions(?int $currentPage = null, ?int $totalPages = null): string
    {
        $options = parent::getNavigationOptions($currentPage, $totalPages);

        if ($this->searchable) {
            $navConfig = $this->config['navigation'];
            $options .= " | {$navConfig['search']} Search";
        }

        return $options;
    }
}

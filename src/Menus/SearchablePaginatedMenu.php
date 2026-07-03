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
        // The search term is persisted in form data (see processStep), and
        // getData() applies the filter on every page, so we simply render page
        // one. Later pages (Next) and the selection snapshot stay filtered
        // instead of silently reverting to the full dataset.
        return $this->showPage(1, $session);
    }

    /**
     * Apply the active search filter to every page fetch. Keying the filter
     * here (rather than swapping the provider for a single render) means paging
     * through search results and selecting an item both operate on the filtered
     * set consistently.
     */
    #[\Override]
    protected function getData(UssdSession $session): array
    {
        $data = parent::getData($session);

        $query = $session->getFormData('search_query', '');
        if (! $this->searchable || ! is_string($query) || $query === '') {
            return $data;
        }

        // A provider may return a bare item list or a wrapper (['data' => ...]).
        // showPage() treats getData()'s return as the item list directly, so
        // return the filtered items as a bare list (an empty list triggers the
        // "no results" page).
        $items = $data['data'] ?? $data;

        return $this->filterData($items, $query);
    }

    #[\Override]
    protected function filterData(array $data, string $query, ?array $searchFields = null): array
    {
        $query = strtolower(trim($query));
        $searchFields = $searchFields ?? $this->searchFields;

        return array_filter($data, function ($item) use ($query, $searchFields): bool {
            if (is_array($item)) {
                if (array_any($searchFields,
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

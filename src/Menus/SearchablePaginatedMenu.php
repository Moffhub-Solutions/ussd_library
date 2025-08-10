<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Menus;

use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class SearchablePaginatedMenu extends PaginatedMenu
{
    protected $searchable;

    protected $searchFields;

    public function __construct($title, $dataProvider, $options = [])
    {
        parent::__construct($title, $dataProvider, $options);

        $this->searchable = $options['searchable'] ?? true;
        $this->searchFields = $options['search_fields'] ?? ['name', 'title', 'description'];
    }

    protected function showInitial(UssdSession $session): UssdResponse
    {
        $session->setFormData('search_query', '');

        return parent::showInitial($session);
    }

    protected function handleNavigationCommand($command, UssdSession $session): UssdResponse
    {
        $navConfig = $this->config['navigation'];

        if ($command === $navConfig['search'] && $this->searchable) {
            return $this->initiateSearch($session);
        }

        return parent::handleNavigationCommand($command, $session);
    }

    protected function processStep($input, $step, UssdSession $session): UssdResponse
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

    protected function showSearchResults($query, UssdSession $session): UssdResponse
    {
        $allData = $this->getData($session);
        $filteredData = $this->filterData($allData, $query);

        $originalProvider = $this->dataProvider;
        $this->dataProvider = $filteredData;

        $response = $this->showPage(1, $session);

        $this->dataProvider = $originalProvider;

        return $response;
    }

    protected function filterData($data, $query): array
    {
        $query = strtolower(trim($query));

        return array_filter($data, function ($item) use ($query) {
            if (is_array($item)) {
                if (array_any($this->searchFields,
                    fn ($field) => isset($item[$field]) && str_contains(strtolower($item[$field]), $query))) {
                    return true;
                }
            } else {
                return str_contains(strtolower($item), $query);
            }

            return false;
        });
    }

    protected function getNavigationOptions($currentPage = null, $totalPages = null): string
    {
        $options = parent::getNavigationOptions($currentPage, $totalPages);

        if ($this->searchable) {
            $navConfig = $this->config['navigation'];
            $options .= " | {$navConfig['search']} Search";
        }

        return $options;
    }
}

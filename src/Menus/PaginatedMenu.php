<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Menus;

use Closure;
use Moffhub\Ussd\Interfaces\DataProviderInterface;
use Moffhub\Ussd\UssdResponse;
use Moffhub\Ussd\UssdSession;

class PaginatedMenu extends UssdMenu
{
    protected string $emptyMessage;

    protected array $filters = [];

    protected ?Closure $itemAction;

    protected ?Closure $itemFormatter;

    protected int $maxSmsLength;

    protected int $reserveChars;

    protected bool $showNavigationHelp;

    protected string $title;

    public function __construct(string $title, protected mixed $dataProvider, array $options = [])
    {
        parent::__construct($title);
        $this->title = $title;

        $defaults = [
            'max_sms_length' => 160,
            'reserve_chars' => 50,
            'item_formatter' => $this->defaultItemFormatter(...),
            'item_action' => null,
            'show_navigation_help' => false,
            'empty_message' => 'No items found.',
            'filters' => [],
        ];

        $options = array_merge($defaults, $options);

        $this->maxSmsLength = $options['max_sms_length'];
        $this->reserveChars = $options['reserve_chars'];
        $this->itemFormatter = $options['item_formatter'];
        $this->itemAction = $options['item_action'];
        $this->showNavigationHelp = $options['show_navigation_help'];
        $this->emptyMessage = $options['empty_message'];
        $this->filters = $options['filters'];
    }

    public function setFilters(array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    public function setItemAction(?Closure $action): static
    {
        $this->itemAction = $action;

        return $this;
    }

    #[\Override]
    public function setItemFormatter(?Closure $formatter): static
    {
        $this->itemFormatter = $formatter;

        return $this;
    }

    protected function defaultItemFormatter(string $key, array|string $item, int $page = 1): string
    {
        if (! is_array($item)) {
            return "$key. $item";
        }
        if (isset($item['name'])) {
            return "$key. {$item['name']}";
        }
        if (isset($item['title'])) {
            return "$key. {$item['title']}";
        }
        if (isset($item['description'])) {
            return "$key. {$item['description']}";
        }

        return "$key. ".json_encode($item);
    }

    /**
     * Route input through this list's own selection/pagination handling rather
     * than the base menu's type dispatch (which, with no explicit type, falls
     * back to simple-menu handling and rejects every selection as invalid).
     */
    #[\Override]
    public function process(string $input, UssdSession $session): UssdResponse
    {
        $input = trim($input);

        if ($input === '') {
            return $this->showInitial($session);
        }

        return $this->processStep($input, $session->getStep(), $session);
    }

    protected function processStep(string $input, int $step, UssdSession $session): UssdResponse
    {
        $input = trim($input);

        if ($this->isNavigationCommand($input)) {
            return $this->handleNavigationCommand($input, $session);
        }

        return $this->handleItemSelection($input, $session);
    }

    protected function isNavigationCommand(string $input): bool
    {
        $navCommands = array_filter([
            $this->getNavigationCommand('next'),
            $this->getNavigationCommand('back'),
            $this->getNavigationCommand('home'),
        ], static fn ($command): bool => $command !== null && $command !== '');

        return in_array($input, $navCommands, true);
    }

    protected function handleNavigationCommand(string $command, UssdSession $session): UssdResponse
    {
        $menuData = $session->getMenuData();
        $currentPage = $menuData['current_page'] ?? 1;
        $totalPages = $menuData['total_pages'] ?? 1;

        $navConfig = [
            'next' => $this->getNavigationCommand('next'),
            'back' => $this->getNavigationCommand('back'),
            'home' => $this->getNavigationCommand('home'),
            'default_menu' => $this->getNavigationCommand('default_menu'),
        ];
        if ($command === $navConfig['back']) {
            if ($this->goBack()) {
                return UssdResponse::continue('Going back...');
            }

            return UssdResponse::end('Cannot go back further.');
        }
        if ($command === $navConfig['home'] && $this->framework) {
            $this->framework->navigateToMenu($this->config['default_menu']);

            return $this->framework->getMenu($this->config['default_menu'])->process('', $session);
        }

        if ($command === $navConfig['next']) {
            if ($currentPage < $totalPages) {
                return $this->showPage($currentPage + 1, $session);
            }

            return UssdResponse::continue('Already on last page.');
        }

        return UssdResponse::continue('Invalid command.');
    }

    protected function showPage(int $pageNumber, UssdSession $session): UssdResponse
    {
        $allData = $this->getData($session);

        if ($allData === []) {
            return UssdResponse::continue($this->title."\n".$this->emptyMessage."\n".$this->getNavigationOptions());
        }

        $paginatedData = $this->calculateSmsBasedPagination($allData, $pageNumber);

        $session->setMenuData([
            'current_page' => $paginatedData['current_page'],
            'total_pages' => $paginatedData['total_pages'],
            'total_items' => count($allData),
            'data' => $allData,
        ]);

        $response = $this->title."\n";
        $response .= "Page {$paginatedData['current_page']} of {$paginatedData['total_pages']}\n\n";

        foreach ($paginatedData['page_items'] as $key => $item) {
            if ($this->itemFormatter instanceof Closure) {
                $response .= call_user_func($this->itemFormatter, $key, $item, $paginatedData['current_page'])."\n";
            } else {
                $response .= $this->defaultItemFormatter($key, $item, $paginatedData['current_page'])."\n";
            }
        }

        $response .= "\n".$this->getNavigationOptions($paginatedData['current_page'], $paginatedData['total_pages']);

        if ($this->showNavigationHelp) {
            $response .= "\n".$this->getNavigationHelp();
        }

        return UssdResponse::continue($response);
    }

    protected function getData(UssdSession $session): array
    {
        $result = [];

        if ($this->dataProvider instanceof DataProviderInterface) {
            $result = $this->dataProvider->getData($session, $this->filters);
        } elseif (is_callable($this->dataProvider)) {
            $result = call_user_func($this->dataProvider, $session, $this->filters);
        } elseif (is_array($this->dataProvider)) {
            $result = $this->dataProvider;
        }

        // Data providers return a paginated envelope {data, total, ...}; the
        // menu displays the item list, so unwrap it. A plain item array (no
        // `data` key) is returned as-is.
        $items = $result;

        if (is_array($result) && isset($result['data']) && is_array($result['data'])) {
            $items = $result['data'];
        }

        if (! is_array($items) || $items === []) {
            return [];
        }

        // Re-key from 1 so the caller presses 1, 2, 3 rather than 0, 1, 2.
        return array_combine(range(1, count($items)), array_values($items));
    }

    protected function getNavigationOptions(?int $currentPage = null, ?int $totalPages = null): string
    {
        $options = [];
        $navConfig = $this->config['navigation'];

        if ($currentPage && $totalPages && $currentPage < $totalPages) {
            $options[] = $navConfig['next'].' Next';
        }

        $options[] = $navConfig['back'].' Back';
        $options[] = $navConfig['home'].' Home';

        return implode(' | ', $options);
    }

    protected function calculateSmsBasedPagination(array $allData, int $requestedPage): array
    {
        $pages = [];
        $currentPageItems = [];
        $currentPageLength = 0;

        $baseLength = strlen($this->title) + 30 + $this->reserveChars;
        $availableLength = $this->maxSmsLength - $baseLength;
        $pageNumber = 1;

        foreach ($allData as $key => $item) {
            if ($this->itemFormatter instanceof Closure) {
                $formattedItem = call_user_func($this->itemFormatter, $key, $item, $pageNumber);
            } else {
                $formattedItem = $this->defaultItemFormatter($key, $item, $pageNumber);
            }
            $itemLength = strlen((string) $formattedItem) + 1;

            if ($currentPageLength + $itemLength > $availableLength && $currentPageItems !== []) {
                $pages[$pageNumber] = $currentPageItems;
                $pageNumber++;
                $currentPageItems = [];
                $currentPageLength = 0;
            }

            $currentPageItems[$key] = $item;
            $currentPageLength += $itemLength;
        }

        if ($currentPageItems !== []) {
            $pages[$pageNumber] = $currentPageItems;
        }

        $totalPages = count($pages);
        $requestedPage = max(1, min($requestedPage, $totalPages));

        return [
            'current_page' => $requestedPage,
            'total_pages' => $totalPages,
            'page_items' => $pages[$requestedPage] ?? [],
            'all_pages' => $pages,
        ];
    }

    protected function getNavigationHelp(): string
    {
        $navConfig = $this->config['navigation'];

        return "Commands: {$navConfig['back']}=Back, {$navConfig['home']}=Home, {$navConfig['next']}=Next";
    }

    #[\Override]
    protected function handleItemSelection(string $input, UssdSession $session): UssdResponse
    {
        if (! is_numeric($input)) {
            return UssdResponse::continue('Invalid selection. Please try again.');
        }

        $menuData = $session->getMenuData();
        $allData = $menuData['data'] ?? [];

        $selectedItem = null;
        $selectedKey = null;

        foreach ($allData as $key => $item) {
            if ($key == $input || (is_array($item) && isset($item['id']) && $item['id'] == $input)) {
                $selectedItem = $item;
                $selectedKey = $key;
                break;
            }
        }

        if (! $selectedItem) {
            return UssdResponse::continue('Item not found. Please try again.');
        }

        if ($this->itemAction && $this->framework) {
            return call_user_func($this->itemAction, $selectedKey, $selectedItem, $session, $this->framework);
        }

        $itemDisplay = is_array($selectedItem) ? json_encode($selectedItem) : $selectedItem;

        return UssdResponse::end('You selected: '.$itemDisplay);
    }

    #[\Override]
    protected function showInitial(UssdSession $session): UssdResponse
    {
        $session->setMenuData([
            'current_page' => 1,
            'total_pages' => 0,
            'total_items' => 0,
            'data' => [],
        ]);

        return $this->showPage(1, $session);
    }
}

<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Interfaces;

use Moffhub\Ussd\UssdSession;

interface DataProviderInterface
{
    /**
     * Get paginated data based on session and filters
     *
     * @param  UssdSession  $session  The current session
     * @param  array<string, mixed>  $filters  Optional filters
     * @return array{data: array<int, mixed>, total: int, current_page: int, per_page: int, has_more: bool}
     */
    public function getData(UssdSession $session, array $filters = []): array;

    /**
     * Get a specific item by ID
     *
     * @param  string|int  $id  The item ID
     * @param  UssdSession  $session  The current session
     * @return mixed|null The item data or null if not found
     */
    public function getItem(string|int $id, UssdSession $session): mixed;

    /**
     * Search for items based on query
     *
     * @param  string  $query  The search query
     * @param  UssdSession  $session  The current session
     * @param  array<string>  $fields  Fields to search in
     * @return array{data: array<int, mixed>, total: int}
     */
    public function search(string $query, UssdSession $session, array $fields = []): array;
}

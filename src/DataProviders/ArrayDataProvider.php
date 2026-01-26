<?php

declare(strict_types=1);

namespace Moffhub\Ussd\DataProviders;

use Moffhub\Ussd\Interfaces\DataProviderInterface;
use Moffhub\Ussd\UssdSession;

class ArrayDataProvider implements DataProviderInterface
{
    public function __construct(protected array $data) {}

    public function getData(UssdSession $session, array $filters = []): array
    {
        $data = $this->data;

        foreach ($filters as $field => $value) {
            if ($field === 'page') {
                continue;
            }
            if ($field === 'per_page') {
                continue;
            }
            $data = array_filter($data, fn ($item): bool => is_array($item) && array_key_exists($field, $item) && $item[$field] == $value);
        }

        $data = array_values($data);

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, (int) ($filters['per_page'] ?? 20));
        $total = count($data);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($data, $offset, $perPage);
        $hasMore = ($offset + count($slice)) < $total;

        return [
            'data' => $slice,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
            'has_more' => $hasMore,
        ];
    }

    public function getItem(string|int $id, UssdSession $session): mixed
    {
        return array_find(
            $this->data,
            fn ($item, $_): bool => (is_array($item) && isset($item['id']) && $item['id'] == $id) || $item == $id
        );
    }

    public function search(string $query, UssdSession $session, array $fields = ['name', 'title']): array
    {
        $q = strtolower(trim($query));

        $filteredData = array_filter($this->data, function ($item) use ($q, $fields): bool {
            if (is_array($item)) {
                return array_any(
                    $fields,
                    fn ($field, $_): bool => isset($item[$field]) && is_scalar($item[$field]) && str_contains(strtolower((string) $item[$field]), $q)
                );
            }

            return is_scalar($item) && str_contains(strtolower((string) $item), $q);
        });

        $filteredData = array_values($filteredData);

        return [
            'data' => $filteredData,
            'total' => count($filteredData),
        ];
    }
}

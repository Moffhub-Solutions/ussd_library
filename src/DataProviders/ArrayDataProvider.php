<?php

declare(strict_types=1);

namespace Moffhub\Ussd\DataProviders;

use Moffhub\Ussd\Interfaces\DataProviderInterface;
use Moffhub\Ussd\UssdSession;

class ArrayDataProvider implements DataProviderInterface
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getData(UssdSession $session, array $filters = []): array
    {
        $data = $this->data;

        foreach ($filters as $field => $value) {
            $data = array_filter($data, function ($item) use ($field, $value) {
                return isset($item[$field]) && $item[$field] == $value;
            });
        }

        return $data;
    }

    public function getItem(string|int $id, UssdSession $session): mixed
    {
        return array_find($this->data,
            fn ($item) => (is_array($item) && isset($item['id']) && $item['id'] == $id) || $item == $id);

    }

    public function search(string $query, UssdSession $session, array $fields = ['name', 'title']): array
    {
        $query = strtolower(trim($query));

        $filteredData = array_filter($this->data, function ($item) use ($query, $fields) {
            if (is_array($item)) {
                if (array_any($fields,
                    fn ($field) => isset($item[$field]) && str_contains(strtolower($item[$field]), $query))) {
                    return true;
                }
            } else {
                return str_contains(strtolower($item), $query);
            }

            return false;
        });

        return [
            'data' => array_values($filteredData),
            'total' => count($filteredData),
        ];
    }
}

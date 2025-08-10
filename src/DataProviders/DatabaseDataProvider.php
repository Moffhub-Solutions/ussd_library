<?php

declare(strict_types=1);

namespace Moffhub\Ussd\DataProviders;

use Moffhub\Ussd\Interfaces\DataProviderInterface;
use Moffhub\Ussd\UssdSession;

class DatabaseDataProvider implements DataProviderInterface
{
    protected mixed $model;

    protected mixed $query;

    public function __construct(mixed $model, mixed $query = null)
    {
        $this->model = $model;
        $this->query = $query;
    }

    public function getData(UssdSession $session, array $filters = []): array
    {
        $query = $this->getBaseQuery();

        foreach ($filters as $field => $value) {
            $query->where($field, $value);
        }

        return $query->get()->toArray();
    }

    public function getItem(string|int $id, UssdSession $session): mixed
    {
        return $this->getBaseQuery()->find($id);
    }

    public function search(string $query, UssdSession $session, array $fields = ['name']): array
    {
        $builder = $this->getBaseQuery();

        $builder->where(function ($q) use ($query, $fields) {
            foreach ($fields as $field) {
                $q->orWhere($field, 'LIKE', "%$query%");
            }
        });

        return $builder->get()->toArray();
    }

    protected function getBaseQuery(): mixed
    {
        if ($this->query && is_callable($this->query)) {
            return call_user_func($this->query);
        }

        return $this->model::query();
    }
}

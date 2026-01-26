<?php

declare(strict_types=1);

namespace Moffhub\Ussd\DataProviders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Moffhub\Ussd\Interfaces\DataProviderInterface;
use Moffhub\Ussd\UssdSession;

class DatabaseDataProvider implements DataProviderInterface
{
    /** @var class-string<Model> */
    protected string $model;

    /** @var (callable(): Builder<Model>)|null */
    protected mixed $query;

    /**
     * @param  class-string<Model>  $model
     * @param  (callable(): Builder<Model>)|null  $query
     */
    public function __construct(string $model, ?callable $query = null)
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

        $page = $session->get('page', 1);
        $perPage = $filters['per_page'] ?? 10;

        $total = (clone $query)->count();
        $data = $query->skip(($page - 1) * $perPage)->take($perPage)->get()->toArray();

        return [
            'data' => $data,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
            'has_more' => ($page * $perPage) < $total,
        ];
    }

    public function getItem(string|int $id, UssdSession $session): mixed
    {
        return $this->getBaseQuery()->find($id);
    }

    public function search(string $query, UssdSession $session, array $fields = ['name']): array
    {
        $builder = $this->getBaseQuery();

        $builder->where(function ($q) use ($query, $fields): void {
            foreach ($fields as $field) {
                $q->orWhere($field, 'LIKE', "%$query%");
            }
        });

        $data = $builder->get()->toArray();

        return [
            'data' => $data,
            'total' => count($data),
        ];
    }

    /**
     * @return Builder<Model>
     */
    protected function getBaseQuery(): Builder
    {
        if ($this->query !== null) {
            return call_user_func($this->query);
        }

        return $this->model::query();
    }
}

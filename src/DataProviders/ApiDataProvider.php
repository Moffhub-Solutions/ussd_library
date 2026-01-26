<?php

declare(strict_types=1);

namespace Moffhub\Ussd\DataProviders;

use Moffhub\Ussd\Interfaces\DataProviderInterface;
use Moffhub\Ussd\UssdSession;

class ApiDataProvider implements DataProviderInterface
{
    protected string $baseUrl;

    public function __construct(string $baseUrl, protected array $headers = [], protected mixed $auth = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function getData(UssdSession $session, array $filters = []): array
    {
        $page = $session->get('page', 1);
        $perPage = $filters['per_page'] ?? 10;

        $url = $this->baseUrl.'/data';
        $queryParams = array_merge($filters, ['page' => $page, 'per_page' => $perPage]);
        $url .= '?'.http_build_query($queryParams);

        $response = $this->makeRequest($url);

        // If API returns paginated data structure, use it
        if (isset($response['data']) && isset($response['total'])) {
            return [
                'data' => $response['data'],
                'total' => $response['total'],
                'current_page' => $response['current_page'] ?? $page,
                'per_page' => $response['per_page'] ?? $perPage,
                'has_more' => $response['has_more'] ?? (($page * $perPage) < ($response['total'] ?? 0)),
            ];
        }

        // If API returns raw array, wrap it
        $data = is_array($response) ? $response : [];

        return [
            'data' => $data,
            'total' => count($data),
            'current_page' => $page,
            'per_page' => $perPage,
            'has_more' => false,
        ];
    }

    public function getItem(string|int $id, UssdSession $session): mixed
    {
        $url = $this->baseUrl.'/data/'.$id;
        $response = $this->makeRequest($url);

        // Return null if empty or error, otherwise return the item
        if (empty($response)) {
            return null;
        }

        // If API wraps item in 'data' key, unwrap it
        return $response['data'] ?? $response;
    }

    public function search(string $query, UssdSession $session, array $fields = []): array
    {
        $url = $this->baseUrl.'/search';
        $params = ['q' => $query, 'fields' => implode(',', $fields)];
        $url .= '?'.http_build_query($params);

        $response = $this->makeRequest($url);

        // If API returns structured data, use it
        if (isset($response['data'])) {
            return [
                'data' => $response['data'],
                'total' => $response['total'] ?? count($response['data']),
            ];
        }

        // If API returns raw array, wrap it
        $data = is_array($response) ? $response : [];

        return [
            'data' => $data,
            'total' => count($data),
        ];
    }

    protected function makeRequest(string $url): mixed
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $this->buildHeaders(),
                'timeout' => 30,
            ],
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            return [];
        }

        return json_decode($response, true) ?: [];
    }

    protected function buildHeaders(): string
    {
        $headers = $this->headers;

        if ($this->auth) {
            if (isset($this->auth['type']) && $this->auth['type'] === 'bearer') {
                $headers[] = 'Authorization: Bearer '.$this->auth['token'];
            } elseif (isset($this->auth['username']) && isset($this->auth['password'])) {
                $auth = base64_encode($this->auth['username'].':'.$this->auth['password']);
                $headers[] = 'Authorization: Basic '.$auth;
            }
        }

        return implode("\r\n", $headers);
    }
}

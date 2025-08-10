<?php

declare(strict_types=1);

namespace Moffhub\Ussd\DataProviders;

use Moffhub\Ussd\Interfaces\DataProviderInterface;
use Moffhub\Ussd\UssdSession;

class ApiDataProvider implements DataProviderInterface
{
    protected string $baseUrl;

    protected array $headers;

    protected mixed $auth;

    public function __construct(string $baseUrl, array $headers = [], mixed $auth = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->headers = $headers;
        $this->auth = $auth;
    }

    public function getData(UssdSession $session, array $filters = []): array
    {
        $url = $this->baseUrl.'/data';
        if (! empty($filters)) {
            $url .= '?'.http_build_query($filters);
        }

        return $this->makeRequest($url);
    }

    public function getItem(string|int $id, UssdSession $session): mixed
    {
        $url = $this->baseUrl.'/data/'.$id;

        return $this->makeRequest($url);
    }

    public function search(string $query, UssdSession $session, array $fields = []): array
    {
        $url = $this->baseUrl.'/search';
        $params = ['q' => $query, 'fields' => implode(',', $fields)];
        $url .= '?'.http_build_query($params);

        return $this->makeRequest($url);
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

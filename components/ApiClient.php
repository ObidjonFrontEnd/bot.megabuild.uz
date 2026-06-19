<?php

namespace app\components;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use yii\base\Component;

class ApiClient extends Component
{
    private ?Client $client = null;
    private ?string $token  = null;

    public function withToken(string $token): static
    {
        $clone = clone $this;
        $clone->client = null;
        $clone->token  = $token;
        return $clone;
    }

    private function getClient(): Client
    {
        if ($this->client === null) {
            $baseUrl = rtrim(\Yii::$app->params['yii2ApiBaseUrl'], '/') . '/';
            $headers = [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ];
            if ($this->token) {
                $headers['Authorization'] = 'Bearer ' . $this->token;
            }
            $this->client = new Client([
                'base_uri' => $baseUrl,
                'headers'  => $headers,
                'timeout'  => 10,
            ]);
        }
        return $this->client;
    }

    // ─── Базовые HTTP-методы ─────────────────────────────────────────────────

    public function get(string $path, array $query = []): array
    {
        try {
            $response = $this->getClient()->get($path, ['query' => $query]);
            return $this->unwrap($response->getBody()->getContents());
        } catch (GuzzleException $e) {
            \Yii::error('[API GET] ' . $path . ': ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function post(string $path, array $body = [], array $query = []): array
    {
        try {
            $response = $this->getClient()->post($path, ['json' => $body, 'query' => $query]);
            return $this->unwrap($response->getBody()->getContents());
        } catch (GuzzleException $e) {
            \Yii::error('[API POST] ' . $path . ': ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function put(string $path, array $body = [], array $query = []): array
    {
        try {
            $response = $this->getClient()->put($path, ['json' => $body, 'query' => $query]);
            return $this->unwrap($response->getBody()->getContents());
        } catch (GuzzleException $e) {
            \Yii::error('[API PUT] ' . $path . ': ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // {"success":true,"data":{...}} → возвращает data или весь ответ
    private function unwrap(string $body): array
    {
        $decoded = json_decode($body, true) ?? [];
        return $decoded['data'] ?? $decoded;
    }

    // ─── Заявки ──────────────────────────────────────────────────────────────

    public function getRequests(array $params = []): array
    {
        return $this->get('request/index', $params);
    }

    public function getRequest(int $requestId): array
    {
        return $this->get('request/get-one', ['request_id' => $requestId]);
    }

    public function getRequestMaterials(int $requestId): array
    {
        return $this->get('request-materials/index', ['request_id' => $requestId, 'size' => 100]);
    }

    // ─── Котировки ────────────────────────────────────────────────────────────

    public function getQuotes(int $requestId, array $params = []): array
    {
        return $this->get('request-quotes/index', array_merge(['request_id' => $requestId], $params));
    }

    public function createQuote(int $requestId, array $data): array
    {
        return $this->post('request-quotes/create', $data, ['request_id' => $requestId]);
    }

    public function updateQuote(int $quoteId, array $data): array
    {
        return $this->put('request-quotes/update', $data, ['id' => $quoteId]);
    }

    public function setWinner(int $quoteId): array
    {
        return $this->post('request-quotes/set-winner', [], ['id' => $quoteId]);
    }

    public function unsetWinner(int $quoteId): array
    {
        return $this->post('request-quotes/unset-winner', [], ['id' => $quoteId]);
    }

    // ─── Поставщики ───────────────────────────────────────────────────────────

    public function searchSuppliers(string $search, int $size = 10): array
    {
        return $this->get('supplier/index', ['search' => $search, 'size' => $size]);
    }

    public function createSupplier(array $data): array
    {
        return $this->post('supplier/create', $data);
    }

    // ─── Аутентификация ───────────────────────────────────────────────────────

    public function login(string $username, string $password): array
    {
        return $this->post('auth/login', ['username' => $username, 'password_hash' => $password]);
    }

    public function setTg(int $tgId, string $token): array
    {
        return $this->withToken($token)->post('user/set-tg', ['tg_id' => $tgId]);
    }

    // ─── Пользователи ─────────────────────────────────────────────────────────

    public function getUsersByRole(string $role, int $size = 200): array
    {
        return $this->get('user/index', ['role' => $role, 'size' => $size]);
    }

    // ─── Трекинг ──────────────────────────────────────────────────────────────

    public function getTracking(int $requestId): array
    {
        return $this->get('request-tracking/index', ['request_id' => $requestId]);
    }

    public function createTracking(int $requestId, string $description): array
    {
        return $this->post('request-tracking/create', [
            'request_id'  => $requestId,
            'description' => $description,
        ]);
    }
}

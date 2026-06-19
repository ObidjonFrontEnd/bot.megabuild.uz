<?php

namespace App\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ApiClient
{
    private Client $client;

    public function __construct(private readonly ?string $token = null)
    {
        $baseUrl = rtrim($_ENV['YII2_API_BASE_URL'] ?? '', '/') . '/';

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

    // ─── Базовые методы ───────────────────────────────────────────────────────

    public function get(string $path, array $query = []): array
    {
        try {
            $response = $this->client->get($path, ['query' => $query]);
            return $this->unwrap($response->getBody()->getContents());
        } catch (GuzzleException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function post(string $path, array $body = [], array $query = []): array
    {
        try {
            $response = $this->client->post($path, ['json' => $body, 'query' => $query]);
            return $this->unwrap($response->getBody()->getContents());
        } catch (GuzzleException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function put(string $path, array $body = [], array $query = []): array
    {
        try {
            $response = $this->client->put($path, ['json' => $body, 'query' => $query]);
            return $this->unwrap($response->getBody()->getContents());
        } catch (GuzzleException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Разворачивает {"success":true,"data":{...}} → возвращает data или весь ответ
    private function unwrap(string $body): array
    {
        $decoded = json_decode($body, true) ?? [];
        return $decoded['data'] ?? $decoded;
    }

    // ─── Заявки ───────────────────────────────────────────────────────────────

    public function getRequests(array $params = []): array
    {
        return $this->get('request/index', $params);
    }

    public function getRequest(int $requestId): array
    {
        return $this->get('request/get-one', ['request_id' => $requestId]);
    }

    /** Возвращает материалы заявки с полем note */
    public function getRequestMaterials(int $requestId): array
    {
        return $this->get('request-materials/index', ['request_id' => $requestId, 'size' => 100]);
    }

    // ─── Котировки ────────────────────────────────────────────────────────────

    public function getQuotes(int $requestId, array $params = []): array
    {
        return $this->get('request-quotes/index', array_merge(['request_id' => $requestId], $params));
    }

    public function getQuote(int $quoteId): array
    {
        return $this->get('request-quotes/get-one', ['id' => $quoteId]);
    }

    /** Создать предложение для одного материала */
    public function createQuote(int $requestId, array $data): array
    {
        return $this->post('request-quotes/create', $data, ['request_id' => $requestId]);
    }

    /** Обновить предложение (только если не победитель) */
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

    public function setLowestRejected(int $quoteId, string $reason): array
    {
        return $this->post('request-quotes/set-lowest-rejected', ['reason' => $reason], ['id' => $quoteId]);
    }

    public function unsetLowestRejected(int $quoteId): array
    {
        return $this->post('request-quotes/unset-lowest-rejected', [], ['id' => $quoteId]);
    }

    // ─── Поставщики ───────────────────────────────────────────────────────────

    /**
     * Поиск поставщика по имени/ИНН.
     * Параметр search задокументирован в requar-api.md — нужно добавить на Yii2.
     */
    public function searchSuppliers(string $search, int $size = 10): array
    {
        return $this->get('supplier/index', ['search' => $search, 'size' => $size]);
    }

    public function createSupplier(array $data): array
    {
        return $this->post('supplier/create', $data);
    }

    // ─── Пользователи ─────────────────────────────────────────────────────────

    /** Получить всех пользователей по роли (для рассылки уведомлений) */
    public function getUsersByRole(string $role, int $size = 200): array
    {
        return $this->get('user/index', ['role' => $role, 'size' => $size]);
    }

    /** Привязать tg_id к своему аккаунту */
    public function setTelegramId(int $tgId): array
    {
        return $this->post('user/set-tg', ['tg_id' => $tgId]);
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

<?php

namespace app\models;

use yii\db\ActiveRecord;

class Session extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'sessions';
    }

    public static function primaryKey(): array
    {
        return ['tg_id'];
    }

    public static function findByTgId(int $tgId): ?static
    {
        return static::findOne(['tg_id' => $tgId]);
    }

    public static function saveSession(int $tgId, int $userId, string $fullName, string $role, string $token): void
    {
        // Удаляем "мусорную" запись tg_id=0 для этого user_id (от логинов без привязки)
        if ($tgId !== 0) {
            static::deleteAll(['tg_id' => 0, 'user_id' => $userId]);
        }

        $session = static::findOne(['tg_id' => $tgId]) ?? new static();
        $now = time();

        if ($session->isNewRecord) {
            $session->tg_id      = $tgId;
            $session->created_at = $now;
        }

        $session->user_id    = $userId;
        $session->full_name  = $fullName;
        $session->role       = $role;
        $session->token      = $token;
        $session->updated_at = $now;
        $session->save(false);
    }

    public static function deleteByTgId(int $tgId): void
    {
        static::deleteAll(['tg_id' => $tgId]);
    }
}

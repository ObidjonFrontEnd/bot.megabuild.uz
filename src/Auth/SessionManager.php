<?php

namespace App\Auth;

use App\Storage\Database;

class SessionManager
{
    public static function save(int $tgId, int $userId, string $fullName, string $role, string $token): void
    {
        $pdo = Database::getInstance();
        $now = time();

        $stmt = $pdo->prepare("
            INSERT INTO sessions (tg_id, user_id, full_name, role, token, created_at, updated_at)
            VALUES (:tg_id, :user_id, :full_name, :role, :token, :created_at, :updated_at)
            ON CONFLICT(tg_id) DO UPDATE SET
                user_id    = excluded.user_id,
                full_name  = excluded.full_name,
                role       = excluded.role,
                token      = excluded.token,
                updated_at = excluded.updated_at
        ");

        $stmt->execute([
            'tg_id'      => $tgId,
            'user_id'    => $userId,
            'full_name'  => $fullName,
            'role'       => $role,
            'token'      => $token,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public static function get(int $tgId): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT * FROM sessions WHERE tg_id = :tg_id");
        $stmt->execute(['tg_id' => $tgId]);
        return $stmt->fetch() ?: null;
    }

    public static function delete(int $tgId): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE tg_id = :tg_id");
        $stmt->execute(['tg_id' => $tgId]);
    }

    public static function exists(int $tgId): bool
    {
        return self::get($tgId) !== null;
    }
}

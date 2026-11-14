<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class Setting {
    private PDO $db;
    private static array $cache = [];

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getAll(): array {
        if (!empty(self::$cache)) {
            return self::$cache;
        }
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM website_settings");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        self::$cache = $rows;
        return $rows;
    }

    public function get(string $key, ?string $default = null): ?string {
        $settings = $this->getAll();
        return $settings[$key] ?? $default;
    }

    public function set(string $key, ?string $value): bool {
        // Upsert for MySQL & SQLite
        $stmt = $this->db->prepare("
            INSERT INTO website_settings (setting_key, setting_value) 
            VALUES (:k, :v) 
            ON CONFLICT(setting_key) DO UPDATE SET setting_value = :v2
        ");
        try {
            $res = $stmt->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
        } catch (\PDOException $e) {
            // MySQL fallback syntax
            $stmt = $this->db->prepare("
                INSERT INTO website_settings (setting_key, setting_value) 
                VALUES (:k, :v) 
                ON DUPLICATE KEY UPDATE setting_value = :v2
            ");
            $res = $stmt->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
        }
        self::$cache[$key] = $value;
        return $res;
    }

    public function updateBulk(array $settings): bool {
        foreach ($settings as $k => $v) {
            $this->set((string)$k, (string)$v);
        }
        return true;
    }
}

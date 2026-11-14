<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class Category {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Get all disease categories with medicine counts
     */
    public function getAll(bool $onlyFeatured = false): array {
        $sql = "
            SELECT c.*, COUNT(m.id) AS medicine_count 
            FROM disease_categories c 
            LEFT JOIN medicines m ON c.id = m.category_id AND m.is_active = 1
        ";
        if ($onlyFeatured) {
            $sql .= " WHERE c.is_featured = 1";
        }
        $sql .= " GROUP BY c.id ORDER BY c.name ASC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM disease_categories WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array {
        $stmt = $this->db->prepare("SELECT * FROM disease_categories WHERE slug = :s LIMIT 1");
        $stmt->execute(['s' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($data['name'])));
        $slug = trim($slug, '-');

        $stmt = $this->db->prepare("
            INSERT INTO disease_categories (name, slug, icon, description, is_featured) 
            VALUES (:name, :slug, :icon, :description, :is_featured)
        ");
        $stmt->execute([
            'name'        => $data['name'],
            'slug'        => $slug,
            'icon'        => !empty($data['icon']) ? $data['icon'] : 'fa-heartbeat',
            'description' => $data['description'] ?? null,
            'is_featured' => isset($data['is_featured']) ? (int)$data['is_featured'] : 1
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE disease_categories 
            SET name = :name, icon = :icon, description = :description, is_featured = :is_featured 
            WHERE id = :id
        ");
        return $stmt->execute([
            'name'        => $data['name'],
            'icon'        => $data['icon'] ?? 'fa-heartbeat',
            'description' => $data['description'] ?? null,
            'is_featured' => isset($data['is_featured']) ? (int)$data['is_featured'] : 1,
            'id'          => $id
        ]);
    }

    public function delete(int $id): bool {
        // Check if any medicines are mapped to this disease
        $check = $this->db->prepare("SELECT COUNT(*) FROM medicines WHERE category_id = :id");
        $check->execute(['id' => $id]);
        if ((int)$check->fetchColumn() > 0) {
            return false; // Prevent orphaned foreign key
        }
        $stmt = $this->db->prepare("DELETE FROM disease_categories WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}

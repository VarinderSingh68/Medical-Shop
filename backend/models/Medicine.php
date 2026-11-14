<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class Medicine {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Get All Medicines with optional search and category filter
     */
    public function getAll(?int $categoryId = null, ?string $search = null, bool $onlyActive = true, int $limit = 500, int $offset = 0): array {
        $sql = "
            SELECT m.*, c.name AS category_name, c.slug AS category_slug 
            FROM medicines m 
            JOIN disease_categories c ON m.category_id = c.id 
            WHERE 1=1
        ";
        $params = [];

        if ($onlyActive) {
            $sql .= " AND m.is_active = 1";
        }
        if ($categoryId !== null && $categoryId > 0) {
            $sql .= " AND m.category_id = :cat_id";
            $params['cat_id'] = $categoryId;
        }
        if (!empty($search)) {
            $sql .= " AND (m.name LIKE :search OR m.generic_name LIKE :search OR m.indications LIKE :search OR m.manufacturer LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY m.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count total medicines matching filters (for pagination)
     */
    public function count(?int $categoryId = null, ?string $search = null, bool $onlyActive = true): int {
        $sql = "SELECT COUNT(*) FROM medicines m WHERE 1=1";
        $params = [];

        if ($onlyActive) {
            $sql .= " AND m.is_active = 1";
        }
        if ($categoryId !== null && $categoryId > 0) {
            $sql .= " AND m.category_id = :cat_id";
            $params['cat_id'] = $categoryId;
        }
        if (!empty($search)) {
            $sql .= " AND (m.name LIKE :search OR m.generic_name LIKE :search OR m.indications LIKE :search OR m.manufacturer LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Find single medicine by ID
     */
    public function find(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT m.*, c.name AS category_name, c.slug AS category_slug 
            FROM medicines m 
            JOIN disease_categories c ON m.category_id = c.id 
            WHERE m.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Create New Medicine
     */
    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO medicines (
                category_id, name, generic_name, dosage_form, strength,
                manufacturer, batch_number, expiry_date, purchase_price,
                selling_price, stock_quantity, low_stock_threshold,
                requires_prescription, image, indications, side_effects, is_active
            ) VALUES (
                :category_id, :name, :generic_name, :dosage_form, :strength,
                :manufacturer, :batch_number, :expiry_date, :purchase_price,
                :selling_price, :stock_quantity, :low_stock_threshold,
                :requires_prescription, :image, :indications, :side_effects, :is_active
            )
        ");
        $stmt->execute([
            'category_id'          => $data['category_id'],
            'name'                 => $data['name'],
            'generic_name'         => $data['generic_name'],
            'dosage_form'          => $data['dosage_form'] ?? 'Tablet',
            'strength'             => $data['strength'],
            'manufacturer'         => $data['manufacturer'],
            'batch_number'         => $data['batch_number'],
            'expiry_date'          => $data['expiry_date'],
            'purchase_price'       => $data['purchase_price'],
            'selling_price'        => $data['selling_price'],
            'stock_quantity'       => $data['stock_quantity'],
            'low_stock_threshold'  => $data['low_stock_threshold'] ?? 10,
            'requires_prescription'=> $data['requires_prescription'] ?? 0,
            'image'                => $data['image'] ?? null,
            'indications'          => $data['indications'] ?? null,
            'side_effects'         => $data['side_effects'] ?? null,
            'is_active'            => $data['is_active'] ?? 1,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Update Medicine
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = ['id' => $id];

        $allowed = [
            'category_id', 'name', 'generic_name', 'dosage_form', 'strength',
            'manufacturer', 'batch_number', 'expiry_date', 'purchase_price',
            'selling_price', 'stock_quantity', 'low_stock_threshold',
            'requires_prescription', 'image', 'indications', 'side_effects', 'is_active'
        ];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "{$col} = :{$col}";
                $params[$col] = $data[$col];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE medicines SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete Medicine
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM medicines WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Get Low Stock Inventory
     */
    public function getLowStock(): array {
        $stmt = $this->db->query("
            SELECT m.*, c.name AS category_name 
            FROM medicines m 
            JOIN disease_categories c ON m.category_id = c.id 
            WHERE m.stock_quantity <= m.low_stock_threshold AND m.is_active = 1
            ORDER BY m.stock_quantity ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get Expiring Soon (Within 90 Days)
     */
    public function getExpiringSoon(): array {
        $stmt = $this->db->query("
            SELECT m.*, c.name AS category_name 
            FROM medicines m 
            JOIN disease_categories c ON m.category_id = c.id 
            WHERE m.expiry_date <= DATE('now', '+90 days') AND m.is_active = 1
            ORDER BY m.expiry_date ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

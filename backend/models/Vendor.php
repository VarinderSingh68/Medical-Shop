<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class Vendor {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getAll(): array {
        $stmt = $this->db->query("SELECT * FROM vendors ORDER BY company_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM vendors WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO vendors (company_name, contact_person, phone, email, drug_license_no, gst_number, address, status) 
            VALUES (:company_name, :contact_person, :phone, :email, :drug_license_no, :gst_number, :address, :status)
        ");
        $stmt->execute([
            'company_name'    => $data['company_name'],
            'contact_person'  => $data['contact_person'],
            'phone'           => $data['phone'],
            'email'           => $data['email'] ?? null,
            'drug_license_no' => $data['drug_license_no'],
            'gst_number'      => $data['gst_number'] ?? null,
            'address'         => $data['address'],
            'status'          => $data['status'] ?? 'Active'
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE vendors SET 
                company_name = :company_name,
                contact_person = :contact_person,
                phone = :phone,
                email = :email,
                drug_license_no = :drug_license_no,
                gst_number = :gst_number,
                address = :address,
                status = :status 
            WHERE id = :id
        ");
        return $stmt->execute([
            'company_name'    => $data['company_name'],
            'contact_person'  => $data['contact_person'],
            'phone'           => $data['phone'],
            'email'           => $data['email'] ?? null,
            'drug_license_no' => $data['drug_license_no'],
            'gst_number'      => $data['gst_number'] ?? null,
            'address'         => $data['address'],
            'status'          => $data['status'] ?? 'Active',
            'id'              => $id
        ]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM vendors WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}

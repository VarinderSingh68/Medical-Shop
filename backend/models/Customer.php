<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;

/**
 * Customer Model
 * Handles customer registration, authentication, Google OAuth linking, and profile management
 */
class Customer {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Find customer by email
     */
    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE LOWER(email) = LOWER(:email) LIMIT 1");
        $stmt->execute([':email' => trim($email)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Find customer by ID
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Find customer by Google ID
     */
    public function findByGoogleId(string $googleId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE google_id = :google_id LIMIT 1");
        $stmt->execute([':google_id' => $googleId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Hash password using Argon2id if available, fallback to Bcrypt
     */
    public function hashPassword(string $password): string {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost'   => 4,
                'threads'     => 1,
            ]);
        }
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify customer password
     */
    public function verifyPassword(string $password, ?string $hash): bool {
        if (empty($hash)) {
            return false;
        }
        return password_verify($password, $hash);
    }

    /**
     * Register a new customer
     */
    public function create(array $data): int|false {
        $name = trim($data['name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $password = !empty($data['password']) ? $this->hashPassword($data['password']) : null;
        $phone = trim($data['phone'] ?? '');
        $address = trim($data['address'] ?? '');
        $city = trim($data['city'] ?? '');
        $pincode = trim($data['pincode'] ?? '');
        $googleId = !empty($data['google_id']) ? trim($data['google_id']) : null;
        $avatar = !empty($data['avatar']) ? trim($data['avatar']) : null;

        $stmt = $this->db->prepare("
            INSERT INTO customers (name, email, password, phone, address, city, pincode, google_id, avatar, status)
            VALUES (:name, :email, :password, :phone, :address, :city, :pincode, :google_id, :avatar, 'Active')
        ");

        try {
            $stmt->execute([
                ':name'      => $name,
                ':email'     => $email,
                ':password'  => $password,
                ':phone'     => $phone ?: null,
                ':address'   => $address ?: null,
                ':city'      => $city ?: null,
                ':pincode'   => $pincode ?: null,
                ':google_id' => $googleId,
                ':avatar'    => $avatar,
            ]);
            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Customer creation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create or update customer via Google OAuth
     */
    public function createOrUpdateGoogleUser(array $googleData): array {
        $googleId = $googleData['google_id'] ?? '';
        $email = strtolower(trim($googleData['email'] ?? ''));
        $name = trim($googleData['name'] ?? '');
        $avatar = $googleData['avatar'] ?? null;

        if ($email === '') {
            throw new \InvalidArgumentException('Google OAuth returned empty email address.');
        }

        // 1. Try finding by Google ID first
        $user = $this->findByGoogleId($googleId);
        if ($user) {
            // Update latest name & avatar if changed
            $stmt = $this->db->prepare("
                UPDATE customers 
                SET name = :name, avatar = COALESCE(:avatar, avatar), updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id
            ");
            $stmt->execute([':name' => $name, ':avatar' => $avatar, ':id' => $user['id']]);
            $user['name'] = $name;
            if ($avatar) $user['avatar'] = $avatar;
            return $user;
        }

        // 2. Try finding by Email (user registered with password, now linking Google)
        $user = $this->findByEmail($email);
        if ($user) {
            $stmt = $this->db->prepare("
                UPDATE customers 
                SET google_id = :google_id, avatar = COALESCE(:avatar, avatar), updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id
            ");
            $stmt->execute([':google_id' => $googleId, ':avatar' => $avatar, ':id' => $user['id']]);
            $user['google_id'] = $googleId;
            if ($avatar) $user['avatar'] = $avatar;
            return $user;
        }

        // 3. Brand new user -> Create record
        $newId = $this->create([
            'name'      => $name ?: 'Google User',
            'email'     => $email,
            'password'  => null,
            'google_id' => $googleId,
            'avatar'    => $avatar,
        ]);

        if (!$newId) {
            throw new \RuntimeException('Failed to create customer account from Google OAuth.');
        }

        return $this->findById($newId);
    }

    /**
     * Update customer profile
     */
    public function updateProfile(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['name'])) {
            $fields[] = 'name = :name';
            $params[':name'] = trim($data['name']);
        }
        if (isset($data['phone'])) {
            $fields[] = 'phone = :phone';
            $params[':phone'] = trim($data['phone']);
        }
        if (isset($data['address'])) {
            $fields[] = 'address = :address';
            $params[':address'] = trim($data['address']);
        }
        if (isset($data['city'])) {
            $fields[] = 'city = :city';
            $params[':city'] = trim($data['city']);
        }
        if (isset($data['pincode'])) {
            $fields[] = 'pincode = :pincode';
            $params[':pincode'] = trim($data['pincode']);
        }
        if (!empty($data['password'])) {
            $fields[] = 'password = :password';
            $params[':password'] = $this->hashPassword($data['password']);
        }

        if (empty($fields)) {
            return true;
        }

        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $sql = "UPDATE customers SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Fetch order history for customer
     */
    public function getOrders(int $customerId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM orders 
            WHERE customer_id = :cid OR phone IN (SELECT phone FROM customers WHERE id = :cid AND phone IS NOT NULL)
            ORDER BY created_at DESC
        ");
        $stmt->execute([':cid' => $customerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

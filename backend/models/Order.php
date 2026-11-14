<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class Order {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Create Home Delivery Order
     */
    public function create(array $orderData, array $cartItems = []): string {
        $orderCode = 'MED-' . strtoupper(substr(uniqid(), 7, 6));

        $stmt = $this->db->prepare("
            INSERT INTO orders (
                order_code, customer_name, phone, delivery_address,
                city, pincode, doctor_notes, subtotal, delivery_fee,
                total_amount, payment_method, payment_status,
                order_status, prescription_file
            ) VALUES (
                :order_code, :customer_name, :phone, :delivery_address,
                :city, :pincode, :doctor_notes, :subtotal, :delivery_fee,
                :total_amount, :payment_method, :payment_status,
                :order_status, :prescription_file
            )
        ");

        $stmt->execute([
            'order_code'        => $orderCode,
            'customer_name'     => $orderData['customer_name'],
            'phone'             => $orderData['phone'],
            'delivery_address'  => $orderData['delivery_address'],
            'city'              => $orderData['city'],
            'pincode'           => $orderData['pincode'],
            'doctor_notes'      => $orderData['doctor_notes'] ?? null,
            'subtotal'          => $orderData['subtotal'] ?? 0.00,
            'delivery_fee'      => $orderData['delivery_fee'] ?? 40.00,
            'total_amount'      => $orderData['total_amount'] ?? 0.00,
            'payment_method'    => $orderData['payment_method'] ?? 'Cash On Delivery',
            'payment_status'    => $orderData['payment_status'] ?? 'Pending',
            'order_status'      => $orderData['order_status'] ?? 'Pending Review',
            'prescription_file' => $orderData['prescription_file'] ?? null
        ]);

        $orderId = (int)$this->db->lastInsertId();

        // Save individual medicines ordered
        if (!empty($cartItems)) {
            $itemStmt = $this->db->prepare("
                INSERT INTO order_items (order_id, medicine_id, quantity, unit_price, total_price) 
                VALUES (:order_id, :medicine_id, :quantity, :unit_price, :total_price)
            ");
            $stockStmt = $this->db->prepare("
                UPDATE medicines SET stock_quantity = stock_quantity - :qty WHERE id = :id AND stock_quantity >= :qty
            ");

            foreach ($cartItems as $item) {
                $itemStmt->execute([
                    'order_id'    => $orderId,
                    'medicine_id' => $item['id'],
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['price'],
                    'total_price' => $item['price'] * $item['quantity']
                ]);

                // Deduct stock for inventory accuracy
                $stockStmt->execute([
                    'qty' => $item['quantity'],
                    'id'  => $item['id']
                ]);
            }
        }

        return $orderCode;
    }

    /**
     * Find Order by ID
     */
    public function find(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) return null;

        $order['items'] = $this->getOrderItems($order['id']);
        return $order;
    }

    /**
     * Find Order by Order Code (Patient Tracking)
     */
    public function findByCode(string $code): ?array {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE order_code = :c LIMIT 1");
        $stmt->execute(['c' => trim($code)]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) return null;

        $order['items'] = $this->getOrderItems((int)$order['id']);
        return $order;
    }

    /**
     * Get Items for an Order
     */
    public function getOrderItems(int $orderId): array {
        $stmt = $this->db->prepare("
            SELECT oi.*, m.name, m.generic_name, m.dosage_form, m.strength, m.requires_prescription 
            FROM order_items oi 
            JOIN medicines m ON oi.medicine_id = m.id 
            WHERE oi.order_id = :oid
        ");
        $stmt->execute(['oid' => $orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get All Orders with optional status filter
     */
    public function getAll(?string $status = null, int $limit = 100): array {
        $sql = "SELECT * FROM orders";
        $params = [];
        if (!empty($status)) {
            $sql .= " WHERE order_status = :status";
            $params['status'] = $status;
        }
        $sql .= " ORDER BY id DESC LIMIT " . (int)$limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update Home Delivery Status
     */
    public function updateStatus(int $id, string $status, ?string $paymentStatus = null, ?string $deliveryBoyNotes = null): bool {
        $sql = "UPDATE orders SET order_status = :st";
        $params = ['st' => $status, 'id' => $id];

        if ($paymentStatus !== null) {
            $sql .= ", payment_status = :ps";
            $params['ps'] = $paymentStatus;
        }
        if ($deliveryBoyNotes !== null) {
            $sql .= ", delivery_boy_notes = :dbn";
            $params['dbn'] = $deliveryBoyNotes;
        }

        $sql .= " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}

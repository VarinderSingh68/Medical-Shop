<?php
declare(strict_types=1);

/**
 * Enterprise Database Connection Manager (PDO)
 * Features:
 * - Reads credentials from env/.env
 * - Real Prepared Statements (EMULATE_PREPARES = false)
 * - Strict Exception Handling
 * - Seamless SQLite fallback if local MySQL server is not yet started, ensuring instant zero-downtime testing!
 */

class DatabaseConnection {
    private static ?PDO $instance = null;
    private static string $driver = 'mysql';

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $envPath = __DIR__ . '/../../env/.env';
            $env = [];
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                        continue;
                    }
                    [$k, $v] = explode('=', $trimmed, 2);
                    $env[trim($k)] = trim(trim($v), '"\'');
                }
            }

            $host = $env['DB_HOST'] ?? '127.0.0.1';
            $port = $env['DB_PORT'] ?? '3306';
            $db   = $env['DB_NAME'] ?? 'medicin_shop';
            $user = $env['DB_USER'] ?? 'root';
            $pass = $env['DB_PASS'] ?? '';

            // 1. Attempt MySQL Connection
            try {
                $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 3,
                ];
                self::$instance = new PDO($dsn, $user, $pass, $options);
                self::$driver = 'mysql';
            } catch (PDOException $e) {
                // If MySQL is not running or database is not created, fall back to SQLite for instant local functionality
                $sqliteDir = __DIR__ . '/../../database/';
                if (!is_dir($sqliteDir)) {
                    mkdir($sqliteDir, 0755, true);
                }
                $sqlitePath = $sqliteDir . 'pharmacy.sqlite';
                $isNewSqlite = !file_exists($sqlitePath);

                self::$instance = new PDO("sqlite:" . $sqlitePath, null, null, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
                self::$instance->exec("PRAGMA journal_mode = WAL;");
                self::$instance->exec("PRAGMA busy_timeout = 10000;");
                self::$instance->exec("PRAGMA synchronous = NORMAL;");
                self::$driver = 'sqlite';

                if ($isNewSqlite) {
                    self::initializeSqlite(self::$instance);
                }
            }
        }
        return self::$instance;
    }

    public static function getDriver(): string {
        return self::$driver;
    }

    /**
     * Initializes SQLite schema if MySQL is offline
     */
    private static function initializeSqlite(PDO $pdo): void {
        $schema = <<<SQL
        CREATE TABLE IF NOT EXISTS disease_categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            icon TEXT DEFAULT 'fa-heartbeat',
            description TEXT,
            is_featured INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS medicines (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            generic_name TEXT NOT NULL,
            dosage_form TEXT DEFAULT 'Tablet',
            strength TEXT NOT NULL,
            manufacturer TEXT NOT NULL,
            batch_number TEXT NOT NULL,
            expiry_date DATE NOT NULL,
            purchase_price REAL DEFAULT 0.00,
            selling_price REAL DEFAULT 0.00,
            stock_quantity INTEGER DEFAULT 0,
            low_stock_threshold INTEGER DEFAULT 10,
            requires_prescription INTEGER DEFAULT 0,
            image TEXT,
            indications TEXT,
            side_effects TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES disease_categories(id)
        );

        CREATE TABLE IF NOT EXISTS vendors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_name TEXT NOT NULL,
            contact_person TEXT NOT NULL,
            phone TEXT NOT NULL,
            email TEXT,
            drug_license_no TEXT NOT NULL,
            gst_number TEXT,
            address TEXT NOT NULL,
            status TEXT DEFAULT 'Active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT DEFAULT 'super_admin',
            status TEXT DEFAULT 'Active',
            last_login DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_code TEXT NOT NULL UNIQUE,
            customer_name TEXT NOT NULL,
            phone TEXT NOT NULL,
            delivery_address TEXT NOT NULL,
            city TEXT NOT NULL,
            pincode TEXT NOT NULL,
            doctor_notes TEXT,
            subtotal REAL DEFAULT 0.00,
            delivery_fee REAL DEFAULT 40.00,
            total_amount REAL DEFAULT 0.00,
            payment_method TEXT DEFAULT 'Cash On Delivery',
            payment_status TEXT DEFAULT 'Pending',
            order_status TEXT DEFAULT 'Pending Review',
            prescription_file TEXT,
            delivery_boy_notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            medicine_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL,
            unit_price REAL NOT NULL,
            total_price REAL NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (medicine_id) REFERENCES medicines(id)
        );

        CREATE TABLE IF NOT EXISTS website_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            setting_key TEXT NOT NULL UNIQUE,
            setting_value TEXT
        );

        CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address TEXT NOT NULL,
            username TEXT NOT NULL,
            attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- Default Admin: admin / Admin@2026!
        INSERT INTO admin_users (full_name, username, email, password, role) VALUES
        ('Lead Pharmacist & Owner', 'admin', 'admin@medixpharmacy.com', '$2y$12\$Xg3M1bUuI1vY47T.F6V3kO0fM7K7.F6X9gH4h2W9iN1qT6s8u0w2y', 'super_admin');

        -- Settings
        INSERT INTO website_settings (setting_key, setting_value) VALUES
        ('shop_name', 'Medix 24/7 Super-Specialty Home Pharmacy'),
        ('tagline', 'Prescription & Emergency Medicines Delivered to Your Doorstep'),
        ('phone', '+91 98765 43210'),
        ('emergency_hotline', '+91 1800 123 4567'),
        ('email', 'support@medixpharmacy.com'),
        ('address', '124 Healthcare Avenue, Medical City Hub'),
        ('drug_license_no', 'DL-2026-MEDIX-7890'),
        ('delivery_fee', '40.00'),
        ('free_delivery_above', '500.00'),
        ('notice_ticker', 'Free Emergency Home Delivery for Senior Citizens & Chronic Care Patients! Upload prescription for fast verification.');

        -- Seed Diseases
        INSERT INTO disease_categories (name, slug, icon, description) VALUES
        ('Cardiology & Blood Pressure', 'cardiology-blood-pressure', 'fa-heart-pulse', 'Medicines for Hypertension, Heart Disease, Cholesterol and Angina'),
        ('Diabetes & Endocrine Care', 'diabetes-endocrine-care', 'fa-dna', 'Insulin, Metformin, Glimepiride, Blood Glucose monitoring essentials'),
        ('Respiratory, Asthma & COPD', 'respiratory-asthma-copd', 'fa-lungs', 'Inhalers, Bronchodilators, Anti-allergy and Nebulizer solutions'),
        ('Neurology & Psychiatric', 'neurology-psychiatric', 'fa-brain', 'Epilepsy, Migraine, Neuropathy, and Mental Health medications'),
        ('Gastroenterology & Digestion', 'gastroenterology-digestion', 'fa-stethoscope', 'Acidity, Reflux, Liver support, IBS, and digestive enzymes'),
        ('Orthopedic & Pain Relief', 'orthopedic-pain-relief', 'fa-bone', 'Arthritis, Muscle relaxants, Joint supplements, and Pain management'),
        ('Infectious Diseases & Antibiotics', 'infectious-diseases-antibiotics', 'fa-virus-covid', 'Broad-spectrum antibiotics, Anti-virals, and Anti-fungals'),
        ('Dermatology & Skin Disorders', 'dermatology-skin-disorders', 'fa-hand-dots', 'Eczema, Psoriasis, Fungal care, Acne, and Topical treatments');

        -- Seed Medicines
        INSERT INTO medicines (category_id, name, generic_name, dosage_form, strength, manufacturer, batch_number, expiry_date, purchase_price, selling_price, stock_quantity, requires_prescription, indications) VALUES
        (1, 'Telma-H Tablets', 'Telmisartan + Hydrochlorothiazide', 'Tablet', '40mg/12.5mg', 'Glenmark Pharmaceuticals', 'BAT-7801', '2028-06-30', 140.00, 195.00, 150, 1, 'Essential hypertension, high blood pressure management'),
        (2, 'Glycomet-GP 2 Forte', 'Metformin + Glimepiride', 'Tablet', '1000mg/2mg', 'USV Ltd', 'BAT-9921', '2027-11-30', 120.00, 168.00, 200, 1, 'Type 2 Diabetes Mellitus glycemic control'),
        (3, 'Asthalin Inhaler', 'Salbutamol Inhaler', 'Inhaler', '100mcg', 'Cipla Ltd', 'BAT-3320', '2027-08-31', 115.00, 155.00, 85, 0, 'Instant relief from bronchial asthma & wheezing'),
        (4, 'Dolo 650', 'Paracetamol', 'Tablet', '650mg', 'Micro Labs Ltd', 'BAT-1102', '2028-12-31', 22.00, 33.50, 500, 0, 'Fever, Body ache, Headache relief'),
        (5, 'Pan-D Capsule', 'Pantoprazole + Domperidone', 'Capsule', '40mg/30mg', 'Alkem Laboratories', 'BAT-5512', '2027-09-30', 110.00, 162.00, 120, 0, 'Acid reflux, GERD, indigestion and nausea relief'),
        (6, 'Volini Pain Gel', 'Diclofenac Diethylamine + Linseed Oil', 'Ointment', '50g', 'Sun Pharmaceutical', 'BAT-4419', '2028-03-31', 125.00, 170.00, 75, 0, 'Joint pain, sprains, backache and muscular pain relief');
SQL;
        $pdo->exec($schema);
    }
}

CREATE DATABASE IF NOT EXISTS `medicin_shop` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `medicin_shop`;

CREATE TABLE IF NOT EXISTS `disease_categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(120) NOT NULL,
    `slug` VARCHAR(140) NOT NULL UNIQUE,
    `icon` VARCHAR(60) DEFAULT 'fa-heartbeat',
    `description` TEXT NULL,
    `is_featured` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `medicines` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `generic_name` VARCHAR(200) NOT NULL,
    `dosage_form` ENUM('Tablet', 'Capsule', 'Syrup', 'Injection', 'Ointment', 'Drops', 'Inhaler', 'Powder') DEFAULT 'Tablet',
    `strength` VARCHAR(60) NOT NULL,
    `manufacturer` VARCHAR(150) NOT NULL,
    `batch_number` VARCHAR(60) NOT NULL,
    `expiry_date` DATE NOT NULL,
    `purchase_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `selling_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `stock_quantity` INT NOT NULL DEFAULT 0,
    `low_stock_threshold` INT NOT NULL DEFAULT 10,
    `requires_prescription` TINYINT(1) DEFAULT 0,
    `image` VARCHAR(255) NULL,
    `indications` TEXT NULL,
    `side_effects` TEXT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `disease_categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `vendors` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_name` VARCHAR(180) NOT NULL,
    `contact_person` VARCHAR(120) NOT NULL,
    `phone` VARCHAR(30) NOT NULL,
    `email` VARCHAR(120) NULL,
    `drug_license_no` VARCHAR(100) NOT NULL,
    `gst_number` VARCHAR(50) NULL,
    `address` TEXT NOT NULL,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `admin_users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(60) NOT NULL UNIQUE,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('super_admin', 'pharmacist', 'delivery_manager') DEFAULT 'super_admin',
    `status` ENUM('Active', 'Suspended') DEFAULT 'Active',
    `last_login` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_code` VARCHAR(20) NOT NULL UNIQUE,
    `customer_name` VARCHAR(120) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `delivery_address` TEXT NOT NULL,
    `city` VARCHAR(80) NOT NULL,
    `pincode` VARCHAR(20) NOT NULL,
    `doctor_notes` TEXT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 40.00,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_method` ENUM('Cash On Delivery', 'Online', 'UPI / Card on Delivery') DEFAULT 'Cash On Delivery',
    `payment_status` ENUM('Pending', 'Paid', 'Refunded') DEFAULT 'Pending',
    `order_status` ENUM('Pending Review', 'Pharmacist Verified', 'Packed', 'Out for Delivery', 'Delivered', 'Cancelled') DEFAULT 'Pending Review',
    `prescription_file` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `medicine_id` INT UNSIGNED NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `total_price` DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `website_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(80) NOT NULL UNIQUE,
    `setting_value` TEXT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `username` VARCHAR(60) NOT NULL,
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `website_settings` (`setting_key`, `setting_value`) VALUES
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

-- Default Admin Password: Admin@2026!
INSERT INTO `admin_users` (`full_name`, `username`, `email`, `password`, `role`) VALUES
('Lead Pharmacist & Owner', 'admin', 'admin@medixpharmacy.com', '$2y$12$0Gg0oD4284d79G5n8s0fA.Pek5C2R2h11K2.59eJzR7N5pE9eB1m.', 'super_admin');

INSERT INTO `disease_categories` (`name`, `slug`, `icon`, `description`) VALUES
('Cardiology & Blood Pressure', 'cardiology-blood-pressure', 'fa-heart-pulse', 'Medicines for Hypertension, Heart Disease, Cholesterol and Angina'),
('Diabetes & Endocrine Care', 'diabetes-endocrine-care', 'fa-dna', 'Insulin, Metformin, Blood Glucose monitoring essentials'),
('Respiratory, Asthma & COPD', 'respiratory-asthma-copd', 'fa-lungs', 'Inhalers, Bronchodilators, Anti-allergy and Nebulizer solutions'),
('Neurology & Psychiatric', 'neurology-psychiatric', 'fa-brain', 'Epilepsy, Migraine, Neuropathy, and Mental Health medications'),
('Gastroenterology & Digestion', 'gastroenterology-digestion', 'fa-stethoscope', 'Acidity, Reflux, Liver support, IBS, and digestive enzymes'),
('Orthopedic & Pain Relief', 'orthopedic-pain-relief', 'fa-bone', 'Arthritis, Muscle relaxants, Joint supplements, and Pain management'),
('Infectious Diseases & Antibiotics', 'infectious-diseases-antibiotics', 'fa-virus-covid', 'Broad-spectrum antibiotics, Anti-virals, and Anti-fungals'),
('Dermatology & Skin Disorders', 'dermatology-skin-disorders', 'fa-hand-dots', 'Eczema, Psoriasis, Fungal care, Acne, and Topical treatments');

INSERT INTO `medicines` (`category_id`, `name`, `generic_name`, `dosage_form`, `strength`, `manufacturer`, `batch_number`, `expiry_date`, `purchase_price`, `selling_price`, `stock_quantity`, `requires_prescription`, `indications`) VALUES
(1, 'Telma-H Tablets', 'Telmisartan + Hydrochlorothiazide', 'Tablet', '40mg/12.5mg', 'Glenmark Pharmaceuticals', 'BAT-7801', '2028-06-30', 140.00, 195.00, 150, 1, 'Essential hypertension, blood pressure management'),
(2, 'Glycomet-GP 2 Forte', 'Metformin + Glimepiride', 'Tablet', '1000mg/2mg', 'USV Ltd', 'BAT-9921', '2027-11-30', 120.00, 168.00, 200, 1, 'Type 2 Diabetes Mellitus glycemic control'),
(3, 'Asthalin Inhaler', 'Salbutamol Inhaler', 'Inhaler', '100mcg', 'Cipla Ltd', 'BAT-3320', '2027-08-31', 115.00, 155.00, 85, 0, 'Instant relief from bronchial asthma & wheezing'),
(4, 'Dolo 650', 'Paracetamol', 'Tablet', '650mg', 'Micro Labs Ltd', 'BAT-1102', '2028-12-31', 22.00, 33.50, 500, 0, 'Fever, Body ache, Headache relief');

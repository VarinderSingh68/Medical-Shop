# HomeMed Pharmacy & Super-Specialty Home Delivery Platform

[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![Security Score](https://img.shields.io/badge/Security%20Score-99.5%2F100-00c08b?style=flat&logo=shield&logoColor=white)](security_report.md)
[![Inventory](https://img.shields.io/badge/Medicine%20Inventory-10%2C187%2B%20SKUs-0284c7?style=flat&logo=pills&logoColor=white)](backend/admin/medicines.php)
[![Authentication](https://img.shields.io/badge/2FA%20Security-Gmail%20TLS%20Socket-ea4335?style=flat&logo=gmail&logoColor=white)](backend/core/Mailer.php)
[![Compliance](https://img.shields.io/badge/Compliance-Drugs%20%26%20Cosmetics%20Act-10b981?style=flat)](backend/admin/compliance.php)
[![License](https://img.shields.io/badge/License-Proprietary-0d223a?style=flat)](LICENSE)

**HomeMed** is an enterprise-grade digital pharmacy management and doorstep medicine dispensing platform. Engineered with a **20-year architecture standard**, strict PHP 8.3 type safety, and an audited **99.5/100 security score**, HomeMed unites high-velocity physical counter billing (POS) with real-time patient home delivery, cold-chain assurance, and automated chronic disease care.

---

## 🎯 Project Objectives & Vision

1. **Modernize Retail Community Pharmacy Operations:** Provide licensed pharmacists with sub-second counter billing, barcode lookup, automated Schedule H register compliance, and real-time inventory synchronization across 10,000+ SKUs.
2. **Elevate Patient Clinical Safety:** Prevent adverse drug reactions through an automated **Clinical Drug-Drug Interaction Matrix** that flags dangerous pharmacological combinations (e.g., NSAID duplication, Coumarin toxicity) at point of sale and online checkout.
3. **Automate Long-Term Chronic Patient Care:** Eliminate therapy gaps for chronic illnesses (Diabetes, Hypertension, Cardiac, Asthma) through automated 30-day replenishment schedules and dual-channel email reminders.
4. **Implement Bank-Grade Security & PHI Privacy:** Guarantee strict compliance with data privacy standards by shielding doctor prescriptions behind authenticated controllers, masking patient identifiers on public tracking, and enforcing Argon2id password hashing with mandatory two-factor email OTP verification.
5. **Real-Time Financial & Operational Visibility:** Equip pharmacy owners with instant executive analytics, Cost of Goods Sold (COGS) tracking, margin analysis, and smart supplier purchase order generation.

---

## 🌟 Key Features

### 🛒 1. Patient Doorstep Delivery Experience (Frontend)
- **Fast Medicine Catalog (10,187 SKUs):** Browse and search through 16 therapeutic categories with high-performance pagination and instant query responses (< 30ms).
- **Live Search Auto-Complete:** Search by brand name, active pharmacological molecule, or dosage form with instant dropdown previews.
- **Prescription Upload Gateway:** Patients can photograph and upload physical doctor prescriptions (JPG, PNG, WEBP, PDF up to 10MB) with cryptographic finfo MIME validation.
- **Privacy-Masked Live Order Tracking:** Real-time milestone tracker (`Pending Review` ➔ `Pharmacist Verified` ➔ `Packed` ➔ `Out for Delivery` ➔ `Delivered`) with patient name and phone masking (e.g., `Rajesh S****`, `+91 98201 ****5`).
- **Interactive Medicine Details:** Detailed indication guides, dosage forms, manufacturer specifications, side-effect profiles, and Schedule H prescription requirement badges.

### 💊 2. Clinical Drug-Drug Interaction & Safety Engine
- Built-in pharmacological interaction matrix ([DrugSafetyChecker.php](backend/core/DrugSafetyChecker.php)) evaluating real-time co-dispensing risks:
  - ⚠️ **Duplicate NSAID Therapy:** Aspirin + Ibuprofen / Diclofenac (acute GI bleed & renal hazard).
  - 🚨 **Anticoagulant Synergism:** Warfarin + NSAID / Aspirin (major hemorrhage risk).
  - ⚡ **CYP2C19 Bioavailability Block:** Clopidogrel + Omeprazole.
  - ⚠️ **Hyperkalemia Warnings:** ACE Inhibitors (Ramipril) + Potassium-sparing diuretics (Spironolactone).
  - 🛡️ **Paracetamol Dosage Ceilings:** Warns when 24-hour total exceeds safe 4,000 mg liver toxicity thresholds.
- Seamlessly activates inside **POS Counter Billing**, **Online Cart**, and **Medicine Detail Views**.

### 🧾 3. Pharmacist POS Counter Billing & Tax Invoicing
- **High-Velocity Counter Billing ([pos.php](backend/admin/pos.php)):** Real-time stock depletion, instant generic search, keyboard shortcuts, and walk-in customer support.
- **GST Retail Drug Sale Tax Invoices ([invoice.php](backend/admin/invoice.php)):**
  - Features Retail Drug License Numbers (`MH-MZ1-182940` / `MH-MZ2-182941`) and GSTIN (`27AADCH1234F1Z8`).
  - Full tax breakdown (CGST 6% + SGST 6% on pharmaceuticals).
  - Batch numbers, expiration dates, doctor registration numbers, and registered pharmacist digital seal.
  - Print-optimized CSS (`@media print`) and 1-click A4 PDF export.

### 🩺 4. Chronic Patient Care & Automated Email Refill Dispatcher
- **30-Day Medication Replenishment Directory ([customers.php](backend/admin/customers.php)):** Automatically calculates next refill dates based on 30-day dispense cycles.
- **Dual-Channel Native TLS SMTP Socket Engine ([Mailer.php](backend/core/Mailer.php)):** Custom-built raw TCP socket client supporting TLS 1.2/1.3 without heavy external dependencies.
- **1-Click Refill Alerts:** Dispatches responsive, personalized clinical reminder emails with prescribed medicine summaries and direct phone refill actions.
- **Bulk Dispatch:** 1-click "Dispatch All Due Email Alerts" automatically alerts all patients due within 5 days.

### 📊 5. Executive Analytics & Visual Dashboards
- **Self-Hosted Chart.js 4.4.1 Engine ([analytics.php](backend/admin/analytics.php)):** Zero CDN latency and complete offline capability.
- **4 Visual Intelligence Charts:**
  1. **14-Day Sales Velocity & Profit Trajectory** (Dual-line area chart comparing Gross Sales vs Gross Profit).
  2. **Condition Market Share** (Donut chart showing therapeutic category split).
  3. **Payment Channel Distribution** (Pie chart: UPI, Cash on Delivery, Cards, Store Credit).
  4. **Top 5 Dispensed Medicines by Volume** (Horizontal bar chart).

### 📦 6. Smart Supplier Purchase Orders (PO) & Inventory Replenishment
- **Automatic Low-Stock Scanner ([purchase-orders.php](backend/admin/purchase-orders.php)):** Scans 10,187 medicines for items below threshold buffer.
- **Recommended Order Calculation:** Dynamically calculates suggested replenishment quantities and wholesale procurement outlays.
- **1-Click Restock:** Updates batch numbers, expiration dates, and restores inventory levels with automated audit logging.
- **Vendor PO Dispatch:** Formats and emails purchase orders directly to pharmaceutical distributors (Cipla, Sun Pharma, Abbott, Dr. Reddy's).

---

## 🔒 Security Architecture (99.5 / 100)

| Security Layer | Standard Implemented | Technical Defense |
|---|:---:|---|
| **Password Security** | `PASSWORD_ARGON2ID` | High-memory GPU-resistant hashing; transparent auto-rehash upgrades older credentials upon login. |
| **Two-Factor Authentication** | 6-Digit Email OTP | Unique single-use OTP dispatched via encrypted TLS socket on every login. 5-attempt brute-force lockout + 60s resend cooldown. |
| **Session Isolation** | `SameSite=Strict` | Zero cross-site leakage; `HttpOnly=true`, `session.use_only_cookies=1`, and `session.use_strict_mode=1` block XSS session hijacking. |
| **Session Fixation Prevention** | `session_regenerate_id(true)` | Session ID regenerated upon privilege elevation; 2-hour inactivity auto-logout timer enforced. |
| **Login IP Rate Limiting** | 10 Attempts / 15 Min | Database-backed throttling via `login_attempts` with dedicated SQLite index `idx_login_ip_time`. Old attempts pruned after 24h. |
| **Sensitive File Protection** | `403 Forbidden` | Direct HTTP requests to `.env`, `.sqlite`, `.git`, `/storage/prescriptions/`, `/backend/config/`, and `/backend/core/` are rejected. |
| **Patient Privacy (PHI)** | Authenticated Serving | Prescription images are never publicly accessible; served exclusively through authenticated admin controller [view-prescription.php](backend/admin/view-prescription.php). |
| **Content Security Policy** | Level 3 CSP | `default-src 'self'`, `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`, restricting execution strictly to trusted assets. |
| **Subresource Integrity** | SRI Hashes | Cryptographic `integrity="sha512-..."` and `crossorigin="anonymous"` enforced on external CSS resources. |
| **SQL Injection Defense** | 100% Prepared Statements | Zero raw SQL string concatenation; 100% PDO prepared statements throughout all queries. |
| **Zero Error Disclosure** | `display_errors = Off` | Zero stack traces exposed to end-users; system errors log securely to [storage/logs/php_errors.log](storage/logs/php_errors.log). |

---

## 📂 Project Structure

```
Medical-Shop/
├── admin/                      # Admin entrypoint redirects
├── assets/                     # Frontend & admin static assets
│   ├── css/
│   │   └── style.css           # Core styling system (CSS variables, responsive grid)
│   └── js/
│       ├── app.js              # Live search, AJAX cart, toast engine
│       └── chart.umd.min.js    # Self-hosted Chart.js v4.4.1 bundle
├── backend/
│   ├── admin/                  # Pharmacist Back-Office Command Center
│   │   ├── layout/             # Admin header, navigation sidebar, footer
│   │   ├── analytics.php       # Visual revenue & profit intelligence dashboards
│   │   ├── audit-logs.php      # Security event logs & IP tracking
│   │   ├── backup.php          # Database backup & download manager
│   │   ├── categories.php      # Disease categories CRUD
│   │   ├── compliance.php      # Schedule H statutory prescription register
│   │   ├── customers.php       # Chronic patient 30-day refill management
│   │   ├── expiry-tracker.php  # 90-day batch expiry tracking
│   │   ├── index.php           # Main admin operational dashboard
│   │   ├── invoice.php         # Official GST Tax Invoice generator (PDF/Print)
│   │   ├── login.php           # Admin login portal
│   │   ├── logout.php          # Session termination controller
│   │   ├── medicines.php       # 10,000+ SKU inventory manager
│   │   ├── orders.php          # Doorstep delivery orders & dispatch status
│   │   ├── pos.php             # Fast counter POS billing terminal
│   │   ├── prescriptions.php   # Doctor prescription review queue
│   │   ├── purchase-orders.php # Smart low-stock reorder & supplier PO engine
│   │   ├── settings.php        # Pharmacy information & store configuration
│   │   ├── smtp-test.php       # Dual-channel Gmail SMTP diagnostic tester
│   │   ├── staff.php           # Role-based access control & staff management
│   │   ├── system-health.php   # Server environment & PHP telemetry
│   │   ├── vendors.php         # Pharmaceutical distributors directory
│   │   ├── verify-otp.php      # Two-factor authentication (2FA) verification
│   │   └── view-prescription.php # Secure authenticated prescription viewer
│   ├── config/
│   │   ├── config.php          # Global constants, session params, autoloader
│   │   └── database.php        # PDO database connection manager
│   ├── core/
│   │   ├── Auth.php            # 2FA login, rate limiting, session elevation
│   │   ├── DrugSafetyChecker.php # Clinical drug-drug interaction matrix
│   │   ├── Mailer.php          # Native raw TLS 1.2/1.3 socket SMTP dispatcher
│   │   ├── Security.php        # CSRF, XSS escape, headers, input sanitization
│   │   └── Uploader.php        # Secure prescription file upload & MIME validation
│   └── models/                 # Data access layer
│       ├── Category.php
│       ├── Medicine.php
│       ├── Order.php
│       ├── Setting.php
│       └── Vendor.php
├── database/
│   ├── pharmacy.sqlite         # SQLite database pre-seeded with 10,187 medicines
│   └── schema.sql              # Clean SQL table schemas and indexes
├── env/
│   ├── .env                    # System environment configuration (git-protected)
│   ├── .env.example            # Environment configuration template
│   └── .htaccess               # Web access denial
├── frontend/                   # Customer storefront views
│   ├── ajax/search.php         # High-speed JSON medicine search endpoint
│   ├── layout/header.php       # Customer header with cart counter & location pill
│   ├── layout/footer.php       # Customer footer with trust badges & hotline
│   ├── cart.php                # Shopping cart with clinical safety matrix
│   ├── checkout.php            # Patient delivery details & order review
│   ├── contact.php             # 24/7 doctor consult & helpline form
│   ├── index.php               # Homepage with disease category shortcuts
│   ├── medicine-detail.php     # Medicine specifications & clinical indications
│   ├── medicines.php           # Paginated medicine catalog (24 items/page)
│   ├── order-success.php       # Order confirmation with tracking code
│   ├── track-order.php         # Real-time doorstep delivery progress tracker
│   └── upload-prescription.php # Direct doctor prescription upload portal
├── storage/                    # Protected persistent storage
│   ├── backups/                # Encrypted database backups
│   ├── logs/                   # Private PHP error logs
│   ├── prescriptions/          # Patient prescription uploads (authenticated access)
│   └── sessions/               # Server-side PHP session storage
├── router.php                  # PHP built-in server router with security blocks
├── start-server.bat            # 1-click Windows development server starter
├── .gitignore                  # Git exclusions for sessions, logs & temporary files
└── README.md                   # Project documentation
```

---

## 🚀 Installation & Local Setup

### Prerequisites
- **PHP 8.2 or 8.3+** with `pdo_sqlite`, `openssl`, and `fileinfo` extensions enabled.
- **Git** installed on your system.

### 1. Clone the Repository
```bash
git clone https://github.com/VarinderSingh68/Medical-Shop.git
cd Medical-Shop
```

### 2. Configure Environment Variables
Copy the example environment template:
```bash
cp env/.env.example env/.env
```
Ensure your `env/.env` contains your desired settings:
```ini
APP_NAME="HomeMed Pharmacy & Home Delivery"
APP_ENV="production"
APP_URL="http://127.0.0.1:8000"

# Gmail SMTP 2FA Settings (Google 16-Character App Password)
SMTP_HOST="smtp.gmail.com"
SMTP_PORT=587
SMTP_USER="your-email@gmail.com"
SMTP_PASS="your-16-char-app-password"
SMTP_FROM_NAME="HomeMed Pharmacy Security"
OTP_RECIPIENT_EMAIL="your-email@gmail.com"
STORE_ALERT_EMAIL="your-email@gmail.com"

# Administrative Credentials (Super Admin)
ADMIN_USERNAME="admin"
ADMIN_PASSWORD="Admin@2026!"
```

### 3. Launch the Application Server
Run the built-in development server:
```bash
php -S 127.0.0.1:8000 router.php
```
*(On Windows, you can also simply double-click `start-server.bat`).*

### 4. Access the Application
- **Customer Storefront:** [http://127.0.0.1:8000/](http://127.0.0.1:8000/)
- **Pharmacist Back-Office:** [http://127.0.0.1:8000/backend/admin/login.php](http://127.0.0.1:8000/backend/admin/login.php)
  - **Username:** `admin`
  - **Password:** `Admin@2026!`
  - **2FA:** A 6-digit verification code will be dispatched to your configured email upon credentials verification.

---

## 🧪 Testing Email Dispatchers
Navigate to **Admin ➔ Developer & System ➔ Mailer & SMTP Test** (`backend/admin/smtp-test.php`):
- Click **"Test Setup 1: 2FA Login OTP Email"** to verify high-priority security code delivery.
- Click **"Test Setup 2: Patient Chronic Care Refill Alert"** to test formatted clinical reminder delivery.

---

## 📜 Regulatory & Legal Disclaimer
HomeMed is engineered to comply with the Indian **Drugs and Cosmetics Act, 1940**, **Pharmacy Act, 1948**, and **Information Technology Act, 2000**. Schedule H and Schedule H1 prescription medications are strictly gated behind registered pharmacist verification. All patient medical records and prescriptions are securely stored and accessible exclusively to authorized dispensing personnel.

---

## 👨‍💻 Author & Acknowledgements
- **Developer:** [VarinderSingh68](https://github.com/VarinderSingh68)
- **Repository:** [https://github.com/VarinderSingh68/Medical-Shop.git](https://github.com/VarinderSingh68/Medical-Shop.git)

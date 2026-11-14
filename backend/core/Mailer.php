<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Mailer Service
 * High-reliability native socket SMTP client with TLS 1.2/1.3 encryption
 * Dedicated to 2FA One-Time Password (OTP) dispatch and emergency alerts
 */
class Mailer {
    private static ?array $config = null;

    /**
     * Load SMTP configuration from env/.env
     */
    public static function getConfig(): array {
        if (self::$config !== null) {
            return self::$config;
        }

        $envFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'env' . DIRECTORY_SEPARATOR . '.env';
        $env = [];
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
                if (str_contains($line, '=')) {
                    [$k, $v] = explode('=', $line, 2);
                    $env[trim($k)] = trim(trim($v), '"\'');
                }
            }
        }

        self::$config = [
            'host'       => getenv('SMTP_HOST') ?: ($env['SMTP_HOST'] ?? 'smtp.gmail.com'),
            'port'       => (int)(getenv('SMTP_PORT') ?: ($env['SMTP_PORT'] ?? 587)),
            'user'       => getenv('SMTP_USER') ?: ($env['SMTP_USER'] ?? 'ngw.designer@gmail.com'),
            'pass'       => str_replace(' ', '', getenv('SMTP_PASS') ?: ($env['SMTP_PASS'] ?? 'xvqehegcyscusszt')),
            'from_name'  => getenv('SMTP_FROM_NAME') ?: ($env['SMTP_FROM_NAME'] ?? 'HomeMed Pharmacy Security'),
            'recipient'  => getenv('OTP_RECIPIENT_EMAIL') ?: ($env['OTP_RECIPIENT_EMAIL'] ?? 'ngw.designer@gmail.com'),
        ];

        return self::$config;
    }

    /**
     * Get the default recipient for 2FA OTP
     */
    public static function getRecipientEmail(): string {
        $cfg = self::getConfig();
        return $cfg['recipient'] ?? 'ngw.designer@gmail.com';
    }

    /**
     * Send 6-digit OTP Email via Gmail SMTP
     */
    public static function sendOtp(string $toEmail, string $otpCode, string $adminName = 'Administrator'): array {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $timeStr = date('d M Y, h:i A T');

        $subject = "HomeMed Admin Login Verification Code: {$otpCode}";
        $htmlBody = self::buildOtpEmailTemplate($otpCode, $adminName, $timeStr, $ip);

        return self::sendHtmlEmail($toEmail, $subject, $htmlBody, 1);
    }

    /**
     * Send Chronic Care Medicine Refill Reminder to Patient
     */
    public static function sendRefillReminder(
        string $toEmail,
        string $patientName,
        string $condition,
        string $medicines,
        string $dueDate,
        string $notes = ''
    ): array {
        $subject = "HomeMed Refill Reminder: Your {$condition} Medication is Due on " . date('d M Y', strtotime($dueDate));
        $htmlBody = self::buildRefillEmailTemplate($patientName, $condition, $medicines, $dueDate, $notes);

        return self::sendHtmlEmail($toEmail, $subject, $htmlBody, 2);
    }

    /**
     * Core Reusable Native TLS SMTP Socket Dispatcher
     */
    public static function sendHtmlEmail(string $toEmail, string $subject, string $htmlBody, int $priority = 3): array {
        $cfg = self::getConfig();
        $smtpHost = $cfg['host'];
        $smtpPort = $cfg['port'];
        $smtpUser = $cfg['user'];
        $smtpPass = $cfg['pass'];
        $fromName = $cfg['from_name'];

        // Connect to SMTP Server
        $socket = @stream_socket_client(
            "tcp://{$smtpHost}:{$smtpPort}",
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT
        );

        if (!$socket) {
            error_log("HomeMed Mailer Error: Connection failed - {$errno}: {$errstr}");
            return ['success' => false, 'message' => "Could not connect to mail server: {$errstr}"];
        }

        stream_set_timeout($socket, 15);

        $read = function () use ($socket): string {
            $data = '';
            while ($line = fgets($socket, 515)) {
                $data .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
            return $data;
        };

        $send = function (string $cmd) use ($socket, $read): string {
            fputs($socket, $cmd . "\r\n");
            return $read();
        };

        // Handshake
        $greeting = $read();
        if (!str_starts_with($greeting, '220')) {
            fclose($socket);
            return ['success' => false, 'message' => 'Unexpected greeting from mail server.'];
        }

        $send("EHLO " . gethostname());
        $tlsRes = $send("STARTTLS");
        if (!str_starts_with($tlsRes, '220')) {
            fclose($socket);
            return ['success' => false, 'message' => 'Mail server does not support TLS.'];
        }

        // Enable TLS Crypto
        $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
            fclose($socket);
            return ['success' => false, 'message' => 'Failed to negotiate secure TLS connection.'];
        }

        // Post-TLS Handshake
        $send("EHLO " . gethostname());
        $authRes = $send("AUTH LOGIN");
        if (!str_starts_with($authRes, '334')) {
            fclose($socket);
            return ['success' => false, 'message' => 'SMTP Authentication rejected.'];
        }

        $send(base64_encode($smtpUser));
        $passRes = $send(base64_encode($smtpPass));
        if (!str_starts_with($passRes, '235')) {
            fclose($socket);
            return ['success' => false, 'message' => 'Invalid SMTP username or application password.'];
        }

        // Mail Transactions
        $mailFromRes = $send("MAIL FROM: <{$smtpUser}>");
        if (!str_starts_with($mailFromRes, '250')) {
            fclose($socket);
            return ['success' => false, 'message' => 'Sender email address rejected.'];
        }

        $rcptRes = $send("RCPT TO: <{$toEmail}>");
        if (!str_starts_with($rcptRes, '250')) {
            fclose($socket);
            return ['success' => false, 'message' => "Recipient {$toEmail} rejected by server."];
        }

        $dataRes = $send("DATA");
        if (!str_starts_with($dataRes, '354')) {
            fclose($socket);
            return ['success' => false, 'message' => 'Data stream rejected.'];
        }

        $headers = [
            "From: \"{$fromName}\" <{$smtpUser}>",
            "To: <{$toEmail}>",
            "Subject: {$subject}",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "X-Priority: {$priority}",
            "X-Mailer: HomeMed Native SMTP Dispatcher"
        ];

        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.";
        $sendRes = $send($payload);

        $send("QUIT");
        fclose($socket);

        if (str_starts_with($sendRes, '250')) {
            return ['success' => true, 'message' => "Email dispatched successfully to {$toEmail}."];
        }

        return ['success' => false, 'message' => "Failed to deliver message: {$sendRes}"];
    }

    /**
     * Professional HTML Template for 2FA OTP
     */
    private static function buildOtpEmailTemplate(string $otp, string $name, string $timeStr, string $ip): string {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>HomeMed 2FA Verification</title>
</head>
<body style="margin:0; padding:30px 10px; background-color:#f1f5f9; font-family:'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; color:#1e293b;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table width="100%" max-width="560" style="max-width:560px; background-color:#ffffff; border-radius:18px; overflow:hidden; box-shadow:0 12px 36px rgba(13,34,58,0.12); border:1px solid #e2e8f0;" cellpadding="0" cellspacing="0">
                    <!-- Brand Top Bar -->
                    <tr>
                        <td style="background-color:#0d223a; padding:28px 36px; text-align:center;">
                            <div style="display:inline-block; vertical-align:middle; width:40px; height:40px; background-color:#00c08b; border-radius:10px; line-height:40px; text-align:center; color:#ffffff; font-size:22px; font-weight:bold; margin-right:12px;">+</div>
                            <span style="display:inline-block; vertical-align:middle; color:#ffffff; font-size:24px; font-weight:800; letter-spacing:-0.5px;">HomeMed</span>
                            <div style="color:#00c08b; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; margin-top:4px;">Security & Access Control</div>
                        </td>
                    </tr>
                    
                    <!-- Content Area -->
                    <tr>
                        <td style="padding:36px 36px 28px;">
                            <h2 style="margin:0 0 10px; color:#0d223a; font-size:20px; font-weight:800;">Administrator 2FA Verification</h2>
                            <p style="margin:0 0 20px; font-size:14px; color:#475569; line-height:1.6;">
                                Hello <strong>{$name}</strong>, a login request was initiated for your HomeMed Pharmacist Back-Office account.
                            </p>
                            
                            <!-- Large 6-Digit OTP Box -->
                            <div style="background-color:#e6f9f3; border:2px dashed #00c08b; border-radius:14px; padding:24px; text-align:center; margin:24px 0;">
                                <div style="font-size:11px; font-weight:800; color:#0f766e; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:8px;">Your 6-Digit One-Time Password</div>
                                <div style="font-size:38px; font-weight:800; color:#00c08b; letter-spacing:10px; font-family:'Courier New', Courier, monospace; margin:4px 0;">{$otp}</div>
                                <div style="font-size:12px; color:#0f766e; font-weight:600; margin-top:8px;">&bull; Valid for 10 minutes &bull; Do not share with anyone &bull;</div>
                            </div>
                            
                            <!-- Request Context Box -->
                            <table width="100%" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; margin-bottom:24px; font-size:13px; color:#475569;">
                                <tr>
                                    <td style="padding:4px 8px;"><strong>Date & Time:</strong></td>
                                    <td style="padding:4px 8px; color:#0f172a;">{$timeStr}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 8px;"><strong>Requesting IP:</strong></td>
                                    <td style="padding:4px 8px; color:#0f172a; font-family:monospace;">{$ip}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 8px;"><strong>System:</strong></td>
                                    <td style="padding:4px 8px; color:#0f172a;">HomeMed Pharmacist Command Center</td>
                                </tr>
                            </table>
                            
                            <p style="font-size:12px; color:#94a3b8; line-height:1.5; margin:0;">
                                <strong>Security Notice:</strong> If you did not attempt this login, your credentials may be compromised. Please change your administrative password immediately.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer Strip -->
                    <tr>
                        <td style="background-color:#f8fafc; border-top:1px solid #e2e8f0; padding:20px 36px; text-align:center; font-size:12px; color:#64748b;">
                            &copy; HomeMed Super-Specialty Home Pharmacy &bull; Strictly Confidential &bull; Automated Dispatch
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Professional Patient Care Refill Reminder HTML Template
     */
    private static function buildRefillEmailTemplate(
        string $patientName,
        string $condition,
        string $medicines,
        string $dueDate,
        string $notes = ''
    ): string {
        $formattedDate = date('l, d F Y', strtotime($dueDate));
        $phone = '+91 98200 88990';
        $notesBlock = !empty($notes) ? "<tr><td style=\"padding:8px 12px; background:#fffbeb; border:1px solid #fef3c7; border-radius:8px; font-size:13px; color:#92400e;\"><strong>Special Pharmacist Note:</strong> {$notes}</td></tr>" : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>HomeMed Chronic Care Refill Alert</title>
</head>
<body style="margin:0; padding:30px 10px; background-color:#f1f5f9; font-family:'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; color:#1e293b;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table width="100%" style="max-width:600px; background-color:#ffffff; border-radius:18px; overflow:hidden; box-shadow:0 12px 36px rgba(13,34,58,0.12); border:1px solid #e2e8f0;" cellpadding="0" cellspacing="0">
                    <!-- Brand Top Bar -->
                    <tr>
                        <td style="background-color:#0d223a; padding:28px 36px; text-align:center;">
                            <div style="display:inline-block; vertical-align:middle; width:40px; height:40px; background-color:#00c08b; border-radius:10px; line-height:40px; text-align:center; color:#ffffff; font-size:22px; font-weight:bold; margin-right:12px;">+</div>
                            <span style="display:inline-block; vertical-align:middle; color:#ffffff; font-size:24px; font-weight:800; letter-spacing:-0.5px;">HomeMed</span>
                            <div style="color:#00c08b; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; margin-top:4px;">Chronic Patient Care & Refill Support</div>
                        </td>
                    </tr>
                    
                    <!-- Content Area -->
                    <tr>
                        <td style="padding:36px 36px 28px;">
                            <span style="background-color:#e6f9f3; color:#00a87a; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; padding:4px 12px; border-radius:20px; display:inline-block; margin-bottom:12px;">Monthly Medication Care</span>
                            <h2 style="margin:0 0 10px; color:#0d223a; font-size:22px; font-weight:800;">Medication Refill Due Soon</h2>
                            <p style="margin:0 0 20px; font-size:15px; color:#475569; line-height:1.6;">
                                Dear <strong>{$patientName}</strong>, our pharmacist care team is reminding you that your monthly chronic prescription for <strong>{$condition}</strong> is due for replenishment.
                            </p>
                            
                            <!-- Refill Schedule Highlight Box -->
                            <div style="background-color:#f0fdfa; border:2px solid #5eead4; border-radius:14px; padding:20px 24px; margin:22px 0;">
                                <div style="font-size:12px; font-weight:800; color:#0f766e; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px;">Next Scheduled Refill Date</div>
                                <div style="font-size:22px; font-weight:800; color:#0d9488; margin-bottom:14px;">{$formattedDate}</div>
                                
                                <div style="font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:4px;">Prescribed Regular Medicines:</div>
                                <div style="font-size:14px; font-weight:700; color:#0f172a; line-height:1.5; background:#ffffff; padding:10px 14px; border-radius:8px; border:1px solid #ccfbf1;">{$medicines}</div>
                            </div>
                            
                            {$notesBlock}

                            <div style="background:#f8fafc; border-radius:10px; padding:16px 20px; margin:20px 0; border:1px solid #e2e8f0; font-size:13px; line-height:1.6; color:#475569;">
                                <strong style="color:#0d223a;">Free Doorstep Delivery Guarantee:</strong><br>
                                We ensure zero-gap treatment with temperature-controlled doorstep packaging. Would you like our pharmacy rider to dispatch your fresh 30-day supply?
                            </div>

                            <!-- Action Button -->
                            <table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin:26px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="tel:{$phone}" style="background-color:#00c08b; color:#ffffff; padding:14px 32px; border-radius:10px; font-weight:800; font-size:15px; text-decoration:none; display:inline-block; box-shadow:0 6px 18px rgba(0,192,139,0.3);">
                                            Confirm Refill via Helpline ({$phone})
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="font-size:12px; color:#94a3b8; line-height:1.5; margin:0; text-align:center;">
                                Dedicated Pharmacist Helpline &bull; Monday to Sunday 24/7 &bull; HomeMed Healthcare
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer Strip -->
                    <tr>
                        <td style="background-color:#f8fafc; border-top:1px solid #e2e8f0; padding:20px 36px; text-align:center; font-size:12px; color:#64748b;">
                            &copy; HomeMed Pharmacy &amp; Chronic Care Management &bull; Strictly Confidential Patient Advisory
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}

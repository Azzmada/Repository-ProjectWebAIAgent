<?php
/**
 * Mail layer.
 *
 * - Uses PHPMailer over SMTP when vendor/autoload.php is available and
 *   MAIL_HOST is configured.
 * - Otherwise writes the rendered HTML e-mail to storage/mail so the whole
 *   booking flow remains testable without an SMTP server.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function absolute_url(string $path = ''): string
{
    $path = $path !== '' ? '/' . ltrim($path, '/') : '';

    // BASE_URL may already be an absolute URL (APP_URL), in which case it
    // must be used as-is instead of prefixing the current host again.
    if (preg_match('#^https?://#i', BASE_URL)) {
        return rtrim(BASE_URL, '/') . $path;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . rtrim(BASE_URL, '/') . $path;
}

/**
 * Send (or log) an HTML e-mail.
 */
function send_mail(string $toEmail, string $toName, string $subject, string $html, string $plain = ''): bool
{
    $autoload = ROOT_PATH . '/vendor/autoload.php';
    $canSmtp = MAIL_HOST !== '' && is_file($autoload);

    if ($canSmtp) {
        require_once $autoload;
        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = MAIL_HOST;
                $mail->Port = MAIL_PORT;
                $mail->SMTPAuth = MAIL_USERNAME !== '';
                if (MAIL_USERNAME !== '') {
                    $mail->Username = MAIL_USERNAME;
                    $mail->Password = MAIL_PASSWORD;
                }
                if (MAIL_ENCRYPTION !== '') {
                    $mail->SMTPSecure = MAIL_ENCRYPTION;
                }
                $mail->CharSet = 'UTF-8';
                $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
                $mail->addAddress($toEmail, $toName);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $html;
                $mail->AltBody = $plain !== '' ? $plain : strip_tags($html);
                $mail->send();
                return true;
            } catch (Throwable $e) {
                error_log('[mail] SMTP error: ' . $e->getMessage());
            }
        }
    }

    // Development fallback: store the email on disk.
    if (!is_dir(MAIL_LOG_PATH)) {
        @mkdir(MAIL_LOG_PATH, 0775, true);
    }
    $safe = preg_replace('/[^a-z0-9]+/i', '-', $subject) ?: 'mail';
    $file = MAIL_LOG_PATH . '/' . date('Ymd-His') . '-' . substr($safe, 0, 40) . '-' . bin2hex(random_bytes(3)) . '.html';
    $header = "<!-- To: " . htmlspecialchars($toEmail, ENT_QUOTES) . " | Subject: " . htmlspecialchars($subject, ENT_QUOTES) . " -->\n";
    $written = @file_put_contents($file, $header . $html);

    return $written !== false;
}

function email_layout(string $heading, string $intro, string $bodyHtml, ?string $ctaText = null, ?string $ctaUrl = null): string
{
    $cta = '';
    if ($ctaText !== null && $ctaUrl !== null) {
        $cta = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0"><tr><td style="border-radius:999px;background:#0ea5e9">'
            . '<a href="' . e($ctaUrl) . '" style="display:inline-block;padding:14px 28px;color:#ffffff;font-weight:700;text-decoration:none;font-size:15px">'
            . e($ctaText) . '</a></td></tr></table>';
    }

    return '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#f1f5f9;font-family:Inter,Arial,sans-serif;color:#1e293b">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 12px">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 8px 30px rgba(15,23,42,.08)">'
        . '<tr><td style="background:linear-gradient(135deg,#0ea5e9,#f97316);padding:28px 32px">'
        . '<div style="color:#ffffff;font-size:22px;font-weight:800;letter-spacing:-.3px">✈ TerbangLebih</div>'
        . '<div style="color:rgba(255,255,255,.85);font-size:13px;margin-top:4px">Terbang lebih mudah, jelajahi dunia.</div>'
        . '</td></tr>'
        . '<tr><td style="padding:32px">'
        . '<h1 style="font-size:22px;margin:0 0 12px;color:#0f172a">' . e($heading) . '</h1>'
        . '<p style="font-size:15px;line-height:1.7;margin:0 0 18px;color:#475569">' . e($intro) . '</p>'
        . $bodyHtml . $cta
        . '<p style="font-size:12px;color:#94a3b8;line-height:1.6;margin-top:28px">Email ini dikirim otomatis oleh sistem ' . e(APP_NAME) . '. Jangan balas email ini.</p>'
        . '</td></tr>'
        . '<tr><td style="background:#0f172a;padding:20px 32px;color:#94a3b8;font-size:12px">'
        . '&copy; ' . date('Y') . ' ' . e(APP_NAME) . ' · ' . e(setting('contact_email', 'support@terbanglebih.com'))
        . '</td></tr></table></td></tr></table></body></html>';
}

/**
 * Build the itinerary + price block shared by all booking e-mails.
 *
 * @param array<string,mixed> $booking
 */
function booking_email_body(array $booking): string
{
    $legs = get_booking_legs((int) $booking['id']);
    $rows = '';
    foreach ($legs as $leg) {
        $rows .= '<tr>'
            . '<td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:13px">'
            . '<strong>' . e($leg['from_code']) . ' → ' . e($leg['to_code']) . '</strong><br>'
            . '<span style="color:#64748b">' . e(format_date_id($leg['travel_date'], true)) . ' · ' . e(substr((string) $leg['dep_time'], 0, 5)) . '</span>'
            . '</td>'
            . '<td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:13px;text-align:right">'
            . e($leg['airline_name']) . '<br><span style="color:#64748b">' . e($leg['flight_num']) . '</span>'
            . '</td></tr>';
    }

    $passengers = get_booking_passengers((int) $booking['id']);
    $paxNames = implode(', ', array_map(static fn ($p) => $p['name'], $passengers));

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;margin:8px 0 18px">'
        . '<tr><td style="padding-bottom:10px;font-size:13px;color:#64748b">Kode Booking</td>'
        . '<td style="padding-bottom:10px;font-size:13px;text-align:right;font-weight:800;color:#0ea5e9;letter-spacing:.5px">' . e($booking['booking_code']) . '</td></tr>'
        . $rows
        . '<tr><td style="padding-top:12px;font-size:13px;color:#64748b">Penumpang</td>'
        . '<td style="padding-top:12px;font-size:13px;text-align:right">' . e($paxNames) . '</td></tr>'
        . '<tr><td style="padding-top:6px;font-size:13px;color:#64748b">Total</td>'
        . '<td style="padding-top:6px;font-size:16px;text-align:right;font-weight:800;color:#f97316">' . e(rupiah($booking['total_price'])) . '</td></tr>'
        . '<tr><td style="padding-top:6px;font-size:13px;color:#64748b">Status</td>'
        . '<td style="padding-top:6px;font-size:13px;text-align:right;font-weight:700">' . e(strtoupper(status_label($booking['status']))) . '</td></tr>'
        . '</table>';
}

function send_booking_confirmation(int $bookingId): bool
{
    $booking = Database::fetch('SELECT * FROM bookings WHERE id = ?', [$bookingId]);
    if (!$booking) {
        return false;
    }
    $user = Database::fetch('SELECT * FROM users WHERE id = ?', [(int) $booking['user_id']]);
    if (!$user) {
        return false;
    }

    $detailUrl = absolute_url('user/booking-detail.php?code=' . urlencode($booking['booking_code']));
    $html = email_layout(
        'Pembayaran diterima — selamat terbang!',
        'Terima kasih ' . $user['name'] . '. Pembayaran untuk booking ' . $booking['booking_code'] . ' telah kami terima. E-tiket Anda sudah aktif.',
        booking_email_body($booking),
        'Lihat E-Tiket',
        $detailUrl
    );

    return send_mail($user['email'], $user['name'], 'Konfirmasi Booking ' . $booking['booking_code'] . ' — ' . APP_NAME, $html);
}

function send_booking_pending(int $bookingId): bool
{
    $booking = Database::fetch('SELECT * FROM bookings WHERE id = ?', [$bookingId]);
    if (!$booking) {
        return false;
    }
    $user = Database::fetch('SELECT * FROM users WHERE id = ?', [(int) $booking['user_id']]);
    if (!$user) {
        return false;
    }

    $payUrl = absolute_url('booking/payment.php?code=' . urlencode($booking['booking_code']));
    $html = email_layout(
        'Selesaikan pembayaran Anda',
        'Booking ' . $booking['booking_code'] . ' sudah kami simpan. Silakan selesaikan pembayaran sebelum batas waktu agar kursi tidak dilepas.',
        booking_email_body($booking),
        'Bayar Sekarang',
        $payUrl
    );

    return send_mail($user['email'], $user['name'], 'Pembayaran Booking ' . $booking['booking_code'] . ' — ' . APP_NAME, $html);
}

function send_booking_status_update(int $bookingId, string $oldStatus): bool
{
    $booking = Database::fetch('SELECT * FROM bookings WHERE id = ?', [$bookingId]);
    if (!$booking) {
        return false;
    }
    $user = Database::fetch('SELECT * FROM users WHERE id = ?', [(int) $booking['user_id']]);
    if (!$user) {
        return false;
    }

    $status = (string) $booking['status'];
    if ($status === $oldStatus) {
        return false;
    }

    $heading = match ($status) {
        'paid'      => 'Pembayaran berhasil dikonfirmasi',
        'cancelled' => 'Booking Anda telah dibatalkan',
        'expired'   => 'Booking Anda telah kedaluwarsa',
        default     => 'Status booking diperbarui',
    };
    $intro = 'Status booking ' . $booking['booking_code'] . ' berubah dari "' . status_label($oldStatus) . '" menjadi "' . status_label($status) . '".';

    $cta = '';
    $ctaUrl = null;
    if ($status === 'paid') {
        $cta = 'Lihat E-Tiket';
        $ctaUrl = absolute_url('user/booking-detail.php?code=' . urlencode($booking['booking_code']));
    } elseif ($status === 'pending') {
        $cta = 'Bayar Sekarang';
        $ctaUrl = absolute_url('booking/payment.php?code=' . urlencode($booking['booking_code']));
    }

    $html = email_layout($heading, $intro, booking_email_body($booking), $cta ?: null, $ctaUrl);
    return send_mail($user['email'], $user['name'], $heading . ' — ' . $booking['booking_code'], $html);
}

function send_password_reset_email(string $email, string $name, string $link, int $minutes): bool
{
    $html = email_layout(
        'Reset password akun Anda',
        'Kami menerima permintaan reset password untuk akun ' . $email . '. Tautan ini berlaku selama ' . $minutes . ' menit dan hanya bisa dipakai satu kali.',
        '<p style="font-size:13px;color:#64748b">Jika Anda tidak meminta reset password, abaikan email ini — password Anda tidak akan berubah.</p>',
        'Buat Password Baru',
        $link
    );
    return send_mail($email, $name, 'Reset Password — ' . APP_NAME, $html);
}

<?php
/**
 * Handle proof-of-payment uploads and (simulated) card payments.
 *
 * A successful upload marks the payment as paid, updates the booking status
 * to "paid" and sends the confirmation e-mail — this is the automatic
 * status update required by the checkout flow.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/booking.php';

require_login();
expire_stale_bookings();

$user = auth_user();
if (!$user) {
    redirect(base_url('auth/login.php'));
}

if (!is_post()) {
    redirect(base_url('user/my-bookings.php'));
}
verify_csrf();

$code = input('code');
$booking = $code !== '' ? get_booking_for_user($code, (int) $user['id']) : false;
if (!$booking) {
    flash('error', 'Pesanan tidak ditemukan atau bukan milik akun Anda.');
    redirect(base_url('user/my-bookings.php'));
}

$bookingId = (int) $booking['id'];
$paymentId = input_int('payment_id');
$payment = $paymentId > 0
    ? Database::fetch('SELECT * FROM payments WHERE id = ? AND booking_id = ? LIMIT 1', [$paymentId, $bookingId])
    : false;

if (!$payment) {
    flash('error', 'Data pembayaran tidak ditemukan.');
    redirect(base_url('booking/payment.php?code=' . urlencode($code)));
}

if ($booking['status'] !== 'pending' || $payment['status'] === 'paid') {
    flash('info', 'Pembayaran pesanan ini sudah diproses.');
    redirect(base_url('user/booking-detail.php?code=' . urlencode($code)));
}

$action = input('action');
$proofPath = null;

if ($action === 'pay') {
    // Only the simulated credit-card flow may mark a payment paid without a file.
    if ($payment['method'] !== 'creditcard') {
        flash('error', 'Metode pembayaran ini membutuhkan bukti transfer.');
        redirect(base_url('booking/instruction.php?code=' . urlencode($code) . '&payment=' . $paymentId));
    }
} else {
    $file = $_FILES['proof'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errorCode = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        $message = match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas 2 MB.',
            UPLOAD_ERR_NO_FILE => 'Pilih file bukti pembayaran terlebih dahulu.',
            default => 'File gagal diunggah. Silakan coba lagi.',
        };
        flash('error', $message);
        redirect(base_url('booking/instruction.php?code=' . urlencode($code) . '&payment=' . $paymentId));
    }

    if ((int) $file['size'] <= 0 || (int) $file['size'] > MAX_UPLOAD_BYTES) {
        flash('error', 'Ukuran file harus antara 1 byte dan 2 MB.');
        redirect(base_url('booking/instruction.php?code=' . urlencode($code) . '&payment=' . $paymentId));
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file((string) $file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    if (!isset($allowed[$mime])) {
        flash('error', 'Format file tidak didukung. Gunakan JPG, PNG atau PDF.');
        redirect(base_url('booking/instruction.php?code=' . urlencode($code) . '&payment=' . $paymentId));
    }

    $directory = UPLOAD_PATH . '/proofs';
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        flash('error', 'Folder upload tidak tersedia. Hubungi administrator.');
        redirect(base_url('booking/instruction.php?code=' . urlencode($code) . '&payment=' . $paymentId));
    }

    $baseName = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', (string) $booking['booking_code']) ?: 'booking');
    $fileName = $baseName . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];

    if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $fileName)) {
        flash('error', 'Gagal menyimpan file bukti pembayaran. Silakan coba lagi.');
        redirect(base_url('booking/instruction.php?code=' . urlencode($code) . '&payment=' . $paymentId));
    }

    $proofPath = 'proofs/' . $fileName;
}

try {
    Database::begin();

    if ($proofPath !== null) {
        Database::execute(
            'UPDATE payments SET status = "paid", proof_path = ?, paid_at = NOW() WHERE id = ? AND booking_id = ?',
            [$proofPath, $paymentId, $bookingId]
        );
    } else {
        Database::execute(
            'UPDATE payments SET status = "paid", paid_at = NOW() WHERE id = ? AND booking_id = ?',
            [$paymentId, $bookingId]
        );
    }

    // Any other pending attempt for this booking is obsolete now.
    Database::execute(
        'UPDATE payments SET status = "failed" WHERE booking_id = ? AND id <> ? AND status = "pending"',
        [$bookingId, $paymentId]
    );

    Database::execute(
        'UPDATE bookings SET status = "paid" WHERE id = ? AND status = "pending"',
        [$bookingId]
    );

    Database::commit();
} catch (Throwable $e) {
    Database::rollback();
    if ($proofPath !== null) {
        @unlink(UPLOAD_PATH . '/' . $proofPath);
    }
    error_log('[payment] confirmation failed: ' . $e->getMessage());
    flash('error', 'Pembayaran gagal dikonfirmasi. Silakan coba lagi atau hubungi CS.');
    redirect(base_url('booking/instruction.php?code=' . urlencode($code) . '&payment=' . $paymentId));
}

// Confirmation e-mail (non-fatal).
try {
    send_booking_confirmation($bookingId);
} catch (Throwable $e) {
    error_log('[payment] confirmation mail failed: ' . $e->getMessage());
}

flash('success', 'Pembayaran berhasil dikonfirmasi. E-tiket Anda sudah aktif!');
redirect(base_url('user/booking-detail.php?code=' . urlencode($code) . '&paid=1'));

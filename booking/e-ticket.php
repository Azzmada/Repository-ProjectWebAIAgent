<?php
/**
 * Downloadable PDF e-ticket.
 *
 * Uses DomPDF when Composer dependencies are installed; otherwise falls
 * back to the bundled dependency-free SimplePDF writer so the feature works
 * on a plain XAMPP install.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/booking.php';
require_once __DIR__ . '/../includes/SimplePDF.php';

require_login();
expire_stale_bookings();

$user = auth_user();
if (!$user) {
    redirect(base_url('auth/login.php'));
}

$code = trim((string) ($_GET['code'] ?? ''));
$booking = $code !== '' ? get_booking_for_user($code, (int) $user['id']) : false;
if (!$booking) {
    flash('error', 'E-tiket tidak ditemukan atau bukan milik akun Anda.');
    redirect(base_url('user/my-bookings.php'));
}
if ($booking['status'] !== 'paid') {
    flash('error', 'E-tiket hanya tersedia setelah pembayaran dikonfirmasi.');
    redirect(base_url('user/booking-detail.php?code=' . urlencode($code)));
}

$bookingId = (int) $booking['id'];
$legs = get_booking_legs($bookingId);
$passengers = get_booking_passengers($bookingId);
$passengerName = $passengers ? implode(', ', array_map(static fn ($p) => $p['name'], $passengers)) : (string) $user['name'];
$fileBase = 'e-ticket-' . preg_replace('/[^A-Za-z0-9-]+/', '-', (string) $booking['booking_code']);
$fileName = $fileBase . '.pdf';

// -----------------------------------------------------------------
// Preferred path: DomPDF (richer layout) when vendor is available.
// -----------------------------------------------------------------
$autoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
    if (class_exists(\Dompdf\Dompdf::class)) {
        try {
            $typeLabels = ['adult' => 'Dewasa', 'child' => 'Anak', 'infant' => 'Bayi'];
            $rows = '';
            foreach ($legs as $leg) {
                $rows .= '<tr>'
                    . '<td style="padding:14px 10px;border-bottom:1px solid #e2e8f0">'
                    . '<div style="font-weight:700;color:#0f172a">' . e($leg['from_code']) . ' → ' . e($leg['to_code']) . '</div>'
                    . '<div style="font-size:11px;color:#64748b">' . e($leg['from_name']) . ' → ' . e($leg['to_name']) . '</div>'
                    . '</td>'
                    . '<td style="padding:14px 10px;border-bottom:1px solid #e2e8f0;font-size:12px">'
                    . e($leg['airline_name']) . ' · ' . e($leg['flight_num']) . '<br>'
                    . '<span style="color:#64748b">' . e(format_date_id($leg['travel_date'], true)) . ' · ' . e(substr((string) $leg['dep_time'], 0, 5)) . ' - ' . e(substr((string) $leg['arr_time'], 0, 5)) . '</span>'
                    . '</td>'
                    . '<td style="padding:14px 10px;border-bottom:1px solid #e2e8f0;font-size:11px;color:#64748b;text-align:right">'
                    . e(format_duration((int) $leg['duration'])) . '<br>' . ((int) $leg['stops'] === 0 ? 'Langsung' : (int) $leg['stops'] . ' transit')
                    . '</td></tr>';
            }
            $paxRows = '';
            foreach ($passengers as $passenger) {
                $paxRows .= '<span style="display:inline-block;background:#f1f5f9;border-radius:999px;padding:6px 12px;margin:0 6px 6px 0;font-size:11px">'
                    . e($passenger['name']) . ' <span style="color:#64748b">(' . e($typeLabels[$passenger['type']] ?? 'Dewasa') . ')</span></span>';
            }

            $html = '<!doctype html><html lang="id"><head><meta charset="utf-8"><style>'
                . 'body{font-family:DejaVu Sans,Arial,sans-serif;color:#1e293b;margin:0;padding:32px}'
                . '.brand{font-size:26px;font-weight:800;color:#0ea5e9}'
                . '.tag{font-size:11px;color:#64748b;margin-top:2px}'
                . '.code{font-size:20px;font-weight:800;letter-spacing:2px;color:#0f172a}'
                . '.status{display:inline-block;background:#dcfce7;color:#15803d;border-radius:999px;padding:5px 14px;font-size:11px;font-weight:700}'
                . 'table{width:100%;border-collapse:collapse}'
                . '.box{border:1px solid #e2e8f0;border-radius:14px;padding:16px;margin-top:16px}'
                . '.muted{color:#64748b;font-size:11px}'
                . '.total{font-size:24px;font-weight:800;color:#f97316}'
                . '.cut{border-top:1px dashed #cbd5e1;margin:20px 0}'
                . '</style></head><body>'
                . '<table><tr><td><div class="brand">✈ TerbangLebih</div><div class="tag">E-TICKET / BOARDING PASS</div></td>'
                . '<td style="text-align:right"><div class="muted">Kode Booking</div><div class="code">' . e($booking['booking_code']) . '</div><div class="status" style="margin-top:6px">LUNAS</div></td></tr></table>'
                . '<div class="cut"></div>'
                . '<table><tr><td><div class="muted">Penumpang</div><div style="font-size:14px;font-weight:700;margin-top:4px">' . e($passengerName) . '</div></td>'
                . '<td style="text-align:right"><div class="muted">Kelas</div><div style="font-size:14px;font-weight:700;margin-top:4px">' . e($booking['cabin_class']) . '</div></td></tr></table>'
                . '<div class="box"><table>' . $rows . '</table></div>'
                . '<div class="box"><div class="muted" style="margin-bottom:8px">Daftar Penumpang</div>' . $paxRows . '</div>'
                . '<table style="margin-top:16px"><tr><td><div class="muted">Total dibayar</div><div class="total">' . e(rupiah($booking['total_price'])) . '</div></td>'
                . '<td style="text-align:right"><div class="muted">Diterbitkan</div><div style="font-size:12px;font-weight:600">' . e(format_date_id(date('Y-m-d'), true)) . '</div></td></tr></table>'
                . '<div class="cut"></div>'
                . '<div class="muted">Tunjukkan e-tiket ini bersama identitas asli saat check-in. Datang minimal 2 jam sebelum keberangkatan. '
                . 'E-tiket diterbitkan otomatis oleh ' . e(APP_NAME) . ' pada ' . e(date('d/m/Y H:i')) . '.</div>'
                . '</body></html>';

            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $dompdf->stream($fileName, ['Attachment' => true]);
            exit;
        } catch (Throwable $e) {
            error_log('[eticket] DomPDF failed, using SimplePDF: ' . $e->getMessage());
        }
    }
}

// -----------------------------------------------------------------
// Fallback: SimplePDF
// -----------------------------------------------------------------
$pdf = new SimplePDF();
$pageWidth = $pdf->pageWidth();
$margin = 40;
$contentWidth = $pageWidth - ($margin * 2);

$right = static function (SimplePDF $pdf, float $rightEdge, float $y, string $text, float $size, bool $bold, array $rgb): void {
    $approx = strlen($text) * $size * 0.52;
    $pdf->text(max(10, $rightEdge - $approx), $y, $text, $size, $bold, $rgb);
};

$drawCompactHeader = static function (SimplePDF $pdf, array $booking, int $page) use ($right): void {
    $pdf->rect(0, 0, 595.28, 88, [0.055, 0.647, 0.91]);
    $pdf->text(40, 32, 'TerbangLebih', 18, true, [1, 1, 1]);
    $pdf->text(40, 52, 'E-TICKET / BOARDING PASS', 8, false, [0.88, 0.95, 1]);
    $right($pdf, 555, 32, (string) $booking['booking_code'], 14, true, [1, 1, 1]);
    $right($pdf, 555, 52, 'LUNAS', 9, true, [0.7, 1, 0.8]);
    if ($page > 1) {
        $pdf->text(40, 70, 'Halaman ' . $page, 8, false, [0.88, 0.95, 1]);
    }
};

$drawCompactHeader($pdf, $booking, 1);

$typeLabels = ['adult' => 'Dewasa', 'child' => 'Anak', 'infant' => 'Bayi'];
$gray = [0.39, 0.45, 0.55];
$dark = [0.06, 0.09, 0.16];

$y = 120;
$pdf->text($margin, $y, 'PENUMPANG', 9, true, $gray);
$y += 18;
foreach ($passengers as $passenger) {
    $pdf->text($margin, $y, (string) $passenger['name'], 12, true, $dark);
    $pdf->text($margin + 300, $y, (string) ($typeLabels[$passenger['type']] ?? 'Dewasa'), 10, false, $gray);
    $y += 18;
}
if (!$passengers) {
    $pdf->text($margin, $y, (string) $user['name'], 12, true, $dark);
    $y += 18;
}

$y += 10;
$pdf->line($margin, $y, $pageWidth - $margin, $y);
$y += 20;
$pdf->text($margin, $y, 'RUTE PENERBANGAN', 9, true, $gray);
$y += 18;

$page = 1;
foreach ($legs as $leg) {
    if ($y + 90 > 730) {
        $pdf->addPage();
        $page++;
        $drawCompactHeader($pdf, $booking, $page);
        $y = 110;
    }

    $pdf->rect($margin, $y, $contentWidth, 82, [0.96, 0.98, 1]);
    $pdf->text($margin + 12, $y + 18, (string) $leg['airline_name'], 11, true, $dark);
    $pdf->text($margin + 12, $y + 33, (string) $leg['flight_num'] . '  ·  ' . (string) $leg['aircraft'], 8, false, $gray);

    $pdf->text($margin + 12, $y + 58, (string) $leg['from_code'] . '  ' . substr((string) $leg['dep_time'], 0, 5), 16, true, $dark);
    $pdf->text($margin + 190, $y + 58, '→', 16, true, [0.055, 0.647, 0.91]);
    $pdf->text($margin + 235, $y + 58, (string) $leg['to_code'] . '  ' . substr((string) $leg['arr_time'], 0, 5), 16, true, $dark);

    $right($pdf, $pageWidth - $margin - 12, $y + 18, (string) format_date_id($leg['travel_date'], true), 10, true, $dark);
    $right(
        $pdf,
        $pageWidth - $margin - 12,
        $y + 33,
        format_duration((int) $leg['duration']) . ' · ' . ((int) $leg['stops'] === 0 ? 'Langsung' : (int) $leg['stops'] . ' Transit'),
        8,
        false,
        $gray
    );
    $pdf->text($margin + 12, $y + 74, (string) $leg['from_name'] . ' → ' . (string) $leg['to_name'], 8, false, $gray);

    $y += 92;
}

if ($y + 130 > 760) {
    $pdf->addPage();
    $page++;
    $drawCompactHeader($pdf, $booking, $page);
    $y = 110;
}

$y += 10;
$pdf->line($margin, $y, $pageWidth - $margin, $y);
$y += 24;
$pdf->text($margin, $y, 'Kode Booking', 9, false, $gray);
$pdf->text($margin + 110, $y, (string) $booking['booking_code'], 11, true, $dark);
$y += 20;
$pdf->text($margin, $y, 'Kelas', 9, false, $gray);
$pdf->text($margin + 110, $y, (string) $booking['cabin_class'], 11, true, $dark);
$y += 20;
$pdf->text($margin, $y, 'Jumlah Penumpang', 9, false, $gray);
$pdf->text($margin + 110, $y, (string) $booking['pax'] . ' orang', 11, true, $dark);

$right($pdf, $pageWidth - $margin, $y - 40, 'TOTAL DIBAYAR', 9, true, $gray);
$right($pdf, $pageWidth - $margin, $y - 20, rupiah($booking['total_price']), 18, true, [0.976, 0.451, 0.086]);
$right($pdf, $pageWidth - $margin, $y, 'Status: LUNAS', 10, true, [0.086, 0.639, 0.29]);

$y += 40;
$pdf->line($margin, $y, $pageWidth - $margin, $y);
$y += 16;
$pdf->text($margin, $y, 'Tunjukkan e-tiket ini bersama identitas asli saat check-in. Datang minimal 2 jam sebelum keberangkatan.', 8, false, $gray);
$y += 13;
$pdf->text($margin, $y, 'Diterbitkan otomatis oleh ' . APP_NAME . ' pada ' . date('d/m/Y H:i') . '.', 8, false, $gray);
$y += 13;
$pdf->text($margin, $y, 'Layanan pelanggan: ' . setting('contact_email', 'support@terbanglebih.com') . ' · ' . setting('contact_phone', '0800-1-888-999'), 8, false, $gray);

$pdf->output($fileName);

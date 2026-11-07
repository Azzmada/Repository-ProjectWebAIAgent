<?php
/**
 * Shared helper functions: URLs, escaping, CSRF, auth, flash messages,
 * settings, money/date formatting and booking utilities.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

// -----------------------------------------------------------------
// Output & URL helpers
// -----------------------------------------------------------------

/** Escape any value for safe HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(string $path = ''): string
{
    return BASE_URL . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function asset(string $path): string
{
    return base_url('assets/' . ltrim($path, '/')) . '?v=' . APP_VERSION;
}

function upload_url(string $path): string
{
    return base_url('uploads/' . ltrim($path, '/'));
}

/**
 * Build a public page URL.
 *
 * @param array<string,mixed> $params
 */
function page_url(string $page, array $params = []): string
{
    if (PRETTY_URLS) {
        $query = $params ? '?' . http_build_query($params) : '';
        return base_url($page . $query);
    }
    return base_url('index.php?' . http_build_query(['page' => $page] + $params));
}

/** Redirect to an absolute path / URL or an app relative path. */
function redirect(string $url): never
{
    if (!preg_match('#^(https?:)?//#i', $url) && !str_starts_with($url, '/')) {
        $url = base_url($url);
    }
    header('Location: ' . $url);
    exit;
}

function current_url(): string
{
    return $_SERVER['REQUEST_URI'] ?? base_url();
}

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

/** Send a JSON response and end the request. */
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function abort(int $status, string $message = ''): never
{
    http_response_code($status);
    echo '<!doctype html><meta charset="utf-8"><title>' . $status . '</title>';
    echo '<div style="font-family:system-ui;padding:3rem;text-align:center">';
    echo '<h1 style="font-size:3rem;margin:0">' . $status . '</h1>';
    echo '<p>' . e($message ?: 'Halaman tidak ditemukan.') . '</p>';
    echo '<a href="' . e(page_url('home')) . '">Kembali ke beranda</a></div>';
    exit;
}

// -----------------------------------------------------------------
// Input helpers
// -----------------------------------------------------------------

function input(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function input_int(string $key, int $default = 0): int
{
    $value = $_POST[$key] ?? $_GET[$key] ?? null;
    return $value === null || $value === '' ? $default : (int) $value;
}

function input_array(string $key): array
{
    $value = $_POST[$key] ?? $_GET[$key] ?? [];
    return is_array($value) ? $value : [];
}

function post_bool(string $key): bool
{
    return isset($_POST[$key]) && in_array((string) $_POST[$key], ['1', 'on', 'true', 'yes'], true);
}

/** HTML-escape then trim — used by admin forms. */
function clean(string $value): string
{
    return trim(strip_tags($value));
}

function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function valid_phone(string $phone): bool
{
    return (bool) preg_match('/^[+]?[0-9][0-9\s\-().]{6,19}$/', $phone);
}

function valid_date(string $date): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt !== false && $dt->format('Y-m-d') === $date;
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

// -----------------------------------------------------------------
// CSRF
// -----------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION[CSRF_KEY])) {
        $_SESSION[CSRF_KEY] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION[CSRF_KEY];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(?string $token = null): bool
{
    $token ??= (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return $token !== '' && hash_equals(csrf_token(), $token);
}

/** Abort the request when the CSRF token is invalid. */
function verify_csrf(): void
{
    if (!csrf_valid()) {
        if (is_ajax()) {
            json_response(['ok' => false, 'message' => 'Sesi kedaluwarsa. Muat ulang halaman.'], 419);
        }
        http_response_code(419);
        exit('CSRF token tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}

// -----------------------------------------------------------------
// Flash messages & old input
// -----------------------------------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** @return array<int,array{type:string,message:string}> */
function take_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flashes;
}

function flash_input(array $data): void
{
    unset($data['_csrf'], $data['password'], $data['password_confirm']);
    $_SESSION['_old_input'] = $data;
}

function old(string $key, string $default = ''): string
{
    $value = $_SESSION['_old_input'][$key] ?? $default;
    return is_string($value) ? $value : $default;
}

function clear_old_input(): void
{
    unset($_SESSION['_old_input']);
}

// -----------------------------------------------------------------
// Settings (cached key/value store)
// -----------------------------------------------------------------

/** @return array<string,string> */
function settings_all(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    try {
        foreach (Database::fetchAll('SELECT `key`, `value` FROM settings') as $row) {
            $cache[$row['key']] = (string) $row['value'];
        }
    } catch (Throwable) {
        $cache = [];
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_all();
    return array_key_exists($key, $all) ? $all[$key] : $default;
}

function setting_int(string $key, int $default = 0): int
{
    $value = setting($key, (string) $default);
    return is_numeric($value) ? (int) $value : $default;
}

function setting_bool(string $key, bool $default = false): bool
{
    $value = setting($key, $default ? '1' : '0');
    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

// -----------------------------------------------------------------
// Formatting
// -----------------------------------------------------------------

function rupiah(float|int|string|null $amount, bool $withPrefix = true): string
{
    $formatted = number_format((float) $amount, 0, ',', '.');
    return $withPrefix ? 'Rp ' . $formatted : $formatted;
}

/** @return array{0:string,1:string} [date, time] */
function split_datetime(?string $datetime): array
{
    if (!$datetime) {
        return ['-', '-'];
    }
    $ts = strtotime($datetime);
    return $ts ? [date('d M Y', $ts), date('H:i', $ts)] : ['-', '-'];
}

function format_date_id(?string $date, bool $withDay = false): string
{
    if (!$date) {
        return '-';
    }
    $ts = strtotime($date);
    if (!$ts) {
        return '-';
    }
    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $months = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $out = date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    return $withDay ? $days[(int) date('w', $ts)] . ', ' . $out : $out;
}

function format_duration(int $minutes): string
{
    $hours = intdiv($minutes, 60);
    $mins = $minutes % 60;
    return ($hours > 0 ? $hours . 'j ' : '') . $mins . 'm';
}

function initials(string $name, int $max = 2): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $out .= mb_strtoupper(mb_substr($part, 0, 1));
        if (mb_strlen($out) >= $max) {
            break;
        }
    }
    return $out !== '' ? $out : 'TL';
}

function status_label(string $status): string
{
    return match ($status) {
        'pending'   => 'Menunggu Pembayaran',
        'paid'      => 'Lunas',
        'cancelled' => 'Dibatalkan',
        'expired'   => 'Kedaluwarsa',
        default     => ucfirst($status),
    };
}

function status_badge(string $status): string
{
    return match ($status) {
        'pending'   => 'bg-yellow-100 text-yellow-700 border-yellow-200',
        'paid'      => 'bg-green-100 text-green-700 border-green-200',
        'cancelled' => 'bg-red-100 text-red-700 border-red-200',
        'expired'   => 'bg-gray-100 text-gray-600 border-gray-200',
        default     => 'bg-gray-100 text-gray-600 border-gray-200',
    };
}

function payment_method_label(string $method): string
{
    return match ($method) {
        'transfer'   => 'Transfer Bank',
        'va'         => 'Virtual Account',
        'ewallet'    => 'E-Wallet',
        'creditcard' => 'Kartu Kredit',
        default      => ucfirst($method),
    };
}

// -----------------------------------------------------------------
// Authentication (users)
// -----------------------------------------------------------------

/** @return array<string,mixed>|null */
function auth_user(): ?array
{
    static $user = null;
    static $loaded = false;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $row = Database::fetch('SELECT id, name, email, phone, created_at FROM users WHERE id = ?', [$id]);
    if (!$row) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $user = $row;
}

function is_logged_in(): bool
{
    return auth_user() !== null;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_logged_at'] = time();
    unset($_SESSION['_csrf_token']); // rotate token after privilege change
}

function logout_user(): void
{
    unset($_SESSION['user_id'], $_SESSION['user_logged_at'], $_SESSION['booking_draft']);
    session_regenerate_id(true);
}

/** Require a logged-in user; remember the intended URL. */
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['intended_url'] = current_url();
        flash('info', 'Silakan masuk terlebih dahulu untuk melanjutkan.');
        redirect(base_url('auth/login.php'));
    }
}

function intended_url(string $fallback = ''): string
{
    $url = $_SESSION['intended_url'] ?? '';
    unset($_SESSION['intended_url']);
    return $url !== '' ? $url : ($fallback !== '' ? $fallback : page_url('home'));
}

// -----------------------------------------------------------------
// Authentication (admins) — separate session key
// -----------------------------------------------------------------

/** @return array<string,mixed>|null */
function auth_admin(): ?array
{
    static $admin = null;
    static $loaded = false;

    if ($loaded) {
        return $admin;
    }
    $loaded = true;

    $id = (int) ($_SESSION['admin_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }
    $row = Database::fetch('SELECT id, name, email FROM admins WHERE id = ?', [$id]);
    if (!$row) {
        unset($_SESSION['admin_id']);
        return null;
    }
    return $admin = $row;
}

function is_admin_logged_in(): bool
{
    return auth_admin() !== null;
}

function login_admin(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_logged_at'] = time();
    unset($_SESSION['_csrf_token']);
}

function logout_admin(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_logged_at']);
    session_regenerate_id(true);
}

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        flash('info', 'Masuk ke panel admin untuk melanjutkan.');
        redirect(base_url('admin/index.php'));
    }
}

// -----------------------------------------------------------------
// Rate limiting (login attempts)
// -----------------------------------------------------------------

function rate_limit_exceeded(string $identifier): bool
{
    try {
        $count = (int) Database::fetch(
            'SELECT COUNT(*) AS c FROM login_attempts
             WHERE (identifier = ? OR ip = ?) AND attempted_at > (NOW() - INTERVAL ? SECOND)',
            [$identifier, client_ip(), LOGIN_WINDOW_SECONDS]
        )['c'];

        return $count >= LOGIN_MAX_ATTEMPTS;
    } catch (Throwable) {
        return false;
    }
}

function rate_limit_retry_after(string $identifier): int
{
    try {
        $row = Database::fetch(
            'SELECT MAX(attempted_at) AS last_attempt FROM login_attempts
             WHERE (identifier = ? OR ip = ?) AND attempted_at > (NOW() - INTERVAL ? SECOND)',
            [$identifier, client_ip(), LOGIN_WINDOW_SECONDS]
        );
        if (empty($row['last_attempt'])) {
            return 0;
        }
        $elapsed = time() - strtotime($row['last_attempt']);
        return max(1, LOGIN_LOCKOUT_SECONDS - $elapsed);
    } catch (Throwable) {
        return 0;
    }
}

function record_login_attempt(string $identifier): void
{
    try {
        Database::insert(
            'INSERT INTO login_attempts (identifier, ip, attempted_at) VALUES (?, ?, NOW())',
            [mb_substr($identifier, 0, 190), client_ip()]
        );
        // Opportunistic garbage collection.
        Database::execute('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    } catch (Throwable) {
        // Never block authentication because of logging issues.
    }
}

function clear_login_attempts(string $identifier): void
{
    try {
        Database::execute('DELETE FROM login_attempts WHERE identifier = ? OR ip = ?', [$identifier, client_ip()]);
    } catch (Throwable) {
    }
}

// -----------------------------------------------------------------
// Cities / flights / pricing
// -----------------------------------------------------------------

/**
 * Resolve user typed city ("Jakarta (CGK)", "CGK", "Jakarta") to a DB row.
 *
 * @return array<string,mixed>|null
 */
function resolve_city(string $value): ?array
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $code = null;
    if (preg_match('/\(([A-Za-z]{3})\)/', $value, $m)) {
        $code = strtoupper($m[1]);
    } elseif (preg_match('/^[A-Za-z]{3}$/', $value)) {
        $code = strtoupper($value);
    }

    if ($code !== null) {
        $row = Database::fetch('SELECT * FROM cities WHERE iata_code = ? LIMIT 1', [$code]);
        if ($row) {
            return $row;
        }
    }

    return Database::fetch(
        'SELECT * FROM cities WHERE name = ? OR name LIKE ? ORDER BY (name = ?) DESC, is_popular DESC LIMIT 1',
        [$value, $value . '%', $value]
    ) ?: null;
}

/**
 * Flights for a route, already joined with airline/city data.
 *
 * @return array<int,array<string,mixed>>
 */
function flights_for_route(int $fromCityId, int $toCityId, int $limit = 60): array
{
    return Database::fetchAll(
        'SELECT f.*, a.name AS airline_name, a.code AS airline_code, a.logo_url,
                fc.name AS from_name, fc.iata_code AS from_code, fc.country AS from_country,
                tc.name AS to_name, tc.iata_code AS to_code, tc.country AS to_country
         FROM flights f
         JOIN airlines a ON a.id = f.airline_id
         JOIN cities fc ON fc.id = f.from_city_id
         JOIN cities tc ON tc.id = f.to_city_id
         WHERE f.is_active = 1 AND f.from_city_id = ? AND f.to_city_id = ?
         ORDER BY (f.price / 1000 + f.duration * 5 - IF(f.stops = 0, 3000, 0)) ASC
         LIMIT ' . (int) $limit,
        [$fromCityId, $toCityId]
    );
}

/**
 * Fetch several flights by ID and return them keyed by flight ID.
 *
 * @param array<int,int|string> $ids
 * @return array<int,array<string,mixed>>
 */
function get_flights_by_ids(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0)));
    if (!$ids) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = Database::fetchAll(
        'SELECT f.*, a.name AS airline_name, a.code AS airline_code, a.logo_url,
                fc.name AS from_name, fc.iata_code AS from_code, fc.country AS from_country,
                tc.name AS to_name, tc.iata_code AS to_code, tc.country AS to_country
         FROM flights f
         JOIN airlines a ON a.id = f.airline_id
         JOIN cities fc ON fc.id = f.from_city_id
         JOIN cities tc ON tc.id = f.to_city_id
         WHERE f.id IN (' . $placeholders . ')',
        $ids
    );
    $keyed = [];
    foreach ($rows as $row) {
        $keyed[(int) $row['id']] = $row;
    }
    return $keyed;
}

/** Price for one passenger: infant 10%, child 75%, adult 100%. */
function passenger_price(float $basePrice, string $type): float
{
    return match ($type) {
        'infant' => round($basePrice * 0.10),
        'child'  => round($basePrice * 0.75),
        default  => $basePrice,
    };
}

/**
 * Total for all passengers based on a per-adult base price.
 *
 * @param array{adult:int,child:int,infant:int} $counts
 */
function passengers_subtotal(float $basePrice, array $counts): float
{
    return
        passenger_price($basePrice, 'adult') * (int) ($counts['adult'] ?? 0) +
        passenger_price($basePrice, 'child') * (int) ($counts['child'] ?? 0) +
        passenger_price($basePrice, 'infant') * (int) ($counts['infant'] ?? 0);
}

function total_pax(array $counts): int
{
    return (int) ($counts['adult'] ?? 0) + (int) ($counts['child'] ?? 0) + (int) ($counts['infant'] ?? 0);
}

/** "1 Dewasa, 1 Anak, Ekonomi" */
function pax_label(array $counts, string $cabin = 'Ekonomi'): string
{
    $parts = [];
    foreach (['adult' => 'Dewasa', 'child' => 'Anak', 'infant' => 'Bayi'] as $key => $label) {
        if ((int) ($counts[$key] ?? 0) > 0) {
            $parts[] = (int) $counts[$key] . ' ' . $label;
        }
    }
    $parts[] = $cabin;
    return implode(', ', $parts);
}

// -----------------------------------------------------------------
// Booking helpers
// -----------------------------------------------------------------

function generate_booking_code(): string
{
    for ($i = 0; $i < 10; $i++) {
        $code = 'TL-' . strtoupper(bin2hex(random_bytes(3)));
        $exists = Database::fetch('SELECT id FROM bookings WHERE booking_code = ?', [$code]);
        if (!$exists) {
            return $code;
        }
    }
    return 'TL-' . strtoupper(bin2hex(random_bytes(5)));
}

/** Mark pending bookings whose payment window passed as expired. */
function expire_stale_bookings(): void
{
    try {
        Database::execute(
            "UPDATE bookings SET status = 'expired'
             WHERE status = 'pending' AND expires_at IS NOT NULL AND expires_at < NOW()"
        );
    } catch (Throwable) {
    }
}

/** @return array<string,mixed>|false */
function get_booking_by_code(string $code): array|false
{
    return Database::fetch(
        'SELECT b.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
                f.flight_num, f.dep_time, f.arr_time, f.duration, f.aircraft,
                a.name AS airline_name, a.code AS airline_code,
                fc.name AS from_name, fc.iata_code AS from_code,
                tc.name AS to_name, tc.iata_code AS to_code,
                p.code AS promo_code
         FROM bookings b
         JOIN users u ON u.id = b.user_id
         JOIN flights f ON f.id = b.flight_id
         JOIN airlines a ON a.id = f.airline_id
         JOIN cities fc ON fc.id = f.from_city_id
         JOIN cities tc ON tc.id = f.to_city_id
         LEFT JOIN promos p ON p.id = b.promo_id
         WHERE b.booking_code = ? LIMIT 1',
        [$code]
    );
}

/** @return array<int,array<string,mixed>> */
function get_booking_legs(int $bookingId): array
{
    return Database::fetchAll(
        'SELECT bf.*, f.flight_num, f.dep_time, f.arr_time, f.duration, f.aircraft,
                f.stops, f.baggage_kg, f.meal, f.wifi, f.entertainment,
                a.name AS airline_name, a.code AS airline_code,
                fc.name AS from_name, fc.iata_code AS from_code,
                tc.name AS to_name, tc.iata_code AS to_code
         FROM booking_flights bf
         JOIN flights f ON f.id = bf.flight_id
         JOIN airlines a ON a.id = f.airline_id
         JOIN cities fc ON fc.id = f.from_city_id
         JOIN cities tc ON tc.id = f.to_city_id
         WHERE bf.booking_id = ?
         ORDER BY bf.leg_order ASC',
        [$bookingId]
    );
}

/** @return array<int,array<string,mixed>> */
function get_booking_passengers(int $bookingId): array
{
    return Database::fetchAll(
        'SELECT * FROM booking_passengers WHERE booking_id = ?
         ORDER BY FIELD(type, "adult", "child", "infant"), id ASC',
        [$bookingId]
    );
}

/** @return array<int,array<string,mixed>> */
function get_booking_payments(int $bookingId): array
{
    return Database::fetchAll('SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC', [$bookingId]);
}

/** @return array<string,mixed>|false */
function get_booking_for_user(string $code, int $userId): array|false
{
    $booking = get_booking_by_code($code);
    if (!$booking || (int) $booking['user_id'] !== $userId) {
        return false;
    }
    return $booking;
}

/**
 * Validate & apply a promo code.
 *
 * @return array{ok:bool,message:string,promo?:array<string,mixed>,discount?:float}
 */
function validate_promo(string $code, float $subtotal, ?int $userId = null): array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return ['ok' => false, 'message' => 'Masukkan kode promo terlebih dahulu.'];
    }

    $promo = Database::fetch('SELECT * FROM promos WHERE UPPER(code) = ? LIMIT 1', [$code]);
    if (!$promo) {
        return ['ok' => false, 'message' => 'Kode promo tidak ditemukan.'];
    }
    if (!(int) $promo['is_active']) {
        return ['ok' => false, 'message' => 'Kode promo sudah tidak aktif.'];
    }

    $today = date('Y-m-d');
    if (!empty($promo['valid_from']) && $today < $promo['valid_from']) {
        return ['ok' => false, 'message' => 'Kode promo belum berlaku.'];
    }
    if (!empty($promo['valid_until']) && $today > $promo['valid_until']) {
        return ['ok' => false, 'message' => 'Kode promo sudah kedaluwarsa.'];
    }
    if ((int) $promo['max_usage'] > 0 && (int) $promo['used_count'] >= (int) $promo['max_usage']) {
        return ['ok' => false, 'message' => 'Kuota kode promo ini sudah habis.'];
    }
    if ($subtotal < (float) $promo['min_transaction']) {
        return [
            'ok' => false,
            'message' => 'Minimal transaksi untuk promo ini adalah ' . rupiah($promo['min_transaction']) . '.',
        ];
    }

    if ($userId !== null) {
        $already = Database::fetch(
            'SELECT id FROM promo_usage pu JOIN bookings b ON b.id = pu.booking_id
             WHERE pu.promo_id = ? AND b.user_id = ? AND b.status IN ("pending","paid") LIMIT 1',
            [(int) $promo['id'], $userId]
        );
        if ($already) {
            return ['ok' => false, 'message' => 'Anda sudah pernah memakai kode promo ini.'];
        }
    }

    $discount = $promo['type'] === 'percent'
        ? round($subtotal * ((float) $promo['value'] / 100))
        : (float) $promo['value'];

    $discount = min($discount, $subtotal);

    return [
        'ok' => true,
        'message' => 'Promo berhasil diterapkan. Anda hemat ' . rupiah($discount) . '.',
        'promo' => $promo,
        'discount' => (float) $discount,
    ];
}

/** Session key used while checking out. */
function booking_draft(): ?array
{
    return $_SESSION['booking_draft'] ?? null;
}

function save_booking_draft(array $draft): void
{
    $_SESSION['booking_draft'] = $draft;
}

function clear_booking_draft(): void
{
    unset($_SESSION['booking_draft']);
}

// -----------------------------------------------------------------
// Misc
// -----------------------------------------------------------------

function client_initials(?string $name): string
{
    return initials($name ?? 'TerbangLebih');
}

function submitted_redirect_fallback(): string
{
    return page_url('home');
}

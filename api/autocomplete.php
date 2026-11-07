<?php
/**
 * City / airport autocomplete endpoint.
 *
 * GET /api/autocomplete.php?q=jak
 * Returns { ok: true, items: [{name, iata_code, country}] }
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 1) {
    json_response(['ok' => true, 'items' => []]);
}
if (mb_strlen($q) > 60) {
    $q = mb_substr($q, 0, 60);
}

$like = '%' . $q . '%';
$prefix = $q . '%';
$code = strtoupper($q);

try {
    $rows = Database::fetchAll(
        'SELECT name, iata_code, country
         FROM cities
         WHERE name LIKE ? OR iata_code LIKE ?
         ORDER BY (iata_code = ?) DESC, (name LIKE ?) DESC, is_popular DESC, sort_order ASC, name ASC
         LIMIT 10',
        [$like, $prefix, $code, $prefix]
    );
} catch (Throwable $e) {
    error_log('[autocomplete] ' . $e->getMessage());
    json_response(['ok' => false, 'message' => 'Gagal memuat saran kota.', 'items' => []], 500);
}

$items = array_map(static fn (array $row): array => [
    'name'      => (string) $row['name'],
    'iata_code' => (string) $row['iata_code'],
    'country'   => (string) $row['country'],
], $rows);

json_response(['ok' => true, 'items' => $items]);

<?php
/**
 * Booking flow helpers.
 *
 * A booking draft lives in the session (`booking_draft`) between the
 * passenger, review, create and payment steps. Flights are never trusted
 * from the client — only IDs + travel dates are stored and re-hydrated from
 * MySQL on every step so prices cannot be tampered with.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Hydrate and validate a set of legs.
 *
 * @param array<int|string,mixed> $flightIds
 * @param array<int|string,mixed> $dates
 * @return array<int,array{flight:array<string,mixed>,flight_id:int,date:string,price:float,order:int}>
 */
function booking_legs_from_ids(array $flightIds, array $dates, ?string &$error = null): array
{
    $error = null;
    $normalized = [];

    foreach ($flightIds as $key => $flightId) {
        $flightId = (int) $flightId;
        $date = trim((string) ($dates[$key] ?? ''));
        if ($flightId <= 0) {
            continue;
        }
        $normalized[] = ['id' => $flightId, 'date' => $date];
    }

    if (!$normalized) {
        $error = 'Pilih minimal satu penerbangan terlebih dahulu.';
        return [];
    }
    if (count($normalized) > 6) {
        $error = 'Maksimal 6 rute dalam satu pemesanan.';
        return [];
    }

    $rows = get_flights_by_ids(array_column($normalized, 'id'));
    $legs = [];
    $previousDate = '';

    foreach ($normalized as $index => $item) {
        $flight = $rows[$item['id']] ?? null;
        if (!$flight) {
            $error = 'Penerbangan yang Anda pilih tidak ditemukan. Silakan cari ulang.';
            return [];
        }
        if ((int) $flight['is_active'] !== 1) {
            $error = 'Penerbangan ' . $flight['flight_num'] . ' sudah tidak tersedia.';
            return [];
        }
        if (!valid_date($item['date'])) {
            $error = 'Tanggal penerbangan tidak valid.';
            return [];
        }
        if ($item['date'] < date('Y-m-d')) {
            $error = 'Tanggal penerbangan tidak boleh di masa lalu.';
            return [];
        }
        if ($previousDate !== '' && $item['date'] < $previousDate) {
            $error = 'Tanggal setiap rute harus berurutan dari rute pertama.';
            return [];
        }
        $previousDate = $item['date'];

        $legs[] = [
            'flight'    => $flight,
            'flight_id' => (int) $flight['id'],
            'date'      => $item['date'],
            'price'     => (float) $flight['price'],
            'order'     => $index + 1,
        ];
    }

    return $legs;
}

/**
 * Re-hydrate the legs stored in a booking draft.
 *
 * @param array<string,mixed> $draft
 * @return array<int,array{flight:array<string,mixed>,flight_id:int,date:string,price:float,order:int}>
 */
function booking_draft_legs(array $draft, ?string &$error = null): array
{
    $ids = [];
    $dates = [];
    foreach ((array) ($draft['legs'] ?? []) as $index => $leg) {
        if (!is_array($leg)) {
            continue;
        }
        $ids[$index] = (int) ($leg['flight_id'] ?? 0);
        $dates[$index] = (string) ($leg['date'] ?? '');
    }

    return booking_legs_from_ids($ids, $dates, $error);
}

/**
 * Calculate the booking price from server-side data only.
 *
 * @param array<int,array<string,mixed>> $legs
 * @param array{adult:int,child:int,infant:int} $counts
 * @param array<string,mixed>|null $promo
 * @return array{subtotal:float,discount:float,total:float,per_type:array<string,float>,leg_totals:array<int,float>}
 */
function booking_pricing(array $legs, array $counts, ?array $promo = null): array
{
    $subtotal = 0.0;
    $perType = ['adult' => 0.0, 'child' => 0.0, 'infant' => 0.0];
    $legTotals = [];

    foreach ($legs as $leg) {
        $base = (float) $leg['price'];
        $legTotal = 0.0;
        foreach (['adult', 'child', 'infant'] as $type) {
            $line = passenger_price($base, $type) * (int) ($counts[$type] ?? 0);
            $perType[$type] += $line;
            $legTotal += $line;
        }
        $legTotals[] = $legTotal;
        $subtotal += $legTotal;
    }

    $discount = 0.0;
    if ($promo !== null) {
        $discount = $promo['type'] === 'percent'
            ? round($subtotal * ((float) $promo['value'] / 100))
            : (float) $promo['value'];
        $discount = max(0.0, min($discount, $subtotal));
    }

    return [
        'subtotal'   => $subtotal,
        'discount'   => $discount,
        'total'      => max(0.0, $subtotal - $discount),
        'per_type'   => $perType,
        'leg_totals' => $legTotals,
    ];
}

/**
 * Validate the promo stored in the draft against the current subtotal.
 *
 * @param array<string,mixed> $draft
 * @return array{code:string,promo:array<string,mixed>|null,discount:float,message:string,valid:bool}
 */
function booking_draft_promo(array $draft, float $subtotal, ?int $userId): array
{
    $code = strtoupper(trim((string) ($draft['promo_code'] ?? '')));
    if ($code === '') {
        return ['code' => '', 'promo' => null, 'discount' => 0.0, 'message' => '', 'valid' => false];
    }

    $result = validate_promo($code, $subtotal, $userId);
    if (!$result['ok']) {
        return [
            'code'    => $code,
            'promo'   => null,
            'discount'=> 0.0,
            'message' => $result['message'],
            'valid'   => false,
        ];
    }

    return [
        'code'    => $code,
        'promo'   => $result['promo'] ?? null,
        'discount'=> (float) ($result['discount'] ?? 0),
        'message' => $result['message'],
        'valid'   => true,
    ];
}

/**
 * Normalise the adult/child/infant counters.
 *
 * @return array{adult:int,child:int,infant:int}
 */
function booking_normalize_counts(int $adults, int $children, int $infants): array
{
    $counts = [
        'adult'  => max(1, min(9, $adults)),
        'child'  => max(0, min(9, $children)),
        'infant' => max(0, min(9, $infants)),
    ];

    // Infants must travel on an adult's lap.
    $counts['infant'] = min($counts['infant'], $counts['adult']);

    while (total_pax($counts) > MAX_PASSENGERS) {
        if ($counts['infant'] > 0) {
            $counts['infant']--;
        } elseif ($counts['child'] > 0) {
            $counts['child']--;
        } else {
            $counts['adult']--;
        }
    }

    return $counts;
}

/**
 * Build default passenger rows (first adult prefilled from the account).
 *
 * @param array{adult:int,child:int,infant:int} $counts
 * @param array<string,mixed>|null $user
 * @return array<int,array{name:string,type:string,email:string,phone:string}>
 */
function booking_default_passengers(array $counts, ?array $user = null): array
{
    $rows = [];
    $labels = ['adult' => 'Dewasa', 'child' => 'Anak', 'infant' => 'Bayi'];
    foreach (['adult', 'child', 'infant'] as $type) {
        for ($i = 0; $i < (int) ($counts[$type] ?? 0); $i++) {
            $rows[] = [
                'name'  => $type === 'adult' && $i === 0 && $user ? (string) $user['name'] : '',
                'type'  => $type,
                'email' => $type === 'adult' && $i === 0 && $user ? (string) $user['email'] : '',
                'phone' => $type === 'adult' && $i === 0 && $user ? (string) $user['phone'] : '',
                'label' => $labels[$type] . ' ' . ($i + 1),
            ];
        }
    }
    return $rows;
}

/**
 * Read the current draft and make sure it is still fresh.
 *
 * @return array<string,mixed>|null
 */
function booking_active_draft(int $maxAgeSeconds = 7200): ?array
{
    $draft = booking_draft();
    if (!is_array($draft)) {
        return null;
    }
    $updated = (int) ($draft['updated_at'] ?? 0);
    if ($updated > 0 && (time() - $updated) > $maxAgeSeconds) {
        clear_booking_draft();
        return null;
    }
    return $draft;
}

/** Store the draft and refresh its timestamp. */
function booking_store_draft(array $draft): void
{
    $draft['updated_at'] = time();
    save_booking_draft($draft);
}

/** Format a datetime for the JS countdown (local Y-m-d H:i:s). */
function countdown_value(?string $datetime): string
{
    $ts = $datetime ? strtotime($datetime) : false;
    return $ts ? date('Y-m-d H:i:s', $ts) : '';
}

/**
 * Derive adult/child/infant counters from stored passenger rows.
 *
 * @param array<int,array<string,mixed>> $passengers
 * @return array{adult:int,child:int,infant:int}
 */
function booking_counts_from_passengers(array $passengers, int $fallbackPax = 1): array
{
    $counts = ['adult' => 0, 'child' => 0, 'infant' => 0];
    foreach ($passengers as $passenger) {
        $type = (string) ($passenger['type'] ?? 'adult');
        if (isset($counts[$type])) {
            $counts[$type]++;
        }
    }
    if (total_pax($counts) === 0) {
        $counts['adult'] = max(1, $fallbackPax);
    }
    return $counts;
}

/** Human friendly payment reference / virtual account number. */
function payment_reference(string $method, string $bookingCode): string
{
    return match ($method) {
        'va'         => '8808' . random_int(10000000, 99999999),
        'ewallet'    => 'EW-' . preg_replace('/[^A-Z0-9]/', '', strtoupper($bookingCode)) . '-' . random_int(1000, 9999),
        'creditcard' => 'CC-' . preg_replace('/[^A-Z0-9]/', '', strtoupper($bookingCode)),
        default      => 'TRF-' . preg_replace('/[^A-Z0-9]/', '', strtoupper($bookingCode)) . '-' . random_int(1000, 9999),
    };
}

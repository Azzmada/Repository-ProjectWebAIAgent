<?php
/**
 * Flight search & results page.
 *
 * Supports one-way, round-trip and true multi-city searches. Every flight
 * is queried from MySQL; the client only handles filtering/sorting and
 * selecting one flight per leg before checkout.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$allowedTrips = ['round-trip', 'one-way', 'multi-city'];
$allowedCabins = ['Ekonomi', 'Bisnis', 'First Class'];

$trip = (string) ($_GET['trip'] ?? 'round-trip');
if (!in_array($trip, $allowedTrips, true)) {
    $trip = 'round-trip';
}

$counts = [
    'adult'  => max(1, min(9, (int) ($_GET['adults'] ?? 1))),
    'child'  => max(0, min(9, (int) ($_GET['children'] ?? 0))),
    'infant' => max(0, min(9, (int) ($_GET['infants'] ?? 0))),
];
// Keep the total within the allowed limit.
while (total_pax($counts) > MAX_PASSENGERS) {
    if ($counts['infant'] > 0) {
        $counts['infant']--;
    } elseif ($counts['child'] > 0) {
        $counts['child']--;
    } else {
        $counts['adult']--;
    }
}

$cabin = (string) ($_GET['class'] ?? 'Ekonomi');
if (!in_array($cabin, $allowedCabins, true)) {
    $cabin = 'Ekonomi';
}

$today = date('Y-m-d');
$defaultDepart = date('Y-m-d', strtotime('+7 days'));
$defaultReturn = date('Y-m-d', strtotime('+12 days'));

$fromRaw = trim((string) ($_GET['from'] ?? 'Jakarta (CGK)'));
$toRaw = trim((string) ($_GET['to'] ?? 'Bali (DPS)'));
$depart = trim((string) ($_GET['depart'] ?? $defaultDepart));
$returnDate = trim((string) ($_GET['return'] ?? $defaultReturn));

$searchError = '';
$legs = [];

/**
 * Build one search leg with its DB flights.
 *
 * @return array<string,mixed>|null
 */
function build_leg(string $fromRaw, string $toRaw, string $date, int $order): ?array
{
    $from = resolve_city($fromRaw);
    $to = resolve_city($toRaw);

    if (!$from) {
        $GLOBALS['searchError'] = 'Kota asal "' . $fromRaw . '" tidak ditemukan.';
        return null;
    }
    if (!$to) {
        $GLOBALS['searchError'] = 'Kota tujuan "' . $toRaw . '" tidak ditemukan.';
        return null;
    }
    if ($from['id'] === $to['id']) {
        $GLOBALS['searchError'] = 'Kota asal dan tujuan tidak boleh sama.';
        return null;
    }
    if (!valid_date($date)) {
        $GLOBALS['searchError'] = 'Format tanggal tidak valid.';
        return null;
    }
    if ($date < date('Y-m-d')) {
        $GLOBALS['searchError'] = 'Tanggal penerbangan tidak boleh di masa lalu.';
        return null;
    }

    return [
        'order'      => $order,
        'from'       => $from,
        'to'         => $to,
        'date'       => $date,
        'flights'    => flights_for_route((int) $from['id'], (int) $to['id']),
    ];
}

if ($trip === 'multi-city') {
    $legsInput = input_array('legs');
    $usable = [];
    foreach ($legsInput as $leg) {
        if (!is_array($leg)) {
            continue;
        }
        $f = trim((string) ($leg['from'] ?? ''));
        $t = trim((string) ($leg['to'] ?? ''));
        $d = trim((string) ($leg['date'] ?? ''));
        if ($f === '' && $t === '' && $d === '') {
            continue;
        }
        $usable[] = [$f, $t, $d];
    }

    // First visit to the multi-city tab: prefill two sensible legs.
    if (!$usable) {
        $usable = [
            ['Jakarta (CGK)', 'Singapore (SIN)', $defaultDepart],
            ['Singapore (SIN)', 'Jakarta (CGK)', $defaultReturn],
        ];
    }

    if (count($usable) < 2) {
        $searchError = 'Multi-kota membutuhkan minimal 2 rute.';
    }

    $previousDate = '';
    foreach ($usable as $i => [$f, $t, $d]) {
        if ($searchError !== '') {
            break;
        }
        $leg = build_leg($f, $t, $d, $i + 1);
        if (!$leg) {
            break;
        }
        if ($previousDate !== '' && $d < $previousDate) {
            $searchError = 'Tanggal rute harus berurutan dari rute pertama ke rute berikutnya.';
            break;
        }
        $previousDate = $d;
        $legs[] = $leg;
    }
    if ($searchError !== '') {
        $legs = [];
    }
} elseif ($trip === 'round-trip') {
    if ($returnDate !== '' && $depart !== '' && $returnDate < $depart) {
        $searchError = 'Tanggal pulang tidak boleh lebih awal dari tanggal keberangkatan.';
    }
    if ($searchError === '') {
        $outbound = build_leg($fromRaw, $toRaw, $depart, 1);
        $inbound = build_leg($toRaw, $fromRaw, $returnDate, 2);
        if ($outbound && $inbound) {
            $legs = [$outbound, $inbound];
        }
    }
} else {
    $leg = build_leg($fromRaw, $toRaw, $depart, 1);
    if ($leg) {
        $legs = [$leg];
    }
}

$isMultiSelect = $trip !== 'one-way' && count($legs) > 0;

// Price boundaries for the filter slider.
$allPrices = [];
foreach ($legs as $leg) {
    foreach ($leg['flights'] as $f) {
        $allPrices[] = (float) $f['price'];
    }
}
$minPrice = $allPrices ? (int) (floor(min($allPrices) / 100000) * 100000) : 0;
$maxPrice = $allPrices ? (int) (ceil(max($allPrices) / 100000) * 100000) : 5000000;

// Airline options for the sidebar filter.
$airlinesInResults = [];
foreach ($legs as $leg) {
    foreach ($leg['flights'] as $f) {
        $airlinesInResults[$f['airline_name']] = true;
    }
}
ksort($airlinesInResults);

$airlineStyles = [
    'Garuda Indonesia' => ['bg' => 'bg-blue-50', 'color' => 'text-blue-600', 'icon' => 'fa-plane'],
    'Lion Air'         => ['bg' => 'bg-red-50', 'color' => 'text-red-600', 'icon' => 'fa-plane'],
    'Batik Air'        => ['bg' => 'bg-teal-50', 'color' => 'text-teal-600', 'icon' => 'fa-plane'],
    'Citilink'         => ['bg' => 'bg-green-50', 'color' => 'text-green-600', 'icon' => 'fa-plane'],
    'Sriwijaya Air'    => ['bg' => 'bg-indigo-50', 'color' => 'text-indigo-600', 'icon' => 'fa-plane'],
    'AirAsia'          => ['bg' => 'bg-red-50', 'color' => 'text-red-500', 'icon' => 'fa-plane'],
    'Super Air Jet'    => ['bg' => 'bg-orange-50', 'color' => 'text-orange-600', 'icon' => 'fa-plane'],
    'TransNusa'        => ['bg' => 'bg-purple-50', 'color' => 'text-purple-600', 'icon' => 'fa-plane'],
    'Nam Air'          => ['bg' => 'bg-amber-50', 'color' => 'text-amber-600', 'icon' => 'fa-plane'],
    'Singapore Airlines' => ['bg' => 'bg-sky-50', 'color' => 'text-sky-700', 'icon' => 'fa-plane'],
    'Malaysia Airlines'  => ['bg' => 'bg-blue-50', 'color' => 'text-blue-700', 'icon' => 'fa-plane'],
];

// Popular routes (computed from the flights table).
$popularRoutes = Database::fetchAll(
    'SELECT fc.name AS from_name, fc.iata_code AS from_code,
            tc.name AS to_name, tc.iata_code AS to_code, COUNT(*) AS total
     FROM flights f
     JOIN cities fc ON fc.id = f.from_city_id
     JOIN cities tc ON tc.id = f.to_city_id
     WHERE f.is_active = 1
     GROUP BY f.from_city_id, f.to_city_id
     ORDER BY total DESC, f.from_city_id ASC
     LIMIT 6'
);

$searchAction = PRETTY_URLS ? base_url('pesawat') : base_url('index.php');
$paxCounts = ['adult' => $counts['adult'], 'child' => $counts['child'], 'infant' => $counts['infant']];

$pageTitle = 'Cari Tiket Pesawat Termurah - ' . APP_NAME;
$metaDescription = 'Bandingkan harga tiket pesawat dari 500+ maskapai. Cari rute one-way, pulang-pergi, atau multi-kota dengan mudah.';
$activeNav = 'pesawat';

require __DIR__ . '/../includes/header.php';
?>
<div id="page-pesawat" class="page-view active">
    <!-- HERO WITH SEARCH -->
    <header class="relative bg-gray-900 h-auto min-h-[580px] py-16 flex items-center justify-center overflow-hidden">
        <img src="https://images.unsplash.com/photo-1436491865332-7a61a109cc05?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80" alt="Pesawat" class="absolute inset-0 w-full h-full object-cover opacity-60">
        <div class="absolute inset-0 bg-gradient-to-b from-black/60 to-black/40"></div>

        <div class="relative z-10 w-full max-w-7xl px-4 sm:px-6 lg:px-8 flex flex-col items-center mt-8">
            <div class="text-center mb-6">
                <h1 class="text-4xl lg:text-5xl font-extrabold text-white mb-3 tracking-tight drop-shadow-lg">Cari Tiket Pesawat Termurah</h1>
                <p class="text-lg text-gray-100 max-w-2xl mx-auto font-light drop-shadow-md">Bandingkan harga dari 500+ maskapai dan temukan penerbangan terbaik untuk Anda.</p>
            </div>

            <!-- SEARCH WIDGET -->
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl p-6">
                <div class="flex space-x-6 border-b border-gray-200 mb-6 pb-2 overflow-x-auto hide-scrollbar" id="search-tabs">
                    <button type="button" class="tab-btn whitespace-nowrap <?= $trip === 'round-trip' ? 'text-primary font-bold border-primary' : 'text-gray-500 font-semibold border-transparent' ?> text-lg pb-2 border-b-4 transition-all" data-target="round-trip">Pulang Pergi</button>
                    <button type="button" class="tab-btn whitespace-nowrap <?= $trip === 'one-way' ? 'text-primary font-bold border-primary' : 'text-gray-500 font-semibold border-transparent' ?> text-lg pb-2 border-b-4 transition-all" data-target="one-way">Sekali Jalan</button>
                    <button type="button" class="tab-btn whitespace-nowrap <?= $trip === 'multi-city' ? 'text-primary font-bold border-primary' : 'text-gray-500 font-semibold border-transparent' ?> text-lg pb-2 border-b-4 transition-all" data-target="multi-city">Multi-Kota</button>
                </div>

                <form method="get" action="<?= e($searchAction) ?>" id="search-form">
                    <?php if (!PRETTY_URLS): ?>
                        <input type="hidden" name="page" value="pesawat">
                    <?php endif; ?>
                    <input type="hidden" name="trip" id="trip-type" value="<?= e($trip) ?>">
                    <input type="hidden" name="adults" id="pax-adults" value="<?= $counts['adult'] ?>">
                    <input type="hidden" name="children" id="pax-children" value="<?= $counts['child'] ?>">
                    <input type="hidden" name="infants" id="pax-infants" value="<?= $counts['infant'] ?>">
                    <input type="hidden" name="class" id="pax-class" value="<?= e($cabin) ?>">

                    <!-- Single route / round trip -->
                    <div id="single-route-fields" class="<?= $trip === 'multi-city' ? 'hidden' : '' ?> grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                        <div class="lg:col-span-1">
                            <label class="block text-sm font-medium text-gray-600 mb-1">Dari</label>
                            <div class="relative city-autocomplete-wrap">
                                <i class="fa-solid fa-plane-departure absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                <input type="text" id="input-from" name="from" placeholder="Kota Asal" autocomplete="off" class="city-autocomplete w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none text-lg transition-shadow" value="<?= e($fromRaw) ?>">
                            </div>
                        </div>
                        <div class="lg:col-span-1">
                            <label class="flex justify-between items-center text-sm font-medium text-gray-600 mb-1">
                                <span>Ke</span>
                                <button type="button" id="swap-cities" onclick="swapCities()" class="text-primary hover:text-primaryDark text-xs font-semibold flex items-center gap-1" title="Tukar Kota">
                                    <i class="fa-solid fa-right-left"></i> Tukar
                                </button>
                            </label>
                            <div class="relative city-autocomplete-wrap">
                                <i class="fa-solid fa-plane-arrival absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                <input type="text" id="input-to" name="to" placeholder="Kota Tujuan" autocomplete="off" class="city-autocomplete w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none text-lg transition-shadow" value="<?= e($toRaw) ?>">
                            </div>
                        </div>
                        <div class="lg:col-span-1">
                            <label class="block text-sm font-medium text-gray-600 mb-1">Berangkat</label>
                            <div class="relative">
                                <i class="fa-regular fa-calendar absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                <input type="date" id="input-date-depart" name="depart" class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none text-lg text-gray-700 transition-shadow cursor-pointer" value="<?= e($depart) ?>">
                            </div>
                        </div>
                        <div class="lg:col-span-1 <?= $trip !== 'round-trip' ? 'hidden' : '' ?>" id="return-date-container">
                            <label class="block text-sm font-medium text-gray-600 mb-1">Pulang</label>
                            <div class="relative">
                                <i class="fa-regular fa-calendar absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                <input type="date" id="input-date-return" name="return" class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none text-lg text-gray-700 transition-shadow cursor-pointer" value="<?= e($returnDate) ?>" <?= $trip === 'round-trip' ? 'required' : '' ?>>
                            </div>
                        </div>
                        <div class="lg:col-span-1">
                            <button type="submit" class="w-full bg-accent hover:bg-accentDark text-white font-bold text-lg py-3.5 rounded-xl shadow-lg transition-all transform hover:scale-105 flex items-center justify-center gap-2">
                                <i class="fa-solid fa-magnifying-glass"></i> Cari Tiket
                            </button>
                        </div>
                    </div>

                    <!-- Multi-city -->
                    <div id="multi-city-fields" class="<?= $trip === 'multi-city' ? '' : 'hidden' ?> mt-2">
                        <div id="multi-legs" class="space-y-3">
                            <?php
                            $uiLegs = [];
                            if ($trip === 'multi-city' && $legs) {
                                foreach ($legs as $leg) {
                                    $uiLegs[] = [
                                        'from' => $leg['from']['name'] . ' (' . $leg['from']['iata_code'] . ')',
                                        'to'   => $leg['to']['name'] . ' (' . $leg['to']['iata_code'] . ')',
                                        'date' => $leg['date'],
                                    ];
                                }
                            } else {
                                $uiLegs = [
                                    ['from' => 'Jakarta (CGK)', 'to' => 'Singapore (SIN)', 'date' => $defaultDepart],
                                    ['from' => 'Singapore (SIN)', 'to' => 'Jakarta (CGK)', 'date' => $defaultReturn],
                                ];
                            }
                            foreach ($uiLegs as $li => $ul):
                            ?>
                                <div class="leg-row grid grid-cols-1 md:grid-cols-12 gap-3 items-end bg-gray-50 p-3 rounded-xl border border-gray-200">
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-semibold text-gray-500 mb-1 leg-label">Rute <?= $li + 1 ?></label>
                                        <button type="button" class="remove-leg text-xs text-red-500 hover:underline <?= count($uiLegs) <= 2 ? 'hidden' : '' ?>"><i class="fa-solid fa-trash-can mr-1"></i>Hapus</button>
                                    </div>
                                    <div class="md:col-span-4 city-autocomplete-wrap relative">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Dari</label>
                                        <input type="text" data-field="from" name="legs[<?= $li ?>][from]" required placeholder="Kota Asal" autocomplete="off" class="city-autocomplete w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none" value="<?= e($ul['from']) ?>">
                                    </div>
                                    <div class="md:col-span-4 city-autocomplete-wrap relative">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Ke</label>
                                        <input type="text" data-field="to" name="legs[<?= $li ?>][to]" required placeholder="Kota Tujuan" autocomplete="off" class="city-autocomplete w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none" value="<?= e($ul['to']) ?>">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal</label>
                                        <input type="date" data-field="date" name="legs[<?= $li ?>][date]" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none" value="<?= e($ul['date']) ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <button type="button" id="add-leg-btn" class="text-primary font-semibold text-sm hover:text-primaryDark flex items-center gap-2"><i class="fa-solid fa-circle-plus"></i> Tambah Rute</button>
                            <button type="submit" class="bg-accent hover:bg-accentDark text-white font-bold py-3 px-8 rounded-xl shadow-lg transition-all transform hover:scale-105 flex items-center justify-center gap-2">
                                <i class="fa-solid fa-magnifying-glass"></i> Cari Multi-Kota
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 text-sm text-gray-500">
                        <button type="button" id="btn-passenger" class="flex items-center gap-2 cursor-pointer hover:text-primary transition font-medium bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-200">
                            <i class="fa-solid fa-user-plus"></i> <span id="passenger-label"><?= e(pax_label($paxCounts, $cabin)) ?></span>
                        </button>
                        <div class="text-green-600 font-medium"><i class="fa-solid fa-shield-halved"></i> Pembayaran Aman &amp; Terpercaya</div>
                    </div>
                </form>
            </div>
        </div>
    </header>

    <!-- QUICK ROUTE CHIPS -->
    <section class="bg-white border-b border-gray-100 py-5 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3 overflow-x-auto hide-scrollbar pb-1">
                <span class="text-sm font-semibold text-gray-500 whitespace-nowrap"><i class="fa-solid fa-fire text-accent mr-1"></i> Rute Populer:</span>
                <?php foreach ($popularRoutes as $route):
                    $chipParams = [
                        'trip'   => 'one-way',
                        'from'   => $route['from_code'],
                        'to'     => $route['to_code'],
                        'depart' => $defaultDepart,
                    ];
                ?>
                    <a href="<?= e(page_url('pesawat', $chipParams)) ?>" class="route-chip bg-blue-50 hover:bg-blue-100 text-primary font-medium text-sm px-4 py-2 rounded-full whitespace-nowrap border border-blue-200">
                        <?= e($route['from_name']) ?> &rarr; <?= e($route['to_name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- RESULTS SECTION -->
    <section id="flight-results" class="py-12 bg-gray-100 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-6">
                <h2 class="text-2xl lg:text-3xl font-bold text-gray-900 mb-1">Penerbangan Tersedia</h2>
                <p class="text-gray-600 text-base">
                    <?php if ($legs): ?>
                        <?= e($legs[0]['from']['name']) ?> (<?= e($legs[0]['from']['iata_code']) ?>)
                        <i class="fa-solid fa-arrow-right mx-1 text-primary"></i>
                        <?= e($legs[0]['to']['name']) ?> (<?= e($legs[0]['to']['iata_code']) ?>)
                        | <?= e(pax_label($paxCounts, $cabin)) ?>
                        <?= count($legs) > 1 ? ' | ' . count($legs) . ' rute' : '' ?>
                    <?php else: ?>
                        Pilih rute penerbangan Anda
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($searchError !== ''): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-2xl p-5 mb-6 flex items-start gap-3">
                    <i class="fa-solid fa-circle-exclamation text-xl mt-0.5"></i>
                    <div>
                        <p class="font-bold">Pencarian tidak dapat diproses</p>
                        <p class="text-sm"><?= e($searchError) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$legs): ?>
                <div class="bg-white rounded-2xl shadow-sm p-12 text-center border border-gray-100">
                    <i class="fa-solid fa-plane-slash text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Belum Ada Rute yang Dicari</h3>
                    <p class="text-gray-500 mb-4">Lengkapi formulir pencarian di atas untuk melihat penerbangan.</p>
                </div>
            <?php else: ?>
                <div class="flex flex-col lg:flex-row gap-6">

                    <!-- SIDEBAR FILTER -->
                    <aside class="w-full lg:w-1/4">
                        <div class="bg-white rounded-2xl shadow-sm p-5 sticky top-24 max-h-[calc(100vh-7rem)] overflow-y-auto">
                            <div class="flex items-center justify-between mb-4 border-b pb-3">
                                <h3 class="text-lg font-bold text-gray-900"><i class="fa-solid fa-filter mr-2 text-primary"></i>Filter</h3>
                                <button type="button" onclick="resetFilters()" class="text-xs text-primary hover:underline font-semibold">Reset</button>
                            </div>

                            <!-- Filter Harga -->
                            <div class="mb-6">
                                <h4 class="font-semibold text-gray-800 mb-2 text-sm">Harga Maksimal</h4>
                                <input type="range" id="price-range" min="<?= max(0, $minPrice) ?>" max="<?= $maxPrice ?>" step="50000" value="<?= $maxPrice ?>" class="w-full accent-primary">
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span><?= e(rupiah($minPrice)) ?></span>
                                    <span class="font-bold text-primary" id="price-range-value"><?= e(rupiah($maxPrice)) ?></span>
                                </div>
                                <div class="space-y-2 mt-3">
                                    <label class="flex items-center gap-2 cursor-pointer text-sm"><input type="checkbox" class="filter-price w-4 h-4 text-primary rounded" value="under-1m"><span class="text-gray-600">Di bawah Rp 1JT</span></label>
                                    <label class="flex items-center gap-2 cursor-pointer text-sm"><input type="checkbox" class="filter-price w-4 h-4 text-primary rounded" value="1m-2m"><span class="text-gray-600">Rp 1JT - Rp 2JT</span></label>
                                    <label class="flex items-center gap-2 cursor-pointer text-sm"><input type="checkbox" class="filter-price w-4 h-4 text-primary rounded" value="above-2m"><span class="text-gray-600">Di atas Rp 2JT</span></label>
                                </div>
                            </div>

                            <!-- Filter Waktu -->
                            <div class="mb-6">
                                <h4 class="font-semibold text-gray-800 mb-2 text-sm">Waktu Keberangkatan</h4>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="flex items-center gap-2 cursor-pointer text-sm bg-gray-50 p-2 rounded-lg hover:bg-blue-50 transition"><input type="checkbox" class="filter-time w-4 h-4 text-primary rounded" value="morning"><span class="text-gray-700"><i class="fa-solid fa-sun text-yellow-500 mr-1"></i>Pagi</span></label>
                                    <label class="flex items-center gap-2 cursor-pointer text-sm bg-gray-50 p-2 rounded-lg hover:bg-blue-50 transition"><input type="checkbox" class="filter-time w-4 h-4 text-primary rounded" value="afternoon"><span class="text-gray-700"><i class="fa-solid fa-cloud-sun text-orange-400 mr-1"></i>Siang</span></label>
                                    <label class="flex items-center gap-2 cursor-pointer text-sm bg-gray-50 p-2 rounded-lg hover:bg-blue-50 transition"><input type="checkbox" class="filter-time w-4 h-4 text-primary rounded" value="evening"><span class="text-gray-700"><i class="fa-solid fa-moon text-indigo-500 mr-1"></i>Malam</span></label>
                                    <label class="flex items-center gap-2 cursor-pointer text-sm bg-gray-50 p-2 rounded-lg hover:bg-blue-50 transition"><input type="checkbox" class="filter-time w-4 h-4 text-primary rounded" value="night"><span class="text-gray-700"><i class="fa-solid fa-star text-purple-500 mr-1"></i>Subuh</span></label>
                                </div>
                            </div>

                            <!-- Filter Maskapai -->
                            <div class="mb-6">
                                <h4 class="font-semibold text-gray-800 mb-2 text-sm">Maskapai</h4>
                                <div class="space-y-2 max-h-40 overflow-y-auto pr-1">
                                    <?php foreach (array_keys($airlinesInResults) as $airlineName): ?>
                                        <label class="flex items-center gap-2 cursor-pointer text-sm"><input type="checkbox" class="filter-airline w-4 h-4 text-primary rounded" value="<?= e($airlineName) ?>"><span class="text-gray-600"><?= e($airlineName) ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Filter Transit -->
                            <div class="mb-6">
                                <h4 class="font-semibold text-gray-800 mb-2 text-sm">Transit</h4>
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 cursor-pointer text-sm"><input type="checkbox" class="filter-stops w-4 h-4 text-primary rounded" value="0"><span class="text-gray-600">Langsung</span></label>
                                    <label class="flex items-center gap-2 cursor-pointer text-sm"><input type="checkbox" class="filter-stops w-4 h-4 text-primary rounded" value="1"><span class="text-gray-600">1 Transit</span></label>
                                </div>
                            </div>

                            <!-- Filter Fasilitas -->
                            <div class="mb-4">
                                <h4 class="font-semibold text-gray-800 mb-2 text-sm">Fasilitas</h4>
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 cursor-pointer text-sm"><input type="checkbox" class="filter-facility w-4 h-4 text-primary rounded" value="baggage"><span class="text-gray-600"><i class="fa-solid fa-suitcase mr-1 text-primary"></i>Bagasi</span></label>
                                    <label class="flex items-center gap-2 cursor-pointer text-sm"><input type="checkbox" class="filter-facility w-4 h-4 text-primary rounded" value="meal"><span class="text-gray-600"><i class="fa-solid fa-utensils mr-1 text-primary"></i>Makanan</span></label>
                                    <label class="flex items-center gap-2 cursor-pointer text-sm"><input type="checkbox" class="filter-facility w-4 h-4 text-primary rounded" value="wifi"><span class="text-gray-600"><i class="fa-solid fa-wifi mr-1 text-primary"></i>WiFi</span></label>
                                    <label class="flex items-center gap-2 cursor-pointer text-sm"><input type="checkbox" class="filter-facility w-4 h-4 text-primary rounded" value="entertainment"><span class="text-gray-600"><i class="fa-solid fa-tv mr-1 text-primary"></i>Hiburan</span></label>
                                </div>
                            </div>
                        </div>
                    </aside>

                    <!-- MAIN LIST -->
                    <div class="w-full lg:w-3/4">
                        <div id="active-filters" class="hidden flex-wrap gap-2 mb-4"></div>

                        <?php if ($isMultiSelect): ?>
                        <form id="multi-leg-form" method="get" action="<?= e(base_url('booking/passengers.php')) ?>" data-legs="<?= count($legs) ?>">
                            <input type="hidden" name="adults" value="<?= $counts['adult'] ?>">
                            <input type="hidden" name="children" value="<?= $counts['child'] ?>">
                            <input type="hidden" name="infants" value="<?= $counts['infant'] ?>">
                            <input type="hidden" name="class" value="<?= e($cabin) ?>">
                            <?php foreach ($legs as $i => $leg): ?>
                                <input type="hidden" id="selected-flight-<?= $i ?>" name="flights[<?= $i ?>]" value="">
                                <input type="hidden" id="selected-date-<?= $i ?>" name="dates[<?= $i ?>]" value="<?= e($leg['date']) ?>">
                            <?php endforeach; ?>

                            <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 mb-4">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
                                    <p class="text-sm font-semibold text-blue-900"><i class="fa-solid fa-route mr-1"></i> Pilih satu penerbangan untuk setiap rute</p>
                                    <p class="text-xs font-medium text-blue-700" id="leg-progress">0 dari <?= count($legs) ?> rute dipilih</p>
                                </div>
                                <div class="progress-track"><div class="progress-bar" id="leg-progress-bar" style="width:0%"></div></div>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($legs as $i => $leg):
                            $flights = $leg['flights'];

                            $bestId = null;
                            $bestScore = PHP_FLOAT_MAX;
                            $cheapestId = null;
                            $cheapestPrice = PHP_FLOAT_MAX;
                            $fastestId = null;
                            $fastestDuration = PHP_INT_MAX;

                            foreach ($flights as $f) {
                                $score = ((float) $f['price'] / 1000) + ((int) $f['duration'] * 5) - ((int) $f['stops'] === 0 ? 3000 : 0);
                                if ($score < $bestScore) {
                                    $bestScore = $score;
                                    $bestId = (int) $f['id'];
                                }
                                if ((float) $f['price'] < $cheapestPrice) {
                                    $cheapestPrice = (float) $f['price'];
                                    $cheapestId = (int) $f['id'];
                                }
                                if ((int) $f['duration'] < $fastestDuration) {
                                    $fastestDuration = (int) $f['duration'];
                                    $fastestId = (int) $f['id'];
                                }
                            }

                            $sort = $flights;
                            usort($sort, static function ($a, $b): int {
                                $sa = ((float) $a['price'] / 1000) + ((int) $a['duration'] * 5) - ((int) $a['stops'] === 0 ? 3000 : 0);
                                $sb = ((float) $b['price'] / 1000) + ((int) $b['duration'] * 5) - ((int) $b['stops'] === 0 ? 3000 : 0);
                                return $sa <=> $sb;
                            });

                            $routeLabel = $leg['from']['name'] . ' (' . $leg['from']['iata_code'] . ') → ' . $leg['to']['name'] . ' (' . $leg['to']['iata_code'] . ')';
                        ?>
                            <div class="leg-results mb-8" data-leg="<?= $i ?>">
                                <?php if (count($legs) > 1): ?>
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                                        <div>
                                            <p class="text-xs font-bold uppercase tracking-wide text-primary">Rute <?= $i + 1 ?></p>
                                            <h3 class="text-lg font-bold text-gray-900"><?= e($routeLabel) ?></h3>
                                            <p class="text-sm text-gray-500"><i class="fa-regular fa-calendar mr-1"></i><?= e(format_date_id($leg['date'], true)) ?></p>
                                        </div>
                                        <span class="text-xs font-semibold bg-white border border-gray-200 text-gray-600 px-3 py-1.5 rounded-full w-max">
                                            <i class="fa-solid fa-plane mr-1 text-primary"></i><?= count($flights) ?> penerbangan
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <div class="bg-white rounded-2xl shadow-sm p-4 mb-4">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                        <div class="flex items-center gap-2 text-sm">
                                            <span class="text-gray-600">Urutkan:</span>
                                            <div class="flex flex-wrap gap-1">
                                                <button type="button" class="sort-btn active bg-primary text-white text-xs font-semibold px-3 py-1.5 rounded-full transition" data-sort="best">Terbaik</button>
                                                <button type="button" class="sort-btn bg-gray-100 text-gray-600 hover:bg-gray-200 text-xs font-semibold px-3 py-1.5 rounded-full transition" data-sort="cheapest">Termurah</button>
                                                <button type="button" class="sort-btn bg-gray-100 text-gray-600 hover:bg-gray-200 text-xs font-semibold px-3 py-1.5 rounded-full transition" data-sort="fastest">Tercepat</button>
                                                <button type="button" class="sort-btn bg-gray-100 text-gray-600 hover:bg-gray-200 text-xs font-semibold px-3 py-1.5 rounded-full transition" data-sort="earliest">Paling Awal</button>
                                            </div>
                                        </div>
                                        <div class="text-sm text-gray-600"><span data-result-count class="font-bold text-primary"><?= count($flights) ?></span> penerbangan ditemukan</div>
                                    </div>
                                </div>

                                <div class="space-y-3 flight-list">
                                    <?php foreach ($sort as $f):
                                        $airlineName = (string) $f['airline_name'];
                                        $style = $airlineStyles[$airlineName] ?? ['bg' => 'bg-gray-50', 'color' => 'text-gray-600', 'icon' => 'fa-plane'];
                                        $depMin = ((int) substr((string) $f['dep_time'], 0, 2)) * 60 + (int) substr((string) $f['dep_time'], 3, 2);
                                        $timeCategory = $depMin >= 360 && $depMin < 720 ? 'morning' : ($depMin >= 720 && $depMin < 1080 ? 'afternoon' : ($depMin >= 1080 ? 'evening' : 'night'));
                                        $score = ((float) $f['price'] / 1000) + ((int) $f['duration'] * 5) - ((int) $f['stops'] === 0 ? 3000 : 0);

                                        $badgeHtml = '';
                                        if ((int) $f['id'] === $bestId) {
                                            $badgeHtml = '<div class="absolute top-0 right-0 bg-green-500 text-white text-xs font-bold px-3 py-1 rounded-bl-lg shadow"><i class="fa-solid fa-star mr-1"></i>Pilihan Terbaik</div>';
                                        } elseif ((int) $f['id'] === $cheapestId) {
                                            $badgeHtml = '<div class="absolute top-0 right-0 bg-orange-500 text-white text-xs font-bold px-3 py-1 rounded-bl-lg shadow"><i class="fa-solid fa-tags mr-1"></i>Termurah</div>';
                                        } elseif ((int) $f['id'] === $fastestId) {
                                            $badgeHtml = '<div class="absolute top-0 right-0 bg-blue-500 text-white text-xs font-bold px-3 py-1 rounded-bl-lg shadow"><i class="fa-solid fa-bolt mr-1"></i>Tercepat</div>';
                                        }

                                        $facilitiesHtml = '';
                                        if ((int) $f['baggage_kg'] > 0) {
                                            $facilitiesHtml .= '<span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded"><i class="fa-solid fa-suitcase text-primary mr-1"></i>' . (int) $f['baggage_kg'] . 'kg</span>';
                                        }
                                        if ((int) $f['meal']) {
                                            $facilitiesHtml .= '<span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded"><i class="fa-solid fa-utensils text-primary mr-1"></i>Makan</span>';
                                        }
                                        if ((int) $f['wifi']) {
                                            $facilitiesHtml .= '<span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded"><i class="fa-solid fa-wifi text-primary mr-1"></i>WiFi</span>';
                                        }
                                        if ((int) $f['entertainment']) {
                                            $facilitiesHtml .= '<span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded"><i class="fa-solid fa-tv text-primary mr-1"></i>Hiburan</span>';
                                        }

                                        if ($trip === 'one-way') {
                                            $bookParams = [
                                                'flights'  => [0 => (int) $f['id']],
                                                'dates'    => [0 => $leg['date']],
                                                'adults'   => $counts['adult'],
                                                'children' => $counts['child'],
                                                'infants'  => $counts['infant'],
                                                'class'    => $cabin,
                                            ];
                                            $actionHtml = '<a href="' . e(base_url('booking/passengers.php?' . http_build_query($bookParams))) . '" class="w-full bg-primary hover:bg-primaryDark text-white font-bold py-2 px-4 rounded-lg transition shadow-sm text-sm text-center block">Pilih</a>';
                                        } else {
                                            $actionHtml = '<button type="button" class="w-full bg-primary hover:bg-primaryDark text-white font-bold py-2 px-4 rounded-lg transition shadow-sm text-sm select-leg-btn" data-leg="' . $i . '" data-flight-id="' . (int) $f['id'] . '">Pilih</button>';
                                        }
                                    ?>
                                        <div class="flight-card bg-white rounded-2xl shadow-sm hover:shadow-lg border border-gray-100 p-5 transition-all duration-300 relative overflow-hidden"
                                             data-flight-id="<?= (int) $f['id'] ?>"
                                             data-airline="<?= e($airlineName) ?>"
                                             data-price="<?= (int) $f['price'] ?>"
                                             data-duration="<?= (int) $f['duration'] ?>"
                                             data-dep-min="<?= $depMin ?>"
                                             data-stops="<?= (int) $f['stops'] ?>"
                                             data-time-category="<?= e($timeCategory) ?>"
                                             data-score="<?= (float) $score ?>"
                                             data-baggage="<?= (int) $f['baggage_kg'] ?>"
                                             data-meal="<?= (int) $f['meal'] ?>"
                                             data-wifi="<?= (int) $f['wifi'] ?>"
                                             data-ent="<?= (int) $f['entertainment'] ?>">
                                            <?= $badgeHtml ?>
                                            <div class="flex flex-col md:flex-row items-center justify-between gap-4 pt-1">
                                                <div class="flex items-center gap-3 w-full md:w-1/4">
                                                    <div class="w-12 h-12 <?= e($style['bg']) ?> rounded-xl flex items-center justify-center flex-shrink-0">
                                                        <i class="fa-solid <?= e($style['icon']) ?> <?= e($style['color']) ?> text-xl"></i>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <h4 class="font-bold text-gray-900 text-sm truncate airline-name"><?= e($airlineName) ?></h4>
                                                        <p class="text-xs text-gray-500 flight-num"><?= e($f['flight_num']) ?> &middot; <?= e($f['aircraft']) ?></p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center justify-between flex-1 w-full md:w-2/4 px-0 md:px-4">
                                                    <div class="text-center">
                                                        <p class="text-xl font-bold text-gray-900 dep-time"><?= e(substr((string) $f['dep_time'], 0, 5)) ?></p>
                                                        <p class="text-xs text-gray-500 dep-code"><?= e($leg['from']['iata_code']) ?></p>
                                                    </div>
                                                    <div class="flex flex-col items-center px-2 flex-1">
                                                        <p class="text-xs text-gray-500 mb-1"><?= e(format_duration((int) $f['duration'])) ?></p>
                                                        <div class="w-full flex items-center">
                                                            <div class="h-0.5 bg-gray-300 flex-1"></div>
                                                            <i class="fa-solid fa-plane text-gray-400 mx-2 text-xs"></i>
                                                            <div class="h-0.5 bg-gray-300 flex-1"></div>
                                                        </div>
                                                        <p class="text-xs <?= (int) $f['stops'] === 0 ? 'text-green-600' : 'text-orange-500' ?> mt-1 font-medium"><?= (int) $f['stops'] === 0 ? 'Langsung' : (int) $f['stops'] . ' Transit' ?></p>
                                                    </div>
                                                    <div class="text-center">
                                                        <p class="text-xl font-bold text-gray-900 arr-time"><?= e(substr((string) $f['arr_time'], 0, 5)) ?></p>
                                                        <p class="text-xs text-gray-500 arr-code"><?= e($leg['to']['iata_code']) ?></p>
                                                    </div>
                                                </div>
                                                <div class="w-full md:w-1/4 flex flex-col items-end border-t md:border-t-0 md:border-l border-gray-100 pt-3 md:pt-0 md:pl-4">
                                                    <p class="text-xs text-gray-500">Mulai dari</p>
                                                    <p class="text-xl font-bold text-accent mb-2 price-text"><?= e(rupiah($f['price'])) ?></p>
                                                    <?= $actionHtml ?>
                                                </div>
                                            </div>
                                            <div class="mt-3 pt-3 border-t border-gray-100 flex flex-wrap items-center gap-1.5">
                                                <?= $facilitiesHtml ?>
                                                <button type="button" onclick="toggleDetails(this)" class="text-xs text-primary hover:underline font-semibold ml-auto">Lihat Detail <i class="fa-solid fa-chevron-down text-[10px]"></i></button>
                                            </div>
                                            <div class="hidden mt-3 pt-3 border-t border-gray-100 text-sm text-gray-600 space-y-1.5 details-panel">
                                                <div class="flex justify-between"><span>Bagasi kabin</span><span class="font-medium">7 kg</span></div>
                                                <div class="flex justify-between"><span>Bagasi tercatat</span><span class="font-medium"><?= (int) $f['baggage_kg'] ?> kg</span></div>
                                                <div class="flex justify-between"><span>Makanan</span><span class="font-medium"><?= (int) $f['meal'] ? 'Termasuk' : 'Tidak termasuk' ?></span></div>
                                                <div class="flex justify-between"><span>WiFi</span><span class="font-medium"><?= (int) $f['wifi'] ? 'Termasuk' : 'Tidak tersedia' ?></span></div>
                                                <div class="flex justify-between"><span>Hiburan</span><span class="font-medium"><?= (int) $f['entertainment'] ? 'Termasuk' : 'Tidak tersedia' ?></span></div>
                                                <div class="flex justify-between"><span>Refund</span><span class="font-medium"><?= e($f['refund_policy']) ?></span></div>
                                                <div class="flex justify-between"><span>Reschedule</span><span class="font-medium"><?= e($f['reschedule_policy']) ?></span></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="empty-state hidden bg-white rounded-2xl shadow-sm p-12 text-center border border-gray-100 mt-3">
                                    <i class="fa-solid fa-plane-slash text-6xl text-gray-300 mb-4"></i>
                                    <h3 class="text-xl font-bold text-gray-800 mb-2">Tidak Ada Penerbangan yang Cocok</h3>
                                    <p class="text-gray-500 mb-4">Coba ubah filter harga, waktu, atau maskapai Anda.</p>
                                    <button type="button" onclick="resetFilters()" class="bg-primary hover:bg-primaryDark text-white font-semibold px-6 py-2.5 rounded-lg transition"><i class="fa-solid fa-rotate-left mr-1"></i> Reset Filter</button>
                                </div>

                                <?php if (!$flights): ?>
                                    <div class="bg-white rounded-2xl shadow-sm p-10 text-center border border-gray-100">
                                        <i class="fa-solid fa-plane-slash text-5xl text-gray-300 mb-3"></i>
                                        <h3 class="text-lg font-bold text-gray-800 mb-1">Rute belum tersedia</h3>
                                        <p class="text-gray-500 text-sm">Tidak ada penerbangan aktif untuk rute ini. Coba rute lain.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($isMultiSelect): ?>
                            <div class="sticky bottom-4 bg-white rounded-2xl shadow-xl border border-gray-100 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Lanjut ke data penumpang</p>
                                    <p class="text-xs text-gray-500" id="leg-progress-label">Pilih satu penerbangan untuk setiap rute di atas.</p>
                                </div>
                                <button type="submit" id="leg-submit" disabled class="bg-accent hover:bg-accentDark text-white font-bold px-8 py-3 rounded-xl shadow-lg transition opacity-50 cursor-not-allowed flex items-center justify-center gap-2">
                                    Lanjut <i class="fa-solid fa-arrow-right"></i>
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- MODAL: PENUMPANG -->
<div id="modal-passenger" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 transform scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center mb-6"><h3 class="text-2xl font-bold text-gray-900">Penumpang &amp; Kelas</h3><button type="button" onclick="closeModal('modal-passenger')" class="text-gray-400 hover:text-gray-800 text-2xl">&times;</button></div>
        <div class="space-y-4 mb-6">
            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-xl">
                <div><p class="font-semibold text-gray-800">Dewasa</p><p class="text-sm text-gray-500">Usia 12+ tahun</p></div>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="updatePassenger('adult', -1)" class="w-8 h-8 rounded-full bg-white border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100">-</button>
                    <span id="count-adult" class="font-bold text-lg w-6 text-center"><?= $counts['adult'] ?></span>
                    <button type="button" onclick="updatePassenger('adult', 1)" class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center hover:bg-primaryDark">+</button>
                </div>
            </div>
            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-xl">
                <div><p class="font-semibold text-gray-800">Anak</p><p class="text-sm text-gray-500">Usia 2-11 tahun</p></div>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="updatePassenger('child', -1)" class="w-8 h-8 rounded-full bg-white border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100">-</button>
                    <span id="count-child" class="font-bold text-lg w-6 text-center"><?= $counts['child'] ?></span>
                    <button type="button" onclick="updatePassenger('child', 1)" class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center hover:bg-primaryDark">+</button>
                </div>
            </div>
            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-xl">
                <div><p class="font-semibold text-gray-800">Bayi</p><p class="text-sm text-gray-500">Di bawah 2 tahun</p></div>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="updatePassenger('infant', -1)" class="w-8 h-8 rounded-full bg-white border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100">-</button>
                    <span id="count-infant" class="font-bold text-lg w-6 text-center"><?= $counts['infant'] ?></span>
                    <button type="button" onclick="updatePassenger('infant', 1)" class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center hover:bg-primaryDark">+</button>
                </div>
            </div>
        </div>
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Kelas Penerbangan</label>
            <select id="select-class" class="w-full p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none text-lg">
                <?php foreach ($allowedCabins as $cabinOption): ?>
                    <option value="<?= e($cabinOption) ?>" <?= $cabinOption === $cabin ? 'selected' : '' ?>><?= e($cabinOption) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="button" onclick="savePassenger()" class="w-full bg-primary hover:bg-primaryDark text-white font-bold py-3 rounded-xl transition">Simpan</button>
    </div>
</div>

<script>
    function toggleDetails(btn) {
        const panel = btn.closest('.flight-card').querySelector('.details-panel');
        panel.classList.toggle('hidden');
        btn.innerHTML = panel.classList.contains('hidden')
            ? 'Lihat Detail <i class="fa-solid fa-chevron-down text-[10px]"></i>'
            : 'Tutup <i class="fa-solid fa-chevron-up text-[10px]"></i>';
    }
</script>
<?php
require __DIR__ . '/../includes/footer.php';

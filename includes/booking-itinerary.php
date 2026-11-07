<?php
/**
 * Itinerary + price summary card used by the booking pages.
 *
 * Expected variables:
 *   $legs     array from booking_legs_from_ids()
 *   $counts   ['adult'=>int,'child'=>int,'infant'=>int]
 *   $cabin    string
 *   $pricing  array from booking_pricing()
 * Optional:
 *   $promoState array from booking_draft_promo() (to show promo line)
 */
declare(strict_types=1);

$pricing = $pricing ?? ['subtotal' => 0, 'discount' => 0, 'total' => 0, 'per_type' => [], 'leg_totals' => []];
$counts = $counts ?? ['adult' => 1, 'child' => 0, 'infant' => 0];
$cabin = $cabin ?? 'Ekonomi';
$promoState = $promoState ?? null;

// The checkout steps pass legs from booking_legs_from_ids() (nested "flight"),
// while payment/instruction pass the flattened get_booking_legs() rows.
// Normalise both shapes so the card works everywhere.
$legs = $legs ?? [];
$normalizedLegs = [];
foreach ($legs as $index => $leg) {
    if (!is_array($leg)) {
        continue;
    }
    if (isset($leg['flight']) && is_array($leg['flight'])) {
        $normalizedLegs[] = $leg;
        continue;
    }
    $normalizedLegs[] = [
        'flight'    => $leg,
        'flight_id' => (int) ($leg['flight_id'] ?? $leg['id'] ?? 0),
        'date'      => (string) ($leg['travel_date'] ?? $leg['date'] ?? ''),
        'price'     => (float) ($leg['price'] ?? 0),
        'order'     => (int) ($leg['leg_order'] ?? $leg['order'] ?? $index + 1),
    ];
}
$legs = $normalizedLegs;
?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="bg-gradient-to-r from-primary to-cyan-500 px-5 py-4">
        <div class="flex items-center justify-between">
            <h2 class="text-white font-bold text-lg"><i class="fa-solid fa-route mr-2"></i>Ringkasan Perjalanan</h2>
            <span class="bg-white/20 text-white text-xs font-semibold px-3 py-1 rounded-full"><?= count($legs) ?> rute</span>
        </div>
        <p class="text-blue-50 text-sm mt-1"><?= e(pax_label($counts, $cabin)) ?></p>
    </div>

    <div class="p-5 space-y-4">
        <?php foreach ($legs as $index => $leg):
            $f = $leg['flight'];
            $depTime = substr((string) $f['dep_time'], 0, 5);
            $arrTime = substr((string) $f['arr_time'], 0, 5);
            $nextDay = (string) $f['arr_time'] < (string) $f['dep_time'];
        ?>
            <div class="relative pl-8 <?= $index > 0 ? 'pt-4 border-t border-dashed border-gray-200' : '' ?>">
                <div class="absolute left-0 top-0 w-6 h-6 rounded-full bg-blue-50 text-primary flex items-center justify-center text-[11px] font-bold"><?= $index + 1 ?></div>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-gray-900 truncate"><?= e($f['airline_name']) ?></p>
                        <p class="text-xs text-gray-500"><?= e($f['flight_num']) ?> &middot; <?= e($f['aircraft']) ?></p>
                    </div>
                    <p class="text-xs text-gray-500 whitespace-nowrap"><i class="fa-regular fa-calendar mr-1"></i><?= e(format_date_id($leg['date'], true)) ?></p>
                </div>
                <div class="flex items-center gap-2 mt-2">
                    <div>
                        <p class="text-lg font-extrabold text-gray-900 leading-none"><?= e($depTime) ?></p>
                        <p class="text-xs text-gray-500"><?= e($f['from_code']) ?></p>
                    </div>
                    <div class="flex-1 flex flex-col items-center px-1">
                        <p class="text-[11px] text-gray-400 mb-0.5"><?= e(format_duration((int) $f['duration'])) ?></p>
                        <div class="w-full flex items-center">
                            <span class="h-px bg-gray-300 flex-1"></span>
                            <i class="fa-solid fa-plane text-gray-400 text-[10px] mx-1"></i>
                            <span class="h-px bg-gray-300 flex-1"></span>
                        </div>
                        <p class="text-[11px] mt-0.5 <?= (int) $f['stops'] === 0 ? 'text-green-600' : 'text-orange-500' ?>">
                            <?= (int) $f['stops'] === 0 ? 'Langsung' : (int) $f['stops'] . ' Transit' ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-extrabold text-gray-900 leading-none"><?= e($arrTime) ?><?= $nextDay ? '<sup class="text-[10px] text-orange-500">+1</sup>' : '' ?></p>
                        <p class="text-xs text-gray-500"><?= e($f['to_code']) ?></p>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-1"><?= e($f['from_name']) ?> &rarr; <?= e($f['to_name']) ?></p>
            </div>
        <?php endforeach; ?>

        <div class="border-t border-gray-200 pt-4 space-y-2 text-sm">
            <?php if ((int) $counts['adult'] > 0): ?>
                <div class="flex justify-between text-gray-600"><span>Dewasa &times; <?= (int) $counts['adult'] ?></span><span><?= e(rupiah($pricing['per_type']['adult'] ?? 0)) ?></span></div>
            <?php endif; ?>
            <?php if ((int) $counts['child'] > 0): ?>
                <div class="flex justify-between text-gray-600"><span>Anak &times; <?= (int) $counts['child'] ?></span><span><?= e(rupiah($pricing['per_type']['child'] ?? 0)) ?></span></div>
            <?php endif; ?>
            <?php if ((int) $counts['infant'] > 0): ?>
                <div class="flex justify-between text-gray-600"><span>Bayi &times; <?= (int) $counts['infant'] ?></span><span><?= e(rupiah($pricing['per_type']['infant'] ?? 0)) ?></span></div>
            <?php endif; ?>
            <?php if ($promoState && $promoState['valid'] && $pricing['discount'] > 0): ?>
                <div class="flex justify-between text-green-600"><span>Diskon promo <span class="font-mono text-xs">(<?= e($promoState['code']) ?>)</span></span><span>-<?= e(rupiah($pricing['discount'])) ?></span></div>
            <?php endif; ?>
            <div class="flex justify-between items-center border-t border-gray-100 pt-3 mt-1">
                <span class="font-bold text-gray-900">Total</span>
                <span class="text-xl font-extrabold text-accent"><?= e(rupiah($pricing['total'])) ?></span>
            </div>
            <p class="text-[11px] text-gray-400">Sudah termasuk pajak &amp; biaya layanan. Harga final dihitung ulang di server.</p>
        </div>
    </div>
</div>

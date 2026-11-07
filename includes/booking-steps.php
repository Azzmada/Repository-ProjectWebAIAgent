<?php
/**
 * Booking progress indicator.
 *
 * Expected variable: $currentStep (1 = flights, 2 = passengers, 3 = payment, 4 = e-ticket)
 * Optional: $stepsDone (array of step numbers that are clickable/completed)
 */
declare(strict_types=1);

$currentStep = (int) ($currentStep ?? 1);
$steps = [
    1 => ['label' => 'Pilih Penerbangan', 'short' => 'Penerbangan', 'icon' => 'fa-plane'],
    2 => ['label' => 'Data Penumpang', 'short' => 'Penumpang', 'icon' => 'fa-users'],
    3 => ['label' => 'Pembayaran', 'short' => 'Pembayaran', 'icon' => 'fa-credit-card'],
    4 => ['label' => 'E-Tiket', 'short' => 'E-Tiket', 'icon' => 'fa-ticket'],
];
?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-4 py-5 sm:px-6 mb-6">
    <ol class="flex items-center justify-between gap-1 sm:gap-3">
        <?php foreach ($steps as $number => $step):
            $isDone = $number < $currentStep;
            $isActive = $number === $currentStep;
            $circle = $isDone
                ? 'bg-green-500 text-white'
                : ($isActive ? 'bg-primary text-white shadow-lg shadow-blue-200' : 'bg-gray-100 text-gray-400');
            $text = $isDone ? 'text-green-600' : ($isActive ? 'text-primary font-bold' : 'text-gray-400');
        ?>
            <li class="flex-1 flex flex-col items-center text-center relative">
                <?php if ($number < 4): ?>
                    <span class="hidden sm:block absolute top-5 left-1/2 w-full h-0.5 <?= $isDone ? 'bg-green-400' : 'bg-gray-200' ?>" aria-hidden="true"></span>
                <?php endif; ?>
                <span class="relative z-10 w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold <?= $circle ?>">
                    <?php if ($isDone): ?><i class="fa-solid fa-check"></i><?php else: ?><i class="fa-solid <?= e($step['icon']) ?>"></i><?php endif; ?>
                </span>
                <span class="mt-2 text-[11px] sm:text-sm leading-tight <?= $text ?>">
                    <span class="hidden sm:inline"><?= e($step['label']) ?></span>
                    <span class="sm:hidden"><?= e($step['short']) ?></span>
                </span>
            </li>
        <?php endforeach; ?>
    </ol>
</div>

<?php

use App\Services\DobbleSetGenerator;
use InvalidArgumentException;

test('a complete order seven set has 57 cards with eight symbols each', function () {
    $deck = (new DobbleSetGenerator)->generate(7, 57);

    expect($deck['total_cards'])->toBe(57)
        ->and($deck['symbols_per_card'])->toBe(8)
        ->and($deck['cards'])->toHaveCount(57)
        ->and($deck['symbols'])->toHaveCount(57);

    foreach ($deck['cards'] as $firstIndex => $firstCard) {
        expect($firstCard['symbols'])->toHaveCount(8)
            ->and(array_unique($firstCard['symbols']))->toHaveCount(8);

        foreach (array_slice($deck['cards'], $firstIndex + 1) as $secondCard) {
            expect(array_intersect($firstCard['symbols'], $secondCard['symbols']))->toHaveCount(1);
        }
    }
});

test('a smaller set keeps the exact-one-shared-symbol rule', function () {
    $deck = (new DobbleSetGenerator)->generate(5, 12);

    expect($deck['cards'])->toHaveCount(12)
        ->and($deck['symbols_per_card'])->toBe(6);

    foreach ($deck['cards'] as $firstIndex => $firstCard) {
        foreach (array_slice($deck['cards'], $firstIndex + 1) as $secondCard) {
            expect(array_intersect($firstCard['symbols'], $secondCard['symbols']))->toHaveCount(1);
        }
    }
});

test('unsupported orders and impossible card counts are rejected', function (int $order, int $cardCount) {
    expect(fn () => (new DobbleSetGenerator)->generate($order, $cardCount))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'order four is not supported' => [4, 10],
    'more than the complete order two set' => [2, 8],
    'fewer than two cards' => [3, 1],
]);

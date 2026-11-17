<?php

use App\Services\CardSymbolArranger;
use InvalidArgumentException;

test('randomized symbol placements stay inside the card with a gap between every pair', function (int $symbolCount) {
    $arranger = new CardSymbolArranger;
    $placements = $arranger->arrange(range(0, $symbolCount - 1), 3842);

    expect($placements)->toHaveCount($symbolCount);

    foreach ($placements as $placement) {
        $x = ($placement['left'] - 50) / 50;
        $y = ($placement['top'] - 50) / 50;
        $size = $placement['size'] / 50;

        expect(hypot($x, $y) + ($size / sqrt(2)))->toBeLessThanOrEqual(0.86);
    }

    foreach ($placements as $firstIndex => $firstPlacement) {
        $firstX = ($firstPlacement['left'] - 50) / 50;
        $firstY = ($firstPlacement['top'] - 50) / 50;
        $firstSize = $firstPlacement['size'] / 50;

        foreach (array_slice($placements, $firstIndex + 1) as $secondPlacement) {
            $secondX = ($secondPlacement['left'] - 50) / 50;
            $secondY = ($secondPlacement['top'] - 50) / 50;
            $secondSize = $secondPlacement['size'] / 50;
            $minimumDistance = ($firstSize / sqrt(2)) + ($secondSize / sqrt(2)) + 0.04;

            expect(hypot($firstX - $secondX, $firstY - $secondY))
                ->toBeGreaterThanOrEqual($minimumDistance - 1e-10);
        }
    }
})->with([3, 4, 6, 8]);

test('the same seed returns a stable layout and another seed changes positions and rotations', function () {
    $arranger = new CardSymbolArranger;
    $symbolIds = range(0, 7);
    $firstLayout = $arranger->arrange($symbolIds, 12345);

    expect($arranger->arrange($symbolIds, 12345))->toEqual($firstLayout)
        ->and($arranger->arrange($symbolIds, 54321))->not->toEqual($firstLayout)
        ->and(count(array_filter($firstLayout, fn (array $placement): bool => $placement['rotation'] !== 0)))
        ->toBeGreaterThan(0);
});

test('unsupported symbol counts and duplicate ids are rejected', function (array $symbolIds) {
    expect(fn () => (new CardSymbolArranger)->arrange($symbolIds, 10))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'unsupported symbol count' => [[1, 2, 3, 4, 5]],
    'duplicate symbol ids' => [[1, 1, 2, 3]],
]);

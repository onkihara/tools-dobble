<?php

use App\Services\CardSymbolArranger;
use InvalidArgumentException;
use Tests\TestCase;

uses(TestCase::class);

test('symbol placements may overlap and extend beyond the card circle', function (int $symbolCount) {
    $arranger = new CardSymbolArranger;
    $placements = $arranger->arrange(range(0, $symbolCount - 1), 3842, 200, 200);

    expect($placements)->toHaveCount($symbolCount);

    $extendsBeyondCircle = false;
    foreach ($placements as $placement) {
        $x = ($placement['left'] - 50) / 50;
        $y = ($placement['top'] - 50) / 50;
        $halfDiagonal = (($placement['size'] / 50) * $placement['scale']) / sqrt(2);

        if (hypot($x, $y) + $halfDiagonal > 1) {
            $extendsBeyondCircle = true;
        }
    }

    $symbolsOverlap = false;
    foreach ($placements as $firstIndex => $firstPlacement) {
        $firstX = ($firstPlacement['left'] - 50) / 50;
        $firstY = ($firstPlacement['top'] - 50) / 50;
        $firstHalfDiagonal = (($firstPlacement['size'] / 50) * $firstPlacement['scale']) / sqrt(2);

        foreach (array_slice($placements, $firstIndex + 1) as $secondPlacement) {
            $secondX = ($secondPlacement['left'] - 50) / 50;
            $secondY = ($secondPlacement['top'] - 50) / 50;
            $secondHalfDiagonal = (($secondPlacement['size'] / 50) * $secondPlacement['scale']) / sqrt(2);

            if (hypot($firstX - $secondX, $firstY - $secondY) < $firstHalfDiagonal + $secondHalfDiagonal) {
                $symbolsOverlap = true;
            }
        }
    }

    expect($extendsBeyondCircle)->toBeTrue()
        ->and($symbolsOverlap)->toBeTrue();
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

test('default symbol scales use the Dobble configuration', function () {
    config([
        'dobble.symbol_scale.minimum' => 40,
        'dobble.symbol_scale.maximum' => 200,
        'dobble.symbol_scale.default_minimum' => 80,
        'dobble.symbol_scale.default_maximum' => 100,
    ]);

    $placements = (new CardSymbolArranger)->arrange(range(0, 3), 12345);

    foreach ($placements as $placement) {
        expect($placement['scale'])->toBeGreaterThanOrEqual(0.8)
            ->toBeLessThanOrEqual(1.0);
    }
});

test('unsupported symbol counts and duplicate ids are rejected', function (array $symbolIds) {
    expect(fn () => (new CardSymbolArranger)->arrange($symbolIds, 10))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'unsupported symbol count' => [[1, 2, 3, 4, 5]],
    'duplicate symbol ids' => [[1, 1, 2, 3]],
]);

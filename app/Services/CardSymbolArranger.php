<?php

namespace App\Services;

use InvalidArgumentException;
use LogicException;
use Random\Engine\Mt19937;
use Random\Randomizer;

class CardSymbolArranger
{
    private const PROFILES = [
        3 => ['radius' => 0.36, 'size' => 0.40, 'angular_jitter' => 0.035, 'radial_jitter' => 0.018],
        4 => ['radius' => 0.43, 'size' => 0.35, 'angular_jitter' => 0.08, 'radial_jitter' => 0.025],
        6 => ['radius' => 0.52, 'size' => 0.27, 'angular_jitter' => 0.11, 'radial_jitter' => 0.03],
        8 => ['radius' => 0.58, 'size' => 0.22, 'angular_jitter' => 0.14, 'radial_jitter' => 0.03],
    ];

    private const EDGE_LIMIT = 0.86;

    private const SYMBOL_GAP = 0.04;

    /**
     * @param  list<int>  $symbolIds
     * @return list<array{symbol_id: int, left: float, top: float, size: float, rotation: int}>
     */
    public function arrange(array $symbolIds, int $seed): array
    {
        $symbolCount = count($symbolIds);

        if (! isset(self::PROFILES[$symbolCount]) || count(array_unique($symbolIds)) !== $symbolCount) {
            throw new InvalidArgumentException('A card must contain a supported number of unique symbols.');
        }

        $profile = self::PROFILES[$symbolCount];
        $randomizer = new Randomizer(new Mt19937($seed));

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $placements = $this->createRandomPlacement($symbolIds, $profile, $randomizer);

            if ($placements !== null) {
                return $placements;
            }
        }

        $placements = $this->createEvenPlacement($symbolIds, $profile, $randomizer);

        if ($placements === null) {
            throw new LogicException('A safe symbol layout could not be generated for this card.');
        }

        return $placements;
    }

    /**
     * @param  list<int>  $symbolIds
     * @param  array{radius: float, size: float, angular_jitter: float, radial_jitter: float}  $profile
     * @return list<array{symbol_id: int, left: float, top: float, size: float, rotation: int}>|null
     */
    private function createRandomPlacement(array $symbolIds, array $profile, Randomizer $randomizer): ?array
    {
        $phase = $randomizer->getInt(0, 6283) / 1000;
        $orderedSymbolIds = $randomizer->shuffleArray($symbolIds);
        $placements = [];

        foreach ($orderedSymbolIds as $slot => $symbolId) {
            $angleJitter = $randomizer->getInt(-1000, 1000) / 1000 * $profile['angular_jitter'];
            $radiusJitter = $randomizer->getInt(-1000, 1000) / 1000 * $profile['radial_jitter'];
            $angle = $phase + (2 * M_PI * $slot / count($symbolIds)) + $angleJitter;
            $radius = $profile['radius'] + $radiusJitter;
            $x = cos($angle) * $radius;
            $y = sin($angle) * $radius;
            $rotation = $randomizer->getInt(-180, 180);

            if (! $this->isSafePlacement($x, $y, $profile['size'], $placements)) {
                return null;
            }

            $placements[] = $this->makePlacement($symbolId, $x, $y, $profile['size'], $rotation);
        }

        return $placements;
    }

    /**
     * @param  list<int>  $symbolIds
     * @param  array{radius: float, size: float, angular_jitter: float, radial_jitter: float}  $profile
     * @return list<array{symbol_id: int, left: float, top: float, size: float, rotation: int}>|null
     */
    private function createEvenPlacement(array $symbolIds, array $profile, Randomizer $randomizer): ?array
    {
        $phase = $randomizer->getInt(0, 6283) / 1000;
        $orderedSymbolIds = $randomizer->shuffleArray($symbolIds);
        $placements = [];

        foreach ($orderedSymbolIds as $slot => $symbolId) {
            $angle = $phase + (2 * M_PI * $slot / count($symbolIds));
            $x = cos($angle) * $profile['radius'];
            $y = sin($angle) * $profile['radius'];

            if (! $this->isSafePlacement($x, $y, $profile['size'], $placements)) {
                return null;
            }

            $placements[] = $this->makePlacement(
                $symbolId,
                $x,
                $y,
                $profile['size'],
                $randomizer->getInt(-180, 180),
            );
        }

        return $placements;
    }

    /**
     * @param  list<array{symbol_id: int, left: float, top: float, size: float, rotation: int}>  $placements
     */
    private function isSafePlacement(float $x, float $y, float $size, array $placements): bool
    {
        $halfDiagonal = $size / sqrt(2);

        if (hypot($x, $y) + $halfDiagonal > self::EDGE_LIMIT) {
            return false;
        }

        foreach ($placements as $placement) {
            $otherX = ($placement['left'] - 50) / 50;
            $otherY = ($placement['top'] - 50) / 50;
            $otherSize = $placement['size'] / 50;
            $minimumDistance = $halfDiagonal + ($otherSize / sqrt(2)) + self::SYMBOL_GAP;

            if (hypot($x - $otherX, $y - $otherY) < $minimumDistance) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{symbol_id: int, left: float, top: float, size: float, rotation: int}
     */
    private function makePlacement(int $symbolId, float $x, float $y, float $size, int $rotation): array
    {
        return [
            'symbol_id' => $symbolId,
            'left' => (0.5 + ($x / 2)) * 100,
            'top' => (0.5 + ($y / 2)) * 100,
            'size' => ($size / 2) * 100,
            'rotation' => $rotation,
        ];
    }
}

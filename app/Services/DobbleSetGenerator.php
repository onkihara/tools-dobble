<?php

namespace App\Services;

use InvalidArgumentException;
use LogicException;

class DobbleSetGenerator
{
    public const SUPPORTED_ORDERS = [2, 3, 5, 7];

    /**
     * @return array{
     *     cards: list<array{id: int, symbols: list<int>}>,
     *     symbols: list<int>,
     *     total_cards: int,
     *     symbols_per_card: int
     * }
     */
    public function generate(int $order, int $cardCount): array
    {
        if (! in_array($order, self::SUPPORTED_ORDERS, true)) {
            throw new InvalidArgumentException('The projective-plane order is not supported.');
        }

        $totalCards = $order ** 2 + $order + 1;

        if ($cardCount < 2 || $cardCount > $totalCards) {
            throw new InvalidArgumentException('The requested number of cards is not supported.');
        }

        $cards = [];

        for ($slope = 0; $slope < $order; $slope++) {
            for ($intercept = 0; $intercept < $order; $intercept++) {
                $symbols = [];

                for ($x = 0; $x < $order; $x++) {
                    $y = ($slope * $x + $intercept) % $order;
                    $symbols[] = $y * $order + $x;
                }

                $symbols[] = $order ** 2 + $slope;
                $cards[] = ['id' => count($cards) + 1, 'symbols' => $symbols];
            }
        }

        for ($x = 0; $x < $order; $x++) {
            $symbols = [];

            for ($y = 0; $y < $order; $y++) {
                $symbols[] = $y * $order + $x;
            }

            $symbols[] = $order ** 2 + $order;
            $cards[] = ['id' => count($cards) + 1, 'symbols' => $symbols];
        }

        $cards[] = [
            'id' => count($cards) + 1,
            'symbols' => range($order ** 2, $order ** 2 + $order),
        ];
        $cards = array_slice($cards, 0, $cardCount);

        $symbolIds = array_values(array_unique(array_merge(...array_column($cards, 'symbols'))));
        sort($symbolIds);

        $this->assertValid($cards, $order + 1);

        return [
            'cards' => $cards,
            'symbols' => $symbolIds,
            'total_cards' => $totalCards,
            'symbols_per_card' => $order + 1,
        ];
    }

    /**
     * @param  list<array{id: int, symbols: list<int>}>  $cards
     */
    private function assertValid(array $cards, int $symbolsPerCard): void
    {
        foreach ($cards as $card) {
            if (count($card['symbols']) !== $symbolsPerCard || count(array_unique($card['symbols'])) !== $symbolsPerCard) {
                throw new LogicException("Card {$card['id']} does not contain the expected number of unique symbols.");
            }
        }

        for ($first = 0; $first < count($cards); $first++) {
            for ($second = $first + 1; $second < count($cards); $second++) {
                $sharedSymbols = array_intersect($cards[$first]['symbols'], $cards[$second]['symbols']);

                if (count($sharedSymbols) !== 1) {
                    throw new LogicException(
                        "Cards {$cards[$first]['id']} and {$cards[$second]['id']} share ".count($sharedSymbols).' symbols instead of one.'
                    );
                }
            }
        }
    }
}

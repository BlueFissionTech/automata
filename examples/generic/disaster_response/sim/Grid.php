<?php

namespace Examples\DisasterResponse\Sim;

class Grid
{
    private int $width;
    private int $height;
    /** @var array<string, Cell> */
    private array $cells;

    public function __construct(int $width, int $height, array $cells = [])
    {
        $this->width = $width;
        $this->height = $height;
        $this->cells = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $key = $x . ',' . $y;
                $cell = $cells[$key] ?? new Cell('road');
                $this->cells[$key] = $cell instanceof Cell ? $cell : new Cell('road');
            }
        }
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function inBounds(Position $position): bool
    {
        return $position->x() >= 0
            && $position->y() >= 0
            && $position->x() < $this->width
            && $position->y() < $this->height;
    }

    public function setCell(Position $position, Cell $cell): void
    {
        if (!$this->inBounds($position)) {
            return;
        }
        $this->cells[$position->toKey()] = $cell;
    }

    public function cell(Position $position): ?Cell
    {
        if (!$this->inBounds($position)) {
            return null;
        }

        return $this->cells[$position->toKey()] ?? null;
    }

    /** @return Position[] */
    public function neighbors(Position $position): array
    {
        $candidates = [
            [0, -1],
            [1, 0],
            [0, 1],
            [-1, 0],
        ];

        $neighbors = [];
        foreach ($candidates as [$dx, $dy]) {
            $neighbor = new Position($position->x() + $dx, $position->y() + $dy);
            if ($this->inBounds($neighbor)) {
                $neighbors[] = $neighbor;
            }
        }

        return $neighbors;
    }

    public function needScore(Position $position): int
    {
        $cell = $this->cell($position);
        if (!$cell) {
            return 0;
        }

        $score = 0;
        if ($cell->people() > 0) {
            $score += 4;
        }
        if ($cell->isBlocked()) {
            $score += 3;
        }
        if ($cell->isDamaged()) {
            $score += 2;
        }
        if ($cell->supplies() > 0) {
            $score += 1;
        }

        return $score;
    }

    public function bestNeighbor(Position $position): ?Position
    {
        $neighbors = $this->neighbors($position);
        if (empty($neighbors)) {
            return null;
        }

        $best = $neighbors[0];
        $bestScore = $this->needScore($best);

        foreach ($neighbors as $neighbor) {
            $score = $this->needScore($neighbor);
            if ($score > $bestScore) {
                $best = $neighbor;
                $bestScore = $score;
            }
        }

        return $best;
    }
}

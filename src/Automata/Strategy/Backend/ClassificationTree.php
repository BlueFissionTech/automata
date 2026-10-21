<?php

namespace BlueFission\Automata\Strategy\Backend;

use BlueFission\Arr;
use BlueFission\Num;
use Phpml\Classification\DecisionTree;

/**
 * Internal PHP-ML compatibility boundary for fitted classification trees.
 *
 * PHP-ML transposes columns with array_map(null, ...$columns). With one column,
 * PHP returns a flat list, so split selection reads scalar values as rows and
 * prediction falls back to the first class. Construct row vectors explicitly.
 * Numeric boundaries use the gap between observed values around the median,
 * preserving the backend's training partition without putting the boundary on
 * an observed value. Feature selection still uses PHP-ML's median/Gini heuristic,
 * not an exhaustive search of all thresholds or a calibrated probability model.
 * This named class is also part of the trusted serialized artifact format.
 *
 * @internal
 */
final class ClassificationTree extends DecisionTree
{
    /** Preserve row dimensions and choose finite gap boundaries from training only. */
    protected function preprocess(array $samples): array
    {
        $rows = [];
        foreach ($this->columnTypes as $column => $type) {
            $values = Arr::make($samples)->map(static fn (array $row) => $row[$column])->values()->val();
            if ($type === self::CONTINUOUS) {
                $ordered = Arr::make($values)->sort(static fn ($a, $b) => $a <=> $b)->val();
                // The lower median identifies the same training partition as
                // PHP-ML without overflowing while averaging extreme values.
                $middle = (int) Num::make(Arr::size($ordered))->subtract(1)->divide(2)->val();
                $lower = $ordered[$middle];
                $upper = null;
                foreach ($ordered as $value) {
                    if ($value > $lower) { $upper = $value; break; }
                }
                $threshold = $upper === null ? $lower : $lower / 2.0 + $upper / 2.0;
                // Adjacent floating-point values may have no representable midpoint.
                if ($upper !== null && $threshold >= $upper) { $threshold = $lower; }
                $boundary = json_encode($threshold, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
                $values = Arr::make($values)->map(static fn ($value) => ($value <= $threshold ? '<= ' : '> ') . $boundary)->val();
            }
            foreach ($values as $row => $value) { $rows[$row][$column] = $value; }
        }
        return $rows;
    }
}

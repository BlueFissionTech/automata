<?php

namespace BlueFission\Automata\Feature;

use BlueFission\Vec;

class InteractionFeatures {
    public function transform($data) {
        $interactionData = new Vec();

        foreach ($data as $row) {
            $vectorRow = new Vec($row);
            $newRow = clone $vectorRow;  // Start with original features, using Vec
            
            for ($i = 0; $i < $vectorRow->count(); $i++) {
                for ($j = $i + 1; $j < $vectorRow->count(); $j++) {
                    $newRow->add($vectorRow->get($i) * $vectorRow->get($j));  // Add interaction term
                }
            }
            $interactionData->add($newRow);
        }
        return $interactionData;
    }
}

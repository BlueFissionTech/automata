<?php

namespace BlueFission\Automata\Feature;

use BlueFission\Vec;
use BlueFission\DevElation as Dev;

class InteractionFeatures {
    public function transform($data) {
        $data = Dev::apply('feature.interaction.input', $data);
        $interactionData = new Vec();

        foreach ($data as $row) {
            $vectorRow = new Vec($row);
            $newRow = new Vec($vectorRow->val());  // Copy values into a fresh Vec to avoid shared storage.
            
            for ($i = 0; $i < $vectorRow->count(); $i++) {
                for ($j = $i + 1; $j < $vectorRow->count(); $j++) {
                    $newRow->add($vectorRow->get($i) * $vectorRow->get($j));  // Add interaction term
                }
            }
            $interactionData->add($newRow);
        }
        $result = Dev::apply('feature.interaction.output', $interactionData);
        Dev::do('feature.interaction.complete', ['result' => $result]);
        return $result;
    }
}

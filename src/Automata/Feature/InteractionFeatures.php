<?php

namespace BlueFission\Automata\Feature;

use BlueFission\Automata\Support\IStructureFactory;
use BlueFission\Automata\Support\StructureFactory;
use BlueFission\DevElation as Dev;
use BlueFission\Num;

class InteractionFeatures {
    private IStructureFactory $_structures;

    public function __construct(?IStructureFactory $structures = null)
    {
        $this->_structures = $structures ?? new StructureFactory();
    }

    public function transform($data) {
        $data = Dev::apply('feature.interaction.input', $data);
        $interactionData = $this->_structures->vec();

        foreach ($data as $row) {
            $vectorRow = $this->_structures->vec($row);
            $newRow = $this->_structures->vec($vectorRow->val());  // Copy values into a fresh Vec to avoid shared storage.
            
            for ($i = 0; $i < $vectorRow->count(); $i++) {
                for ($j = $i + 1; $j < $vectorRow->count(); $j++) {
                    $newRow->add(Num::make($vectorRow->get($i))->times($vectorRow->get($j))->val());
                }
            }
            $interactionData->add($newRow);
        }
        $result = Dev::apply('feature.interaction.output', $interactionData);
        Dev::do('feature.interaction.complete', ['result' => $result]);
        return $result;
    }
}

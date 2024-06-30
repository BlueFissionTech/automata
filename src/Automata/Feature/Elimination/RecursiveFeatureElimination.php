<?php

namespace BlueFission\Automata\Feature\Elimination;

class RecursiveFeatureElimination {
    private $model;
    private $nFeaturesToSelect;

    public function __construct($model, $nFeaturesToSelect) {
        $this->model = $model; // This should be an instance of a model class with fit and predict methods.
        $this->nFeaturesToSelect = $nFeaturesToSelect;
    }

    public function fit(array $X, array $y) {
        $nFeatures = count($X[0]);
        $featuresIndices = range(0, $nFeatures - 1);

        while (count($featuresIndices) > $this->nFeaturesToSelect) {
            $this->model->fit($X, $y);
            $importances = $this->model->getFeatureImportances();
            $leastImportantIndex = array_search(min($importances), $importances);
            unset($featuresIndices[$leastImportantIndex]);
            $X = $this->filterFeatures($X, $featuresIndices);
        }

        return $featuresIndices;
    }

    private function filterFeatures(array $data, array $featureIndices) {
        $filteredData = [];
        foreach ($data as $row) {
            $filteredRow = array_intersect_key($row, array_flip($featureIndices));
            $filteredData[] = array_values($filteredRow); // Reindex array
        }
        return $filteredData;
    }
}

<?php

namespace BlueFission\Tests\Automata\Genetic;

use PHPUnit\Framework\TestCase;
use BlueFission\Obj;
use BlueFission\Automata\Genetic\Population;
use BlueFission\Automata\Genetic\UniformCrossover;
use BlueFission\Automata\Genetic\RandomMutation;
use BlueFission\Automata\Genetic\FitnessFunction;

class DisasterChromosome extends Obj
{
}

class SimpleFitness extends FitnessFunction
{
    public function evaluate($individual): float
    {
        // Higher fitness for lower risk and time weight, with moderate capacity bias.
        $data = $individual->data();

        $risk   = (float)($data['risk_weight'] ?? 0.0);
        $time   = (float)($data['time_weight'] ?? 0.0);
        $cap    = (float)($data['capacity_bias'] ?? 0.0);

        return max(0.0, 10.0 - ($risk + $time) + (1.0 - abs($cap - 1.0)));
    }
}

class GeneticPopulationTest extends TestCase
{
    public function testPopulationInitializeAndSelection(): void
    {
        $population = new Population();

        $population->initialize(5, function () {
            $chromosome = new DisasterChromosome();
            $chromosome->assign([
                'risk_weight'   => mt_rand(0, 10) / 10,
                'time_weight'   => mt_rand(0, 10) / 10,
                'capacity_bias' => mt_rand(0, 10) / 10,
            ]);

            return $chromosome;
        });

        $individuals = $population->getIndividuals();

        $this->assertCount(5, $individuals);
        $this->assertInstanceOf(DisasterChromosome::class, $individuals[0]);

        $fitness = new SimpleFitness();

        $selected = $population->selection(function (array $pool) use ($fitness) {
            usort($pool, function ($a, $b) use ($fitness) {
                return $fitness->evaluate($b) <=> $fitness->evaluate($a);
            });

            // Keep the top half.
            return array_slice($pool, 0, (int)ceil(count($pool) / 2));
        });

        $this->assertLessThan(count($individuals), count($selected->getIndividuals()));
        $this->assertGreaterThan(0, count($selected->getIndividuals()));
    }

    public function testUniformCrossoverProducesHybridIndividual(): void
    {
        $parent1 = new DisasterChromosome();
        $parent1->assign([
            'risk_weight'   => 0.1,
            'time_weight'   => 0.9,
            'capacity_bias' => 1.0,
        ]);

        $parent2 = new DisasterChromosome();
        $parent2->assign([
            'risk_weight'   => 0.9,
            'time_weight'   => 0.1,
            'capacity_bias' => 0.0,
        ]);

        $crossover = new UniformCrossover(1.0); // always take from parent2

        $offspring = $crossover->cross($parent1, $parent2);

        $data = $offspring->data();

        $this->assertSame(0.9, $data['risk_weight']);
        $this->assertSame(0.1, $data['time_weight']);
        $this->assertSame(0.0, $data['capacity_bias']);
    }

    public function testRandomMutationPerturbsNumericFields(): void
    {
        mt_srand(123);

        $chromosome = new DisasterChromosome();
        $chromosome->assign([
            'risk_weight'   => 0.5,
            'time_weight'   => 0.5,
            'capacity_bias' => 0.5,
        ]);

        $original = $chromosome->data();

        $mutation = new RandomMutation(1.0); // always mutate for test
        $mutation->mutate($chromosome);

        $mutated = $chromosome->data();

        $this->assertNotSame($original['risk_weight'], $mutated['risk_weight']);
        $this->assertNotSame($original['time_weight'], $mutated['time_weight']);
        $this->assertNotSame($original['capacity_bias'], $mutated['capacity_bias']);
    }
}

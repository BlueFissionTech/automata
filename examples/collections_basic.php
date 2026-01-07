<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use BlueFission\Deq;
use BlueFission\Dict;
use BlueFission\Set;
use BlueFission\Pile;
use BlueFission\Pri;
use BlueFission\Vec;

/*
 * Basic demonstration of Automata's core collection value objects.
 *
 * These examples favor Develation-style usage: value objects wrapping
 * underlying DS structures, intended for large data and streaming workloads.
 */

echo "=== Deq (double-ended queue) ===\n";
$deq = new Deq();
$deq->pushBack('b')->pushFront('a')->pushBack('c');
echo "Count: " . $deq->count() . "\n";
echo "Front: " . $deq->get(0) . "\n";
echo "Back (popBack): " . $deq->popBack() . "\n";
echo "New count after popBack: " . $deq->count() . "\n\n";

echo "=== Dict (map) ===\n";
$dict = new Dict();
$dict->put('alpha', 1)->put('beta', 2);
echo "alpha => " . $dict->get('alpha') . "\n";
echo "Has key 'beta'? " . ($dict->hasKey('beta') ? 'yes' : 'no') . "\n";
$dict->remove('beta');
echo "Has key 'beta' after remove? " . ($dict->hasKey('beta') ? 'yes' : 'no') . "\n\n";

echo "=== Set (set) ===\n";
$list = new Set();
$list->add('x')->add('y')->add('x');
echo "Contains 'x'? " . ($list->has('x') ? 'yes' : 'no') . "\n";
echo "Count (set semantics): " . $list->count() . "\n";
$list->remove('x');
echo "Contains 'x' after remove? " . ($list->has('x') ? 'yes' : 'no') . "\n\n";

echo "=== Pile (stack) ===\n";
$pile = new Pile(['first', 'second']);
$pile->push('third');
echo "Count: " . $pile->count() . "\n";
echo "Pop: " . $pile->pop() . "\n";
echo "Count after pop: " . $pile->count() . "\n\n";

echo "=== Pri (priority queue) ===\n";
$pri = new Pri();
$pri->insert('low', 1)->insert('high', 10)->insert('medium', 5);
echo "Extract (highest priority): " . $pri->extract() . "\n";
echo "Peek next: " . $pri->peek() . "\n";
echo "Is empty? " . ($pri->isEmpty() ? 'yes' : 'no') . "\n\n";

echo "=== Vec (vector) ===\n";
$vec = new Vec([10, 20, 30]);
$vec->add(40);
echo "Count: " . $vec->count() . "\n";
echo "Index 2: " . $vec->get(2) . "\n";
$vec->set(1, 99);
echo "Index 1 after set: " . $vec->get(1) . "\n";

echo "\nCollections basic example completed.\n";

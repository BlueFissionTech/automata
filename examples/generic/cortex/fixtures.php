<?php

// Versioned synthetic observations. Hash decoded JSON so checkout line endings do not matter.
$fixture = json_decode(file_get_contents(__DIR__ . '/fixture-v1.json'), true, 512, JSON_THROW_ON_ERROR);
$baseline = json_decode(file_get_contents(__DIR__ . '/baseline-v1.json'), true, 512, JSON_THROW_ON_ERROR);
$digest = hash('sha256', json_encode($fixture, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
if ($fixture['id'] !== $baseline['fixture_id'] || $digest !== $baseline['fixture_sha256']) {
    throw new RuntimeException('Cortex fixture differs from the reviewed regression baseline.');
}
return ['training' => $fixture['training'], 'holdout' => $fixture['evaluation']];

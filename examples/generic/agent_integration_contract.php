<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Automata\LLM\Agent\Integration\AgentIntegrationContract;
use BlueFission\Net\HTTP;

$contract = AgentIntegrationContract::standard();

print HTTP::jsonEncode([
    'version' => $contract->version(),
    'jenss_bindings' => $contract->bindings(AgentIntegrationContract::CONSUMER_JENSS),
    'linqr_features' => $contract->features(AgentIntegrationContract::CONSUMER_LINQR),
    'catalog_filters' => $contract->toolCatalogFilters(),
    'production_checks' => $contract->acceptanceCriteria(),
]) . PHP_EOL;

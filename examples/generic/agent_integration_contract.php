<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Automata\LLM\Agent\Integration\AgentIntegrationContract;
use BlueFission\Net\HTTP;

$contract = AgentIntegrationContract::standard();

print HTTP::jsonEncode([
    'version' => $contract->version(),
    'contract_template' => $contract->contractTemplate(),
    'binding_template' => $contract->bindingTemplate(),
    'features' => $contract->features([
        AgentIntegrationContract::FEATURE_TOOLS,
        AgentIntegrationContract::FEATURE_HOLOSCENE,
        AgentIntegrationContract::FEATURE_TELEMETRY,
    ]),
    'catalog_filters' => $contract->toolCatalogFilters(),
    'production_checks' => $contract->acceptanceCriteria(),
]) . PHP_EOL;

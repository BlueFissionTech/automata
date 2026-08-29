<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Automata\LLM\Generation\GeneratedArtifact;
use BlueFission\Automata\LLM\Generation\GenerationDiagnostic;
use BlueFission\Automata\LLM\Generation\GenerationRunRequest;
use BlueFission\Automata\LLM\Generation\GenerationRunResult;
use BlueFission\Automata\LLM\Generation\GenerationStatus;
use BlueFission\Automata\LLM\Generation\GenerationStep;
use BlueFission\Net\HTTP;

$request = new GenerationRunRequest([
    'run_id' => 'release-notes-example',
    'input' => ['prompt' => 'Draft release notes from approved changes.'],
    'constraints' => ['format' => 'markdown', 'max_sections' => 5],
    'policy' => ['publish' => false, 'human_review' => true],
    'evidence' => [['source' => 'approved-change-set']],
]);

$draft = new GeneratedArtifact([
    'artifact_id' => 'release-notes-draft',
    'kind' => 'document',
    'media_type' => 'text/markdown',
    'content' => "# Draft release notes\n\n- Added typed generation run contracts.",
]);

$step = new GenerationStep([
    'step_id' => 'draft',
    'name' => 'Draft release notes',
    'status' => GenerationStatus::COMPLETED,
    'output_artifact_ids' => [$draft->id()],
    'diagnostics' => [new GenerationDiagnostic([
        'code' => 'human_review_pending',
        'message' => 'The draft is not authorized for publication.',
        'severity' => GenerationDiagnostic::INFO,
    ])],
]);

$result = GenerationRunResult::completed($request, [$draft], [$step], [
    'usage' => ['total_tokens' => 128],
    'metadata' => ['adapter' => 'example'],
]);

print HTTP::jsonEncode($result->toArray()) . PHP_EOL;

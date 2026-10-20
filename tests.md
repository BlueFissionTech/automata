# Test configuration

Run deterministic tests and scenario evidence from the repository root:

```sh
php vendor/phpunit/phpunit/phpunit --do-not-cache-result
php examples/generic/cortex/scenarios.php
php examples/generic/cortex/confidence.php
php examples/generic/cortex/replay.php
php examples/generic/cortex/prerequisites.php
```

Dependencies must already be installed for the selected checkout. The default
scenario suite needs no credentials, network, database or additional service.
See [the experiment rubric](docs/cortex-agent-proof.md) for frozen splits,
negative controls and limitations.

## Optional live Cortex proposals

Supply credentials through the process environment or the application's
existing secret-management mechanism. Never commit keys or print them.

| Variable | Purpose |
| --- | --- |
| `AUTOMATA_CORTEX_LIVE=1` | Explicit opt-in to paid provider requests |
| `OPENAI_API_KEY` | OpenAI credential |
| `GOOGLE_GEMINI_API_KEY` | Gemini credential |
| `POKEE_API_KEY` | Pokee credential |

Run one provider at a time:

```sh
php examples/generic/cortex/live_scenarios.php openai
php examples/generic/cortex/live_scenarios.php gemini
php examples/generic/cortex/live_scenarios.php pokee
```

Without the opt-in, the command emits `UNATTEMPTED` and performs no network
request. Each provider run permits at most eight calls, 384 output tokens per
call, a 45-second transport timeout and no automatic retries. Transport failure
stops that provider's run. Inputs are bounded synthetic fixtures; no external
tools or real effects are enabled. SimpleClients and PHP cURL are required.

Reports distinguish `PASS`, observed `FAIL`, and unavailable `BLOCKED` evidence.
Failed or unavailable live qualification exits nonzero. Missing token usage or
monetary cost remains unknown. Returned HTTP errors are summarized without
provider error text, which could contain credentials. Keys never belong in
reports, command-line arguments, test fixtures or committed files.

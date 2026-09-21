# Operator and agent entrypoints

Run commands from the repository root. PHP examples and the default test suite need existing Composer dependencies. Optional service tests remain opt-in; see tests.md. GitHub and cross-repository operations use the installed scripts/bf-keryx.ps1 wrapper and retain the approval boundaries in AGENTS.md.

| Entrypoint | Purpose and required inputs | Mutation and approval boundary |
| --- | --- | --- |
| `vendor/bin/phpunit --do-not-cache-result [test path]` | Default tests or a focused test directory | Runs repository tests; optional external-service tests require their documented opt-in configuration |
| `php examples/generic/cortex/run.php` | Experience capture, projection and held-out classification proof | Local synthetic computation; JSON evidence on stdout; no provider credentials or operational actions |
| `php examples/generic/cortex/adapt.php` | Apply admitted outcome feedback and demonstrate changed selection | Local synthetic routing; validates policy guards and process-local replay handling |
| `php examples/generic/cortex/respond.php` | Demonstrate progressive response, lost-ack recovery, fallback and cancellation | In-memory simulated receiver; no live effects or durable exactly-once guarantee |
| `php examples/generic/cortex/evaluate.php` | Compare exact candidate versions and reject regressions | Local synthetic computation; recommendations do not install models or authorize actions |
| `powershell -File scripts/bf-keryx.ps1 -Action capabilities -Json` | Discover reviewed wrapper capabilities | Read-only discovery; availability is not permission |
| `powershell -File scripts/bf-keryx.ps1 -Action repo-index-status -RepoPath <repo> -HeadSha <default-head> -Json` | Verify retrieval snapshot against the current default-branch head | Read-only; refresh missing or stale snapshots with repo-index |
| `powershell -File scripts/bf-keryx.ps1 -Action repo-index-search -RepoPath <repo> -Query <terms> -SourceKind <kind> -Json` | Retrieve narrowly scoped source cards | Read-only; retain repository ownership and secret boundaries |
| `powershell -File scripts/bf-keryx.ps1 -Action repo-index-issue -RepoPath <repo> -Number <issue> -Title <title> -Progress in_review -Body <evidence> -Json` | Retain issue progress and evidence | Updates local retrieval metadata; does not close provider issues |
| `powershell -File scripts/bf-keryx.ps1 -Action get-messages -Unread -Json` | Read this repository's coordination inbox | Read-only; verify repository identity before acknowledging messages |
| `powershell -File scripts/bf-keryx.ps1 -Action send-message -Repo <owner> -Body <message> -Json` | Coordinate with another owning repository | Sends a message; requires task authorization; never transfers local write ownership |
| `powershell -File scripts/bf-landing-preflight.ps1 -RepoPath <repo> -Json` | Verify GitHub identity and mutation/landing surface | Read-only prerequisite to Git/GitHub mutations; never emits credential values |
| `powershell -File scripts/bf-keryx.ps1 -Action git-push -RepoPath <repo> -Branch <branch> -SetUpstream -Json` | Publish a reviewed local branch | Remote write; requires authorized scope and passing preflight |
| `powershell -File scripts/bf-keryx.ps1 -Action github-pr-create -RepoPath <repo> -HeadBranch <head> -BaseBranch <base> -Title <title> -BodyFile <markdown> -Json` | Stage a reviewable PR | Remote write; requires authorized scope, explicit branch selection and passing preflight |
| `powershell -File scripts/bf-keryx.ps1 -Action job-get -Number <job> -Wait -Json` | Observe an already submitted async job | Read-only; poll the same job after observation timeouts |
| `powershell -File scripts/bf-keryx.ps1 -Action docker-run -RepoPath <repo> -DockerAction <allowed-action> -BFMode php-lib -Json` | Run an approved service/test recipe when required | Changes service state; no raw Docker; respect leases, conflicts and service approvals |

Dependency installation/upgrades, secrets or .env changes, additional services,
foreign-checkout mutation and PR landing are not authorized by this map. Landing
requires an exact-head reviewed plan and explicit confirmation. Use the Keryx
owner's documented plan/apply actions; never bypass repository protections.

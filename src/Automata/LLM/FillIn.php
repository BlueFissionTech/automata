<?php
namespace BlueFission\Automata\LLM;

use BlueFission\Behavioral\IDispatcher;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Parsing\Parser;
use BlueFission\Parsing\Elements\EvalElement;
use BlueFission\Parsing\Registry\TagRegistry;
use BlueFission\Parsing\Registry\RendererRegistry;
use BlueFission\Parsing\Registry\ExecutorRegistry;
use BlueFission\Parsing\Registry\PreparerRegistry;
use BlueFission\Parsing\Registry\GeneratorRegistry;
use BlueFission\Parsing\Contracts\IRenderableElement;
use BlueFission\Parsing\Contracts\IExecutableElement;
use BlueFission\Parsing\Element;
use BlueFission\Parsing\TagDefinition;
use BlueFission\Automata\Parsing\Elements\PromptElement;
use BlueFission\Automata\Parsing\Generators\LLMGenerator;
use BlueFission\Automata\Parsing\Preparers\LLMPreparer;
use BlueFission\Automata\Parsing\Preparers\ToolPreparer;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\State;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\Data\FileSystem;
use BlueFission\Obj;
use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Val;
use RuntimeException;

class FillIn implements IDispatcher
{
    use Dispatches {
        Dispatches::__construct as private __dispatchConstruct;
    }

    protected $llm;
    protected $template;
    protected $parser;
    protected ?LLMGenerator $generator = null;
    protected array $tools = [];
    protected array $vars = [];
    protected array $includePaths = [];
    protected array $profilePaths = [];
    protected array $profileOverrides = [];
    protected string $output = '';
    /**
     * Optional soft token budget for the rendered prompt, expressed as
     * approximate tokens (using a simple heuristic). This is intended
     * as a guidance-style safeguard and does not perform hard truncation
     * yet; callers can inspect or extend this in the future.
     */
    protected ?int $maxTokens = null;

    public function __construct($llm, string $prompt)
    {
        $this->__dispatchConstruct();

        $this->llm = $llm;
        $this->setPrompt($prompt);
    }

    public function setPrompt(string $prompt): void
    {
        TagRegistry::registerDefaults();
        TagRegistry::register(new TagDefinition(
            name: 'eval',
            pattern: '{open}=(.*?)(?:->(\\w+))?(?:\\s+silent=[\'\"]?(true|false)[\'\"]?)?{close}',
            attributes: ['*'],
            interface: IRenderableElement::class,
            class: PromptElement::class
        ));
        RendererRegistry::registerDefaults();
        ExecutorRegistry::registerDefaults();
        PreparerRegistry::registerDefaults();
        PreparerRegistry::register(new LLMPreparer($this), [EvalElement::class]);

        $this->generator = new LLMGenerator();
        $this->generator->setDriver($this->llm);
        $this->generator->setProfileResolver(fn (string $profile, Element $element): array => $this->resolveProfile($profile, $element));
        GeneratorRegistry::set($this->generator);

        $this->parser = new Parser($prompt);
        $this->generator->registerEchoes($this->parser);
        $this->parser->setVariables($this->vars);
        $this->parser->setIncludePaths($this->includePaths);
        foreach ([Event::STARTED, Event::SENT, Event::ERROR, Event::RECEIVED, Event::COMPLETE] as $behavior) {
            $this->parser->when($behavior, function ($event, $meta = null) use ($behavior) {
                $this->dispatch($behavior, $meta);
            });
        }
        $this->template = $prompt;
    }

    public function setVariables(array $vars): void
    {
        $this->vars = $vars;
        $this->parser->setVariables($vars);
    }

    public function addTool(string $name, $tool): void
    {
        $this->tools[$name] = $tool;
    }

    public function setIncludePaths(array $paths): void
    {
        $this->includePaths = $paths;
        if ($this->parser) {
            $this->parser->setIncludePaths($paths);
        }
    }

    public function setProfilePaths(array $paths): void
    {
        $this->profilePaths = $paths;
    }

    public function registerProfileOverride(string $name, mixed $profile): void
    {
        $this->profileOverrides[$name] = $profile;
    }

    public function registerProfile(string $name, mixed $profile): void
    {
        $this->registerProfileOverride($name, $profile);
    }

    public function getLLM()
    {
        return $this->llm;
    }

    public function generator(): ?LLMGenerator
    {
        return $this->generator;
    }

    public function usageLedger(): array
    {
        return $this->generator ? $this->generator->usageLedger() : [];
    }

    public function usageTotals(): array
    {
        return $this->generator ? $this->generator->usageTotals() : [];
    }

    public function resolveProfile(string $profile, ?Element $element = null): array
    {
        $profile = Str::trim($profile);
        if ($profile === '') {
            throw new RuntimeException('Profile attribute was provided but no profile name or path was given.');
        }

        $definition = $this->profileOverrides[$profile] ?? null;
        if (is_callable($definition)) {
            $definition = $definition($profile, $element, $this);
        }

        $resolved = [
            'name' => $profile,
            'driver' => $this->llm,
            'prompt' => '',
            'path' => null,
        ];

        if ($definition !== null) {
            $resolved = $this->mergeProfileDefinition($resolved, $definition, $profile);
        } else {
            $resolved['path'] = $this->resolveProfilePath($profile);
        }

        if ($resolved['path']) {
            $resolved['prompt'] = $resolved['prompt'] !== ''
                ? $resolved['prompt']
                : $this->readProfileContents($resolved['path']);
        }

        if ($resolved['prompt'] === '' && $resolved['driver'] === $this->llm && $definition === null) {
            throw new RuntimeException("Unable to resolve generation profile '{$profile}'.");
        }

        return $resolved;
    }

    /**
     * Set an approximate soft token budget for the rendered prompt.
     * This does not enforce truncation by default, but downstream
     * LLM drivers or preparers may use it to adjust requests.
     */
    public function setMaxTokens(?int $maxTokens): void
    {
        $this->maxTokens = $maxTokens;
    }

    public function maxTokens(): ?int
    {
        return $this->maxTokens;
    }

    public function run(array $config = []): array
    {
        $this->dispatch(Event::STARTED, new Meta(when: State::RUNNING, src: $this));
        if ($this->generator) {
            $this->generator->resetUsage();
        }

        $this->output = $this->parser->render();

        // TODO: Extend with richer guidance-style token management:
        //  - Estimate tokens with model-specific heuristics.
        //  - Enforce or negotiate budgets with the LLM client.
        //  - Support streaming or chunked execution when prompts exceed limits.

        $this->dispatch(Event::COMPLETE, new Meta(when: State::RUNNING, data: [
            'output' => $this->output,
            'usage' => $this->usageTotals(),
            'output_token_estimate' => $this->estimateTokens($this->output),
        ], src: $this));

        return $this->parser->root()->getAllVariables();
    }

    public function output(): string
    {
        return $this->output;
    }

    protected function mergeProfileDefinition(array $resolved, mixed $definition, string $profile): array
    {
        if (Arr::is($definition)) {
            if (isset($definition['driver']) && Val::is($definition['driver']) && method_exists($definition['driver'], 'generate')) {
                $resolved['driver'] = $definition['driver'];
            }

            if (isset($definition['prompt']) && Val::isNotNull($definition['prompt'])) {
                $resolved['prompt'] = (string)$definition['prompt'];
            }

            if (isset($definition['path']) && Val::isNotNull($definition['path'])) {
                $resolved['path'] = $this->resolveProfilePath((string)$definition['path']);
            }

            return $resolved;
        }

        if (Val::is($definition) && method_exists($definition, 'generate')) {
            $resolved['driver'] = $definition;
            return $resolved;
        }

        if (Str::is($definition)) {
            $resolved['path'] = $this->resolveProfilePath($definition, false);
            if (!$resolved['path']) {
                $resolved['prompt'] = $definition;
            }
            return $resolved;
        }

        throw new RuntimeException("Invalid profile override registered for '{$profile}'.");
    }

    protected function resolveProfilePath(string $profile, bool $failIfMissing = true): ?string
    {
        $candidates = [];
        $profile = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $profile);

        if ($this->isAbsolutePath($profile)) {
            $candidates[] = $profile;
        } else {
            $searchPaths = Arr::merge($this->profilePaths, $this->includePaths);
            foreach ($searchPaths as $base) {
                $base = rtrim((string)$base, '\\/');
                if ($base === '') {
                    continue;
                }
                $candidates[] = $base . DIRECTORY_SEPARATOR . $profile;
            }

            $candidates[] = $profile;
        }

        foreach ($candidates as $candidate) {
            $filesystem = new FileSystem($candidate);
            if ($filesystem->exists($candidate)) {
                return rtrim((string)$filesystem->path(), '\\/') . DIRECTORY_SEPARATOR . basename($candidate);
            }
        }

        if ($failIfMissing) {
            throw new RuntimeException("Unable to resolve profile file '{$profile}'.");
        }

        return null;
    }

    protected function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        return (bool)preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) || Str::pos($path, DIRECTORY_SEPARATOR) === 0;
    }

    protected function readProfileContents(string $path): string
    {
        $filesystem = new FileSystem($path);
        $filesystem->read();

        return (string)$filesystem->contents();
    }

    protected function estimateTokens(string $text): int
    {
        $text = Str::trim($text);
        if ($text === '') {
            return 0;
        }

        preg_match_all('/\S+/u', $text, $matches);
        return (int)Num::max(Arr::size($matches[0] ?? []), (int)ceil(Str::len($text) / 4));
    }
}

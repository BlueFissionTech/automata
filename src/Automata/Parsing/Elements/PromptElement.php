<?php
namespace BlueFission\Automata\Parsing\Elements;

use BlueFission\Parsing\Elements\EvalElement;
use BlueFission\Parsing\Contracts\IRenderableElement;
use BlueFission\Parsing\Contracts\IExecutableElement;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\State;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\Collections\Collection;
use BlueFission\Str;
use BlueFission\DevElation as Dev;
use BlueFission\Val;
use Exception;

class PromptElement extends EvalElement implements IExecutableElement, IRenderableElement
{
    protected $llm;
    protected array $buffer = [];
    protected const ATTRIBUTE_INTERPOLATION_PATTERN = '/(?:\[\[\s*(.*?)\s*\]\]|\{\{\s*(.*?)\s*\}\})/';

    public function setLLM($llm): void
    {
        $llm = Dev::apply('automata.parsing.elements.promptelement.setLLM.1', $llm);
        $this->llm = $llm;
        Dev::do('automata.parsing.elements.promptelement.setLLM.action1', ['element' => $this, 'llm' => $llm]);
    }

    public function getDescription(): string
    {
        $descriptionString = sprintf('Evalute the expression "%s" and generate or recieve a result.', $this->name);

        $this->description = $descriptionString;
        $this->description = Dev::apply('automata.parsing.elements.promptelement.getDescription.1', $this->description);
        Dev::do('automata.parsing.elements.promptelement.getDescription.action1', ['element' => $this, 'description' => $this->description]);

        return $this->description;
    }

    public function getAttribute($name): mixed
    {
        $value = parent::getAttribute($name);

        if (Str::is($value)) {
            $value = $this->interpolateAttributeString((string)$value);
        }

        return $value;
    }

    public function getAttributes(): array
    {
        $attributes = parent::getAttributes();

        foreach ($attributes as $key => $value) {
            if (Str::is($value)) {
                $attributes[$key] = $this->interpolateAttributeString((string)$value);
            }
        }

        return $attributes;
    }

    public function render(): string
    {
        $silent = $this->getAttribute('silent');
        if ($silent === 'true' || $silent === true) {
            return '';
        }

        return (string)$this->value;
    }

    protected function interpolateAttributeString(string $value): string
    {
        if (Str::pos($value, '[[') === false && Str::pos($value, '{{') === false) {
            return $value;
        }

        $interpolated = preg_replace_callback(
            self::ATTRIBUTE_INTERPOLATION_PATTERN,
            function (array $matches): string {
                $expression = Str::trim((string)(($matches[1] ?? '') !== '' ? $matches[1] : ($matches[2] ?? '')));
                if ($expression === '') {
                    return '';
                }

                return $this->resolveInterpolationExpression($expression);
            },
            $value
        );

        $interpolated = Dev::apply('automata.parsing.elements.promptelement.interpolate_attribute', $interpolated);
        Dev::do('automata.parsing.elements.promptelement.interpolate_attribute.action1', [
            'element' => $this,
            'value' => $value,
            'interpolated' => $interpolated,
        ]);

        return (string)$interpolated;
    }

    protected function resolveInterpolationExpression(string $expression): string
    {
        $segments = preg_split('/\|/', $expression) ?: [];
        $base = Str::trim((string)array_shift($segments));
        $value = $this->resolveInterpolationValue($base);

        foreach ($segments as $segment) {
            $segment = Str::trim((string)$segment);
            if ($segment === '') {
                continue;
            }

            [$filter, $arguments] = $this->parseInterpolationFilter($segment);
            $value = $this->applyInterpolationFilter($value, $filter, $arguments);
        }

        return $this->stringifyInterpolatedValue($value);
    }

    protected function resolveInterpolationValue(string $expression): mixed
    {
        if ($expression === '') {
            return '';
        }

        if (preg_match('/^(["\']).*\\1$/', $expression)) {
            return trim($expression, '\'"');
        }

        return parent::resolveValue($expression);
    }

    protected function parseInterpolationFilter(string $segment): array
    {
        $parts = explode(':', $segment);
        $filter = Str::lower(Str::trim((string)array_shift($parts)));
        $arguments = array_map(fn ($argument) => Str::trim((string)$argument), $parts);

        return [$filter, $arguments];
    }

    protected function applyInterpolationFilter(mixed $value, string $filter, array $arguments = []): mixed
    {
        return match ($filter) {
            'slug', 'slugify' => Str::slugify($this->stringifyInterpolatedValue($value)),
            'lower' => Str::lower($this->stringifyInterpolatedValue($value)),
            'upper' => Str::upper($this->stringifyInterpolatedValue($value)),
            'trim' => Str::trim($this->stringifyInterpolatedValue($value)),
            'pad' => $this->applyPadFilter($value, $arguments),
            'default' => $this->applyDefaultFilter($value, $arguments),
            default => $value,
        };
    }

    protected function applyPadFilter(mixed $value, array $arguments = []): string
    {
        $string = $this->stringifyInterpolatedValue($value);
        $length = isset($arguments[0]) && is_numeric($arguments[0]) ? (int)$arguments[0] : 2;
        $pad = isset($arguments[1]) && $arguments[1] !== '' ? $arguments[1] : '0';
        $direction = isset($arguments[2]) && Str::lower($arguments[2]) === 'right' ? STR_PAD_RIGHT : STR_PAD_LEFT;

        return str_pad($string, $length, $pad, $direction);
    }

    protected function applyDefaultFilter(mixed $value, array $arguments = []): mixed
    {
        $default = $arguments[0] ?? '';
        if ($value === null) {
            return $default;
        }

        if (Str::is($value) && Str::trim((string)$value) === '') {
            return $default;
        }

        if (Val::is($value) && !Str::is($value) && !$value) {
            return $default;
        }

        return $value;
    }

    protected function stringifyInterpolatedValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        if (is_array($value)) {
            return implode(',', array_map(fn ($item) => $this->stringifyInterpolatedValue($item), $value));
        }

        return (string)$value;
    }
}

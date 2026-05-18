<?php

namespace BlueFission\Automata\LLM\Agent\Security;

class LpciTaxonomy
{
    public const S1_RECONNAISSANCE = 'reconnaissance';
    public const S2_LOGIC_LAYER_INJECTION = 'logic_layer_injection';
    public const S3_TRIGGER_EXECUTION = 'trigger_execution';
    public const S4_PERSISTENCE_REUSE = 'persistence_reuse';
    public const S5_EVASION_OBFUSCATION = 'evasion_obfuscation';
    public const S6_TRACE_TAMPERING = 'trace_tampering';

    public const CATEGORY_ENCODING = 'encoding';
    public const CATEGORY_SEMANTIC_REFRAME = 'semantic_reframe';
    public const CATEGORY_LAYERED = 'layered_obfuscation';
    public const CATEGORY_TRIGGER = 'conditional_trigger';
    public const CATEGORY_EXFILTRATION = 'exfiltration_reframe';
    public const CATEGORY_TRACE = 'trace_tamper';

    /**
     * Return the supported LPCI lifecycle stages.
     */
    public static function stages(): array
    {
        return [
            self::S1_RECONNAISSANCE,
            self::S2_LOGIC_LAYER_INJECTION,
            self::S3_TRIGGER_EXECUTION,
            self::S4_PERSISTENCE_REUSE,
            self::S5_EVASION_OBFUSCATION,
            self::S6_TRACE_TAMPERING,
        ];
    }
}

<?php

namespace BlueFission\Automata\Language\Claims;

interface IClaimNormalizer
{
    public function normalize(ClaimEnvelope $envelope): ClaimNormalizationResult;
}

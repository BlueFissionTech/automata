<?php
namespace BlueFission\Framework\Chat;

interface ITool {
    public function execute($input): string;
    public function name(): string;
    public function description(): string;
}
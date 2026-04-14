<?php

namespace BlueFission\Automata\Media\Processing\Image;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\Support\TempFile;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;
use BlueFission\System\CommandLocator;

class TesseractOcrHandler extends Obj
{
    protected ?string $_binary;
    protected string $_lang;
    protected ?int $_psm;

    public function __construct(?string $binary = null, string $lang = 'eng', ?int $psm = null)
    {
        parent::__construct();
        $this->_binary = $binary;
        $this->_lang = $lang;
        $this->_psm = $psm;
    }

    public function isAvailable(): bool
    {
        return $this->resolveBinary() !== null;
    }

    public function __invoke(MediaItem $item, Context $context, array $options = []): ?string
    {
        $binary = $this->resolveBinary($options['binary'] ?? $this->_binary);
        if (!$binary) {
            return null;
        }

        $inputPath = $this->resolveInputPath($item);
        if (!$inputPath) {
            return null;
        }

        $lang = $options['lang'] ?? $this->_lang;
        $psm = $options['psm'] ?? $this->_psm;
        $text = $this->runTesseract($binary, $inputPath, $lang, $psm);

        if ($inputPath !== $item->path()) {
            TempFile::cleanup($inputPath);
        }

        return $text;
    }

    protected function resolveBinary(?string $binary = null): ?string
    {
        $binary = $binary ?: 'tesseract';

        if (is_file($binary)) {
            return $binary;
        }

        return CommandLocator::find($binary);
    }

    protected function resolveInputPath(MediaItem $item): ?string
    {
        $path = $item->path();
        if ($path && is_file($path)) {
            return $path;
        }

        $content = $item->content();
        if (!is_string($content) || $content === '') {
            return null;
        }

        $extension = $this->extensionFromMime($item->meta()['mime'] ?? null) ?? 'png';
        return TempFile::createFromContent($content, $extension);
    }

    protected function extensionFromMime(?string $mime): ?string
    {
        if (!$mime) {
            return null;
        }

        $map = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        return $map[$mime] ?? null;
    }

    protected function runTesseract(string $binary, string $inputPath, string $lang, ?int $psm): ?string
    {
        $command = escapeshellarg($binary)
            . ' ' . escapeshellarg($inputPath)
            . ' stdout'
            . ' -l ' . escapeshellarg($lang);

        if ($psm !== null) {
            $command .= ' --psm ' . (int)$psm;
        }

        $output = [];
        $status = 0;
        @exec($command . ' 2>&1', $output, $status);

        Dev::do('media.processing.image.tesseract', ['status' => $status, 'input' => $inputPath]);

        if ($status !== 0) {
            return null;
        }

        $text = trim(implode(PHP_EOL, $output));
        return $text !== '' ? $text : null;
    }
}

<?php

namespace BlueFission\Automata\Media\Processing\Audio;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\Support\TempFile;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;
use BlueFission\System\CommandLocator;

class WhisperCliHandler extends Obj
{
    protected ?string $_binary;
    protected string $_model;
    protected string $_language;

    public function __construct(?string $binary = null, string $model = 'base', string $language = 'en')
    {
        parent::__construct();
        $this->_binary = $binary;
        $this->_model = $model;
        $this->_language = $language;
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

        $model = $options['model'] ?? $this->_model;
        $language = $options['language'] ?? $this->_language;
        $result = $this->runWhisper($binary, $inputPath, $model, $language, $options);

        if ($inputPath !== $item->path()) {
            TempFile::cleanup($inputPath);
        }

        return $result;
    }

    protected function resolveBinary(?string $binary = null): ?string
    {
        $binary = $binary ?: 'whisper';

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

        $extension = $this->extensionFromMime($item->meta()['mime'] ?? null) ?? 'wav';
        return TempFile::createFromContent($content, $extension);
    }

    protected function extensionFromMime(?string $mime): ?string
    {
        if (!$mime) {
            return null;
        }

        $map = [
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/flac' => 'flac',
        ];

        return $map[$mime] ?? null;
    }

    protected function runWhisper(string $binary, string $inputPath, string $model, string $language, array $options): ?string
    {
        $outputDir = $options['output_dir'] ?? $this->createOutputDir();
        if (!$outputDir) {
            return null;
        }

        $command = escapeshellarg($binary)
            . ' ' . escapeshellarg($inputPath)
            . ' --model ' . escapeshellarg($model)
            . ' --language ' . escapeshellarg($language)
            . ' --output_format txt'
            . ' --output_dir ' . escapeshellarg($outputDir);

        $output = [];
        $status = 0;
        @exec($command . ' 2>&1', $output, $status);

        Dev::do('media.processing.audio.whisper', ['status' => $status, 'input' => $inputPath]);

        if ($status !== 0) {
            $this->cleanupOutput($outputDir, $options);
            return null;
        }

        $text = $this->readOutputText($outputDir);
        $this->cleanupOutput($outputDir, $options);

        return $text;
    }

    protected function createOutputDir(): ?string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'automata_whisper_' . uniqid();
        if (!@mkdir($path, 0777, true)) {
            return null;
        }

        return $path;
    }

    protected function readOutputText(string $outputDir): ?string
    {
        $files = glob($outputDir . DIRECTORY_SEPARATOR . '*.txt');
        if (!$files) {
            return null;
        }

        $text = @file_get_contents($files[0]);
        if ($text === false) {
            return null;
        }

        $text = trim($text);
        return $text !== '' ? $text : null;
    }

    protected function cleanupOutput(string $outputDir, array $options): void
    {
        if (!empty($options['keep_output'])) {
            return;
        }

        if (is_dir($outputDir)) {
            $files = glob($outputDir . DIRECTORY_SEPARATOR . '*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
            @rmdir($outputDir);
        }
    }
}

<?php

namespace BlueFission\Automata\Media\Processing\Video;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\Support\TempFile;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;
use BlueFission\System\CommandLocator;

class FfmpegFrameExtractor extends Obj
{
    protected ?string $_binary;
    protected int $_fps;

    public function __construct(?string $binary = null, int $fps = 1)
    {
        parent::__construct();
        $this->_binary = $binary;
        $this->_fps = $fps;
    }

    public function isAvailable(): bool
    {
        return $this->resolveBinary() !== null;
    }

    public function __invoke(MediaItem $item, Context $context, array $options = []): ?array
    {
        $binary = $this->resolveBinary($options['binary'] ?? $this->_binary);
        if (!$binary) {
            return null;
        }

        $inputPath = $this->resolveInputPath($item);
        if (!$inputPath) {
            return null;
        }

        $fps = $options['fps'] ?? $this->_fps;
        $limit = isset($options['limit']) ? (int)$options['limit'] : null;

        $frames = $this->runFfmpeg($binary, $inputPath, $fps, $limit, $options);

        if ($inputPath !== $item->path()) {
            TempFile::cleanup($inputPath);
        }

        return $frames;
    }

    protected function resolveBinary(?string $binary = null): ?string
    {
        $binary = $binary ?: 'ffmpeg';

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

        $extension = $this->extensionFromMime($item->meta()['mime'] ?? null) ?? 'mp4';
        return TempFile::createFromContent($content, $extension);
    }

    protected function extensionFromMime(?string $mime): ?string
    {
        if (!$mime) {
            return null;
        }

        $map = [
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'video/webm' => 'webm',
        ];

        return $map[$mime] ?? null;
    }

    protected function runFfmpeg(string $binary, string $inputPath, int $fps, ?int $limit, array $options): ?array
    {
        $outputDir = $options['output_dir'] ?? $this->createOutputDir();
        if (!$outputDir) {
            return null;
        }

        $pattern = $outputDir . DIRECTORY_SEPARATOR . 'frame_%04d.png';
        $command = escapeshellarg($binary)
            . ' -i ' . escapeshellarg($inputPath)
            . ' -vf fps=' . max(1, $fps)
            . ' ' . escapeshellarg($pattern);

        if ($limit !== null && $limit > 0) {
            $command = escapeshellarg($binary)
                . ' -i ' . escapeshellarg($inputPath)
                . ' -vf fps=' . max(1, $fps)
                . ' -vframes ' . $limit
                . ' ' . escapeshellarg($pattern);
        }

        $output = [];
        $status = 0;
        @exec($command . ' 2>&1', $output, $status);

        Dev::do('media.processing.video.ffmpeg', ['status' => $status, 'input' => $inputPath]);

        $frames = [];
        if ($status === 0) {
            $files = glob($outputDir . DIRECTORY_SEPARATOR . 'frame_*.png');
            if (is_array($files)) {
                foreach ($files as $index => $file) {
                    $frames[] = ['index' => $index, 'path' => $file];
                }
            }
        }

        if (empty($options['keep_output'])) {
            $this->cleanupOutput($outputDir);
        }

        return $frames ?: null;
    }

    protected function createOutputDir(): ?string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'automata_ffmpeg_' . uniqid();
        if (!@mkdir($path, 0777, true)) {
            return null;
        }

        return $path;
    }

    protected function cleanupOutput(string $outputDir): void
    {
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

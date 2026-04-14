# Media Utilities Specification

## Purpose
Provide a lightweight, consistent media ingestion and processing toolkit for Automata that can:
- Accept text, images, audio, video, documents, and URLs as file paths, stream handles, or raw strings/bytes.
- Normalize, parse, and extract features/entities without adding new required dependencies.
- Feed outputs into Intelligence/Engine, Sensory Input, and Context with a predictable, structured payload.
- Allow advanced capabilities (OCR, speech recognition, frame extraction) via optional callables or external engines.

## Core Concepts

### MediaItem
A normalized representation of an input asset with:
- type (InputType::TEXT, IMAGE, AUDIO, VIDEO, DOCUMENT, URL)
- content (string/bytes if loaded)
- path (if file-based)
- stream (resource or Stream wrapper)
- meta (mime, size, dimensions, duration, encoding, etc)
- context (Context instance for tags, normalization info)
- features (optional computed features)

### Stream
A lightweight wrapper for stream resources to provide:
- read(), rewind(), meta(), and length() accessors
- a consistent interface for file handles, resources, or SplFileObject

### Ingestion
Raw intake, opening, and normalization of input.
- IIngestor interface with supports(...) and ingest(...)
- Ingestion Gateway to register and select ingestors by type or profile
- Default ingestors: Text, Image, Audio, Video, Document, URL

### Processing
Pipelines that use analysis, normalization, and learning utilities.
- Pipeline class with ordered processors
- IProcessor interface and optional ITrainable interface
- Processing Gateway to select pipelines by type
- Result object containing tokens, entities, features, segments, and meta

### Result
Structured processing output with:
- tokens, entities, features, and metrics
- segments array compatible with Intelligence::analyze
- helpers to convert to engine-ready segments and context metadata

## Ingestion Responsibilities

### Input types
- Text: raw string or text file
- Image/Audio/Video/Document: file path, bytes, or stream
- URL: raw URL (no fetch by default)

### Metadata extraction
- MIME type via finfo
- File size via filesize
- Image dimensions via getimagesize (if available)
- Stream meta via stream_get_meta_data

### Options
- load_content (bool): read bytes into content; default true for text, false for large binaries
- max_bytes (int): cap on content read
- force_type (string): override InputTypeDetector
- context (Context|array): inject base context

## Processing Pipelines

### Text Pipeline
Processors:
- Normalizer: strip tags, lower-case, normalize quotes, optional contraction normalization
- Tokenizer: Language\Preparer tokenize
- Entity extraction: Language\EntityExtractor (email, url, dates, etc)
- Bag of words: token counts
- Heuristics: length, sentence count, token count

Outputs:
- normalized_text, tokens, bag_of_words, entities, heuristics
- segments with payload set to normalized text + meta

### Image Pipeline
Processors:
- Metadata: width, height, aspect ratio
- OCR (hook/callable)
- Boundary detection (hook/callable)
- Convolution features (hook/callable)

Outputs:
- entities (optional), boundaries (optional), feature vectors

### Audio Pipeline
Processors:
- Metadata (mime, size)
- Volume normalization (hook/callable)
- Speech-to-text (hook/callable)
- Event classification: speech/music/sfx (hook/callable)

Outputs:
- transcript, events, speaker signatures (optional), normalized levels

### Video Pipeline
Processors:
- Frame extraction (hook/callable)
- Frame entities (hook/callable)
- Timeline convergence (hook/callable)

Outputs:
- frames, timeline entities, aggregated features

## Handler Adapters (Optional CLI)

Media processors accept callables via the `options` array. The library includes
lightweight CLI handler adapters that only run if the external binaries are
installed:

- `Image\TesseractOcrHandler` (requires `tesseract`)
- `Audio\WhisperCliHandler` (requires `whisper` CLI)
- `Video\FfmpegFrameExtractor` (requires `ffmpeg`)
- `Image\SimpleClientsOcrHandler` (uses SimpleClients Vision\OcrClient if available)
- `Audio\SimpleClientsTranscriptionHandler` (uses SimpleClients Speech\TranscriptionClient if available)
- `Video\SimpleClientsVideoAnalysisHandler` (uses SimpleClients Video\AnalysisClient if available)

Example wiring:

```php
use BlueFission\Automata\Media\Processing\Image\TesseractOcrHandler;
use BlueFission\Automata\Media\Processing\Image\SimpleClientsOcrHandler;
use BlueFission\Automata\Media\Processing\Audio\WhisperCliHandler;
use BlueFission\Automata\Media\Processing\Video\FfmpegFrameExtractor;

$ocr = new TesseractOcrHandler();
$frames = new FfmpegFrameExtractor();

$imageResult = $imagePipeline->process($imageItem, null, [
    'ocr' => $ocr,
]);

$videoResult = $videoPipeline->process($videoItem, null, [
    'frame_extractor' => $frames,
    'fps' => 2,
]);

$audioResult = $audioPipeline->process($audioItem, null, [
    'speech_to_text' => new Audio\\WhisperCliHandler(),
]);

$imageResult = $imagePipeline->process($imageItem, null, [
    'ocr' => new Image\\SimpleClientsOcrHandler(),
]);
```

Handler signatures are:

```
function(MediaItem $item, Context $context, array $options = []): mixed
```

Handlers should return:

- OCR: string or null
- Frame extractor: array of frame descriptors
- Audio transcriber: string or null
- Boundary/convolution: array of numeric features

### Handler Registry

You can register handlers once and let processors resolve them by capability:

```php
use BlueFission\Automata\Media\Processing\HandlerRegistry;
use BlueFission\Automata\Media\Processing\Image\TesseractOcrHandler;

$registry = new HandlerRegistry();
$registry->register('ocr', new TesseractOcrHandler(), 'tesseract', 10);

$imageResult = $imagePipeline->process($imageItem, null, [
    'handler_registry' => $registry,
]);
```

Pipelines and gateways can also store a default registry:

```php
$pipeline->setRegistry($registry);
$processingGateway->setRegistry($registry);
```

Capabilities used by default processors:
- `ocr`
- `boundary_detector`
- `convolution`
- `volume_normalizer`
- `speech_to_text`
- `audio_event_detector`
- `frame_extractor`
- `timeline_analyzer`

## Integration Notes

### Intelligence and Engine
- Result::segments() produces input compatible with Intelligence::analyze
- Use type-specific pipelines to enrich Context and meta, then pass to Engine/Intelligence

### Sensory Input
- MediaItem->context() can be passed through Sensory Input processors
- Pipelines can emit Context tags (type, confidence, normalization info)

## Extensibility
- Register custom ingestors/pipelines via Gateways
- Use DevElation hooks to inject external OCR/ASR/video tools
- Optional adapters for PHP-ML or external engines (future roadmap)

## Proposed Refactors / Additions
- InputTypeDetector: support stream resources or accept a custom detector callable
- Sensory Input: accept MediaItem payloads directly
- Additional Normalization utilities for text and media-specific scaling
- Optional wrappers for external OCR/ASR tools (no required deps)

## Example Flow
1) Ingestion Gateway selects a TextIngestor for a file path.
2) TextPipeline normalizes, tokenizes, extracts entities, and builds features.
3) Result segments are passed to Intelligence::analyze.

## Roadmap Notes
- Pluggable adapters for OCR/ASR/video engines.
- Optional ML backend abstraction (Rubix/Python) as a broader ML decoupling effort.

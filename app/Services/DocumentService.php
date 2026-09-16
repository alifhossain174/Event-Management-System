<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class DocumentService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly StatusTransitionService $statuses,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<Model>  $linkables
     */
    public function create(array $attributes, UploadedFile $file, array $linkables, User $actor): Document
    {
        $metadata = $this->validateAndDescribe($file);
        $path = $this->store($file, $metadata['extension'], $metadata['disk']);

        try {
            return DB::transaction(function () use ($attributes, $metadata, $path, $linkables, $actor) {
                $document = Document::query()->create([
                    'document_category_id' => $attributes['document_category_id'] ?? null,
                    'branch_id' => $attributes['branch_id'] ?? null,
                    'title' => $attributes['title'],
                    'description' => $attributes['description'] ?? null,
                    'expiry_date' => $attributes['expiry_date'] ?? null,
                    'status' => 'active',
                    'uploaded_by_user_id' => $actor->getKey(),
                ]);

                $version = $this->createVersion($document, 1, $path, $metadata, $attributes['version_notes'] ?? null, $actor);
                $document->update(['current_version_id' => $version->getKey()]);

                foreach ($linkables as $linkable) {
                    $this->attach($document, $linkable, $actor);
                }

                $this->audit->record('document.created', $document, [], [
                    'title' => $document->title,
                    'category_id' => $document->document_category_id,
                    'expiry_date' => $document->expiry_date?->toDateString(),
                    'version' => 1,
                    'mime_type' => $metadata['mime_type'],
                    'size_bytes' => $metadata['size_bytes'],
                ], $actor);

                return $document->load(['currentVersion', 'links']);
            });
        } catch (Throwable $exception) {
            Storage::disk($metadata['disk'])->delete($path);
            throw $exception;
        }
    }

    public function replace(Document $document, UploadedFile $file, ?string $notes, User $actor): DocumentVersion
    {
        if ($document->status !== 'active') {
            throw ValidationException::withMessages(['file' => 'Archived documents cannot receive new versions.']);
        }

        $metadata = $this->validateAndDescribe($file);
        $path = $this->store($file, $metadata['extension'], $metadata['disk']);

        try {
            return DB::transaction(function () use ($document, $path, $metadata, $notes, $actor) {
                $locked = Document::query()->lockForUpdate()->findOrFail($document->getKey());
                $next = ((int) $locked->versions()->max('version_number')) + 1;
                $version = $this->createVersion($locked, $next, $path, $metadata, $notes, $actor);
                $previousVersionId = $locked->current_version_id;
                $locked->update(['current_version_id' => $version->getKey()]);

                $this->audit->record('document.version_created', $locked,
                    ['current_version_id' => $previousVersionId],
                    ['current_version_id' => $version->getKey(), 'version' => $next, 'notes' => $notes],
                    $actor,
                );

                return $version;
            });
        } catch (Throwable $exception) {
            Storage::disk($metadata['disk'])->delete($path);
            throw $exception;
        }
    }

    public function attach(Document $document, Model $linkable, User $actor, string $relationship = 'attachment'): DocumentLink
    {
        $allowed = array_values(config('documents.linkable_types', []));

        if (! in_array($linkable::class, $allowed, true)) {
            throw ValidationException::withMessages(['context' => 'This record type cannot receive documents.']);
        }

        return DocumentLink::query()->firstOrCreate([
            'document_id' => $document->getKey(),
            'linkable_type' => $linkable->getMorphClass(),
            'linkable_id' => $linkable->getKey(),
        ], [
            'relationship' => $relationship,
            'created_by_user_id' => $actor->getKey(),
            'created_at' => now(),
        ]);
    }

    public function archive(Document $document, User $actor, ?string $reason = null): void
    {
        if ($document->status === 'archived') {
            return;
        }

        $this->statuses->transition($document, 'archived', $actor, $reason);
    }

    /** @return array{disk: string, extension: string, mime_type: string, size_bytes: int, original_name: string, sha256: string} */
    private function validateAndDescribe(UploadedFile $file): array
    {
        $disk = (string) config('documents.disk', 'local');
        $allowedDisks = config('documents.allowed_disks', ['local']);
        $extension = mb_strtolower($file->getClientOriginalExtension());
        $mimeType = (string) ($file->getMimeType() ?: $file->getClientMimeType());
        $size = (int) $file->getSize();
        $allowedTypes = config("documents.extensions.{$extension}");
        $maxBytes = ((int) config('documents.max_kilobytes', 25600)) * 1024;

        if (! $file->isValid() || ! in_array($disk, $allowedDisks, true)) {
            throw ValidationException::withMessages(['file' => 'The document could not be accepted by protected storage.']);
        }

        if (! is_array($allowedTypes) || ! in_array($mimeType, $allowedTypes, true) || $size < 1 || $size > $maxBytes) {
            throw ValidationException::withMessages(['file' => 'The file type, extension, or size is not allowed.']);
        }

        return [
            'disk' => $disk,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size_bytes' => $size,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
            'sha256' => hash_file('sha256', $file->getRealPath()),
        ];
    }

    private function store(UploadedFile $file, string $extension, string $disk): string
    {
        $directory = 'documents/'.now()->format('Y/m');
        $path = Storage::disk($disk)->putFileAs($directory, $file, Str::uuid().'.'.$extension);

        if (! is_string($path) || $path === '' || str_contains($path, '..')) {
            throw ValidationException::withMessages(['file' => 'The file could not be stored safely.']);
        }

        return $path;
    }

    /** @param array{disk: string, extension: string, mime_type: string, size_bytes: int, original_name: string, sha256: string} $metadata */
    private function createVersion(Document $document, int $number, string $path, array $metadata, ?string $notes, User $actor): DocumentVersion
    {
        return $document->versions()->create([
            'version_number' => $number,
            'disk' => $metadata['disk'],
            'path' => $path,
            'original_name' => $metadata['original_name'],
            'extension' => $metadata['extension'],
            'mime_type' => $metadata['mime_type'],
            'size_bytes' => $metadata['size_bytes'],
            'sha256' => $metadata['sha256'],
            'notes' => $notes,
            'uploaded_by_user_id' => $actor->getKey(),
            'created_at' => now(),
        ]);
    }
}

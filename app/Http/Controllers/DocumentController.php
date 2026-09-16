<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\ArchiveDocumentRequest;
use App\Http\Requests\Documents\ReplaceDocumentRequest;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Services\DocumentAccessService;
use App\Services\DocumentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentController extends Controller
{
    public function index(Request $request, DocumentAccessService $access): View
    {
        Gate::authorize('viewAny', Document::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'integer', 'exists:document_categories,id'],
            'status' => ['nullable', 'in:active,archived'],
            'expiry' => ['nullable', 'in:upcoming,expired,none'],
        ]);

        $query = $access->scopeVisibleTo(Document::query(), $request->user());
        $documents = $query->with(['category', 'currentVersion', 'uploadedBy'])
            ->search($filters['q'] ?? null)
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('document_category_id', $category))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(($filters['expiry'] ?? null) === 'upcoming', fn ($query) => $query->whereBetween('expiry_date', [today(), today()->addDays(30)]))
            ->when(($filters['expiry'] ?? null) === 'expired', fn ($query) => $query->whereDate('expiry_date', '<', today()))
            ->when(($filters['expiry'] ?? null) === 'none', fn ($query) => $query->whereNull('expiry_date'))
            ->orderByDesc('created_at')->paginate(20)->withQueryString();
        $categories = DocumentCategory::query()->where('is_active', true)->orderBy('name')->get();

        return view('documents.index', compact('documents', 'categories', 'filters'));
    }

    public function create(): View
    {
        Gate::authorize('create', Document::class);
        $categories = DocumentCategory::query()->where('is_active', true)->orderBy('name')->get();

        return view('documents.create', compact('categories'));
    }

    public function store(StoreDocumentRequest $request, DocumentService $documents): RedirectResponse
    {
        $company = Company::query()->firstOrFail();
        $document = $documents->create($request->safe()->except('file'), $request->file('file'), [$company], $request->user());

        return redirect()->route('documents.show', $document)->with('status', 'Document uploaded securely.');
    }

    public function show(Document $document): View
    {
        Gate::authorize('view', $document);

        return view('documents.show', ['document' => $document->load(['category', 'versions.uploadedBy', 'links.linkable', 'statusHistory.actor'])]);
    }

    public function replace(ReplaceDocumentRequest $request, Document $document, DocumentService $documents): RedirectResponse
    {
        $documents->replace($document, $request->file('file'), $request->string('version_notes')->toString(), $request->user());

        return back()->with('status', 'A new document version was stored.');
    }

    public function download(Document $document): StreamedResponse
    {
        Gate::authorize('download', $document);
        $version = $document->currentVersion()->firstOrFail();

        return $this->downloadVersionResponse($document, $version);
    }

    public function downloadVersion(Document $document, DocumentVersion $version): StreamedResponse
    {
        Gate::authorize('download', $document);
        abort_unless($version->document_id === $document->getKey(), 404);

        return $this->downloadVersionResponse($document, $version);
    }

    public function destroy(ArchiveDocumentRequest $request, Document $document, DocumentService $documents): RedirectResponse
    {
        $documents->archive($document, $request->user(), $request->string('reason')->toString() ?: null);

        return redirect()->route('documents.index')->with('status', 'Document archived; all versions remain protected.');
    }

    private function downloadVersionResponse(Document $document, DocumentVersion $version): StreamedResponse
    {
        abort_unless(in_array($version->disk, config('documents.allowed_disks', []), true), 404);
        abort_unless(Storage::disk($version->disk)->exists($version->path), 404);

        return Storage::disk($version->disk)->download($version->path, $version->original_name, [
            'Content-Type' => $version->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

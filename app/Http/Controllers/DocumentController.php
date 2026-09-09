<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use App\Models\HrTask;
use App\Models\User;
use App\Services\DocumentStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $filters = $this->filters($request);
        $rows = $this->rows();
        if ($request->expectsJson()) {
            return response()->json(['rows' => $rows, 'today' => today()->toDateString()]);
        }

        return view('documents.index', [
            'rows' => $rows, 'filters' => $filters, 'routePrefix' => $this->routePrefix(), 'selfService' => $this->selfService(), 'defaultCategory' => $this->defaultCategory(),
            'employees' => User::role('employee')->when($this->selfService(), fn ($query) => $query->whereKey($request->user()->id))->orderBy('name')->get(['id', 'name']),
            'categories' => Document::CATEGORIES, 'statuses' => Document::STATUSES,
            'classifications' => Document::CLASSIFICATIONS, 'retentionCategories' => Document::RETENTION_CATEGORIES,
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route($this->routePrefix().'.index', ['new' => 1, 'employee' => $this->filters($request)['employee']]);
    }

    public function store(StoreDocumentRequest $request): JsonResponse|RedirectResponse
    {
        $document = app(DocumentStorage::class)->create($request->safe()->except(['attachment']), $request->user(), $request->file('attachment'), $request->routeIs('admin.contracts.*') ? 'contracts' : 'documents');

        $message = $document->title.' has been added to the library.';

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'id' => $document->id], 201)
            : redirect()->route($this->routePrefix().'.index', ['category' => $document->category])
                ->with('success', $message)->with('toast_title', 'Document saved');
    }

    public function download(Document $document): StreamedResponse
    {
        abort_unless(Document::visibleTo(request()->user(), $this->selfService())->whereKey($document->id)->exists(), 404);
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name, [
            'Content-Type' => $document->file_mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $sort = $request->validate(['sort' => ['nullable', 'integer', 'between:0,7'], 'direction' => ['nullable', Rule::in(['asc', 'desc'])]]);
        $rows = $this->rows()->filter(function ($row) use ($filters) {
            foreach (['category', 'status', 'employee'] as $key) {
                if ($filters[$key] !== 'all' && (string) $row[$key === 'employee' ? 'employee_id' : $key] !== $filters[$key]) {
                    return false;
                }
            }
            $haystack = mb_strtolower(implode(' ', [$row['title'], $row['category'], $row['employee'], $row['issue_date'], $row['expiry_date'], $row['status'], $row['access_classification'], $row['uploaded_by']]));

            return $filters['search'] === '' || str_contains($haystack, mb_strtolower($filters['search']));
        });
        $columns = ['title', 'category', 'employee', 'issue_date', 'expiry_date', 'status', 'access_classification', 'uploaded_by'];
        $column = $columns[(int) ($sort['sort'] ?? 4)];
        $rows = $rows->sortBy(fn ($row) => mb_strtolower((string) ($row[$column] ?? ($column === 'expiry_date' ? '9999-12-31' : ''))), SORT_NATURAL, ($sort['direction'] ?? 'asc') === 'desc');

        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Title', 'Category', 'Employee', 'Issue Date', 'Expiry Date', 'Status', 'Classification'], ',', '"', '');
            foreach ($rows as $row) {
                $values = [$row['title'], $row['category'], $row['employee'], $row['issue_date'], $row['expiry_date'], $row['status'], $row['access_classification']];
                fputcsv($stream, array_map(function ($value) {
                    $value = (string) ($value ?? '');

                    return preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value;
                }, $values), ',', '"', '');
            }
            fclose($stream);
        }, 'documents.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function filters(Request $request): array
    {
        $data = $request->validate([
            'category' => ['nullable', Rule::in(['all', ...Document::CATEGORIES])],
            'status' => ['nullable', Rule::in(['all', ...Document::STATUSES])],
            'employee' => ['nullable', 'regex:/^(all|[1-9][0-9]*)$/'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        return ['category' => $data['category'] ?? $this->defaultCategory(), 'status' => $data['status'] ?? 'all', 'employee' => $this->selfService() ? (string) $request->user()->id : (string) ($data['employee'] ?? 'all'), 'search' => $data['search'] ?? ''];
    }

    protected function rows(): Collection
    {
        return Document::visibleTo(request()->user(), $this->selfService())->with(['employee', 'uploader'])->orderBy('id')->get()->map(fn ($doc) => [
            'id' => $doc->id, 'title' => $doc->title, 'category' => $doc->category,
            'employee_id' => $doc->employee_id, 'employee' => $doc->employee?->name ?? $doc->employee_name ?? 'Company-wide',
            'issue_date' => $doc->issue_date?->toDateString(), 'expiry_date' => $doc->expiry_date?->toDateString(),
            'status' => $doc->displayStatus(), 'access_classification' => $doc->access_classification,
            'uploaded_by' => $doc->uploader?->name ?? $doc->uploader_name,
            'download_url' => $doc->file_path ? route($this->routePrefix().'.download', $doc) : null,
        ]);
    }

    protected function routePrefix(): string
    {
        return $this->selfService() ? 'employee.documents' : 'admin.documents';
    }

    protected function defaultCategory(): string
    {
        return 'all';
    }

    protected function selfService(): bool
    {
        return request()->routeIs('employee.*');
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:255']]);
        $needle = mb_strtolower($data['q']);
        $documents = Document::visibleTo($request->user(), $this->selfService())->orderBy('title')->get(['id', 'title'])
            ->filter(fn ($document) => str_contains(mb_strtolower($document->title), $needle))->take(4)
            ->map(fn ($document) => ['title' => $document->title, 'url' => route($this->routePrefix().'.index', ['highlight' => $document->id])])->values();
        $payload = ['documents' => $documents];
        if ($request->user()->hasRole('admin') && ! $this->selfService()) {
            $payload['employees'] = User::role('employee')->where('status', '!=', 'Left')->orderBy('name')->get(['id', 'name', 'employee_number'])
                ->filter(fn ($employee) => str_contains(mb_strtolower($employee->name.' '.$employee->employee_number), $needle))->take(4)
                ->map(fn ($employee) => ['title' => $employee->name, 'url' => route('admin.employees.show', $employee)])->values();
            $payload['tasks'] = HrTask::query()->orderBy('title')->get(['id', 'title'])
                ->filter(fn ($task) => str_contains(mb_strtolower($task->title), $needle))->take(4)
                ->map(fn ($task) => ['title' => $task->title, 'url' => route('admin.reports.index', ['tab' => 'tasks', 'highlight' => $task->id])])->values();
        }

        return response()->json($payload);
    }
}

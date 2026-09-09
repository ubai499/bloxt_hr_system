<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HrNotification;
use App\Models\HrTask;
use App\Models\User;
use App\Services\ReportCatalogue;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportCatalogue $catalogue) {}

    public function index(Request $request): View|JsonResponse
    {
        $payload = $this->payload($request);
        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return view('admin.reports.index', [
            'payload' => $payload,
            'employees' => User::role('employee')->where('status', '!=', 'Left')->orderBy('name')->get(['id', 'name']),
            'priorities' => HrTask::PRIORITIES,
            'statuses' => HrTask::STATUSES,
            'activeTab' => $this->tab($request),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('admin.reports.index', array_filter([
            'tab' => 'tasks',
            'new' => 1,
            'employee' => $request->query('employee'),
        ]));
    }

    public function show(string $report): JsonResponse
    {
        if (! in_array($report, $this->catalogue->ids(), true)) {
            abort(404);
        }

        return response()->json($this->catalogue->run($report));
    }

    public function export(string $report): StreamedResponse
    {
        if (! in_array($report, $this->catalogue->ids(), true)) {
            abort(404);
        }
        $data = $this->catalogue->run($report);

        return $this->csv($data['id'].'.csv', $data['columns'], $data['rows']);
    }

    public function storeTask(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->taskData($request);
        $task = HrTask::create($data);
        HrNotification::create([
            'user_id' => null,
            'title' => 'HR task created',
            'body' => $task->title.(filled($task->assigned_to) ? ' assigned to '.$task->assigned_to : ''),
            'status' => 'Unread',
            'href' => route('admin.reports.index', ['tab' => 'tasks', 'highlight' => $task->id]),
        ]);

        return $this->success($request, 'The HR task has been added.', 'Task created', 'tasks');
    }

    public function updateTask(Request $request, HrTask $task): JsonResponse|RedirectResponse
    {
        $task->update($this->taskData($request, $task));

        return $this->success($request, 'The HR task has been updated.', 'Task updated', 'tasks');
    }

    public function updateTaskStatus(Request $request, HrTask $task): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(HrTask::STATUSES)],
        ]);
        $task->update($data);

        return $this->success($request, 'Status changed to '.$task->status.'.', 'Task updated', 'tasks');
    }

    public function resolveNotification(Request $request, HrNotification $notification): JsonResponse|RedirectResponse
    {
        if ($notification->user_id && $notification->user_id !== $request->user()->id) {
            abort(403);
        }
        $notification->update(['status' => 'Resolved']);

        return $this->success($request, 'The notification has been resolved.', 'Notification resolved', 'notifications');
    }

    private function payload(Request $request): array
    {
        return [
            'catalogue' => $this->catalogue->definitions(),
            'tasks' => HrTask::query()->with('employee')->latest('due_date')->orderByDesc('id')->get()->map(fn (HrTask $task) => $this->taskRow($task))->values(),
            'notifications' => HrNotification::query()->visibleTo($request->user())->latest()->orderByDesc('id')->get()->map(fn (HrNotification $notification) => $this->notificationRow($notification))->values(),
        ];
    }

    private function taskRow(HrTask $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'employee_id' => $task->employee_id,
            'employee' => $task->employee?->name,
            'category' => $task->category,
            'assigned_to' => $task->assigned_to,
            'priority' => $task->priority,
            'due_date' => $task->due_date?->toDateString(),
            'status' => $task->status,
            'description' => $task->description,
            'update_url' => route('admin.reports.tasks.update', $task),
            'status_url' => route('admin.reports.tasks.status', $task),
        ];
    }

    private function notificationRow(HrNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'body' => $notification->body,
            'created_at' => $notification->created_at?->toIso8601String(),
            'status' => $notification->status,
            'href' => $notification->href,
            'resolve_url' => $notification->status === 'Resolved' ? null : route('admin.reports.notifications.resolve', $notification),
        ];
    }

    private function taskData(Request $request, ?HrTask $task = null): array
    {
        foreach (['employee_id', 'due_date'] as $field) {
            if ($request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'employee_id' => ['nullable', 'integer', 'exists:users,id'],
            'category' => ['nullable', 'string', 'max:255'],
            'assigned_to' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', Rule::in(HrTask::PRIORITIES)],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['required', Rule::in(HrTask::STATUSES)],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $data['title'] = trim($data['title']);
        $data['category'] = filled($data['category'] ?? null) ? trim($data['category']) : 'General';
        $data['assigned_to'] = filled($data['assigned_to'] ?? null) ? trim($data['assigned_to']) : $request->user()->name;
        $data['description'] = filled($data['description'] ?? null) ? trim($data['description']) : null;
        if ($data['employee_id']) {
            $employee = User::findOrFail($data['employee_id']);
            if (! $employee->hasRole('employee') || $employee->status === 'Left') {
                throw ValidationException::withMessages(['employee_id' => 'Choose a current employee.']);
            }
        }

        return $data;
    }

    private function tab(Request $request): string
    {
        $tab = $request->query('tab', 'catalogue');

        return in_array($tab, ['catalogue', 'tasks', 'notifications'], true) ? $tab : 'catalogue';
    }

    private function csv(string $filename, array $headers, $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, $headers);
            foreach ($rows as $row) {
                fputcsv($stream, array_map(fn ($cell) => preg_match('/^[\s]*[=+@-]/u', (string) $cell) ? "'".$cell : $cell, is_array($row) ? $row : []));
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function success(Request $request, string $message, string $title, string $tab): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->route('admin.reports.index', ['tab' => $tab])->with('success', $message)->with('toast_title', $title);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $filters = $this->filters($request);
        $rows = $this->rows($filters);
        $payload = ['rows' => $rows->values()];
        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return view('admin.audit.index', [
            'payload' => $payload,
            'filters' => $filters,
            'modules' => AuditEvent::query()->distinct()->orderBy('module')->pluck('module')->all() ?: AuditEvent::MODULES,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->rows($this->filters($request));

        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Timestamp', 'User', 'Action', 'Module', 'Employee', 'Description', 'Previous', 'New']);
            foreach ($rows as $row) {
                $cells = [$row['timestamp'], $row['user'], $row['action'], $row['module'], $row['employee'], $row['description'], $row['previous_value'], $row['new_value']];
                fputcsv($stream, array_map(fn ($cell) => preg_match('/^[\s]*[=+@-]/u', (string) $cell) ? "'".$cell : $cell, $cells));
            }
            fclose($stream);
        }, 'audit-log.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{search: string, module: string, date: string}
     */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'module' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return [
            'search' => $data['search'] ?? '',
            'module' => $data['module'] ?? 'all',
            'date' => $data['date'] ?? '',
        ];
    }

    private function rows(array $filters)
    {
        $search = trim($filters['search']);

        return AuditEvent::query()->with('employee')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('action', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhere('user_name', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['module'] !== 'all' && $filters['module'] !== '', fn (Builder $query) => $query->where('module', $filters['module']))
            ->when($filters['date'] !== '', fn (Builder $query) => $query->whereDate('occurred_at', $filters['date']))
            ->latest('occurred_at')->latest('id')
            ->get()
            ->map(fn (AuditEvent $event) => [
                'id' => $event->id,
                'timestamp' => $event->occurred_at?->toIso8601String(),
                'user' => $event->user_name ?: 'System',
                'action' => $event->action,
                'module' => $event->module,
                'employee' => $event->employee?->name,
                'description' => $event->description,
                'previous_value' => $event->previous_value,
                'new_value' => $event->new_value,
            ]);
    }
}

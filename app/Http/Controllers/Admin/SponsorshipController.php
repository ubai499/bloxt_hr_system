<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyChange;
use App\Models\GuidanceReference;
use App\Models\SponsorEvent;
use App\Models\SponsorLicence;
use App\Models\SponsorshipRecord;
use App\Models\User;
use App\Services\ComplianceMonitor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SponsorshipController extends Controller
{
    public function __construct(private ComplianceMonitor $monitor) {}

    public function index(Request $request): View|JsonResponse
    {
        $payload = $this->payload();
        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return view('admin.sponsorship.index', [
            'payload' => $payload,
            'licence' => SponsorLicence::current(),
            'employees' => User::role('employee')->where('status', '!=', 'Left')->orderBy('name')->get(['id', 'name', 'job_title', 'weekly_hours', 'work_location']),
            'managers' => User::role('employee')->where('status', '!=', 'Left')->orderBy('name')->get(['id', 'name']),
            'routes' => SponsorshipRecord::ROUTES,
            'recordStatuses' => SponsorshipRecord::STATUSES,
            'eventTypes' => SponsorEvent::TYPES,
            'eventStatuses' => SponsorEvent::STATUSES,
            'changeTypes' => CompanyChange::TYPES,
            'licenceStatuses' => SponsorLicence::STATUSES,
            'activeTab' => $this->tab($request),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('admin.sponsorship.index', array_filter([
            'tab' => $request->query('tab', 'workers'),
            'new' => $request->query('type', 'worker'),
            'employee' => $request->query('employee'),
        ]));
    }

    public function storeWorker(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->workerData($request);
        DB::transaction(function () use ($data) {
            $this->lockEmployee($data['employee_id']);
            if (SponsorshipRecord::where('employee_id', $data['employee_id'])->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['employee_id' => 'This employee already has a sponsorship record.']);
            }
            SponsorshipRecord::create($data);
        });

        return $this->success($request, 'The sponsored worker record has been added.', 'Sponsorship record saved', 'workers');
    }

    public function showWorker(SponsorshipRecord $record): JsonResponse
    {
        $record->load(['employee.latestRightToWorkCheck', 'employee.departmentRecord', 'lineManager']);

        return response()->json($this->workerDetail($record));
    }

    public function updateWorker(Request $request, SponsorshipRecord $record): JsonResponse|RedirectResponse
    {
        $data = $this->workerData($request, $record);
        DB::transaction(function () use ($data, $record) {
            $this->lockEmployee($record->employee_id, false);
            $row = SponsorshipRecord::lockForUpdate()->findOrFail($record->id);
            unset($data['employee_id']);
            $row->update($data);
        });

        return $this->success($request, 'The sponsored worker record has been updated.', 'Sponsorship record updated', 'workers');
    }

    public function destroyWorker(Request $request, SponsorshipRecord $record): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($record) {
            User::whereKey($record->employee_id)->lockForUpdate()->firstOrFail();
            SponsorshipRecord::lockForUpdate()->findOrFail($record->id)->delete();
        });

        return $this->success($request, 'The sponsored worker record has been removed.', 'Sponsorship record removed', 'workers');
    }

    public function storeEvent(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->eventData($request);
        DB::transaction(function () use ($data) {
            $this->lockSponsoredEmployee($data['employee_id']);
            SponsorEvent::create($data);
        });

        return $this->success($request, 'The event has been added to the reporting register.', 'Sponsor event saved', 'events');
    }

    public function showEvent(SponsorEvent $event): JsonResponse
    {
        $event->load('employee');

        return response()->json($this->eventDetail($event));
    }

    public function updateEvent(Request $request, SponsorEvent $event): JsonResponse|RedirectResponse
    {
        $data = $this->eventData($request, $event);
        DB::transaction(function () use ($data, $event) {
            $this->lockEmployee($event->employee_id, false);
            $row = SponsorEvent::lockForUpdate()->findOrFail($event->id);
            unset($data['employee_id']);
            $row->update($data);
        });

        return $this->success($request, 'The sponsor event has been updated.', 'Sponsor event updated', 'events');
    }

    public function destroyEvent(Request $request, SponsorEvent $event): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($event) {
            User::whereKey($event->employee_id)->lockForUpdate()->firstOrFail();
            SponsorEvent::lockForUpdate()->findOrFail($event->id)->delete();
        });

        return $this->success($request, 'The sponsor event has been removed.', 'Sponsor event removed', 'events');
    }

    public function storeChange(Request $request): JsonResponse|RedirectResponse
    {
        CompanyChange::create($this->changeData($request));

        return $this->success($request, 'The company change has been recorded.', 'Company change saved', 'changes');
    }

    public function showChange(CompanyChange $change): JsonResponse
    {
        return response()->json($this->changeDetail($change));
    }

    public function updateChange(Request $request, CompanyChange $change): JsonResponse|RedirectResponse
    {
        $change->update($this->changeData($request, $change));

        return $this->success($request, 'The company change has been updated.', 'Company change updated', 'changes');
    }

    public function destroyChange(Request $request, CompanyChange $change): JsonResponse|RedirectResponse
    {
        $change->delete();

        return $this->success($request, 'The company change has been removed.', 'Company change removed', 'changes');
    }

    public function storeGuidance(Request $request): JsonResponse|RedirectResponse
    {
        GuidanceReference::create($this->guidanceData($request));

        return $this->success($request, 'The guidance reference has been added.', 'Guidance saved', 'guidance');
    }

    public function showGuidance(GuidanceReference $guidance): JsonResponse
    {
        return response()->json($this->guidanceDetail($guidance));
    }

    public function updateGuidance(Request $request, GuidanceReference $guidance): JsonResponse|RedirectResponse
    {
        $guidance->update($this->guidanceData($request, $guidance));

        return $this->success($request, 'The guidance reference has been updated.', 'Guidance updated', 'guidance');
    }

    public function destroyGuidance(Request $request, GuidanceReference $guidance): JsonResponse|RedirectResponse
    {
        $guidance->delete();

        return $this->success($request, 'The guidance reference has been removed.', 'Guidance removed', 'guidance');
    }

    public function updateLicence(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(SponsorLicence::STATUSES)],
            'reference' => ['nullable', 'string', 'max:100'],
            'rating' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'renewal_review_date' => ['nullable', 'date_format:Y-m-d'],
            'worker_routes' => ['nullable', 'string', 'max:500'],
            'authorising_officer' => ['nullable', 'string', 'max:255'],
            'key_contact' => ['nullable', 'string', 'max:255'],
            'level1_user' => ['nullable', 'string', 'max:255'],
            'level2_users' => ['nullable', 'string', 'max:500'],
            'org_details_last_reviewed' => ['nullable', 'date_format:Y-m-d'],
            'next_internal_review_date' => ['nullable', 'date_format:Y-m-d'],
            'sms_url' => ['required', 'url', 'max:500'],
        ]);
        foreach (['reference', 'rating', 'authorising_officer', 'key_contact', 'level1_user'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim($data[$field]) : null;
        }
        $data['worker_routes'] = $this->list($data['worker_routes'] ?? null);
        $data['level2_users'] = $this->list($data['level2_users'] ?? null);
        SponsorLicence::current()->update($data);

        return $this->success($request, 'The sponsor licence details have been updated.', 'Licence updated', 'overview');
    }

    private function payload(): array
    {
        $licence = SponsorLicence::current();
        $workers = SponsorshipRecord::query()->with(['employee.latestRightToWorkCheck', 'employee.departmentRecord', 'lineManager'])->orderBy('id')->get();
        $current = $workers->filter(fn (SponsorshipRecord $record) => $record->isCurrent() && $record->employee && $record->employee->status !== 'Left');
        $summaries = $current->map(fn (SponsorshipRecord $record) => $this->monitor->workerSummary($record->employee, $record));

        return [
            'licence' => $this->licenceRow($licence),
            'workers' => $workers->map(fn (SponsorshipRecord $record) => $this->workerRow($record))->values(),
            'events' => SponsorEvent::query()->with('employee')->latest('date_occurred')->orderByDesc('id')->get()->map(fn (SponsorEvent $event) => $this->eventRow($event))->values(),
            'changes' => CompanyChange::query()->latest('date')->orderByDesc('id')->get()->map(fn (CompanyChange $change) => $this->changeRow($change))->values(),
            'guidance' => GuidanceReference::query()->orderBy('title')->get()->map(fn (GuidanceReference $guidance) => $this->guidanceRow($guidance))->values(),
            'stats' => [
                'sponsored' => $current->count(),
                'review_required' => $summaries->where('overall', 'Review Required')->count(),
                'action_required' => $summaries->where('overall', 'Action Required')->count(),
                'rating' => $licence->rating ?: '—',
            ],
        ];
    }

    private function workerRow(SponsorshipRecord $record): array
    {
        $employee = $record->employee;
        $summary = $employee ? $this->monitor->workerSummary($employee, $record) : ['rtw_status' => 'Evidence Missing', 'contact_current' => false, 'overall' => 'Action Required'];

        return [
            'id' => $record->id,
            'employee_id' => $record->employee_id,
            'employee' => $employee?->name,
            'role' => $record->internal_job_title ?: $employee?->job_title,
            'department' => $employee?->departmentRecord?->name,
            'soc_code' => $record->soc_code,
            'annual_salary' => $record->annual_salary !== null ? (float) $record->annual_salary : null,
            'rtw_status' => $summary['rtw_status'],
            'contact_current' => $summary['contact_current'],
            'overall' => $summary['overall'],
            'sponsorship_status' => $record->sponsorship_status,
            'profile_url' => $employee ? route('admin.employees.show', ['employee' => $employee, 'tab' => 'sponsorship']) : null,
            'details_url' => route('admin.sponsorship.workers.show', $record),
            'update_url' => route('admin.sponsorship.workers.update', $record),
            'destroy_url' => route('admin.sponsorship.workers.destroy', $record),
        ];
    }

    private function workerDetail(SponsorshipRecord $record): array
    {
        $employee = $record->employee;
        $summary = $employee ? $this->monitor->workerSummary($employee, $record) : [];

        return [
            'id' => $record->id,
            'employee_id' => $record->employee_id,
            'employee' => $employee?->name,
            'worker_route' => $record->worker_route,
            'sponsor_licence_ref' => $record->sponsor_licence_ref,
            'cos_reference' => $record->cos_reference,
            'cos_assigned_date' => $record->cos_assigned_date?->toDateString(),
            'cos_start_date' => $record->cos_start_date?->toDateString(),
            'cos_end_date' => $record->cos_end_date?->toDateString(),
            'permission_start' => $record->permission_start?->toDateString(),
            'permission_expiry' => $record->permission_expiry?->toDateString(),
            'soc_code' => $record->soc_code,
            'soc_title' => $record->soc_title,
            'internal_job_title' => $record->internal_job_title,
            'annual_salary' => $record->annual_salary !== null ? (float) $record->annual_salary : null,
            'weekly_hours' => $record->weekly_hours !== null ? (float) $record->weekly_hours : null,
            'work_pattern' => $record->work_pattern,
            'work_location' => $record->work_location,
            'line_manager_id' => $record->line_manager_id,
            'line_manager' => $record->lineManager?->name,
            'sponsorship_status' => $record->sponsorship_status,
            'hr_responsible_person' => $record->hr_responsible_person,
            'next_review_date' => $record->next_review_date?->toDateString(),
            'notes' => $record->notes,
            'overall' => $summary['overall'] ?? null,
            'update_url' => route('admin.sponsorship.workers.update', $record),
            'destroy_url' => route('admin.sponsorship.workers.destroy', $record),
        ];
    }

    private function eventRow(SponsorEvent $event): array
    {
        return [
            'id' => $event->id,
            'employee_id' => $event->employee_id,
            'employee' => $event->employee?->name,
            'event_type' => $event->event_type,
            'date_occurred' => $event->date_occurred?->toDateString(),
            'details' => $event->details,
            'assigned_to' => $event->assigned_to,
            'reported_through_sms' => $event->reported_through_sms,
            'date_reported' => $event->date_reported?->toDateString(),
            'status' => $event->status,
            'details_url' => route('admin.sponsorship.events.show', $event),
            'update_url' => route('admin.sponsorship.events.update', $event),
            'destroy_url' => route('admin.sponsorship.events.destroy', $event),
        ];
    }

    private function eventDetail(SponsorEvent $event): array
    {
        return [
            'id' => $event->id,
            'employee_id' => $event->employee_id,
            'employee' => $event->employee?->name,
            'event_type' => $event->event_type,
            'date_occurred' => $event->date_occurred?->toDateString(),
            'date_aware' => $event->date_aware?->toDateString(),
            'details' => $event->details,
            'requires_assessment' => $event->requires_assessment,
            'reporting_deadline' => $event->reporting_deadline?->toDateString(),
            'assigned_to' => $event->assigned_to,
            'reported_through_sms' => $event->reported_through_sms,
            'date_reported' => $event->date_reported?->toDateString(),
            'reported_by' => $event->reported_by,
            'evidence_ref' => $event->evidence_ref,
            'notes' => $event->notes,
            'status' => $event->status,
            'update_url' => route('admin.sponsorship.events.update', $event),
            'destroy_url' => route('admin.sponsorship.events.destroy', $event),
        ];
    }

    private function changeRow(CompanyChange $change): array
    {
        return [
            'id' => $change->id,
            'date' => $change->date?->toDateString(),
            'change_type' => $change->change_type,
            'description' => $change->description,
            'potential_sponsor_impact' => $change->potential_sponsor_impact,
            'report_required' => $change->report_required,
            'reported' => $change->reported,
            'reported_date' => $change->reported_date?->toDateString(),
            'details_url' => route('admin.sponsorship.changes.show', $change),
            'update_url' => route('admin.sponsorship.changes.update', $change),
            'destroy_url' => route('admin.sponsorship.changes.destroy', $change),
        ];
    }

    private function changeDetail(CompanyChange $change): array
    {
        return $this->changeRow($change) + [
            'reported_internally_by' => $change->reported_internally_by,
            'reviewed_by' => $change->reviewed_by,
            'evidence_ref' => $change->evidence_ref,
            'notes' => $change->notes,
        ];
    }

    private function guidanceRow(GuidanceReference $guidance): array
    {
        return [
            'id' => $guidance->id,
            'title' => $guidance->title,
            'source' => $guidance->source,
            'url' => $guidance->url,
            'last_reviewed' => $guidance->last_reviewed?->toDateString(),
            'reviewed_by' => $guidance->reviewed_by,
            'notes' => $guidance->notes,
            'details_url' => route('admin.sponsorship.guidance.show', $guidance),
            'update_url' => route('admin.sponsorship.guidance.update', $guidance),
            'destroy_url' => route('admin.sponsorship.guidance.destroy', $guidance),
        ];
    }

    private function guidanceDetail(GuidanceReference $guidance): array
    {
        return $this->guidanceRow($guidance);
    }

    private function licenceRow(SponsorLicence $licence): array
    {
        return [
            'status' => $licence->status,
            'reference' => $licence->reference,
            'rating' => $licence->rating,
            'start_date' => $licence->start_date?->toDateString(),
            'renewal_review_date' => $licence->renewal_review_date?->toDateString(),
            'worker_routes' => $licence->worker_routes ?? [],
            'authorising_officer' => $licence->authorising_officer,
            'key_contact' => $licence->key_contact,
            'level1_user' => $licence->level1_user,
            'level2_users' => $licence->level2_users ?? [],
            'org_details_last_reviewed' => $licence->org_details_last_reviewed?->toDateString(),
            'next_internal_review_date' => $licence->next_internal_review_date?->toDateString(),
            'sms_url' => $licence->sms_url,
            'update_url' => route('admin.sponsorship.licence.update'),
        ];
    }

    private function workerData(Request $request, ?SponsorshipRecord $record = null): array
    {
        foreach (['cos_assigned_date', 'cos_start_date', 'cos_end_date', 'permission_start', 'permission_expiry', 'next_review_date', 'annual_salary', 'weekly_hours', 'line_manager_id'] as $field) {
            if ($request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }
        $data = $request->validate([
            'employee_id' => [$record ? 'nullable' : 'required', 'integer', 'exists:users,id'],
            'worker_route' => ['required', Rule::in(SponsorshipRecord::ROUTES)],
            'sponsor_licence_ref' => ['nullable', 'string', 'max:100'],
            'cos_reference' => ['nullable', 'string', 'max:100'],
            'cos_assigned_date' => ['nullable', 'date_format:Y-m-d'],
            'cos_start_date' => ['nullable', 'date_format:Y-m-d'],
            'cos_end_date' => ['nullable', 'date_format:Y-m-d', ...($request->filled('cos_start_date') ? ['after_or_equal:cos_start_date'] : [])],
            'permission_start' => ['nullable', 'date_format:Y-m-d'],
            'permission_expiry' => ['nullable', 'date_format:Y-m-d', ...($request->filled('permission_start') ? ['after_or_equal:permission_start'] : [])],
            'soc_code' => ['nullable', 'string', 'max:20'],
            'soc_title' => ['nullable', 'string', 'max:255'],
            'internal_job_title' => ['nullable', 'string', 'max:255'],
            'annual_salary' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'weekly_hours' => ['nullable', 'numeric', 'min:0', 'max:168'],
            'work_pattern' => ['nullable', 'string', 'max:255'],
            'work_location' => ['nullable', 'string', 'max:255'],
            'line_manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'sponsorship_status' => ['required', Rule::in(SponsorshipRecord::STATUSES)],
            'hr_responsible_person' => ['nullable', 'string', 'max:255'],
            'next_review_date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        foreach (['sponsor_licence_ref', 'cos_reference', 'soc_code', 'soc_title', 'internal_job_title', 'work_pattern', 'work_location', 'hr_responsible_person', 'notes'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim($data[$field]) : null;
        }
        $data['hr_responsible_person'] = $data['hr_responsible_person'] ?: $request->user()->name;

        return $data;
    }

    private function eventData(Request $request, ?SponsorEvent $event = null): array
    {
        foreach (['date_occurred', 'date_aware', 'reporting_deadline', 'date_reported'] as $field) {
            if ($request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }
        $data = $request->validate([
            'employee_id' => [$event ? 'nullable' : 'required', 'integer', 'exists:users,id'],
            'event_type' => ['required', Rule::in(SponsorEvent::TYPES)],
            'date_occurred' => ['nullable', 'date_format:Y-m-d'],
            'date_aware' => ['nullable', 'date_format:Y-m-d'],
            'details' => ['required', 'string', 'max:5000'],
            'assigned_to' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(SponsorEvent::STATUSES)],
            'reporting_deadline' => ['nullable', 'date_format:Y-m-d'],
            'reported_through_sms' => ['nullable', 'boolean'],
            'date_reported' => ['nullable', 'required_if:reported_through_sms,1,true,yes', 'date_format:Y-m-d'],
            'reported_by' => ['nullable', 'string', 'max:255'],
            'evidence_ref' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'date_reported.required_if' => 'Enter the date reported when the event was reported through SMS.',
        ]);
        $data['details'] = trim($data['details']);
        $data['assigned_to'] = filled($data['assigned_to'] ?? null) ? trim($data['assigned_to']) : $request->user()->name;
        $data['reported_through_sms'] = $request->boolean('reported_through_sms');
        $data['requires_assessment'] = $data['status'] !== 'Not Reportable';
        foreach (['reported_by', 'evidence_ref', 'notes'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim($data[$field]) : null;
        }

        return $data;
    }

    private function changeData(Request $request, ?CompanyChange $change = null): array
    {
        foreach (['reported_date'] as $field) {
            if ($request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }
        $data = $request->validate([
            'change_type' => ['required', Rule::in(CompanyChange::TYPES)],
            'date' => ['required', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'max:5000'],
            'reported_internally_by' => ['nullable', 'string', 'max:255'],
            'reviewed_by' => ['nullable', 'string', 'max:255'],
            'potential_sponsor_impact' => ['nullable', 'string', 'max:255'],
            'report_required' => ['nullable', 'boolean'],
            'reported' => ['nullable', 'boolean'],
            'reported_date' => ['nullable', 'required_if:reported,1,true,yes', 'date_format:Y-m-d'],
            'evidence_ref' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'reported_date.required_if' => 'Enter the reported date when the change has been reported.',
        ]);
        $data['description'] = trim($data['description']);
        $data['report_required'] = $request->boolean('report_required');
        $data['reported'] = $request->boolean('reported');
        foreach (['reported_internally_by', 'reviewed_by', 'potential_sponsor_impact', 'evidence_ref', 'notes'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim($data[$field]) : null;
        }
        $data['reported_internally_by'] = $data['reported_internally_by'] ?: $request->user()->name;

        return $data;
    }

    private function guidanceData(Request $request, ?GuidanceReference $guidance = null): array
    {
        if ($request->input('last_reviewed') === '') {
            $request->merge(['last_reviewed' => null]);
        }
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:500'],
            'last_reviewed' => ['nullable', 'date_format:Y-m-d'],
            'reviewed_by' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $data['title'] = trim($data['title']);
        $data['url'] = trim($data['url']);
        foreach (['source', 'reviewed_by', 'notes'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim($data[$field]) : null;
        }
        $data['reviewed_by'] = $data['reviewed_by'] ?: $request->user()->name;

        return $data;
    }

    private function list(?string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }

    private function lockEmployee(int $id, bool $currentOnly = true): User
    {
        $employee = User::lockForUpdate()->findOrFail($id);
        if ($currentOnly && (! $employee->hasRole('employee') || $employee->status === 'Left')) {
            throw ValidationException::withMessages(['employee_id' => 'Choose a current employee.']);
        }

        return $employee;
    }

    private function lockSponsoredEmployee(int $id): User
    {
        $employee = $this->lockEmployee($id);
        if (! SponsorshipRecord::where('employee_id', $employee->id)->where('sponsorship_status', 'Current')->exists()) {
            throw ValidationException::withMessages(['employee_id' => 'Choose a current sponsored worker.']);
        }

        return $employee;
    }

    private function tab(Request $request): string
    {
        $tab = $request->query('tab', 'overview');

        return in_array($tab, ['overview', 'workers', 'events', 'changes', 'guidance'], true) ? $tab : 'overview';
    }

    private function success(Request $request, string $message, string $title, string $tab): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->route('admin.sponsorship.index', ['tab' => $tab])->with('success', $message)->with('toast_title', $title);
    }
}

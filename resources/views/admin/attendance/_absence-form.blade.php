<form method="POST" action="{{ $absence->exists ? route('admin.attendance.absence.update', $absence) : route('admin.attendance.absence.store') }}" novalidate>
    @csrf
    @if ($absence->exists)
        @method('PUT')
    @endif

    <div class="panel mb-4" style="max-width: 900px;">
        <div class="panel-header">
            <div>
                <div class="panel-title">Absence details</div>
                <div class="panel-desc">Record an absence and the follow-up information provided by the employee.</div>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="mb-3">
                <label for="absenceEmployee" class="form-label">Employee <span class="required-indicator">*</span></label>
                <select id="absenceEmployee" name="employee_id" class="form-select @error('employee_id') is-invalid @enderror" required>
                    <option value="">Choose an employee...</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('employee_id', $absence->employee_id) === (string) $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </select>
                @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="absenceDate" class="form-label">Date <span class="required-indicator">*</span></label>
                <input id="absenceDate" name="date" type="date" class="form-control @error('date') is-invalid @enderror" value="{{ old('date', $absence->date?->format('Y-m-d')) }}" required>
                @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="absenceType" class="form-label">Absence type <span class="required-indicator">*</span></label>
                <select id="absenceType" name="absence_type" class="form-select @error('absence_type') is-invalid @enderror" required>
                    @foreach ($absenceTypes as $type)
                        <option value="{{ $type }}" @selected(old('absence_type', $absence->absence_type) === $type)>{{ $type }}</option>
                    @endforeach
                </select>
                @error('absence_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="reportedDate" class="form-label">Reported date</label>
                <input id="reportedDate" name="reported_date" type="date" class="form-control @error('reported_date') is-invalid @enderror" value="{{ old('reported_date', $absence->reported_date?->format('Y-m-d')) }}">
                @error('reported_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="howReported" class="form-label">How reported</label>
                <input id="howReported" name="how_reported" type="text" class="form-control @error('how_reported') is-invalid @enderror" value="{{ old('how_reported', $absence->how_reported) }}" placeholder="e.g. Phone call, email">
                @error('how_reported')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="reportedTo" class="form-label">Reported to</label>
                <input id="reportedTo" name="reported_to" type="text" class="form-control @error('reported_to') is-invalid @enderror" value="{{ old('reported_to', $absence->reported_to) }}" placeholder="e.g. Line manager">
                @error('reported_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="expectedReturn" class="form-label">Expected return</label>
                <input id="expectedReturn" name="expected_return" type="date" class="form-control @error('expected_return') is-invalid @enderror" value="{{ old('expected_return', $absence->expected_return?->format('Y-m-d')) }}">
                @error('expected_return')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="reason" class="form-label">Reason</label>
                <input id="reason" name="reason" type="text" class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason', $absence->reason) }}">
                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="managerNotes" class="form-label">Manager notes</label>
            <textarea id="managerNotes" name="manager_notes" rows="3" class="form-control @error('manager_notes') is-invalid @enderror">{{ old('manager_notes', $absence->manager_notes) }}</textarea>
            @error('manager_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="d-flex gap-4">
            <div class="form-check"><input id="authorised" name="authorised" value="1" type="checkbox" class="form-check-input" @checked(old('authorised', $absence->authorised))><label for="authorised" class="form-check-label">Authorised</label></div>
            <div class="form-check"><input id="followUp" name="follow_up_required" value="1" type="checkbox" class="form-check-input" @checked(old('follow_up_required', $absence->follow_up_required))><label for="followUp" class="form-check-label">Follow-up required</label></div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-5">
        <button class="btn btn-primary" type="submit">{{ $absence->exists ? 'Update Absence Record' : 'Save Absence Record' }}</button>
        <a class="btn btn-light-custom" href="{{ route('admin.attendance.index', ['tab' => 'absence']) }}">Cancel</a>
    </div>
</form>

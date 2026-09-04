<form method="POST" action="{{ $record->exists ? route('admin.attendance.update', $record) : route('admin.attendance.store') }}" novalidate>
    @csrf
    @if ($record->exists)
        @method('PUT')
    @endif

    <div class="panel mb-4" style="max-width: 900px;">
        <div class="panel-header">
            <div>
                <div class="panel-title">Attendance details</div>
                <div class="panel-desc">Record the employee's attendance for a single working day.</div>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="mb-3">
                <label for="employee" class="form-label">Employee <span class="required-indicator">*</span></label>
                <select id="employee" name="employee_id" class="form-select @error('employee_id') is-invalid @enderror" required>
                    <option value="">Choose an employee...</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('employee_id', $record->employee_id) === (string) $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </select>
                @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="attendanceDate" class="form-label">Date <span class="required-indicator">*</span></label>
                <input id="attendanceDate" name="date" type="date" class="form-control @error('date') is-invalid @enderror" value="{{ old('date', $record->date?->format('Y-m-d')) }}" required>
                @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="expectedStart" class="form-label">Expected start</label>
                <input id="expectedStart" name="expected_start" type="time" class="form-control @error('expected_start') is-invalid @enderror" value="{{ old('expected_start', $record->expected_start ? substr($record->expected_start, 0, 5) : '') }}">
                @error('expected_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="attendanceStatus" class="form-label">Status <span class="required-indicator">*</span></label>
                <select id="attendanceStatus" name="status" class="form-select @error('status') is-invalid @enderror" required>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(old('status', $record->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="clockIn" class="form-label">Clock in</label>
                <input id="clockIn" name="clock_in" type="time" class="form-control @error('clock_in') is-invalid @enderror" value="{{ old('clock_in', $record->clock_in ? substr($record->clock_in, 0, 5) : '') }}">
                @error('clock_in')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="clockOut" class="form-label">Clock out</label>
                <input id="clockOut" name="clock_out" type="time" class="form-control @error('clock_out') is-invalid @enderror" value="{{ old('clock_out', $record->clock_out ? substr($record->clock_out, 0, 5) : '') }}">
                @error('clock_out')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="workLocation" class="form-label">Work location</label>
                <select id="workLocation" name="work_location" class="form-select @error('work_location') is-invalid @enderror">
                    <option value="">Choose a location...</option>
                    @foreach (['Office', 'Remote', 'Client Site', 'N/A'] as $location)
                        <option value="{{ $location }}" @selected(old('work_location', $record->work_location) === $location)>{{ $location }}</option>
                    @endforeach
                </select>
                @error('work_location')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="hours" class="form-label">Hours</label>
                <input id="hours" name="hours" type="number" min="0" max="24" step="0.25" class="form-control @error('hours') is-invalid @enderror" value="{{ old('hours', $record->hours) }}">
                @error('hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="notes" class="form-label">Notes</label>
            <textarea id="notes" name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $record->notes) }}</textarea>
            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="form-check mb-0">
            <input id="managerReviewed" name="manager_reviewed" value="1" type="checkbox" class="form-check-input" @checked(old('manager_reviewed', $record->manager_reviewed))>
            <label for="managerReviewed" class="form-check-label">Mark as manager reviewed</label>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-5">
        <button class="btn btn-primary" type="submit">{{ $record->exists ? 'Update Attendance' : 'Save Record' }}</button>
        <a class="btn btn-light-custom" href="{{ route('admin.attendance.index') }}">Cancel</a>
    </div>
</form>

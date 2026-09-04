<form method="POST" action="{{ $leaveRequest->exists ? route('admin.leave.update', $leaveRequest) : route('admin.leave.store') }}" novalidate>
    @csrf
    @if ($leaveRequest->exists)
        @method('PUT')
    @endif

    <div class="panel mb-4" style="max-width: 900px;">
        <div class="panel-header">
            <div>
                <div class="panel-title">Leave request details</div>
                <div class="panel-desc">Record the leave type, dates, and supporting information.</div>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="mb-3">
                <label for="leaveEmployee" class="form-label">Employee <span class="required-indicator">*</span></label>
                <select id="leaveEmployee" name="employee_id" class="form-select @error('employee_id') is-invalid @enderror" required>
                    <option value="">Choose an employee...</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('employee_id', $leaveRequest->employee_id) === (string) $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </select>
                @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="leaveType" class="form-label">Leave type <span class="required-indicator">*</span></label>
                <select id="leaveType" name="leave_type" class="form-select @error('leave_type') is-invalid @enderror" required>
                    @foreach ($leaveTypes as $type)
                        <option value="{{ $type }}" @selected(old('leave_type', $leaveRequest->leave_type) === $type)>{{ $type }}</option>
                    @endforeach
                </select>
                @error('leave_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="fromDate" class="form-label">From <span class="required-indicator">*</span></label>
                <input id="fromDate" name="from_date" type="date" class="form-control @error('from_date') is-invalid @enderror" value="{{ old('from_date', $leaveRequest->from_date?->format('Y-m-d')) }}" required>
                @error('from_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="toDate" class="form-label">To <span class="required-indicator">*</span></label>
                <input id="toDate" name="to_date" type="date" class="form-control @error('to_date') is-invalid @enderror" value="{{ old('to_date', $leaveRequest->to_date?->format('Y-m-d')) }}" required>
                @error('to_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="form-check mb-3">
            <input id="partialDay" name="partial_day" value="1" type="checkbox" class="form-check-input" @checked(old('partial_day', $leaveRequest->partial_day))>
            <label for="partialDay" class="form-check-label">Partial day leave</label>
        </div>

        <div class="mb-3">
            <label for="reason" class="form-label">Reason</label>
            <input id="reason" name="reason" type="text" class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason', $leaveRequest->reason) }}">
            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-0">
            <label for="notes" class="form-label">Notes</label>
            <textarea id="notes" name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $leaveRequest->notes) }}</textarea>
            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-5">
        <button class="btn btn-primary" type="submit">{{ $leaveRequest->exists ? 'Update Request' : 'Submit Request' }}</button>
        <a class="btn btn-light-custom" href="{{ route('admin.leave.index') }}">Cancel</a>
    </div>
</form>

<form method="POST" action="{{ $employee->exists ? route('admin.employees.update', $employee) : route('admin.employees.store') }}" novalidate>
    @csrf
    @if ($employee->exists)
        @method('PUT')
    @endif

    <div class="panel mb-4" style="max-width: 900px;">
        <div class="panel-header">
            <div>
                <div class="panel-title">Personal details</div>
                <div class="panel-desc">Contact details used for the employee record and login.</div>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="mb-3">
                <label for="employeeNumber" class="form-label">Employee number</label>
                <input id="employeeNumber" name="employee_number" type="text" class="form-control @error('employee_number') is-invalid @enderror" value="{{ old('employee_number', $employee->employee_number) }}">
                @error('employee_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="employeeName" class="form-label">Full name <span class="required-indicator">*</span></label>
                <input id="employeeName" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $employee->name) }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="employeeEmail" class="form-label">Work email <span class="required-indicator">*</span></label>
                <input id="employeeEmail" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $employee->email) }}" required>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="employeePhone" class="form-label">Phone number</label>
                <input id="employeePhone" name="phone" type="text" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $employee->phone) }}">
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="contactVerifiedDate" class="form-label">Contact verified date</label>
                <input id="contactVerifiedDate" name="contact_verified_date" type="date" class="form-control @error('contact_verified_date') is-invalid @enderror" value="{{ old('contact_verified_date', $employee->contact_verified_date?->format('Y-m-d')) }}">
                @error('contact_verified_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="contactVerifiedBy" class="form-label">Contact verified by</label>
                <input id="contactVerifiedBy" name="contact_verified_by" type="text" class="form-control @error('contact_verified_by') is-invalid @enderror" value="{{ old('contact_verified_by', $employee->contact_verified_by) }}">
                @error('contact_verified_by')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mb-0">
            <label for="employeeAddress" class="form-label">Home address</label>
            <textarea id="employeeAddress" name="address" rows="3" class="form-control @error('address') is-invalid @enderror">{{ old('address', $employee->address) }}</textarea>
            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="panel mb-4" style="max-width: 900px;">
        <div class="panel-header">
            <div>
                <div class="panel-title">Employment details</div>
                <div class="panel-desc">Role, reporting line, and current employment status.</div>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="mb-3">
                <label for="jobTitle" class="form-label">Job title <span class="required-indicator">*</span></label>
                <input id="jobTitle" name="job_title" type="text" class="form-control @error('job_title') is-invalid @enderror" value="{{ old('job_title', $employee->job_title) }}" required>
                @error('job_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="department" class="form-label">Department <span class="required-indicator">*</span></label>
                <select id="department" name="department_id" class="form-select @error('department_id') is-invalid @enderror" required>
                    <option value="">Choose a department...</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) old('department_id', $employee->department_id) === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
                @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="employmentType" class="form-label">Employment type <span class="required-indicator">*</span></label>
                <select id="employmentType" name="employment_type" class="form-select @error('employment_type') is-invalid @enderror" required>
                    @foreach (['Full-time', 'Part-time', 'Contract', 'Temporary'] as $type)
                        <option value="{{ $type }}" @selected(old('employment_type', $employee->employment_type) === $type)>{{ $type }}</option>
                    @endforeach
                </select>
                @error('employment_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="workLocation" class="form-label">Work location</label>
                <input id="workLocation" name="work_location" type="text" class="form-control @error('work_location') is-invalid @enderror" value="{{ old('work_location', $employee->work_location) }}" placeholder="e.g. London office">
                @error('work_location')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="manager" class="form-label">Manager</label>
                <select id="manager" name="manager_id" class="form-select @error('manager_id') is-invalid @enderror">
                    <option value="">No manager assigned</option>
                    @foreach ($managers as $manager)
                        <option value="{{ $manager->id }}" @selected((string) old('manager_id', $employee->manager_id) === (string) $manager->id)>{{ $manager->name }}</option>
                    @endforeach
                </select>
                @error('manager_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="startDate" class="form-label">Start date</label>
                <input id="startDate" name="start_date" type="date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', $employee->start_date?->format('Y-m-d')) }}">
                @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-0">
                <label for="employmentStatus" class="form-label">Status <span class="required-indicator">*</span></label>
                <select id="employmentStatus" name="status" class="form-select @error('status') is-invalid @enderror" required>
                    @foreach (['Active', 'On Leave', 'Probation', 'Left'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $employee->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    @if ($departments->isEmpty())
        <div class="alert alert-warning" role="alert">Create a <a href="{{ route('admin.departments.create') }}" class="alert-link">department</a> before adding an employee.</div>
    @endif

    <div class="panel mb-4" style="max-width: 900px;">
        <div class="panel-header">
            <div>
                <div class="panel-title">Login access</div>
                <div class="panel-desc">{{ $employee->exists ? 'Leave these fields empty to keep the current password.' : 'The employee uses these details to sign in to their dashboard.' }}</div>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="mb-0">
                <label for="password" class="form-label">{{ $employee->exists ? 'New password' : 'Password *' }}</label>
                <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" {{ $employee->exists ? '' : 'required' }}>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-0">
                <label for="passwordConfirmation" class="form-label">Confirm password{{ $employee->exists ? '' : ' *' }}</label>
                <input id="passwordConfirmation" name="password_confirmation" type="password" class="form-control" {{ $employee->exists ? '' : 'required' }}>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-5">
        <button class="btn btn-primary" type="submit">{{ $employee->exists ? 'Update Employee' : 'Create Employee' }}</button>
        <a class="btn btn-light-custom" href="{{ $employee->exists ? route('admin.employees.show', $employee) : route('admin.employees.index') }}">Cancel</a>
    </div>
</form>

<form method="POST" action="{{ $department->exists ? route('admin.departments.update', $department) : route('admin.departments.store') }}" novalidate>
    @csrf
    @if ($department->exists)
        @method('PUT')
    @endif

    <div class="panel mb-4" style="max-width: 720px;">
        <div class="panel-header">
            <div>
                <div class="panel-title">Department details</div>
                <div class="panel-desc">Departments are used when creating and organising employee records.</div>
            </div>
        </div>

        <div class="mb-3">
            <label for="departmentName" class="form-label">Department name <span class="required-indicator">*</span></label>
            <input id="departmentName" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $department->name) }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-0">
            <label for="departmentStatus" class="form-label">Status <span class="required-indicator">*</span></label>
            <select id="departmentStatus" name="status" class="form-select @error('status') is-invalid @enderror" required>
                @foreach (['Active', 'Inactive'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $department->status) === $status)>{{ $status }}</option>
                @endforeach
            </select>
            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-5">
        <button class="btn btn-primary" type="submit">{{ $department->exists ? 'Update Department' : 'Create Department' }}</button>
        <a class="btn btn-light-custom" href="{{ $department->exists ? route('admin.departments.show', $department) : route('admin.departments.index') }}">Cancel</a>
    </div>
</form>

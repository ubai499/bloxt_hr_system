<?php

namespace App\Providers;

use App\Models\AbsenceRecord;
use App\Models\AttendanceRecord;
use App\Models\Candidate;
use App\Models\CompanyChange;
use App\Models\ComplianceReview;
use App\Models\Department;
use App\Models\Document;
use App\Models\EmployeeCompensation;
use App\Models\HrNotification;
use App\Models\HrTask;
use App\Models\LeaveRequest;
use App\Models\PayrollRecord;
use App\Models\RightToWorkCheck;
use App\Models\SponsorEvent;
use App\Models\SponsorshipRecord;
use App\Models\User;
use App\Models\Vacancy;
use App\Observers\RecordsAudit;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([
            User::class, Department::class, AttendanceRecord::class, AbsenceRecord::class, LeaveRequest::class,
            Document::class, Vacancy::class, Candidate::class, RightToWorkCheck::class, SponsorshipRecord::class,
            SponsorEvent::class, CompanyChange::class, ComplianceReview::class, EmployeeCompensation::class,
            PayrollRecord::class, HrTask::class,
        ] as $model) {
            $model::observe(RecordsAudit::class);
        }

        View::composer('partial.header', function ($view) {
            $user = auth()->user();
            $notifications = collect();
            if ($user?->hasRole('admin')) {
                $notifications = HrNotification::query()->visibleTo($user)->where('status', '!=', 'Resolved')->latest()->limit(8)->get();
            }
            $view->with('headerNotifications', $notifications);
        });
    }
}

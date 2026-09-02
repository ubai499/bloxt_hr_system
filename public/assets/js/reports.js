/**
 * reports.js
 * Report catalogue definitions for the Reports workspace. Each report reads
 * directly from live records — nothing here is precomputed or cached stale.
 */
(function (global) {
  "use strict";

  function empName(id) {
    const e = global.HR.db.employees.get(id);
    return e ? `${e.firstName} ${e.lastName}` : "—";
  }

  const REPORTS = [
    {
      id: "directory", title: "Employee Directory", icon: "bi-people", description: "All current employee records.",
      columns: ["Employee ID", "Name", "Job Title", "Department", "Status", "Start Date"],
      rows: () => global.HR.db.employees.all().map((e) => [e.employeeNumber, global.HR.employees.fullName(e), e.jobTitle, global.HR.employees.departmentName(e.departmentId), e.status, e.startDate])
    },
    {
      id: "current-employees", title: "Current Employees", icon: "bi-person-check", description: "Employees with an active employment status.",
      columns: ["Employee ID", "Name", "Job Title", "Department", "Employment Type"],
      rows: () => global.HR.db.employees.find((e) => e.status === "Active").map((e) => [e.employeeNumber, global.HR.employees.fullName(e), e.jobTitle, global.HR.employees.departmentName(e.departmentId), e.employmentType])
    },
    {
      id: "contact", title: "Employee Contact Report", icon: "bi-telephone", description: "Current contact details and last verification date.",
      columns: ["Name", "Mobile", "Company Email", "Address", "Postcode", "Last Verified"],
      rows: () => global.HR.db.employees.all().map((e) => [global.HR.employees.fullName(e), e.mobile, e.companyEmail, e.address, e.postcode, global.HR.format.date(e.contactVerifiedDate)])
    },
    {
      id: "employment-status", title: "Employment Status Report", icon: "bi-diagram-2", description: "Breakdown of employment status and type across the workforce.",
      columns: ["Name", "Status", "Employment Type", "Start Date", "End Date"],
      rows: () => global.HR.db.employees.all().map((e) => [global.HR.employees.fullName(e), e.status, e.employmentType, e.startDate, e.endDate || "N/A"])
    },
    {
      id: "rtw-status", title: "Right-to-Work Status", icon: "bi-patch-check", description: "Current right-to-work status for every employee.",
      columns: ["Name", "Nationality", "Status", "Permission Expiry"],
      rows: () => global.HR.db.employees.find((e) => e.status !== "Left").map((e) => { const c = global.HR.compliance.latestRtwCheck(e.id); return [global.HR.employees.fullName(e), e.nationality, c ? global.HR.compliance.rtwStatus(c) : "Evidence Missing", c && c.permissionExpiry ? global.HR.format.date(c.permissionExpiry) : "N/A"]; })
    },
    {
      id: "rtw-review-dates", title: "Right-to-Work Review Dates", icon: "bi-calendar-check", description: "Employees with a scheduled right-to-work follow-up.",
      columns: ["Name", "Next Review Date", "Responsible Person"],
      rows: () => global.HR.db.rightToWorkChecks.find((c) => c.nextCheckDate).map((c) => [empName(c.employeeId), global.HR.format.date(c.nextCheckDate), c.performedBy])
    },
    {
      id: "immigration-expiry", title: "Immigration Expiry Report", icon: "bi-passport", description: "Time-limited immigration permissions and their expiry dates.",
      columns: ["Name", "Immigration Category", "Permission Expiry"],
      rows: () => global.HR.db.rightToWorkChecks.find((c) => c.permissionExpiry).map((c) => [empName(c.employeeId), c.immigrationCategory, global.HR.format.date(c.permissionExpiry)])
    },
    {
      id: "sponsored-register", title: "Sponsored Worker Register", icon: "bi-shield-check", description: "All employees currently on a sponsored worker route.",
      columns: ["Name", "Route", "SOC Code", "CoS End Date", "Status"],
      rows: () => global.HR.db.sponsorshipRecords.all().map((s) => [empName(s.employeeId), s.workerRoute, s.socCode, global.HR.format.date(s.cosEndDate), s.sponsorshipStatus])
    },
    {
      id: "attendance", title: "Attendance Report", icon: "bi-calendar-check", description: "Attendance records across the workforce.",
      columns: ["Name", "Date", "Status", "Location", "Hours"],
      rows: () => global.HR.db.attendance.all().sort((a, b) => new Date(b.date) - new Date(a.date)).map((a) => [empName(a.employeeId), a.date, a.status, a.workLocation, a.hours])
    },
    {
      id: "absence", title: "Absence Report", icon: "bi-clipboard-x", description: "Recorded absence events and authorisation status.",
      columns: ["Name", "Date", "Type", "Authorised", "Follow-up Required"],
      rows: () => global.HR.db.absence.all().map((a) => [empName(a.employeeId), a.date, a.absenceType, a.authorised ? "Yes" : "No", a.followUpRequired ? "Yes" : "No"])
    },
    {
      id: "leave", title: "Leave Report", icon: "bi-airplane", description: "Leave requests and their approval status.",
      columns: ["Name", "Type", "From", "To", "Status"],
      rows: () => global.HR.db.leaveRequests.all().map((l) => [empName(l.employeeId), l.leaveType, l.from, l.to, l.status])
    },
    {
      id: "documents", title: "Employee Document Report", icon: "bi-folder2-open", description: "All documents currently on file.",
      columns: ["Title", "Employee", "Category", "Status", "Expiry Date"],
      rows: () => global.HR.db.documents.all().map((d) => [d.title, d.employeeId ? empName(d.employeeId) : "Company-wide", d.category, global.HR.compliance.documentStatus(d), d.expiryDate ? global.HR.format.date(d.expiryDate) : "N/A"])
    },
    {
      id: "expiring-documents", title: "Expiring Documents", icon: "bi-file-earmark-text", description: "Documents expiring within 90 days, or already expired.",
      columns: ["Title", "Employee", "Status", "Expiry Date"],
      rows: () => global.HR.db.documents.find((d) => d.expiryDate && global.HR.format.daysUntil(d.expiryDate) <= 90).map((d) => [d.title, d.employeeId ? empName(d.employeeId) : "Company-wide", global.HR.compliance.documentStatus(d), global.HR.format.date(d.expiryDate)])
    },
    {
      id: "salary-history", title: "Salary History", icon: "bi-cash-stack", description: "Historical salary changes across the workforce.",
      columns: ["Name", "Effective Date", "Salary", "Reason", "Approved By"],
      rows: () => global.HR.db.salaryRecords.all().sort((a, b) => new Date(b.effectiveDate) - new Date(a.effectiveDate)).map((s) => [empName(s.employeeId), s.effectiveDate, global.HR.format.currency(s.salary), s.reason, s.approvedBy])
    },
    {
      id: "job-role", title: "Job Role Report", icon: "bi-briefcase", description: "Current job records and role ownership.",
      columns: ["Job Title", "Department", "Employment Type", "Status", "Approved By"],
      rows: () => global.HR.db.jobs.all().map((j) => [j.title, global.HR.employees.departmentName(j.departmentId), j.employmentType, j.status, j.approvedBy])
    },
    {
      id: "work-location", title: "Work Location Report", icon: "bi-geo-alt", description: "Company work locations and assigned headcount.",
      columns: ["Site", "Type", "Status", "Employees Assigned"],
      rows: () => global.HR.db.workLocations.all().map((l) => [l.name, l.siteType, l.status, global.HR.db.employees.find((e) => e.workLocationId === l.id && e.status !== "Left").length])
    },
    {
      id: "outstanding-actions", title: "Outstanding Compliance Actions", icon: "bi-list-check", description: "All items currently in the compliance action centre.",
      columns: ["Employee", "Issue", "Priority", "Due Date"],
      rows: () => global.HR.compliance.getActionCentreItems().map((i) => [i.employee, i.issue, i.priority, i.dueDate ? global.HR.format.date(i.dueDate) : "—"])
    },
    {
      id: "sponsor-events", title: "Sponsor Events Register", icon: "bi-shield-exclamation", description: "All logged sponsor duty events.",
      columns: ["Worker", "Event Type", "Date Occurred", "Status"],
      rows: () => global.HR.db.sponsorEvents.all().map((e) => [empName(e.employeeId), e.eventType, global.HR.format.date(e.dateOccurred), e.status])
    },
    {
      id: "internal-review", title: "Internal Review Report", icon: "bi-journal-check", description: "Internal HR compliance review history.",
      columns: ["Review #", "Date", "Area", "Result"],
      rows: () => global.HR.db.complianceReviews.all().map((r) => [r.reviewNumber, global.HR.format.date(r.reviewDate), r.area, r.result])
    }
  ];

  global.HR = global.HR || {};
  global.HR.reports = { catalogue: REPORTS };
})(window);

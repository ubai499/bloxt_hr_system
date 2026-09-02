/**
 * compliance.js
 * Shared compliance calculations used by the dashboard, Compliance workspace,
 * Right to Work and Sponsorship pages. Everything here is DERIVED from real
 * stored records (dates, statuses) — nothing is fabricated or backdated.
 */
(function (global) {
  "use strict";

  function documentStatus(doc) {
    if (!doc.expiryDate) return doc.status === "Archived" ? "Archived" : "Valid";
    const days = global.HR.format.daysUntil(doc.expiryDate);
    if (days < 0) return "Expired";
    if (days <= 14) return "Expiring Soon";
    if (days <= 90) return "Review Due";
    return "Valid";
  }

  function rtwStatus(check) {
    if (!check.permissionExpiry) return check.status || "Valid";
    const days = global.HR.format.daysUntil(check.permissionExpiry);
    if (days < 0) return "Expired";
    if (days <= 30) return "Expiring Soon";
    if (days <= 90) return "Review Due";
    return check.status === "Follow-up Required" ? "Follow-up Required" : "Valid";
  }

  function latestRtwCheck(employeeId) {
    const checks = global.HR.db.rightToWorkChecks.find((c) => c.employeeId === employeeId);
    if (!checks.length) return null;
    return checks.sort((a, b) => new Date(b.checkDate) - new Date(a.checkDate))[0];
  }

  function employeeMissingInfo(emp) {
    const missing = [];
    if (!emp.address || !emp.postcode) missing.push("Current address");
    if (!emp.emergencyContact || !emp.emergencyPhone) missing.push("Emergency contact");
    if (!emp.mobile) missing.push("Mobile number");
    if (!emp.workingPattern) missing.push("Working pattern");
    return missing;
  }

  // ---------------------------------------------------------------------------
  // Action Centre — HR / compliance "action required" feed
  // ---------------------------------------------------------------------------
  function getActionCentreItems() {
    const db = global.HR.db;
    const items = [];
    const employees = db.employees.find((e) => e.status !== "Left");

    employees.forEach((emp) => {
      const name = `${emp.firstName} ${emp.lastName}`;

      const rtw = latestRtwCheck(emp.id);
      if (rtw) {
        const status = rtwStatus(rtw);
        if (["Review Due", "Expiring Soon", "Follow-up Required", "Expired"].includes(status)) {
          items.push({
            employeeId: emp.id, employee: name, issue: "Right-to-work follow-up due",
            priority: status === "Expired" || status === "Expiring Soon" ? "High" : "Medium",
            dueDate: rtw.permissionExpiry || rtw.nextCheckDate, responsible: rtw.performedBy || "HR Administrator",
            href: `right-to-work.html?employee=${emp.id}`
          });
        }
      }

      db.documents.find((d) => d.employeeId === emp.id).forEach((doc) => {
        const status = documentStatus(doc);
        if (status === "Expiring Soon" || status === "Expired") {
          items.push({
            employeeId: emp.id, employee: name, issue: `Document approaching expiry ${doc.title}`,
            priority: status === "Expired" ? "High" : "Medium", dueDate: doc.expiryDate,
            responsible: doc.uploadedBy || "HR Administrator", href: `documents.html?highlight=${doc.id}`
          });
        }
      });

      const missing = employeeMissingInfo(emp);
      if (missing.length) {
        items.push({
          employeeId: emp.id, employee: name, issue: `Employee details incomplete ${missing.join(", ")}`,
          priority: "Low", dueDate: null, responsible: "HR Administrator", href: `employee-profile.html?id=${emp.id}&tab=overview`
        });
      }

      db.absence.find((a) => a.employeeId === emp.id && a.followUpRequired).forEach((a) => {
        items.push({
          employeeId: emp.id, employee: name, issue: "Unexplained absence requiring review",
          priority: "High", dueDate: a.date, responsible: "Line manager", href: `attendance.html?tab=absence&highlight=${a.id}`
        });
      });

      db.policyAcknowledgements.find((ack) => ack.employeeId === emp.id && ack.status === "Outstanding").forEach((ack) => {
        const policy = db.policies.get(ack.policyId);
        items.push({
          employeeId: emp.id, employee: name, issue: `Policy acknowledgement outstanding ${policy ? policy.name : "Company policy"}`,
          priority: "Low", dueDate: null, responsible: "Employee", href: `employee-profile.html?id=${emp.id}&tab=notes`
        });
      });
    });

    db.sponsorEvents.find((ev) => ev.status === "Requires Review").forEach((ev) => {
      const emp = db.employees.get(ev.employeeId);
      items.push({
        employeeId: ev.employeeId, employee: emp ? `${emp.firstName} ${emp.lastName}` : "Unknown",
        issue: `Sponsorship event requires review ${ev.eventType}`, priority: "High",
        dueDate: ev.reportingDeadline, responsible: ev.assignedTo, href: `sponsorship.html?tab=events&highlight=${ev.id}`
      });
    });

    return items.sort((a, b) => {
      const order = { High: 0, Medium: 1, Low: 2 };
      if (order[a.priority] !== order[b.priority]) return order[a.priority] - order[b.priority];
      if (!a.dueDate) return 1;
      if (!b.dueDate) return -1;
      return new Date(a.dueDate) - new Date(b.dueDate);
    });
  }

  // ---------------------------------------------------------------------------
  // Compliance calendar events
  // ---------------------------------------------------------------------------
  function getCalendarEvents() {
    const db = global.HR.db;
    const events = [];
    const empName = (id) => { const e = db.employees.get(id); return e ? `${e.firstName} ${e.lastName}` : ""; };

    db.rightToWorkChecks.find((c) => c.nextCheckDate).forEach((c) => {
      events.push({ date: c.nextCheckDate, type: "Right-to-work follow-up", label: `RTW follow-up ${empName(c.employeeId)}`, tone: "warning" });
    });
    db.documents.find((d) => d.expiryDate).forEach((d) => {
      events.push({ date: d.expiryDate, type: "Document renewal", label: `${d.title}`, tone: documentStatus(d) === "Expired" ? "danger" : "warning" });
    });
    db.employees.find((e) => e.endDate).forEach((e) => {
      events.push({ date: e.endDate, type: "Contract expiry", label: `Contract end ${e.firstName} ${e.lastName}`, tone: "danger" });
    });
    db.employees.find((e) => e.probationEndDate).forEach((e) => {
      events.push({ date: e.probationEndDate, type: "Probation review", label: `Probation review ${e.firstName} ${e.lastName}`, tone: "info" });
    });
    db.sponsorshipRecords.find((s) => s.nextReviewDate).forEach((s) => {
      events.push({ date: s.nextReviewDate, type: "Sponsorship review", label: `Sponsorship review ${empName(s.employeeId)}`, tone: "warning" });
    });
    db.complianceReviews.find((r) => r.dueDate && !r.completionDate).forEach((r) => {
      events.push({ date: r.dueDate, type: "Internal review due", label: r.reviewNumber, tone: "info" });
    });
    return events;
  }

  // ---------------------------------------------------------------------------
  // Dashboard-level compliance metrics
  // ---------------------------------------------------------------------------
  function getComplianceMetrics() {
    const db = global.HR.db;
    const employees = db.employees.find((e) => e.status !== "Left");

    const rtwDue = employees.filter((e) => {
      const rtw = latestRtwCheck(e.id);
      return rtw && ["Review Due", "Expiring Soon", "Follow-up Required", "Expired"].includes(rtwStatus(rtw));
    }).length;

    const immigrationNearExpiry = db.rightToWorkChecks.find((c) => c.permissionExpiry && global.HR.format.daysUntil(c.permissionExpiry) <= 90 && global.HR.format.daysUntil(c.permissionExpiry) >= 0).length;

    const missingInfoCount = employees.filter((e) => employeeMissingInfo(e).length > 0).length;

    const unresolvedAttendance = db.attendance.find((a) => a.managerReviewed === false).length;

    const docsExpiring = db.documents.find((d) => ["Expiring Soon", "Expired"].includes(documentStatus(d))).length;

    const sponsorEventsUnderReview = db.sponsorEvents.find((e) => e.status === "Requires Review").length;

    const overdueReviews = db.complianceReviews.find((r) => r.dueDate && !r.completionDate && global.HR.format.daysUntil(r.dueDate) < 0).length;

    const actionRequired = getActionCentreItems().length;

    return { actionRequired, rtwDue, immigrationNearExpiry, missingInfoCount, unresolvedAttendance, docsExpiring, sponsorEventsUnderReview, overdueReviews };
  }

  // ---------------------------------------------------------------------------
  // Employee compliance checklist (Section 40)
  // ---------------------------------------------------------------------------
  function getEmployeeChecklist(employeeId) {
    const db = global.HR.db;
    const emp = db.employees.get(employeeId);
    if (!emp) return [];
    const docs = db.documents.find((d) => d.employeeId === employeeId);
    const hasDoc = (cat) => docs.some((d) => d.category === cat);
    const rtw = latestRtwCheck(employeeId);
    const salary = db.salaryRecords.find((s) => s.employeeId === employeeId);
    const sponsorship = db.sponsorshipRecords.find((s) => s.employeeId === employeeId)[0];

    return [
      { label: "Personal details complete", complete: !!(emp.firstName && emp.lastName && emp.dob && emp.nationality) },
      { label: "Current address recorded", complete: !!(emp.address && emp.postcode) },
      { label: "Contact details current", complete: !!emp.mobile && !!emp.contactVerifiedDate },
      { label: "Emergency contact recorded", complete: !!(emp.emergencyContact && emp.emergencyPhone) },
      { label: "Employment contract on file", complete: hasDoc("Employment Contract") },
      { label: "Job description on file", complete: hasDoc("Job Description") },
      { label: "Working hours recorded", complete: !!emp.weeklyHours },
      { label: "Work location recorded", complete: !!emp.workLocationId },
      { label: "Salary information recorded", complete: salary.length > 0 },
      { label: "Right-to-work record on file", complete: !!rtw },
      { label: "Attendance records present", complete: db.attendance.find((a) => a.employeeId === employeeId).length > 0 },
      { label: "Leave record present", complete: typeof emp.leaveAllowance === "number" },
      { label: "Sponsor information recorded (if applicable)", complete: !emp.sponsored || !!sponsorship }
    ];
  }

  global.HR = global.HR || {};
  global.HR.compliance = {
    documentStatus, rtwStatus, latestRtwCheck, employeeMissingInfo,
    getActionCentreItems, getCalendarEvents, getComplianceMetrics, getEmployeeChecklist
  };
})(window);

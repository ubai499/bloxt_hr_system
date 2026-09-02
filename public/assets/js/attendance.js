/**
 * attendance.js
 * Helpers for the Attendance & Absence workspace.
 */
(function (global) {
  "use strict";

  function getAttendanceAlerts() {
    const db = global.HR.db;
    const alerts = [];
    const today = new Date().toISOString().slice(0, 10);
    const employees = db.employees.find((e) => e.status === "Active");

    employees.forEach((emp) => {
      const records = db.attendance.find((a) => a.employeeId === emp.id).sort((a, b) => new Date(b.date) - new Date(a.date));
      if (records.length === 0) {
        alerts.push({ employeeId: emp.id, type: "No attendance recorded", detail: `No attendance history has been recorded for ${emp.firstName} ${emp.lastName}.`, tone: "info" });
        return;
      }

      const latest = records[0];
      if (latest.date < today && ["Present", "Office", "Remote"].indexOf(latest.status) === -1) {
        // not necessarily a problem — could be planned leave; only flag unauthorised/unreviewed
      }

      const unauthorised = records.filter((r) => r.status === "Unauthorised Absence");
      if (unauthorised.length >= 1) {
        const unresolved = unauthorised.filter((r) => !r.managerReviewed);
        if (unresolved.length) {
          alerts.push({
            employeeId: emp.id,
            type: unauthorised.length > 1 ? "Repeated unexplained absence" : "Employee has missed expected working day",
            detail: `${emp.firstName} ${emp.lastName} has ${unresolved.length} unreviewed unauthorised absence record(s).`,
            tone: unauthorised.length > 1 ? "danger" : "warning",
            date: unresolved[0].date
          });
        }
      }

      if (emp.sponsored) {
        const unresolvedAbsence = db.absence.find((a) => a.employeeId === emp.id && a.followUpRequired);
        if (unresolvedAbsence.length) {
          alerts.push({
            employeeId: emp.id, type: "Sponsored worker has an unresolved absence record",
            detail: `${emp.firstName} ${emp.lastName} is a sponsored worker with an absence record requiring follow-up.`,
            tone: "warning", date: unresolvedAbsence[0].date
          });
        }
      }

      const pendingReview = records.filter((r) => !r.managerReviewed);
      if (pendingReview.length) {
        alerts.push({
          employeeId: emp.id, type: "Attendance information awaiting manager confirmation",
          detail: `${pendingReview.length} attendance record(s) for ${emp.firstName} ${emp.lastName} await manager review.`,
          tone: "info", date: pendingReview[0].date
        });
      }
    });

    return alerts;
  }

  global.HR = global.HR || {};
  global.HR.attendanceHelpers = { getAttendanceAlerts };
})(window);

/**
 * leave.js
 * Helpers for the Leave Management workspace.
 */
(function (global) {
  "use strict";

  function remainingAllowance(emp) {
    return (emp.leaveAllowance || 0) - (emp.leaveTakenDays || 0) - (emp.leaveBookedDays || 0);
  }

  function businessDaysBetween(fromISO, toISO) {
    let count = 0;
    const d = new Date(fromISO);
    const end = new Date(toISO);
    while (d <= end) {
      if (d.getDay() !== 0 && d.getDay() !== 6) count++;
      d.setDate(d.getDate() + 1);
    }
    return count;
  }

  function approveLeave(requestId, approverName) {
    const req = global.HR.db.leaveRequests.get(requestId);
    if (!req) return;
    global.HR.db.leaveRequests.update(requestId, { status: "Approved", approvedBy: approverName });
    const emp = global.HR.db.employees.get(req.employeeId);
    if (emp) {
      const days = businessDaysBetween(req.from, req.to);
      global.HR.db.employees.update(emp.id, { leaveBookedDays: (emp.leaveBookedDays || 0) + days });
    }
    global.HR.audit.log({ employeeId: req.employeeId, action: "Leave approved", module: "Leave", description: `${req.leaveType} approved for ${req.from} to ${req.to}.` });
  }

  function rejectLeave(requestId, approverName) {
    const req = global.HR.db.leaveRequests.get(requestId);
    if (!req) return;
    global.HR.db.leaveRequests.update(requestId, { status: "Rejected", approvedBy: approverName });
    global.HR.audit.log({ employeeId: req.employeeId, action: "Leave rejected", module: "Leave", description: `${req.leaveType} request for ${req.from} to ${req.to} was rejected.` });
  }

  function cancelLeave(requestId) {
    const req = global.HR.db.leaveRequests.get(requestId);
    if (!req) return;
    if (req.status === "Approved") {
      const emp = global.HR.db.employees.get(req.employeeId);
      if (emp) {
        const days = businessDaysBetween(req.from, req.to);
        global.HR.db.employees.update(emp.id, { leaveBookedDays: Math.max(0, (emp.leaveBookedDays || 0) - days) });
      }
    }
    global.HR.db.leaveRequests.update(requestId, { status: "Cancelled" });
    global.HR.audit.log({ employeeId: req.employeeId, action: "Leave cancelled", module: "Leave", description: `${req.leaveType} request for ${req.from} to ${req.to} was cancelled.` });
  }

  global.HR = global.HR || {};
  global.HR.leaveHelpers = { remainingAllowance, businessDaysBetween, approveLeave, rejectLeave, cancelLeave };
})(window);

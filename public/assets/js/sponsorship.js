/**
 * sponsorship.js
 * Helpers for the Sponsor Compliance Workspace.
 */
(function (global) {
  "use strict";

  function sponsoredWorkerSummary(emp) {
    const db = global.HR.db;
    const record = db.sponsorshipRecords.find((s) => s.employeeId === emp.id)[0];
    const rtw = global.HR.compliance.latestRtwCheck(emp.id);
    const rtwStatus = rtw ? global.HR.compliance.rtwStatus(rtw) : "Evidence Missing";
    const contactCurrent = !!emp.contactVerifiedDate && global.HR.format.daysUntil(emp.contactVerifiedDate) > -365;
    const missing = global.HR.compliance.employeeMissingInfo(emp);

    let overall = "Current";
    if (!record || rtwStatus === "Expired" || rtwStatus === "Evidence Missing" || missing.length > 0) overall = "Action Required";
    else if (["Review Due", "Expiring Soon", "Follow-up Required"].includes(rtwStatus)) overall = "Review Required";

    return { record, rtwStatus, contactCurrent, missing, overall };
  }

  global.HR = global.HR || {};
  global.HR.sponsorshipHelpers = { sponsoredWorkerSummary };
})(window);

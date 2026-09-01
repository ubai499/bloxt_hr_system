/**
 * employees.js
 * Shared helpers for the Employee Directory, Add Employee wizard and
 * Employee Profile pages.
 */
(function (global) {
  "use strict";

  const db = () => global.HR.db;

  function departmentName(id) {
    const d = db().departments.get(id);
    return d ? d.name : "—";
  }

  function locationName(id) {
    const l = db().workLocations.get(id);
    return l ? l.name : "—";
  }

  function managerName(id) {
    if (!id) return "—";
    const m = db().employees.get(id);
    return m ? `${m.firstName} ${m.lastName}` : "—";
  }

  function fullName(emp) {
    return `${emp.firstName} ${emp.middleName ? emp.middleName + " " : ""}${emp.lastName}`;
  }

  function rtwStatusFor(employeeId) {
    const check = global.HR.compliance.latestRtwCheck(employeeId);
    if (!check) return "Evidence Missing";
    return global.HR.compliance.rtwStatus(check);
  }

  function directRatingsForManager(managerId) {
    return db().employees.find((e) => e.managerId === managerId && e.status !== "Left");
  }

  /**
   * Create a full employee record (plus linked salary / RTW / document
   * metadata rows) from the Add Employee wizard's collected form data.
   */
  function createEmployeeFromWizard(data) {
    const emp = db().employees.create({
      employeeNumber: data.employeeNumber, title: data.title, firstName: data.firstName,
      middleName: data.middleName || "", lastName: data.lastName, preferredName: data.preferredName || data.firstName,
      personalEmail: data.personalEmail, companyEmail: data.companyEmail, mobile: data.mobile,
      dob: data.dob, nationality: data.nationality, address: data.address, postcode: data.postcode,
      emergencyContact: data.emergencyContact, emergencyRelationship: data.emergencyRelationship, emergencyPhone: data.emergencyPhone,
      jobTitle: data.jobTitle, departmentId: data.departmentId, managerId: data.managerId || null,
      employmentType: data.employmentType, startDate: data.startDate, endDate: data.endDate || null,
      probationEndDate: data.probationEndDate || null, workLocationId: data.workLocationId,
      workArrangement: data.workArrangement, weeklyHours: Number(data.weeklyHours) || 37.5,
      status: "Active", sponsored: data.rtwRequired === "yes" && data.rtwCheckType === "Online Home Office check" && !!data.permissionExpiry,
      contactVerifiedDate: global.HR.util.nowISO().slice(0, 10), contactVerifiedBy: "HR Administrator",
      leaveAllowance: 25, leaveTakenDays: 0, leaveBookedDays: 0,
      workingPattern: {
        monday: "09:00-17:30", tuesday: "09:00-17:30", wednesday: "09:00-17:30",
        thursday: "09:00-17:30", friday: "09:00-17:30", saturday: null, sunday: null, breakMinutes: 30
      }
    });

    db().salaryRecords.create({
      employeeId: emp.id, salary: Number(data.annualSalary) || 0, basis: data.salaryFrequency || "Annual",
      contractedHours: Number(data.weeklyHours) || 37.5, effectiveDate: data.salaryEffectiveDate || data.startDate,
      reason: "Starting salary", approvedBy: data.authorisedBy || "HR Administrator", recordedBy: "HR Administrator", previousSalary: null
    });

    if (data.rtwRequired === "yes") {
      db().rightToWorkChecks.create({
        employeeId: emp.id, checkDate: data.rtwCheckDate || data.startDate, checkMethod: data.rtwCheckType,
        performedBy: data.rtwCheckedBy || "HR Administrator", createdBy: "HR Administrator",
        immigrationCategory: data.immigrationStatus || "British Citizen",
        permissionStart: data.permissionStart || null, permissionExpiry: data.permissionExpiry || null,
        restrictions: null, followUpRequired: data.rtwFollowUp === "yes", nextCheckDate: data.rtwNextReview || null,
        evidenceRef: data.evidenceRef || "On file", status: data.permissionExpiry ? "Review Due" : "Valid",
        notes: data.rtwNotes || ""
      });
    }

    (data.documentTitles || []).forEach((title) => {
      db().documents.create({
        employeeId: emp.id, title, category: "Recruitment", employeeIdRef: emp.id,
        issueDate: data.startDate, expiryDate: null, uploadedBy: "HR Administrator", uploadDate: global.HR.util.nowISO().slice(0, 10),
        reviewDate: null, status: "Valid", notes: "Uploaded during onboarding.", version: 1, accessClassification: "Confidential"
      });
    });

    global.HR.audit.log({
      employeeId: emp.id, action: "Employee created", module: "Employees", recordType: "Employee", recordId: emp.id,
      description: `New employee record created for ${fullName(emp)}.`
    });

    return emp;
  }

  function recordFieldChange(employeeId, field, previousValue, newValue, reason) {
    global.HR.audit.log({
      employeeId, action: `${field} changed`, module: "Employees", recordType: "Employee", recordId: employeeId,
      description: `${field} updated${reason ? " " + reason : ""}.`, previousValue: String(previousValue), newValue: String(newValue)
    });
  }

  global.HR = global.HR || {};
  global.HR.employees = {
    departmentName, locationName, managerName, fullName, rtwStatusFor, directRatingsForManager,
    createEmployeeFromWizard, recordFieldChange
  };
})(window);

/**
 * storage.js
 * -----------------------------------------------------------------------------
 * LocalStorage-backed persistence layer for the Bloxt HR prototype.
 *
 * Architecture note: this module is written as a thin repository layer
 * (HR.db.<collection>.all()/get()/create()/update()/remove()) precisely so
 * that a real backend/API can be swapped in later without touching page code.
 * In production none of this should live in LocalStorage — session tokens,
 * salary, immigration and identity data must be served from an authenticated
 * server API with proper access control, encryption at rest and audit
 * logging enforced server-side. Frontend role checks in this prototype
 * (see HR.auth.can) are for UI convenience only and are NOT a security
 * boundary.
 * -----------------------------------------------------------------------------
 */
(function (global) {
  "use strict";

  const STORAGE_PREFIX = "bloxthr_";
  const SEED_VERSION = "2026.2";

  // ---------------------------------------------------------------------------
  // Low-level storage helpers
  // ---------------------------------------------------------------------------
  function readRaw(key, fallback) {
    try {
      const raw = window.localStorage.getItem(STORAGE_PREFIX + key);
      return raw === null ? fallback : JSON.parse(raw);
    } catch (err) {
      console.error("Storage read failed for", key, err);
      return fallback;
    }
  }

  function writeRaw(key, value) {
    try {
      window.localStorage.setItem(STORAGE_PREFIX + key, JSON.stringify(value));
    } catch (err) {
      console.error("Storage write failed for", key, err);
    }
  }

  let idCounters = readRaw("id_counters", {});
  function nextId(prefix) {
    idCounters[prefix] = (idCounters[prefix] || 0) + 1;
    writeRaw("id_counters", idCounters);
    return prefix + "-" + String(idCounters[prefix]).padStart(4, "0");
  }

  function nowISO() {
    return new Date().toISOString();
  }

  // ---------------------------------------------------------------------------
  // Generic collection factory
  // ---------------------------------------------------------------------------
  function makeCollection(key, idPrefix) {
    return {
      all() {
        return readRaw(key, []);
      },
      get(id) {
        return this.all().find((r) => r.id === id) || null;
      },
      find(predicate) {
        return this.all().filter(predicate);
      },
      create(record) {
        const rows = this.all();
        const withMeta = Object.assign(
          { id: nextId(idPrefix), createdAt: nowISO(), updatedAt: nowISO() },
          record
        );
        rows.push(withMeta);
        writeRaw(key, rows);
        return withMeta;
      },
      /** Insert a fully-formed record (used by the demo seeder to control ids). */
      insertRaw(record) {
        const rows = this.all();
        rows.push(record);
        writeRaw(key, rows);
        return record;
      },
      update(id, patch) {
        const rows = this.all();
        const idx = rows.findIndex((r) => r.id === id);
        if (idx === -1) return null;
        rows[idx] = Object.assign({}, rows[idx], patch, { updatedAt: nowISO() });
        writeRaw(key, rows);
        return rows[idx];
      },
      remove(id) {
        const rows = this.all().filter((r) => r.id !== id);
        writeRaw(key, rows);
      },
      replaceAll(rows) {
        writeRaw(key, rows);
      },
      clear() {
        writeRaw(key, []);
      }
    };
  }

  // ---------------------------------------------------------------------------
  // Reference data / enums
  // ---------------------------------------------------------------------------
  const ROLES = {
    SUPER_ADMIN: "super_admin",
    DIRECTOR: "director",
    HR_ADMIN: "hr_admin",
    COMPLIANCE_ADMIN: "compliance_admin",
    MANAGER: "manager",
    EMPLOYEE: "employee",
    AUDITOR: "auditor"
  };

  const ROLE_LABELS = {
    [ROLES.SUPER_ADMIN]: "Super Administrator",
    [ROLES.DIRECTOR]: "Company Director",
    [ROLES.HR_ADMIN]: "HR Administrator",
    [ROLES.COMPLIANCE_ADMIN]: "Compliance Administrator",
    [ROLES.MANAGER]: "Manager",
    [ROLES.EMPLOYEE]: "Employee",
    [ROLES.AUDITOR]: "Read-only Auditor"
  };

  // Permission matrix consumed by HR.auth.can() -------------------------------
  const PERMISSIONS = {
    VIEW_ALL_EMPLOYEES: [ROLES.SUPER_ADMIN, ROLES.DIRECTOR, ROLES.HR_ADMIN, ROLES.COMPLIANCE_ADMIN, ROLES.AUDITOR],
    VIEW_TEAM_EMPLOYEES: [ROLES.MANAGER],
    EDIT_EMPLOYEE: [ROLES.SUPER_ADMIN, ROLES.HR_ADMIN],
    VIEW_SALARY: [ROLES.SUPER_ADMIN, ROLES.DIRECTOR, ROLES.HR_ADMIN],
    EDIT_SALARY: [ROLES.SUPER_ADMIN, ROLES.HR_ADMIN],
    VIEW_IMMIGRATION: [ROLES.SUPER_ADMIN, ROLES.HR_ADMIN, ROLES.COMPLIANCE_ADMIN, ROLES.DIRECTOR, ROLES.AUDITOR],
    EDIT_IMMIGRATION: [ROLES.SUPER_ADMIN, ROLES.HR_ADMIN, ROLES.COMPLIANCE_ADMIN],
    VIEW_COMPLIANCE: [ROLES.SUPER_ADMIN, ROLES.HR_ADMIN, ROLES.COMPLIANCE_ADMIN, ROLES.DIRECTOR, ROLES.AUDITOR],
    EDIT_COMPLIANCE: [ROLES.SUPER_ADMIN, ROLES.COMPLIANCE_ADMIN, ROLES.HR_ADMIN],
    VIEW_AUDIT_LOG: [ROLES.SUPER_ADMIN, ROLES.DIRECTOR, ROLES.HR_ADMIN, ROLES.COMPLIANCE_ADMIN, ROLES.AUDITOR],
    MANAGE_USERS: [ROLES.SUPER_ADMIN],
    MANAGE_SETTINGS: [ROLES.SUPER_ADMIN, ROLES.HR_ADMIN],
    APPROVE_LEAVE: [ROLES.SUPER_ADMIN, ROLES.HR_ADMIN, ROLES.MANAGER],
    VIEW_REPORTS: [ROLES.SUPER_ADMIN, ROLES.DIRECTOR, ROLES.HR_ADMIN, ROLES.COMPLIANCE_ADMIN, ROLES.AUDITOR, ROLES.MANAGER],
    VIEW_PAYROLL: [ROLES.SUPER_ADMIN, ROLES.HR_ADMIN, ROLES.DIRECTOR],
    SELF_SERVICE: [ROLES.EMPLOYEE]
  };

  const EMPLOYMENT_TYPES = ["Permanent", "Fixed-term", "Part-time", "Temporary", "Contractor", "Intern"];
  const WORK_ARRANGEMENTS = ["Office", "Hybrid", "Remote"];
  const EMPLOYMENT_STATUSES = ["Active", "On Leave", "Probation", "Notice Period", "Inactive", "Left"];

  const RTW_STATUSES = ["Valid", "Review Due", "Expiring Soon", "Expired", "Evidence Missing", "Follow-up Required", "Not Applicable"];
  const RTW_CHECK_METHODS = ["Online Home Office check", "Manual document check", "Other permitted method"];

  const DOCUMENT_CATEGORIES = [
    "Identity", "Right to Work", "Employment Contract", "Job Description", "Recruitment",
    "Qualifications", "Professional Accreditation", "Immigration", "Payroll", "Policies",
    "Training", "Performance", "Sponsorship", "Other"
  ];

  const DOCUMENT_STATUSES = ["Valid", "Review Due", "Expiring Soon", "Expired", "Archived"];
  const DATA_CLASSIFICATIONS = ["Normal", "Confidential", "Highly Confidential"];

  const ATTENDANCE_STATUSES = [
    "Present", "Remote", "Office", "Approved Leave", "Sick", "Authorised Absence",
    "Unpaid Leave", "Business Travel", "Training", "Unauthorised Absence", "Other"
  ];

  const LEAVE_TYPES = ["Annual leave", "Sick leave", "Unpaid leave", "Compassionate leave", "Parental leave", "Other leave"];
  const LEAVE_STATUSES = ["Pending", "Approved", "Rejected", "Cancelled"];

  const SPONSOR_EVENT_TYPES = [
    "Change of work location", "Salary change", "Change in contracted hours", "Role/job change",
    "Extended/unexplained absence", "Employment termination", "Worker does not start employment",
    "Change in immigration status", "Organisation change", "Other"
  ];
  const SPONSOR_EVENT_STATUSES = ["Not Reportable", "Requires Review", "Report Required", "Reported"];

  const TASK_STATUSES = ["Open", "In Progress", "Awaiting Information", "Completed", "Cancelled"];
  const TASK_PRIORITIES = ["High", "Medium", "Low"];

  // ---------------------------------------------------------------------------
  // Collections
  // ---------------------------------------------------------------------------
  const db = {
    users: makeCollection("users", "USR"),
    employees: makeCollection("employees", "BE"),
    departments: makeCollection("departments", "DEPT"),
    jobs: makeCollection("jobs", "JOB"),
    workLocations: makeCollection("work_locations", "LOC"),
    attendance: makeCollection("attendance", "ATT"),
    absence: makeCollection("absence", "ABS"),
    leaveRequests: makeCollection("leave_requests", "LV"),
    documents: makeCollection("documents", "DOC"),
    rightToWorkChecks: makeCollection("rtw_checks", "RTW"),
    sponsorshipRecords: makeCollection("sponsorship_records", "SPN"),
    sponsorEvents: makeCollection("sponsor_events", "SEV"),
    companyChanges: makeCollection("company_changes", "CCH"),
    complianceReviews: makeCollection("compliance_reviews", "REV"),
    complianceRules: makeCollection("compliance_rules", "RULE"),
    guidanceReferences: makeCollection("guidance_references", "GDN"),
    policies: makeCollection("policies", "POL"),
    policyAcknowledgements: makeCollection("policy_acks", "ACK"),
    salaryRecords: makeCollection("salary_records", "SAL"),
    payrollRecords: makeCollection("payroll_records", "PAY"),
    vacancies: makeCollection("vacancies", "VAC"),
    candidates: makeCollection("candidates", "CAN"),
    tasks: makeCollection("tasks", "TSK"),
    notifications: makeCollection("notifications", "NTF"),
    auditLog: makeCollection("audit_log", "AUD")
  };

  function getCompany() {
    return readRaw("company", null);
  }
  function setCompany(company) {
    writeRaw("company", Object.assign({}, getCompany(), company, { updatedAt: nowISO() }));
  }

  // ---------------------------------------------------------------------------
  // Audit logging — single source of truth for change history & timelines
  // ---------------------------------------------------------------------------
  function logAudit(entry) {
    const user = auth.getCurrentUser();
    return db.auditLog.create(Object.assign(
      {
        timestamp: nowISO(),
        userId: user ? user.id : null,
        userName: user ? user.name : "System",
        device: "Web session (browser prototype)"
      },
      entry
    ));
  }

  function getEmployeeHistory(employeeId) {
    return db.auditLog
      .find((e) => e.employeeId === employeeId)
      .sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
  }

  // ---------------------------------------------------------------------------
  // Auth / session
  // ---------------------------------------------------------------------------
  const SESSION_KEY = "session";

  const auth = {
    login(email, password) {
      const user = db.users.find((u) => u.email.toLowerCase() === String(email).toLowerCase())[0];
      if (!user || user.password !== password) return { ok: false, error: "Invalid work email or password." };
      if (user.status === "Inactive") return { ok: false, error: "This account has been deactivated." };
      writeRaw(SESSION_KEY, { userId: user.id, loggedInAt: nowISO() });
      logAudit({ action: "User signed in", module: "Authentication", recordType: "User", recordId: user.id, description: `${user.name} signed in.` });
      return { ok: true, user };
    },
    logout() {
      const user = auth.getCurrentUser();
      if (user) {
        logAudit({ action: "User signed out", module: "Authentication", recordType: "User", recordId: user.id, description: `${user.name} signed out.` });
      }
      writeRaw(SESSION_KEY, null);
    },
    getCurrentUser() {
      const session = readRaw(SESSION_KEY, null);
      if (!session) return null;
      return db.users.get(session.userId);
    },
    requireAuth() {
      const user = auth.getCurrentUser();
      if (!user) {
        window.location.href = "login.html";
        return null;
      }
      return user;
    },
    can(permissionKey) {
      const user = auth.getCurrentUser();
      if (!user) return false;
      const allowed = PERMISSIONS[permissionKey];
      return Array.isArray(allowed) && allowed.includes(user.role);
    },
    hasRole() {
      const user = auth.getCurrentUser();
      if (!user) return false;
      return Array.prototype.slice.call(arguments).includes(user.role);
    },
    employeeRecordFor(user) {
      if (!user || !user.employeeId) return null;
      return db.employees.get(user.employeeId);
    }
  };

  // ---------------------------------------------------------------------------
  // Demo data seeding
  // ---------------------------------------------------------------------------
  function daysFromNow(n) {
    const d = new Date();
    d.setHours(9, 0, 0, 0);
    d.setDate(d.getDate() + n);
    return d.toISOString().slice(0, 10);
  }

  function seedDemoData(force) {
    const seededVersion = readRaw("seed_version", null);
    if (seededVersion === SEED_VERSION && !force) return;

    // Wipe all collections before reseeding ------------------------------------
    Object.keys(db).forEach((k) => db[k].clear());
    idCounters = {};
    writeRaw("id_counters", {});

    setCompany({
      legalName: "Bloxt Limited",
      tradingName: "Bloxt",
      companiesHouseNumber: "09876543",
      registeredOffice: "4th Floor, Wellington House, 12 Aire Street, Leeds, LS1 4PR",
      tradingAddress: "4th Floor, Wellington House, 12 Aire Street, Leeds, LS1 4PR",
      telephone: "0113 555 0142",
      generalEmail: "info@bloxtenergy.co.uk",
      website: "www.bloxtenergy.co.uk",
      industry: "Energy & Utilities",
      payeReference: "120/BE45821",
      sponsorLicence: {
        status: "Valid",
        reference: "SPN-8842-2024",
        rating: "A-Rating",
        startDate: "2024-03-01",
        renewalReviewDate: "2027-03-01",
        workerRoutes: ["Skilled Worker"],
        authorisingOfficer: "Louise Farrington",
        keyContact: "Sarah Whitfield",
        level1User: "Sarah Whitfield",
        level2Users: ["Meera Iyer"],
        orgDetailsLastReviewed: "2026-04-10",
        nextInternalReviewDate: "2026-10-10",
        smsUrl: "https://www.gov.uk/sponsor-management-system"
      },
      documentExpiryReminderDays: [90, 60, 30, 14, 7]
    });

    // Departments ---------------------------------------------------------------
    const depts = [
      "Executive", "People & Culture", "Engineering", "Finance",
      "Marketing", "Customer Operations", "Operations", "Compliance & Governance"
    ].map((name) => db.departments.create({ name, status: "Active" }));
    const deptId = (name) => depts.find((d) => d.name === name).id;

    // Work locations --------------------------------------------------------------
    const locMain = db.workLocations.create({ name: "Bloxt House", address: "12 Aire Street, Leeds", postcode: "LS1 4PR", siteType: "Main Office", status: "Active" });
    const locClient = db.workLocations.create({ name: "Manchester Client Site", address: "3 Deansgate, Manchester", postcode: "M3 2FW", siteType: "Client Site", status: "Active" });
    const locRemote = db.workLocations.create({ name: "Remote / Home-based", address: "N/A", postcode: "N/A", siteType: "Remote", status: "Active" });

    // Jobs ------------------------------------------------------------------------
    const jobDefs = [
      { title: "HR Administrator", dept: "People & Culture", soc: null },
      { title: "Company Director", dept: "Executive", soc: null },
      { title: "Engineering Manager", dept: "Engineering", soc: "2133" },
      { title: "Finance Manager", dept: "Finance", soc: null },
      {
        title: "Software Developer", dept: "Engineering", soc: "2134", socTitle: "Programmers and software development professionals",
        responsibilities: [
          "Develop and maintain company web applications",
          "Develop backend application functionality",
          "Develop REST APIs",
          "Maintain production systems",
          "Database development and optimisation",
          "Integrate third-party services",
          "Resolve software defects",
          "Participate in code reviews",
          "Maintain technical documentation",
          "Support deployment and production maintenance"
        ]
      },
      { title: "Marketing Executive", dept: "Marketing", soc: null },
      { title: "Customer Support Advisor", dept: "Customer Operations", soc: null },
      { title: "Operations Assistant", dept: "Operations", soc: null },
      { title: "Compliance Administrator", dept: "Compliance & Governance", soc: null }
    ];
    const jobs = jobDefs.map((j) =>
      db.jobs.create({
        title: j.title,
        departmentId: deptId(j.dept),
        socCode: j.soc || null,
        socTitle: j.socTitle || null,
        employmentType: "Permanent",
        workLocationId: locMain.id,
        weeklyHours: 37.5,
        salaryRangeMin: null,
        salaryRangeMax: null,
        responsibilities: j.responsibilities || [],
        requiredSkills: [],
        qualifications: [],
        status: "Active",
        version: 1,
        approvedBy: "Louise Farrington",
        approvedDate: "2025-01-06"
      })
    );
    const jobFor = (title) => jobs.find((j) => j.title === title);

    // Users are created after employees so we can cross-link employeeId --------
    function makeEmployee(cfg) {
      const emp = db.employees.create(cfg);
      return emp;
    }

    const louise = makeEmployee({
      employeeNumber: "BE-0002", title: "Ms", firstName: "Louise", lastName: "Farrington", preferredName: "Louise",
      personalEmail: "louise.farrington@personalmail.example", companyEmail: "louise.farrington@bloxtenergy.co.uk",
      mobile: "07700 900201", dob: "1978-04-11", nationality: "British",
      address: "14 Meadowfield Close, Leeds", postcode: "LS6 2QF",
      emergencyContact: "Robert Farrington", emergencyRelationship: "Spouse", emergencyPhone: "07700 900202",
      jobTitle: "Company Director", departmentId: deptId("Executive"), managerId: null,
      employmentType: "Permanent", startDate: "2016-02-01", endDate: null, probationEndDate: null,
      workLocationId: locMain.id, workArrangement: "Hybrid", weeklyHours: 37.5,
      status: "Active", sponsored: false, contactVerifiedDate: "2026-06-01", contactVerifiedBy: "Sarah Whitfield"
    });

    const sarah = makeEmployee({
      employeeNumber: "BE-0001", title: "Ms", firstName: "Sarah", lastName: "Whitfield", preferredName: "Sarah",
      personalEmail: "s.whitfield@personalmail.example", companyEmail: "sarah.whitfield@bloxtenergy.co.uk",
      mobile: "07700 900211", dob: "1985-09-23", nationality: "British",
      address: "22 Kirkstall Road, Leeds", postcode: "LS3 1HP",
      emergencyContact: "Anna Whitfield", emergencyRelationship: "Sister", emergencyPhone: "07700 900212",
      jobTitle: "HR Administrator", departmentId: deptId("People & Culture"), managerId: louise.id,
      employmentType: "Permanent", startDate: "2019-06-10", endDate: null, probationEndDate: null,
      workLocationId: locMain.id, workArrangement: "Hybrid", weeklyHours: 37.5,
      status: "Active", sponsored: false, contactVerifiedDate: "2026-07-15", contactVerifiedBy: "Sarah Whitfield"
    });

    const david = makeEmployee({
      employeeNumber: "BE-0003", title: "Mr", firstName: "David", lastName: "Chen", preferredName: "David",
      personalEmail: "d.chen@personalmail.example", companyEmail: "david.chen@bloxtenergy.co.uk",
      mobile: "07700 900221", dob: "1982-01-30", nationality: "British",
      address: "8 Roundhay Road, Leeds", postcode: "LS8 4HE",
      emergencyContact: "Wei Chen", emergencyRelationship: "Parent", emergencyPhone: "07700 900222",
      jobTitle: "Engineering Manager", departmentId: deptId("Engineering"), managerId: louise.id,
      employmentType: "Permanent", startDate: "2018-03-19", endDate: null, probationEndDate: null,
      workLocationId: locMain.id, workArrangement: "Hybrid", weeklyHours: 37.5,
      status: "Active", sponsored: false, contactVerifiedDate: "2026-05-20", contactVerifiedBy: "Sarah Whitfield"
    });

    const james = makeEmployee({
      employeeNumber: "BE-0004", title: "Mr", firstName: "James", lastName: "Okafor", preferredName: "James",
      personalEmail: "j.okafor@personalmail.example", companyEmail: "james.okafor@bloxtenergy.co.uk",
      mobile: "07700 900231", dob: "1988-11-05", nationality: "British",
      address: "31 Chapel Allerton, Leeds", postcode: "LS7 3PQ",
      emergencyContact: "Grace Okafor", emergencyRelationship: "Spouse", emergencyPhone: "07700 900232",
      jobTitle: "Finance Manager", departmentId: deptId("Finance"), managerId: louise.id,
      employmentType: "Permanent", startDate: "2020-01-13", endDate: null, probationEndDate: null,
      workLocationId: locMain.id, workArrangement: "Office", weeklyHours: 37.5,
      status: "Active", sponsored: false, contactVerifiedDate: "2026-03-11", contactVerifiedBy: "Sarah Whitfield"
    });

    const priya = makeEmployee({
      employeeNumber: "BE-0005", title: "Ms", firstName: "Priya", lastName: "Sharma", preferredName: "Priya",
      personalEmail: "p.sharma@personalmail.example", companyEmail: "priya.sharma@bloxtenergy.co.uk",
      mobile: "07700 900241", dob: "1994-07-18", nationality: "Indian",
      address: "56 Burley Road, Leeds", postcode: "LS4 2QY",
      emergencyContact: "Raj Sharma", emergencyRelationship: "Parent", emergencyPhone: "07700 900242",
      jobTitle: "Software Developer", departmentId: deptId("Engineering"), managerId: david.id,
      employmentType: "Permanent", startDate: "2023-09-04", endDate: null, probationEndDate: "2023-12-04",
      workLocationId: locMain.id, workArrangement: "Hybrid", weeklyHours: 37.5,
      status: "Active", sponsored: true, contactVerifiedDate: "2026-08-01", contactVerifiedBy: "Sarah Whitfield"
    });

    const tom = makeEmployee({
      employeeNumber: "BE-0006", title: "Mr", firstName: "Tom", lastName: "Bracewell", preferredName: "Tom",
      personalEmail: "t.bracewell@personalmail.example", companyEmail: "tom.bracewell@bloxtenergy.co.uk",
      mobile: "07700 900251", dob: "1996-02-27", nationality: "British",
      address: "9 Kirkgate, Leeds", postcode: "LS2 7DJ",
      emergencyContact: "Holly Bracewell", emergencyRelationship: "Sibling", emergencyPhone: "07700 900252",
      jobTitle: "Marketing Executive", departmentId: deptId("Marketing"), managerId: louise.id,
      employmentType: "Fixed-term", startDate: "2025-05-01", endDate: daysFromNow(120), probationEndDate: "2025-08-01",
      workLocationId: locMain.id, workArrangement: "Office", weeklyHours: 37.5,
      status: "Active", sponsored: false, contactVerifiedDate: "2026-05-01", contactVerifiedBy: "Sarah Whitfield"
    });

    const elena = makeEmployee({
      employeeNumber: "BE-0007", title: "Ms", firstName: "Elena", lastName: "Novak", preferredName: "Elena",
      personalEmail: "e.novak@personalmail.example", companyEmail: "elena.novak@bloxtenergy.co.uk",
      mobile: "07700 900261", dob: "1991-12-09", nationality: "Slovak",
      address: "77 Woodhouse Lane, Leeds", postcode: "LS2 3AR",
      emergencyContact: "Petr Novak", emergencyRelationship: "Spouse", emergencyPhone: "07700 900262",
      jobTitle: "Customer Support Advisor", departmentId: deptId("Customer Operations"), managerId: sarah.id,
      employmentType: "Permanent", startDate: "2021-10-11", endDate: null, probationEndDate: "2022-01-11",
      workLocationId: locClient.id, workArrangement: "Hybrid", weeklyHours: 35,
      status: "Active", sponsored: false, contactVerifiedDate: "2026-02-14", contactVerifiedBy: "Sarah Whitfield"
    });

    const grace = makeEmployee({
      employeeNumber: "BE-0008", title: "Ms", firstName: "Grace", lastName: "Mensah", preferredName: "Grace",
      personalEmail: "g.mensah@personalmail.example", companyEmail: "grace.mensah@bloxtenergy.co.uk",
      mobile: "07700 900271", dob: "1999-05-14", nationality: "British",
      address: "3 Hyde Park Corner, Leeds", postcode: "LS6 1AJ",
      emergencyContact: "Kwame Mensah", emergencyRelationship: "Parent", emergencyPhone: "07700 900272",
      jobTitle: "Operations Assistant", departmentId: deptId("Operations"), managerId: sarah.id,
      employmentType: "Permanent", startDate: "2024-06-03", endDate: null, probationEndDate: "2024-09-03",
      workLocationId: locMain.id, workArrangement: "Office", weeklyHours: 37.5,
      status: "Active", sponsored: false, contactVerifiedDate: "2026-06-20", contactVerifiedBy: "Sarah Whitfield"
    });

    const meera = makeEmployee({
      employeeNumber: "BE-0009", title: "Ms", firstName: "Meera", lastName: "Iyer", preferredName: "Meera",
      personalEmail: "m.iyer@personalmail.example", companyEmail: "meera.iyer@bloxtenergy.co.uk",
      mobile: "07700 900281", dob: "1987-08-02", nationality: "British",
      address: "18 Headingley Lane, Leeds", postcode: "LS6 1BL",
      emergencyContact: "Anil Iyer", emergencyRelationship: "Spouse", emergencyPhone: "07700 900282",
      jobTitle: "Compliance Administrator", departmentId: deptId("Compliance & Governance"), managerId: louise.id,
      employmentType: "Permanent", startDate: "2022-04-25", endDate: null, probationEndDate: "2022-07-25",
      workLocationId: locMain.id, workArrangement: "Hybrid", weeklyHours: 37.5,
      status: "Active", sponsored: false, contactVerifiedDate: "2026-07-01", contactVerifiedBy: "Sarah Whitfield"
    });

    // Working patterns are stored on the employee record --------------------------
    const standardPattern = {
      monday: "09:00-17:30", tuesday: "09:00-17:30", wednesday: "09:00-17:30",
      thursday: "09:00-17:30", friday: "09:00-17:30", saturday: null, sunday: null,
      breakMinutes: 30
    };
    [louise, sarah, david, james, priya, tom, elena, grace, meera].forEach((e) => {
      db.employees.update(e.id, { workingPattern: standardPattern });
    });

    // Users -------------------------------------------------------------------------
    const DEMO_PASSWORD = "Password123!";
    db.users.create({ name: "System Administrator", email: "admin@bloxtenergy.co.uk", password: DEMO_PASSWORD, role: ROLES.SUPER_ADMIN, employeeId: null, status: "Active" });
    db.users.create({ name: "Louise Farrington", email: "louise.farrington@bloxtenergy.co.uk", password: DEMO_PASSWORD, role: ROLES.DIRECTOR, employeeId: louise.id, status: "Active" });
    db.users.create({ name: "Sarah Whitfield", email: "sarah.whitfield@bloxtenergy.co.uk", password: DEMO_PASSWORD, role: ROLES.HR_ADMIN, employeeId: sarah.id, status: "Active" });
    db.users.create({ name: "Meera Iyer", email: "meera.iyer@bloxtenergy.co.uk", password: DEMO_PASSWORD, role: ROLES.COMPLIANCE_ADMIN, employeeId: meera.id, status: "Active" });
    db.users.create({ name: "David Chen", email: "david.chen@bloxtenergy.co.uk", password: DEMO_PASSWORD, role: ROLES.MANAGER, employeeId: david.id, status: "Active" });
    db.users.create({ name: "Priya Sharma", email: "priya.sharma@bloxtenergy.co.uk", password: DEMO_PASSWORD, role: ROLES.EMPLOYEE, employeeId: priya.id, status: "Active" });
    db.users.create({ name: "External Auditor", email: "auditor@bloxtenergy.co.uk", password: DEMO_PASSWORD, role: ROLES.AUDITOR, employeeId: null, status: "Active" });

    // Right to work checks ------------------------------------------------------------
    function rtwCheck(emp, cfg) {
      return db.rightToWorkChecks.create(Object.assign({
        employeeId: emp.id, performedBy: "Sarah Whitfield", createdBy: "Sarah Whitfield"
      }, cfg));
    }
    [louise, sarah, david, james, tom, grace, meera].forEach((e) => {
      rtwCheck(e, {
        checkDate: "2024-01-15", checkMethod: "Manual document check", immigrationCategory: "British Citizen",
        permissionStart: null, permissionExpiry: null, restrictions: null, followUpRequired: false, nextCheckDate: null,
        evidenceRef: "Passport verified original seen", status: "Valid", notes: "Standard onboarding right-to-work check completed."
      });
    });
    rtwCheck(priya, {
      checkDate: "2023-09-01", checkMethod: "Online Home Office check", immigrationCategory: "Skilled Worker",
      permissionStart: "2023-09-04", permissionExpiry: "2026-09-03", restrictions: "Work restricted to sponsoring employer and permitted occupation",
      followUpRequired: true, nextCheckDate: "2026-09-03", evidenceRef: "Home Office share code check HOC-88213-PS", status: "Review Due",
      notes: "Follow-up check required ahead of visa expiry."
    });
    rtwCheck(elena, {
      checkDate: "2021-10-05", checkMethod: "Online Home Office check", immigrationCategory: "EU Settlement Scheme Pre-Settled Status",
      permissionStart: "2021-10-05", permissionExpiry: daysFromNow(55), restrictions: null,
      followUpRequired: true, nextCheckDate: daysFromNow(55), evidenceRef: "Home Office share code check HOC-51032-EN", status: "Expiring Soon",
      notes: "Pre-settled status approaching expiry employee should be reminded to apply for settled status."
    });

    // Sponsorship record (Priya only) -----------------------------------------------
    db.sponsorshipRecords.create({
      employeeId: priya.id, isSponsored: true, workerRoute: "Skilled Worker",
      sponsorLicenceRef: "SPN-8842-2024", cosReference: "C2H9F8K21X", cosAssignedDate: "2023-08-10",
      cosStartDate: "2023-09-04", cosEndDate: "2026-09-03",
      permissionStart: "2023-09-04", permissionExpiry: "2026-09-03",
      socCode: "2134", socTitle: "Programmers and software development professionals",
      internalJobTitle: "Software Developer", jobDescriptionRef: jobFor("Software Developer").id,
      annualSalary: 42000, weeklyHours: 37.5, primaryWorkLocationId: locMain.id, workPattern: "Monday-Friday, 09:00-17:30, hybrid",
      lineManagerId: david.id, sponsorshipStatus: "Current", hrResponsiblePerson: "Meera Iyer",
      nextReviewDate: "2026-09-03", notes: "CoS and right-to-work follow-up review scheduled ahead of visa expiry."
    });

    // Sponsor events register --------------------------------------------------------
    db.sponsorEvents.create({
      employeeId: priya.id, eventType: "Change of work location", dateOccurred: "2026-06-01", dateAware: "2026-06-02",
      details: "Employee moved to a hybrid pattern with two days per week at home, remainder at Bloxt House.",
      requiresAssessment: true, reportingDeadline: null, assignedTo: "Meera Iyer",
      reportedThroughSMS: false, dateReported: null, reportedBy: null, evidenceRef: "Hybrid working agreement signed 02 Jun 2026",
      notes: "Assessed against sponsor guidance main office remains the primary reporting address.",
      status: "Not Reportable"
    });
    db.sponsorEvents.create({
      employeeId: priya.id, eventType: "Salary change", dateOccurred: "2026-08-15", dateAware: "2026-08-15",
      details: "Annual salary review increase proposed effective 01 Sept 2026, pending sign-off.",
      requiresAssessment: true, reportingDeadline: null, assignedTo: "Meera Iyer",
      reportedThroughSMS: false, dateReported: null, reportedBy: null, evidenceRef: null,
      notes: "Awaiting confirmation of final figure before compliance assessment.",
      status: "Requires Review"
    });

    // Company change register -------------------------------------------------------
    db.companyChanges.create({
      changeType: "Registered office change", date: "2025-11-03",
      description: "Registered office relocated within Leeds to Wellington House, Aire Street.",
      reportedInternallyBy: "Sarah Whitfield", reviewedBy: "Meera Iyer", potentialSponsorImpact: "Low same region, no change to trading activity.",
      reportRequired: true, reported: true, reportedDate: "2025-11-10",
      evidenceRef: "Companies House filing AD01 Nov 2025", notes: "Sponsor record updated on SMS following the move."
    });

    // Guidance references -------------------------------------------------------------
    db.guidanceReferences.create({
      title: "GOV.UK guidance and services home", source: "GOV.UK", url: "https://www.gov.uk",
      lastReviewed: "2026-06-01", reviewedBy: "Meera Iyer",
      notes: "Starting point for locating current sponsor duties, right-to-work and Skilled Worker guidance. Add specific guidance page links here as they are reviewed."
    });

    // Compliance rules (manually maintained, version tracked) -------------------------
    db.complianceRules.create({
      name: "Right-to-work follow-up check interval", description: "Time-limited permission holders are re-checked before their permission expiry date.",
      effectiveDate: "2024-01-01", source: "Internal HR policy, informed by Home Office right-to-work guidance", sourceVersion: "v3",
      lastChecked: "2026-06-01", checkedBy: "Meera Iyer", status: "Active"
    });
    db.complianceRules.create({
      name: "Document expiry reminder schedule", description: "Reminders issued at 90/60/30/14/7 days before document expiry.",
      effectiveDate: "2024-01-01", source: "Internal HR policy", sourceVersion: "v1",
      lastChecked: "2026-01-05", checkedBy: "Sarah Whitfield", status: "Active"
    });

    // Policies -------------------------------------------------------------------------
    const policyDefs = [
      { name: "Attendance Policy", owner: "Sarah Whitfield", version: "2.1", issueDate: "2025-01-10" },
      { name: "Annual Leave Policy", owner: "Sarah Whitfield", version: "1.4", issueDate: "2025-01-10" },
      { name: "Data Protection Policy", owner: "Meera Iyer", version: "3.0", issueDate: "2025-06-01" },
      { name: "Remote Working Policy", owner: "Sarah Whitfield", version: "1.2", issueDate: "2024-09-15" },
      { name: "Equality, Diversity & Inclusion Policy", owner: "Louise Farrington", version: "1.1", issueDate: "2024-03-01" }
    ];
    const policies = policyDefs.map((p) => db.policies.create(Object.assign({ document: p.name + ".pdf", requiresAcknowledgement: true }, p)));
    [louise, sarah, david, james, priya, tom, elena, grace, meera].forEach((e) => {
      policies.forEach((p) => {
        db.policyAcknowledgements.create({
          employeeId: e.id, policyId: p.id, policyVersion: p.version, issuedDate: p.issueDate,
          acknowledgedDate: e.id === grace.id && p.name === "Data Protection Policy" ? null : p.issueDate,
          method: "Employee portal acknowledgement", status: e.id === grace.id && p.name === "Data Protection Policy" ? "Outstanding" : "Acknowledged"
        });
      });
    });

    // Documents --------------------------------------------------------------------------
    function addDoc(emp, cfg) {
      return db.documents.create(Object.assign({
        employeeId: emp ? emp.id : null, uploadedBy: "Sarah Whitfield", uploadDate: "2025-01-15", version: 1, accessClassification: "Confidential"
      }, cfg));
    }
    [louise, sarah, david, james, priya, tom, elena, grace, meera].forEach((e) => {
      addDoc(e, { title: `${e.firstName} ${e.lastName} Employment Contract`, category: "Employment Contract", issueDate: e.startDate, expiryDate: null, reviewDate: null, status: "Valid", notes: "Signed contract of employment on file." });
    });
    addDoc(priya, { title: "Priya Sharma Right to Work Evidence (Skilled Worker)", category: "Right to Work", issueDate: "2023-09-01", expiryDate: "2026-09-03", reviewDate: "2026-08-03", status: "Review Due", notes: "Home Office share code evidence." , accessClassification: "Highly Confidential"});
    addDoc(elena, { title: "Elena Novak Right to Work Evidence (EUSS Pre-Settled)", category: "Right to Work", issueDate: "2021-10-05", expiryDate: daysFromNow(55), reviewDate: daysFromNow(25), status: "Expiring Soon", notes: "Pre-settled status share code evidence.", accessClassification: "Highly Confidential" });
    addDoc(grace, { title: "Grace Mensah First Aid at Work Certificate", category: "Qualifications", issueDate: "2023-08-20", expiryDate: daysFromNow(18), reviewDate: daysFromNow(4), status: "Expiring Soon", notes: "Renewal course booked." });
    addDoc(priya, { title: "Priya Sharma Certificate of Sponsorship Record", category: "Sponsorship", issueDate: "2023-08-10", expiryDate: "2026-09-03", reviewDate: "2026-08-03", status: "Review Due", notes: "CoS reference C2H9F8K21X.", accessClassification: "Highly Confidential" });
    addDoc(tom, { title: "Tom Bracewell Fixed-term Contract Schedule", category: "Employment Contract", issueDate: "2025-05-01", expiryDate: daysFromNow(120), reviewDate: daysFromNow(90), status: "Review Due", notes: "Review ahead of fixed-term end date." });
    [louise, sarah, david, james, priya, tom, elena, grace, meera].forEach((e) => {
      addDoc(e, { title: `${e.firstName} ${e.lastName} Job Description`, category: "Job Description", issueDate: "2025-01-06", expiryDate: null, reviewDate: null, status: "Valid", notes: "Current approved job description on file.", accessClassification: "Normal" });
    });

    // Salary records --------------------------------------------------------------------
    function addSalary(emp, amount, effective, reason, previous) {
      return db.salaryRecords.create({
        employeeId: emp.id, salary: amount, basis: "Annual", contractedHours: emp.weeklyHours,
        effectiveDate: effective, reason, approvedBy: "Louise Farrington", recordedBy: "Sarah Whitfield",
        previousSalary: previous || null
      });
    }
    addSalary(louise, 92000, "2016-02-01", "Starting salary");
    addSalary(sarah, 38500, "2019-06-10", "Starting salary");
    addSalary(david, 68000, "2018-03-19", "Starting salary");
    addSalary(james, 61000, "2020-01-13", "Starting salary");
    addSalary(priya, 38000, "2023-09-04", "Starting salary");
    addSalary(priya, 42000, "2025-09-04", "Annual performance review increase", 38000);
    addSalary(tom, 29500, "2025-05-01", "Starting salary");
    addSalary(elena, 27500, "2021-10-11", "Starting salary");
    addSalary(grace, 24500, "2024-06-03", "Starting salary");
    addSalary(meera, 45000, "2022-04-25", "Starting salary");

    // Payroll records (most recent period only, per employee) ---------------------------
    [louise, sarah, david, james, priya, tom, elena, grace, meera].forEach((e) => {
      const sal = db.salaryRecords.find((s) => s.employeeId === e.id).sort((a, b) => new Date(b.effectiveDate) - new Date(a.effectiveDate))[0];
      const gross = Math.round((sal.salary / 12) * 100) / 100;
      db.payrollRecords.create({
        employeeId: e.id, payrollPeriod: "July 2026", grossSalary: gross, basicSalary: gross, overtime: 0, bonus: 0,
        deductions: Math.round(gross * 0.22 * 100) / 100, netAmount: Math.round(gross * 0.78 * 100) / 100,
        paymentDate: "2026-07-28", payrollReference: "PR-2026-07-" + e.employeeNumber, evidenceUploaded: true
      });
    });

    // Leave requests -------------------------------------------------------------------
    db.leaveRequests.create({ employeeId: priya.id, leaveType: "Annual leave", from: "2026-09-14", to: "2026-09-18", partialDay: false, reason: "Family visit", notes: null, status: "Approved", approvedBy: "David Chen", requestedDate: "2026-08-01" });
    db.leaveRequests.create({ employeeId: tom.id, leaveType: "Sick leave", from: "2026-08-25", to: "2026-08-26", partialDay: false, reason: "Flu", notes: "Self-certified", status: "Approved", approvedBy: "Sarah Whitfield", requestedDate: "2026-08-25" });
    db.leaveRequests.create({ employeeId: grace.id, leaveType: "Annual leave", from: daysFromNow(10), to: daysFromNow(12), partialDay: false, reason: "Personal", notes: null, status: "Pending", approvedBy: null, requestedDate: daysFromNow(-2) });

    // Leave allowances (stored on employee record) ---------------------------------------
    [louise, sarah, david, james, priya, tom, elena, grace, meera].forEach((e) => {
      db.employees.update(e.id, { leaveAllowance: 25, leaveTakenDays: e.id === priya.id ? 5 : 3, leaveBookedDays: e.id === priya.id ? 5 : (e.id === grace.id ? 3 : 0) });
    });

    // Attendance — last 3 working days including today -----------------------------------
    function lastWorkingDays(count) {
      const out = []; let d = new Date();
      while (out.length < count) {
        if (d.getDay() !== 0 && d.getDay() !== 6) out.unshift(new Date(d));
        d.setDate(d.getDate() - 1);
      }
      return out.map((dt) => dt.toISOString().slice(0, 10));
    }
    const workDays = lastWorkingDays(3);
    [louise, sarah, david, james, priya, tom, elena, grace, meera].forEach((e) => {
      workDays.forEach((date, i) => {
        let status = "Present";
        let clockIn = "08:55", clockOut = "17:32", loc = "Office";
        if (e.workArrangement === "Remote") loc = "Remote";
        if (e.id === elena.id && i === workDays.length - 1) { status = "Sick"; clockIn = null; clockOut = null; loc = "N/A"; }
        if (e.id === grace.id && i === workDays.length - 1) { status = "Unauthorised Absence"; clockIn = null; clockOut = null; loc = "N/A"; }
        if (e.workArrangement === "Hybrid" && i % 2 === 0) loc = "Remote";
        db.attendance.create({
          employeeId: e.id, date, expectedStart: "09:00", clockIn, clockOut,
          hours: clockIn && clockOut ? 8 : 0, workLocation: loc, status,
          notes: status === "Unauthorised Absence" ? "No contact received following up." : null,
          managerReviewed: status === "Unauthorised Absence" ? false : true,
          createdBy: "Sarah Whitfield"
        });
      });
    });

    // Absence records -------------------------------------------------------------------
    db.absence.create({
      employeeId: elena.id, date: workDays[workDays.length - 1], expectedWorkDay: true, absenceType: "Sick",
      reason: "Flu-like symptoms", dateReported: workDays[workDays.length - 1], howReported: "Phone call", reportedTo: "Sarah Whitfield",
      expectedReturn: daysFromNow(1), actualReturn: null, evidenceRef: null, managerNotes: "Employee called ahead of shift.",
      hrNotes: null, authorised: true, followUpRequired: false
    });
    db.absence.create({
      employeeId: grace.id, date: workDays[workDays.length - 1], expectedWorkDay: true, absenceType: "Unauthorised Absence",
      reason: null, dateReported: null, howReported: null, reportedTo: null,
      expectedReturn: null, actualReturn: null, evidenceRef: null, managerNotes: "No contact received before shift start.",
      hrNotes: null, authorised: false, followUpRequired: true
    });

    // Internal compliance review ----------------------------------------------------------
    db.complianceReviews.create({
      reviewNumber: "REV-2026-Q2", reviewDate: "2026-06-15", reviewer: "Meera Iyer", area: "Right to work & sponsorship records",
      employeesSampled: 4, recordsReviewed: "Right-to-work checks, sponsorship record, contact detail verification",
      issuesFound: "One employee record (Elena Novak) had a right-to-work follow-up date not yet actioned.",
      actionsRequired: "Schedule and complete follow-up right-to-work check before permission expiry.",
      responsiblePerson: "Sarah Whitfield", dueDate: daysFromNow(50), completionDate: null,
      evidenceRef: "Internal review checklist REV-2026-Q2.pdf", notes: "Quarterly sample review no other issues identified.",
      result: "Follow-up Required"
    });

    // Recruitment ------------------------------------------------------------------------
    const vac1 = db.vacancies.create({
      jobTitle: "Software Developer", departmentId: deptId("Engineering"), hiringManager: "David Chen",
      openingDate: "2026-07-01", closingDate: daysFromNow(14), reasonForVacancy: "Team growth", employmentType: "Permanent",
      salaryRangeMin: 36000, salaryRangeMax: 46000, location: "Bloxt House (Hybrid)", jobDescriptionRef: jobFor("Software Developer").id,
      recruitmentChannel: "Company careers page", status: "Open"
    });
    db.candidates.create({
      vacancyId: vac1.id, name: "Alex Turner", applicationDate: "2026-08-05", source: "Company careers page",
      cvRef: "alex-turner-cv.pdf", interviewRecords: "First-stage interview scheduled 08 Sep 2026.", outcome: "In Progress",
      offer: null, hiringDecision: null
    });

    // Tasks -----------------------------------------------------------------------------
    db.tasks.create({
      title: "Complete right-to-work follow-up Priya Sharma", description: "Skilled Worker visa approaching expiry confirm continued permission to work.",
      employeeId: priya.id, category: "Right to Work", assignedTo: "Meera Iyer", priority: "High",
      dueDate: "2026-09-03", status: "Open", attachments: [], comments: []
    });
    db.tasks.create({
      title: "Renew First Aid at Work certificate Grace Mensah", description: "Certificate expiring; renewal course already booked.",
      employeeId: grace.id, category: "Documents", assignedTo: "Sarah Whitfield", priority: "Medium",
      dueDate: daysFromNow(18), status: "In Progress", attachments: [], comments: []
    });
    db.tasks.create({
      title: "Review unauthorised absence Grace Mensah", description: "No contact received before shift start; follow up required per absence policy.",
      employeeId: grace.id, category: "Attendance", assignedTo: "Sarah Whitfield", priority: "High",
      dueDate: daysFromNow(1), status: "Open", attachments: [], comments: []
    });
    db.tasks.create({
      title: "Confirm Data Protection Policy acknowledgement Grace Mensah", description: "Outstanding acknowledgement of policy v3.0.",
      employeeId: grace.id, category: "Policies", assignedTo: "Sarah Whitfield", priority: "Low",
      dueDate: daysFromNow(20), status: "Open", attachments: [], comments: []
    });

    // Seed audit trail for the demo timeline ----------------------------------------------
    const seedEvents = [
      { employeeId: priya.id, action: "Right-to-work check completed", module: "Right to Work", description: "Online Home Office check recorded for Priya Sharma.", timestamp: "2023-09-01T10:00:00.000Z", userName: "Sarah Whitfield" },
      { employeeId: priya.id, action: "Sponsorship record created", module: "Sponsorship", description: "Certificate of Sponsorship details recorded following CoS assignment.", timestamp: "2023-08-10T09:00:00.000Z", userName: "Meera Iyer" },
      { employeeId: priya.id, action: "Salary changed", module: "Salary", description: "Salary updated from £38,000 to £42,000 following annual review.", previousValue: "£38,000", newValue: "£42,000", timestamp: "2025-09-04T09:00:00.000Z", userName: "Sarah Whitfield" },
      { employeeId: elena.id, action: "Right-to-work check completed", module: "Right to Work", description: "EU Settlement Scheme pre-settled status verified for Elena Novak.", timestamp: "2021-10-05T09:00:00.000Z", userName: "Sarah Whitfield" },
      { employeeId: elena.id, action: "Address confirmed", module: "Contact Details", description: "Elena Novak confirmed current residential address via employee portal.", timestamp: "2026-02-14T09:00:00.000Z", userName: "Elena Novak" },
      { employeeId: grace.id, action: "Document uploaded", module: "Documents", description: "First Aid at Work certificate uploaded to employee record.", timestamp: "2023-08-20T09:00:00.000Z", userName: "Sarah Whitfield" },
      { employeeId: tom.id, action: "Employee created", module: "Employees", description: "New employee record created for Tom Bracewell.", timestamp: "2025-05-01T09:00:00.000Z", userName: "Sarah Whitfield" }
    ];
    seedEvents.forEach((ev) => db.auditLog.create(Object.assign({ userId: null, device: "Web session (browser prototype)" }, ev)));

    writeRaw("seed_version", SEED_VERSION);
  }

  function resetDemoData() {
    seedDemoData(true);
  }

  // ---------------------------------------------------------------------------
  // Public namespace
  // ---------------------------------------------------------------------------
  global.HR = global.HR || {};
  global.HR.db = db;
  global.HR.auth = auth;
  global.HR.company = { get: getCompany, set: setCompany };
  global.HR.audit = { log: logAudit, forEmployee: getEmployeeHistory };
  global.HR.enums = {
    ROLES, ROLE_LABELS, PERMISSIONS, EMPLOYMENT_TYPES, WORK_ARRANGEMENTS, EMPLOYMENT_STATUSES,
    RTW_STATUSES, RTW_CHECK_METHODS, DOCUMENT_CATEGORIES, DOCUMENT_STATUSES, DATA_CLASSIFICATIONS,
    ATTENDANCE_STATUSES, LEAVE_TYPES, LEAVE_STATUSES, SPONSOR_EVENT_TYPES, SPONSOR_EVENT_STATUSES,
    TASK_STATUSES, TASK_PRIORITIES
  };
  global.HR.util = { nextId, nowISO, daysFromNow };
  global.HR.seed = { run: seedDemoData, reset: resetDemoData };

  seedDemoData(false);
})(window);

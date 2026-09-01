/**
 * navigation.js
 * Renders the shared sidebar + header shell into #sidebarMount / #headerMount
 * placeholders present on every authenticated page, applies role-based menu
 * visibility, and wires offcanvas / dropdown / global-search behaviour.
 *
 * NOTE: hiding menu items by role is a UX convenience only. A production
 * build MUST also enforce authorisation server-side for every request —
 * a hidden link is not an access control.
 */
(function (global) {
  "use strict";

  const R = () => global.HR.enums.ROLES;

  function navStructure() {
    const ROLES = global.HR.enums.ROLES;
    const ALL = Object.values(ROLES);
    const STAFF = [ROLES.SUPER_ADMIN, ROLES.DIRECTOR, ROLES.HR_ADMIN, ROLES.COMPLIANCE_ADMIN, ROLES.MANAGER, ROLES.AUDITOR];
    const HR_LIKE = [ROLES.SUPER_ADMIN, ROLES.DIRECTOR, ROLES.HR_ADMIN];
    const COMPLIANCE_LIKE = [ROLES.SUPER_ADMIN, ROLES.DIRECTOR, ROLES.HR_ADMIN, ROLES.COMPLIANCE_ADMIN, ROLES.AUDITOR];

    return [
      {
        heading: "Overview",
        items: [{ key: "dashboard", label: "Dashboard", icon: "bi-speedometer2", href: "dashboard.html", roles: ALL }]
      },
      {
        heading: "People",
        items: [
          { key: "employees", label: "Employees", icon: "bi-people", href: "employees.html", roles: STAFF },
          { key: "departments", label: "Departments", icon: "bi-diagram-3", href: "employees.html?tab=departments", roles: HR_LIKE }
        ]
      },
      {
        heading: "Time",
        items: [
          { key: "attendance", label: "Attendance", icon: "bi-calendar-check", href: "attendance.html", roles: ALL },
          { key: "leave", label: "Leave", icon: "bi-airplane", href: "leave.html", roles: ALL },
          { key: "absence", label: "Absence", icon: "bi-clipboard-x", href: "attendance.html?tab=absence", roles: STAFF }
        ]
      },
      {
        heading: "Employment",
        items: [
          { key: "contracts", label: "Contracts", icon: "bi-file-earmark-text", href: "documents.html?category=Employment+Contract", roles: COMPLIANCE_LIKE },
          { key: "documents", label: "Documents", icon: "bi-folder2-open", href: "documents.html", roles: ALL },
          { key: "recruitment", label: "Recruitment", icon: "bi-person-plus", href: "recruitment.html", roles: HR_LIKE }
        ]
      },
      {
        heading: "Compliance",
        items: [
          { key: "right-to-work", label: "Right to Work", icon: "bi-patch-check", href: "right-to-work.html", roles: COMPLIANCE_LIKE },
          { key: "immigration", label: "Immigration Records", icon: "bi-passport", href: "right-to-work.html?tab=immigration", roles: COMPLIANCE_LIKE },
          { key: "sponsorship", label: "Sponsor Compliance", icon: "bi-shield-check", href: "sponsorship.html", roles: COMPLIANCE_LIKE },
          { key: "compliance-calendar", label: "Compliance Calendar", icon: "bi-calendar-week", href: "compliance.html?tab=calendar", roles: COMPLIANCE_LIKE }
        ]
      },
      {
        heading: "Finance",
        items: [
          { key: "salary", label: "Salary Records", icon: "bi-cash-stack", href: "payroll.html?tab=salary", roles: HR_LIKE },
          { key: "payroll-records", label: "Payroll Records", icon: "bi-receipt", href: "payroll.html?tab=payroll", roles: HR_LIKE }
        ]
      },
      {
        heading: "Insights",
        items: [
          { key: "reports", label: "Reports", icon: "bi-bar-chart", href: "reports.html", roles: STAFF },
          { key: "audit-log", label: "Audit Log", icon: "bi-journal-text", href: "audit-log.html", roles: COMPLIANCE_LIKE }
        ]
      },
      {
        heading: "Administration",
        items: [
          { key: "users", label: "Users & Roles", icon: "bi-person-gear", href: "settings.html?tab=users", roles: [ROLES.SUPER_ADMIN] },
          { key: "company-settings", label: "Company Settings", icon: "bi-building", href: "settings.html?tab=company", roles: HR_LIKE },
          { key: "hr-settings", label: "HR Settings", icon: "bi-sliders", href: "settings.html?tab=hr", roles: HR_LIKE }
        ]
      }
    ];
  }

  function renderSidebar(activeKey, user) {
    const groups = navStructure()
      .map((group) => ({
        heading: group.heading,
        items: group.items.filter((item) => item.roles.includes(user.role))
      }))
      .filter((group) => group.items.length > 0);

    const groupsHtml = groups
      .map(
        (group) => `
      <div class="sidebar-nav-group">
        <div class="sidebar-nav-heading">${group.heading}</div>
        ${group.items
          .map(
            (item) => `
          <a class="sidebar-nav-link ${item.key === activeKey ? "active" : ""}" href="${item.href}">
            <i class="bi ${item.icon}"></i><span class="sidebar-nav-label">${item.label}</span>
          </a>`
          )
          .join("")}
      </div>`
      )
      .join("");

    return `
      <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
      <aside class="app-sidebar" id="appSidebar" aria-label="Primary navigation">
        <div class="sidebar-brand">
          <span class="sidebar-brand-plate"><img src="assets/images/bloxt-logo.jpg" alt="Bloxt Limited" class="sidebar-brand-logo"></span>
          <span class="sidebar-brand-tag">People &amp; Compliance</span>
        </div>
        <nav class="sidebar-nav-scroll">${groupsHtml}</nav>
        <div class="sidebar-footer">
          <div class="sidebar-footer-text">Internal HR system.<br>Authorised company users only.</div>
        </div>
      </aside>`;
  }

  function renderHeader(user) {
    const roleLabel = global.HR.enums.ROLE_LABELS[user.role];
    return `
      <header class="app-header">
        <button class="header-menu-toggle" id="sidebarToggle" aria-label="Toggle navigation" aria-expanded="false">
          <i class="bi bi-list"></i>
        </button>
        <div class="header-search">
          <i class="bi bi-search"></i>
          <input type="search" id="globalSearchInput" placeholder="Search employees, documents, tasks…" autocomplete="off" aria-label="Global search">
          <div class="dropdown-menu shadow-sm" id="globalSearchResults" style="width:100%;max-height:360px;overflow-y:auto;"></div>
        </div>
        <div class="header-actions">
          <div class="dropdown">
            <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-plus-lg"></i><span class="d-none d-sm-inline">Create</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" id="quickCreateMenu"></ul>
          </div>
          <div class="dropdown">
            <button class="header-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
              <i class="bi bi-bell"></i>
              <span class="header-icon-dot d-none" id="notifDot"></span>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-0" id="notifDropdown" style="width:340px;max-height:420px;overflow-y:auto;"></div>
          </div>
          <a class="header-icon-btn" href="#" data-bs-toggle="tooltip" title="Help" aria-label="Help" onclick="HR.toast({type:'info', title:'Help', text:'Contact your HR administrator or system administrator for support.'}); return false;">
            <i class="bi bi-question-circle"></i>
          </a>
          <div class="dropdown">
            <button class="header-user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="header-user-avatar">${global.HR.format.initials(user.name.split(" ")[0], user.name.split(" ").slice(-1)[0])}</span>
              <span class="header-user-info">
                <span class="header-user-name d-block">${user.name}</span>
                <span class="header-user-role d-block">${roleLabel}</span>
              </span>
              <i class="bi bi-chevron-down d-none d-sm-inline" style="font-size:.7rem;color:#8994A3;"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><h6 class="dropdown-header">Signed in as ${user.email}</h6></li>
              ${user.employeeId ? `<li><a class="dropdown-item" href="employee-profile.html?id=${user.employeeId}"><i class="bi bi-person me-2"></i>My Profile</a></li>` : ""}
              <li><a class="dropdown-item" href="settings.html?tab=account"><i class="bi bi-gear me-2"></i>Account Settings</a></li>
              <li><a class="dropdown-item" href="settings.html?tab=security"><i class="bi bi-shield-lock me-2"></i>Security</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><button class="dropdown-item text-danger" type="button" id="signOutBtn"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</button></li>
            </ul>
          </div>
        </div>
      </header>`;
  }

  const QUICK_CREATE_ITEMS = [
    { label: "Add Employee", icon: "bi-person-plus", href: "employee-add.html", roles: ["hr_admin", "super_admin"] },
    { label: "Record Attendance", icon: "bi-calendar-check", href: "attendance.html?new=1", roles: ["hr_admin", "super_admin", "manager"] },
    { label: "Record Absence", icon: "bi-clipboard-x", href: "attendance.html?tab=absence&new=1", roles: ["hr_admin", "super_admin", "manager"] },
    { label: "Create Leave Request", icon: "bi-airplane", href: "leave.html?new=1", roles: null },
    { label: "Upload Document", icon: "bi-cloud-upload", href: "documents.html?new=1", roles: null },
    { label: "Create HR Task", icon: "bi-list-task", href: "reports.html?tab=tasks&new=1", roles: ["hr_admin", "super_admin", "compliance_admin"] },
    { label: "Record Right-to-Work Check", icon: "bi-patch-check", href: "right-to-work.html?new=1", roles: ["hr_admin", "super_admin", "compliance_admin"] },
    { label: "Record Sponsor Event", icon: "bi-shield-exclamation", href: "sponsorship.html?tab=events&new=1", roles: ["hr_admin", "super_admin", "compliance_admin"] }
  ];

  function renderQuickCreate(user) {
    const menu = document.getElementById("quickCreateMenu");
    if (!menu) return;
    menu.innerHTML = QUICK_CREATE_ITEMS.filter((i) => !i.roles || i.roles.includes(user.role))
      .map((i) => `<li><a class="dropdown-item" href="${i.href}"><i class="bi ${i.icon} me-2"></i>${i.label}</a></li>`)
      .join("");
  }

  function renderNotifications(user) {
    const items = global.HR.notifications.forUser(user);
    const dot = document.getElementById("notifDot");
    const unread = items.filter((n) => n.status === "Unread").length;
    if (dot) dot.classList.toggle("d-none", unread === 0);

    const dropdown = document.getElementById("notifDropdown");
    if (!dropdown) return;
    if (items.length === 0) {
      dropdown.innerHTML = `<div class="empty-state py-4"><div class="empty-state-icon"><i class="bi bi-bell-slash"></i></div><div class="empty-state-text mb-0">You have no notifications right now.</div></div>`;
      return;
    }
    dropdown.innerHTML = `
      <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
        <span class="fw-semibold small">Notifications</span>
        <span class="text-meta">${unread} unread</span>
      </div>
      ${items
        .slice(0, 8)
        .map(
          (n) => `
        <div class="px-3 py-2 border-bottom small ${n.status === "Unread" ? "" : "text-secondary-custom"}" style="${n.status === "Unread" ? "background:#F5F7FA" : ""}">
          <div class="fw-medium">${global.HR.format.escapeHtml(n.title)}</div>
          <div class="text-meta">${global.HR.format.escapeHtml(n.body || "")}</div>
          <div class="text-meta mt-1">${global.HR.format.date(n.createdAt)}</div>
        </div>`
        )
        .join("")}
      <div class="text-center py-2"><a href="reports.html?tab=notifications" class="small">View all notifications</a></div>`;
  }

  function wireGlobalSearch() {
    const input = document.getElementById("globalSearchInput");
    const results = document.getElementById("globalSearchResults");
    if (!input || !results) return;

    input.addEventListener("input", () => {
      const q = input.value.trim().toLowerCase();
      if (q.length < 2) { results.classList.remove("show"); return; }

      const db = global.HR.db;
      const employees = db.employees.find((e) =>
        `${e.firstName} ${e.lastName}`.toLowerCase().includes(q) ||
        (e.companyEmail || "").toLowerCase().includes(q) ||
        (e.employeeNumber || "").toLowerCase().includes(q) ||
        (e.jobTitle || "").toLowerCase().includes(q)
      ).slice(0, 5);
      const documents = db.documents.find((d) => (d.title || "").toLowerCase().includes(q)).slice(0, 4);
      const tasks = db.tasks.find((t) => (t.title || "").toLowerCase().includes(q)).slice(0, 4);

      let html = "";
      if (employees.length) {
        html += `<h6 class="dropdown-header">Employees</h6>`;
        html += employees.map((e) => `<a class="dropdown-item" href="employee-profile.html?id=${e.id}">${e.firstName} ${e.lastName} <span class="text-meta">${e.jobTitle}</span></a>`).join("");
      }
      if (documents.length) {
        html += `<h6 class="dropdown-header">Documents</h6>`;
        html += documents.map((d) => `<a class="dropdown-item" href="documents.html?highlight=${d.id}">${d.title}</a>`).join("");
      }
      if (tasks.length) {
        html += `<h6 class="dropdown-header">HR Tasks</h6>`;
        html += tasks.map((t) => `<a class="dropdown-item" href="reports.html?tab=tasks&highlight=${t.id}">${t.title}</a>`).join("");
      }
      if (!html) html = `<div class="px-3 py-3 text-meta">No matches found for "${global.HR.format.escapeHtml(input.value)}".</div>`;
      results.innerHTML = html;
      results.classList.add("show");
    });

    document.addEventListener("click", (e) => {
      if (!results.contains(e.target) && e.target !== input) results.classList.remove("show");
    });
  }

  function wireSidebarToggle() {
    const toggle = document.getElementById("sidebarToggle");
    const sidebar = document.getElementById("appSidebar");
    const backdrop = document.getElementById("sidebarBackdrop");
    if (!toggle || !sidebar) return;
    const close = () => { sidebar.classList.remove("is-open"); backdrop.classList.remove("is-open"); toggle.setAttribute("aria-expanded", "false"); };
    const open = () => { sidebar.classList.add("is-open"); backdrop.classList.add("is-open"); toggle.setAttribute("aria-expanded", "true"); };
    toggle.addEventListener("click", () => (sidebar.classList.contains("is-open") ? close() : open()));
    backdrop.addEventListener("click", close);
    sidebar.querySelectorAll("a").forEach((a) => a.addEventListener("click", close));
  }

  function mount(opts) {
    const config = Object.assign({ active: "" }, opts);
    const user = global.HR.auth.requireAuth();
    if (!user) return null;

    const sidebarMount = document.getElementById("sidebarMount");
    const headerMount = document.getElementById("headerMount");
    if (sidebarMount) sidebarMount.innerHTML = renderSidebar(config.active, user);
    if (headerMount) headerMount.innerHTML = renderHeader(user);

    wireSidebarToggle();
    wireGlobalSearch();
    renderQuickCreate(user);
    renderNotifications(user);

    const signOutBtn = document.getElementById("signOutBtn");
    if (signOutBtn) {
      signOutBtn.addEventListener("click", () => {
        global.HR.auth.logout();
        window.location.href = "login.html";
      });
    }

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));

    return user;
  }

  global.HR = global.HR || {};
  global.HR.nav = { mount };
})(window);

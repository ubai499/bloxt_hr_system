/**
 * app.js
 * Shared application utilities: formatting, confirmation dialogs, table
 * helpers (search/sort/paginate), CSV export and print support. Loaded on
 * every page after storage.js / notifications.js / navigation.js.
 */
(function (global) {
  "use strict";

  // ---------------------------------------------------------------------------
  // Formatting
  // ---------------------------------------------------------------------------
  const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

  function formatDate(isoDate, opts) {
    if (!isoDate) return "—";
    const d = new Date(isoDate.length <= 10 ? isoDate + "T00:00:00" : isoDate);
    if (isNaN(d.getTime())) return "—";
    const config = Object.assign({ withTime: false }, opts);
    const base = `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
    if (!config.withTime) return base;
    const time = d.toLocaleTimeString("en-GB", { hour: "2-digit", minute: "2-digit", timeZone: "Europe/London" });
    return `${base}, ${time} ${Intl.DateTimeFormat("en-GB", { timeZoneName: "short", timeZone: "Europe/London" }).formatToParts(d).find((p) => p.type === "timeZoneName").value}`;
  }

  function formatCurrency(amount) {
    if (amount === null || amount === undefined || amount === "") return "—";
    return new Intl.NumberFormat("en-GB", { style: "currency", currency: "GBP" }).format(amount);
  }

  function daysUntil(isoDate) {
    if (!isoDate) return null;
    const target = new Date(isoDate.length <= 10 ? isoDate + "T00:00:00" : isoDate);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    target.setHours(0, 0, 0, 0);
    return Math.round((target - today) / 86400000);
  }

  function initials(firstName, lastName) {
    return `${(firstName || "").charAt(0)}${(lastName || "").charAt(0)}`.toUpperCase();
  }

  function escapeHtml(str) {
    if (str === null || str === undefined) return "";
    return String(str)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }

  // ---------------------------------------------------------------------------
  // Status badge rendering
  // ---------------------------------------------------------------------------
  const STATUS_TONE_MAP = {
    Active: "success", Valid: "success", Approved: "success", Completed: "success", Current: "success", Reported: "success",
    "Review Due": "warning", "Expiring Soon": "warning", Pending: "warning", "Awaiting Information": "warning",
    "Requires Review": "warning", "Follow-up Required": "warning", "On Leave": "warning", Probation: "warning",
    Expired: "danger", "Non-sponsored": "neutral", Rejected: "danger", "Unauthorised Absence": "danger",
    "Report Required": "danger", "Action Required": "danger", "Notice Period": "danger", Overdue: "danger",
    "Evidence Missing": "danger", Inactive: "neutral", Cancelled: "neutral", "Not Applicable": "neutral", Left: "neutral",
    Sponsored: "accent", "In Progress": "info", Open: "info", "No Action": "success", "Not Reportable": "success"
  };

  function statusBadge(status) {
    const tone = STATUS_TONE_MAP[status] || "neutral";
    return `<span class="status-badge badge-${tone}">${escapeHtml(status)}</span>`;
  }

  // ---------------------------------------------------------------------------
  // Confirmation dialogs (Bootstrap modal — never native confirm())
  // ---------------------------------------------------------------------------
  function confirmAction(opts) {
    return new Promise((resolve) => {
      const config = Object.assign({
        title: "Confirm action", body: "Are you sure you want to continue?",
        confirmLabel: "Confirm", confirmVariant: "danger", cancelLabel: "Cancel"
      }, opts);

      let modalEl = document.getElementById("globalConfirmModal");
      if (modalEl) modalEl.remove();

      modalEl = document.createElement("div");
      modalEl.className = "modal fade";
      modalEl.id = "globalConfirmModal";
      modalEl.tabIndex = -1;
      modalEl.setAttribute("aria-hidden", "true");
      modalEl.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content ${config.confirmVariant === "danger" ? "modal-danger" : ""}">
            <div class="modal-header">
              <h2 class="modal-title h5">${escapeHtml(config.title)}</h2>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">${config.body}</div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light-custom" data-action="cancel">${escapeHtml(config.cancelLabel)}</button>
              <button type="button" class="btn btn-${config.confirmVariant === "danger" ? "danger" : "primary"}" data-action="confirm">${escapeHtml(config.confirmLabel)}</button>
            </div>
          </div>
        </div>`;
      document.body.appendChild(modalEl);
      const bsModal = new bootstrap.Modal(modalEl);

      let resolved = false;
      modalEl.querySelector('[data-action="confirm"]').addEventListener("click", () => {
        resolved = true;
        bsModal.hide();
        resolve(true);
      });
      modalEl.querySelector('[data-action="cancel"]').addEventListener("click", () => {
        resolved = true;
      });
      modalEl.addEventListener("hidden.bs.modal", () => {
        modalEl.remove();
        if (!resolved) resolve(false);
      });
      bsModal.show();
    });
  }

  // ---------------------------------------------------------------------------
  // CSV export + print
  // ---------------------------------------------------------------------------
  function exportCsv(filename, headers, rows) {
    const escapeCell = (v) => {
      const s = v === null || v === undefined ? "" : String(v);
      return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
    };
    const lines = [headers.map(escapeCell).join(",")];
    rows.forEach((r) => lines.push(r.map(escapeCell).join(",")));
    const blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
    toastSuccess("Export complete", `${filename} has been downloaded.`);
  }

  function printPage() {
    window.print();
  }

  // ---------------------------------------------------------------------------
  // jQuery DataTables integration
  // -----------------------------------------------------------------------------
  // Every data table in the app is powered by jQuery DataTables (core +
  // Bootstrap 5 styling) for sorting, pagination and search. Responsive
  // column-collapsing is intentionally off — narrow tables scroll
  // horizontally within their own .table-scroll wrapper instead, so no
  // separate expand/collapse control button is shown. The app keeps its own
  // premium toolbar (search input, filter dropdowns, export button) rather
  // than DataTables' default controls, and drives the table through its API
  // — see createFilterState() for the exact-match dropdown filters.
  // ---------------------------------------------------------------------------
  function initDataTable(selector, config) {
    const emptyIcon = config.emptyIcon || "bi-inbox";
    const emptyTitle = config.emptyTitle || "No records found";
    const emptyText = config.emptyText || "Try adjusting your search or filters.";
    const emptyHtml = `<div class="empty-state"><div class="empty-state-icon"><i class="bi ${emptyIcon}"></i></div><div class="empty-state-title">${escapeHtml(emptyTitle)}</div><div class="empty-state-text">${escapeHtml(emptyText)}</div></div>`;

    const pagingEnabled = config.paging !== false;
    const defaults = {
      autoWidth: false,
      responsive: false,
      pageLength: config.pageLength || 10,
      lengthMenu: [10, 25, 50, 100],
      lengthChange: pagingEnabled,
      dom: pagingEnabled
        ? '<"table-meta-bar d-flex flex-wrap justify-content-between align-items-center gap-3"li><"table-scroll"t><"table-footer d-flex justify-content-end align-items-center"p>'
        : '<"table-meta-bar d-flex justify-content-end align-items-center"i><"table-scroll"t>',
      language: {
        search: "",
        emptyTable: emptyHtml,
        zeroRecords: emptyHtml,
        info: "Showing _START_–_END_ of _TOTAL_",
        infoEmpty: "No records",
        infoFiltered: "",
        lengthMenu: "Show _MENU_",
        paginate: { previous: '<i class="bi bi-chevron-left"></i>', next: '<i class="bi bi-chevron-right"></i>' }
      }
    };

    const merged = Object.assign({}, defaults, config, {
      language: Object.assign({}, defaults.language, config.language || {})
    });
    delete merged.emptyIcon;
    delete merged.emptyTitle;
    delete merged.emptyText;

    return window.jQuery(selector).DataTable(merged);
  }

  /**
   * Custom exact-match dropdown filtering for a DataTable, since DataTables'
   * own search only does free-text matching. `tableEl` must have a unique id.
   * Usage: const filters = HR.ui.createFilterState(table, "employeeTable");
   *        filters.set("dept", val === "all" ? null : (row) => row.departmentId === val);
   */
  function createFilterState(tableApi, tableId) {
    const predicates = {};
    window.jQuery.fn.dataTable.ext.search.push(function (settings, searchData, index, rowData) {
      const settingsTableId = settings.tableId || (settings.nTable && settings.nTable.id);
      if (settingsTableId !== tableId) return true;
      return Object.values(predicates).every((fn) => fn(rowData));
    });
    return {
      set(key, predicateFn) {
        if (!predicateFn) delete predicates[key];
        else predicates[key] = predicateFn;
        tableApi.draw();
      }
    };
  }

  function toastSuccess(title, text) { return global.HR.toast({ type: "success", title, text }); }
  function toastError(title, text) { return global.HR.toast({ type: "danger", title, text }); }

  // ---------------------------------------------------------------------------
  // Skeleton loading rows
  // ---------------------------------------------------------------------------
  global.HR = global.HR || {};
  global.HR.format = { date: formatDate, currency: formatCurrency, daysUntil, initials, escapeHtml, statusBadge };
  global.HR.ui = {
    confirmAction, exportCsv, printPage,
    toastSuccess, toastError, initDataTable, createFilterState
  };
})(window);

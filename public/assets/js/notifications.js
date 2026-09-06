/**
 * notifications.js
 * Toast feedback (transient) + persistent notification centre entries.
 */
(function (global) {
  "use strict";

  function ensureToastStack() {
    let stack = document.querySelector(".toast-stack");
    if (!stack) {
      stack = document.createElement("div");
      stack.className = "toast-stack";
      stack.setAttribute("aria-live", "polite");
      document.body.appendChild(stack);
    }
    return stack;
  }

  const ICONS = {
    success: "bi-check-circle-fill",
    warning: "bi-exclamation-triangle-fill",
    danger: "bi-x-circle-fill",
    info: "bi-info-circle-fill"
  };

  function toast(opts) {
    const config = Object.assign({ type: "info", title: "", text: "", duration: 5000 }, opts);
    if (!Object.prototype.hasOwnProperty.call(ICONS, config.type)) config.type = "info";
    const stack = ensureToastStack();
    const el = document.createElement("div");
    el.className = `app-toast toast-${config.type}`;
    el.setAttribute("role", "status");
    el.innerHTML = `
      <i class="bi ${ICONS[config.type] || ICONS.info} app-toast-icon"></i>
      <div class="app-toast-body">
      </div>
      <button type="button" class="app-toast-close" aria-label="Dismiss notification">&times;</button>
    `;
    const body = el.querySelector(".app-toast-body");
    for (const [className, value] of [["app-toast-title", config.title], ["app-toast-text", config.text]]) {
      if (!value) continue;
      const line = document.createElement("div");
      line.className = className;
      line.textContent = value;
      body.appendChild(line);
    }
    stack.appendChild(el);
    const remove = () => {
      el.style.transition = "opacity 0.2s ease";
      el.style.opacity = "0";
      setTimeout(() => el.remove(), 200);
    };
    el.querySelector(".app-toast-close").addEventListener("click", remove);
    if (config.duration) setTimeout(remove, config.duration);
    return el;
  }

  // Persistent notification centre entries --------------------------------------
  function createNotification(cfg) {
    return global.HR.db.notifications.create(Object.assign({
      status: "Unread", createdAt: global.HR.util.nowISO()
    }, cfg));
  }

  function getNotificationsForUser(user) {
    if (!user) return [];
    return global.HR.db.notifications
      .find((n) => !n.userId || n.userId === user.id)
      .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
  }

  function markRead(id) {
    global.HR.db.notifications.update(id, { status: "Read" });
  }

  function markResolved(id) {
    global.HR.db.notifications.update(id, { status: "Resolved" });
  }

  function unreadCount(user) {
    return getNotificationsForUser(user).filter((n) => n.status === "Unread").length;
  }

  global.HR = global.HR || {};
  global.HR.toast = toast;
  global.HR.notifications = { create: createNotification, forUser: getNotificationsForUser, markRead, markResolved, unreadCount };
})(window);

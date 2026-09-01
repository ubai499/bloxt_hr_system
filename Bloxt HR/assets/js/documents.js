/**
 * documents.js
 * Helpers for the central Document Library, including expiry reminder
 * bucketing driven by the configurable reminder-interval setting.
 */
(function (global) {
  "use strict";

  function reminderDays() {
    const company = global.HR.company.get();
    return (company && company.documentExpiryReminderDays) || [90, 60, 30, 14, 7];
  }

  function expiryBucket(doc) {
    if (!doc.expiryDate) return null;
    const days = global.HR.format.daysUntil(doc.expiryDate);
    if (days < 0) return "Expired";
    const thresholds = reminderDays().slice().sort((a, b) => a - b);
    for (const t of thresholds) {
      if (days <= t) return `Within ${t} days`;
    }
    return null;
  }

  function expiringWithin(days) {
    return global.HR.db.documents.find((d) => {
      if (!d.expiryDate) return false;
      const remaining = global.HR.format.daysUntil(d.expiryDate);
      return remaining !== null && remaining >= 0 && remaining <= days;
    });
  }

  global.HR = global.HR || {};
  global.HR.documentHelpers = { reminderDays, expiryBucket, expiringWithin };
})(window);

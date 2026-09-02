/**
 * validation.js
 * Reusable client-side validation helpers. These mirror checks that MUST also
 * be enforced server-side in a production build — client validation here is
 * for UX only, not a security boundary.
 */
(function (global) {
  "use strict";

  const UK_POSTCODE_RE = /^[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2}$/i;
  const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const PHONE_RE = /^[0-9+()\s-]{7,20}$/;

  const rules = {
    required(value) {
      if (value === null || value === undefined) return "This field is required.";
      if (typeof value === "string" && value.trim() === "") return "This field is required.";
      return null;
    },
    email(value) {
      if (!value) return null;
      return EMAIL_RE.test(value) ? null : "Enter a valid email address.";
    },
    ukPostcode(value) {
      if (!value) return null;
      return UK_POSTCODE_RE.test(value.trim()) ? null : "Enter a valid UK postcode (e.g. LS1 4PR).";
    },
    phone(value) {
      if (!value) return null;
      return PHONE_RE.test(value) ? null : "Enter a valid phone number.";
    },
    numeric(value) {
      if (value === "" || value === null || value === undefined) return null;
      return isNaN(Number(value)) ? "Enter a numeric value." : null;
    },
    positiveNumber(value) {
      if (value === "" || value === null || value === undefined) return null;
      const n = Number(value);
      return isNaN(n) || n < 0 ? "Enter a positive number." : null;
    },
    maxWeeklyHours(value) {
      if (value === "" || value === null || value === undefined) return null;
      const n = Number(value);
      return isNaN(n) || n < 0 || n > 84 ? "Enter a realistic weekly hours figure." : null;
    },
    dateNotFuture(value) {
      if (!value) return null;
      return new Date(value) > new Date() ? "Date cannot be in the future." : null;
    },
    endAfterStart(endValue, startValue) {
      if (!endValue || !startValue) return null;
      return new Date(endValue) < new Date(startValue) ? "End date must be after the start date." : null;
    }
  };

  /**
   * Validate a form element against declared rules and toggle Bootstrap-style
   * invalid feedback. `fieldRules` is { fieldName: [ruleFns...] }.
   * Returns true if the whole form is valid.
   */
  function validateForm(formEl, fieldRules) {
    let isValid = true;
    Object.keys(fieldRules).forEach((fieldName) => {
      const field = formEl.querySelector(`[name="${fieldName}"]`);
      if (!field) return;
      const wrapper = field.closest(".form-field") || field.parentElement;
      const feedback = wrapper ? wrapper.querySelector(".invalid-feedback-custom") : null;
      let message = null;
      for (const rule of fieldRules[fieldName]) {
        message = rule(field.value, formEl);
        if (message) break;
      }
      if (message) {
        isValid = false;
        if (wrapper) wrapper.classList.add("field-invalid");
        if (feedback) feedback.textContent = message;
      } else {
        if (wrapper) wrapper.classList.remove("field-invalid");
      }
    });
    return isValid;
  }

  function clearValidation(formEl) {
    formEl.querySelectorAll(".field-invalid").forEach((el) => el.classList.remove("field-invalid"));
  }

  function isDuplicateEmployeeNumber(value, excludeId) {
    return global.HR.db.employees.find((e) => e.employeeNumber === value && e.id !== excludeId).length > 0;
  }

  global.HR = global.HR || {};
  global.HR.validate = { rules, validateForm, clearValidation, isDuplicateEmployeeNumber };
})(window);

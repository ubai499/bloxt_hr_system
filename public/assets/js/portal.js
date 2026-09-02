document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('appSidebar');
    var backdrop = document.getElementById('sidebarBackdrop');

    function closeSidebar() {
        if (!sidebar || !backdrop || !toggle) {
            return;
        }

        sidebar.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
    }

    function openSidebar() {
        if (!sidebar || !backdrop || !toggle) {
            return;
        }

        sidebar.classList.add('is-open');
        backdrop.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
    }

    if (toggle && sidebar && backdrop) {
        toggle.addEventListener('click', function () {
            if (sidebar.classList.contains('is-open')) {
                closeSidebar();
                return;
            }

            openSidebar();
        });

        backdrop.addEventListener('click', closeSidebar);

        sidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeSidebar);
        });
    }

    document.querySelectorAll('[data-action="logout"]').forEach(function (button) {
        button.addEventListener('click', function () {
            var form = document.getElementById('logout-form');

            if (form) {
                form.submit();
            }
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var target = document.getElementById(button.getAttribute('data-password-target'));

            if (!target) {
                return;
            }

            var icon = button.querySelector('i');
            var isPassword = target.type === 'password';

            target.type = isPassword ? 'text' : 'password';

            if (icon) {
                icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
            }
        });
    });
});

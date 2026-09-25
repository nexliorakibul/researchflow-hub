(() => {
    'use strict';

    const body = document.body;
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const closeButton = document.querySelector('[data-sidebar-close]');

    const setSidebarOpen = (isOpen) => {
        body.classList.toggle('sidebar-open', isOpen);
        if (toggle) {
            toggle.setAttribute('aria-expanded', String(isOpen));
        }
    };

    if (toggle && closeButton) {
        toggle.addEventListener('click', () => {
            setSidebarOpen(!body.classList.contains('sidebar-open'));
        });

        closeButton.addEventListener('click', () => setSidebarOpen(false));

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setSidebarOpen(false);
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 992) {
                setSidebarOpen(false);
            }
        });
    }

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm');

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

})();

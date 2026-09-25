(() => {
    'use strict';

    document.querySelectorAll('[data-task-form]').forEach((form) => {
        const startDate = form.querySelector('#start_date');
        const deadline = form.querySelector('#deadline');
        if (!startDate || !deadline) {
            return;
        }

        const validateDates = () => {
            deadline.min = startDate.value || '';
            const invalid = startDate.value && deadline.value && deadline.value < startDate.value;
            deadline.setCustomValidity(invalid ? 'Deadline cannot be earlier than the start date.' : '');
        };

        startDate.addEventListener('change', validateDates);
        deadline.addEventListener('change', validateDates);
        form.addEventListener('submit', validateDates);
        validateDates();
    });
})();

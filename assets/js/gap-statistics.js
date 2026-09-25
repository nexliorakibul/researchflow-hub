(() => {
    'use strict';

    const canvas = document.querySelector('#gap-type-chart');
    const fallback = document.querySelector('#gap-chart-fallback');

    if (!canvas) {
        return;
    }

    const showFallback = () => {
        if (fallback) {
            fallback.hidden = false;
        }
    };

    if (typeof window.Chart !== 'function') {
        showFallback();
        return;
    }

    try {
        const labels = JSON.parse(canvas.dataset.labels || '[]');
        const values = JSON.parse(canvas.dataset.values || '[]');

        new window.Chart(canvas, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    label: 'Research gaps',
                    data: values,
                    backgroundColor: [
                        '#3157d5', '#6f8cff', '#12a36d', '#e6a11a',
                        '#d65353', '#8b5cf6', '#0f8b8d', '#ec4899',
                        '#64748b', '#f97316', '#94a3b8',
                    ],
                    borderColor: '#ffffff',
                    borderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, padding: 14 },
                    },
                },
            },
        });
    } catch (error) {
        showFallback();
    }
})();

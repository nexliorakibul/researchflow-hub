(() => {
    'use strict';

    const canvases = document.querySelectorAll('[data-experiment-chart]');
    if (canvases.length === 0) {
        return;
    }

    canvases.forEach((canvas, index) => {
        const panel = canvas.closest('.comparison-chart-panel');
        const fallback = panel ? panel.querySelector('[data-chart-fallback]') : null;
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
            const colors = ['#3157d5', '#12a36d', '#e6a11a'];

            new window.Chart(canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: `${canvas.dataset.metric || 'Metric'} (%)`,
                        data: values,
                        backgroundColor: colors[index % colors.length],
                        borderRadius: 5,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: { display: true, text: 'Percentage' },
                        },
                        x: {
                            ticks: { maxRotation: 45, minRotation: 0 },
                        },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => context.raw === null ? 'Not recorded' : `${context.raw}%`,
                            },
                        },
                    },
                },
            });
        } catch (error) {
            showFallback();
        }
    });
})();

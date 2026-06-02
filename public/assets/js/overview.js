// ===== REVENUE CHART =====
runWhenReady( function() {
    const ctx = document.getElementById('revenueChart');
    if (!ctx) return;

    // Chart data - 7 days (fallback nếu không có PHP data)
    const chartData = {
        labels: ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'],
        datasets: [{
            label: 'Doanh thu (triệu VNĐ)',
            data: [28.5, 32.8, 25.3, 38.9, 42.1, 36.7, 48.2],
            borderColor: '#b5890f',
            backgroundColor: function(context) {
                const ctx = context.chart.ctx;
                const gradient = ctx.createLinearGradient(0, 0, 0, 280);
                gradient.addColorStop(0, 'rgba(181, 137, 15, 0.18)');
                gradient.addColorStop(1, 'rgba(181, 137, 15, 0.01)');
                return gradient;
            },
            borderWidth: 2.5,
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: '#d4a017',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointHoverBackgroundColor: '#b5890f',
            pointHoverBorderColor: '#d4a017',
            pointHoverBorderWidth: 2
        }]
    };

    const config = {
        type: 'line',
        data: chartData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.97)',
                    titleColor: '#1a1d23',
                    bodyColor: '#b5890f',
                    borderColor: 'rgba(181, 137, 15, 0.25)',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    titleFont: { weight: '700', size: 13 },
                    bodyFont: { weight: '600', size: 13 },
                    boxShadow: '0 4px 16px rgba(0,0,0,0.1)',
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y.toFixed(1) + ' triệu VNĐ';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#9ca3af',
                        font: {
                            size: 12,
                            weight: '500',
                            family: "'Be Vietnam Pro', sans-serif"
                        },
                        callback: function(value) {
                            return value + 'M';
                        }
                    }
                },
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        color: '#6b7280',
                        font: {
                            size: 12,
                            weight: '600',
                            family: "'Be Vietnam Pro', sans-serif"
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    };

    new Chart(ctx, config);
});

// ===== ANIMATE STATS ON LOAD =====
function animateValue(element, start, end, duration) {
    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;

    const timer = setInterval(() => {
        current += increment;
        if (current >= end) {
            current = end;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current).toLocaleString('vi-VN');
    }, 16);
}

runWhenReady( function() {
    const statValues = document.querySelectorAll('.stat-value');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const element = entry.target;
                const text = element.textContent.replace(/[^\d.]/g, '');
                const endValue = parseFloat(text);
                
                if (!isNaN(endValue) && endValue > 0) {
                    element.textContent = '0';
                    animateValue(element, 0, endValue, 1500);
                }
                
                observer.unobserve(element);
            }
        });
    }, { threshold: 0.5 });

    statValues.forEach(stat => observer.observe(stat));
});

// ===== REFRESH BUTTON =====
document.querySelectorAll('.btn-icon').forEach(btn => {
    btn.addEventListener('click', function() {
        const icon = this.querySelector('i');
        if (icon && icon.classList.contains('fa-sync')) {
            icon.classList.add('fa-spin');
            setTimeout(() => {
                icon.classList.remove('fa-spin');
                location.reload();
            }, 600);
        }
    });
});

// ===== PACKAGE PROGRESS ANIMATION =====
runWhenReady( function() {
    const progressBars = document.querySelectorAll('.progress-bar');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const bar = entry.target;
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 120);
                observer.unobserve(bar);
            }
        });
    }, { threshold: 0.5 });

    progressBars.forEach(bar => observer.observe(bar));
});

// ===== LOG =====
console.log('%c✨ Elite Gym Overview Dashboard Loaded', 'color: #b5890f; font-size: 16px; font-weight: bold;');

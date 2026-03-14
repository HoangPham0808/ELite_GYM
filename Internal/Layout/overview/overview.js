// ===== REVENUE CHART =====
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('revenueChart');
    if (!ctx) return;

    // Chart data - 7 days
    const chartData = {
        labels: ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'],
        datasets: [{
            label: 'Doanh thu (triệu VNĐ)',
            data: [28.5, 32.8, 25.3, 38.9, 42.1, 36.7, 48.2],
            borderColor: '#d4a017',
            backgroundColor: function(context) {
                const ctx = context.chart.ctx;
                const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                gradient.addColorStop(0, 'rgba(212, 160, 23, 0.3)');
                gradient.addColorStop(1, 'rgba(212, 160, 23, 0)');
                return gradient;
            },
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointRadius: 5,
            pointHoverRadius: 7,
            pointBackgroundColor: '#d4a017',
            pointBorderColor: '#000',
            pointBorderWidth: 2,
            pointHoverBackgroundColor: '#f0c040',
            pointHoverBorderColor: '#d4a017',
            pointHoverBorderWidth: 3
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
                    backgroundColor: 'rgba(26, 26, 26, 0.95)',
                    titleColor: '#ffffff',
                    bodyColor: '#d4a017',
                    borderColor: 'rgba(212, 160, 23, 0.3)',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
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
                        color: 'rgba(255, 255, 255, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        color: 'rgba(255, 255, 255, 0.5)',
                        font: {
                            size: 12,
                            weight: '500'
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
                        color: 'rgba(255, 255, 255, 0.6)',
                        font: {
                            size: 13,
                            weight: '600'
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

document.addEventListener('DOMContentLoaded', function() {
    const statValues = document.querySelectorAll('.stat-value');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const element = entry.target;
                const text = element.textContent.replace(/[^\d.]/g, '');
                const endValue = parseFloat(text);
                
                if (!isNaN(endValue)) {
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
    btn.addEventListener('click', function(e) {
        const icon = this.querySelector('i');
        if (icon && icon.classList.contains('fa-sync')) {
            icon.classList.add('fa-spin');
            setTimeout(() => {
                icon.classList.remove('fa-spin');
                // Add refresh logic here
                console.log('Refreshing data...');
            }, 1000);
        }
    });
});

// ===== SMOOTH SCROLL FOR LINKS =====
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href === '#') return;
        
        // Let parent handle routing
        if (window.parent && window.parent !== window) {
            window.parent.location.hash = href;
        }
    });
});

// ===== AUTO-REFRESH ACTIVITY (Optional) =====
// Uncomment if you want to auto-refresh activities
/*
setInterval(() => {
    // Fetch new activities via AJAX
    console.log('Auto-refreshing activities...');
}, 30000); // Every 30 seconds
*/

// ===== PACKAGE PROGRESS ANIMATION =====
document.addEventListener('DOMContentLoaded', function() {
    const progressBars = document.querySelectorAll('.progress-bar');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const bar = entry.target;
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
                observer.unobserve(bar);
            }
        });
    }, { threshold: 0.5 });

    progressBars.forEach(bar => observer.observe(bar));
});

// ===== LOG FOR DEBUGGING =====
console.log('%c✨ Elite Gym Overview Dashboard Loaded', 'color: #d4a017; font-size: 16px; font-weight: bold;');
console.log('%cVersion 1.0', 'color: #666; font-size: 12px;');

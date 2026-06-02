/**
 * Chạy callback sau khi DOM sẵn sàng — hỗ trợ module admin load qua AJAX (sau DOMContentLoaded).
 */
window.runWhenReady = function (fn) {
    if (typeof fn !== 'function') return;
    const run = () => {
        try {
            fn();
        } catch (e) {
            console.error('[runWhenReady]', e);
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
        queueMicrotask(run);
    }
};

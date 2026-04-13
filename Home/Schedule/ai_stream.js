/**
 * ai_stream.js  v4  — SSE Streaming client cho ai_proxy.php (Anthropic)
 * ══════════════════════════════════════════════════════════════
 * Nhận SSE từ ai_proxy.php và hiển thị:
 *   1. Token xuất hiện dần (plain text) trong khi stream
 *   2. Khi event:done → render thành HTML đẹp qua aiMarkdownToHTML()
 *   3. Cập nhật equipment tag từ event:meta ngay lập tức
 *
 * Export: fetchWorkoutStream(params, outputEl, onMeta?, onDone?)
 * ══════════════════════════════════════════════════════════════
 */

/**
 * @param {object}   params      - { class_id, bmi, bmi_cat, goal, burn_target, gender, age }
 * @param {Element}  outputEl    - DOM element sẽ hiển thị kết quả
 * @param {Function} [onMeta]    - callback(meta) khi nhận event:meta
 * @param {Function} [onDone]    - callback(fullText) khi stream xong
 */
function fetchWorkoutStream(params, outputEl, onMeta, onDone) {

    // ── Loading state ──────────────────────────────────────────
    outputEl.innerHTML = `
        <div class="ai-loading">
            <div class="ai-spinner"></div>
            AI đang phân tích thiết bị phòng và tạo lịch tập...
        </div>`;

    let fullText   = '';
    let metaSent   = false;
    let lineBuffer = '';

    // ── Fetch + đọc stream thủ công (không dùng EventSource
    //    vì cần POST với body JSON) ────────────────────────────
    fetch('ai_proxy.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(params),
    })
    .then(res => {
        if (!res.ok) throw new Error('HTTP ' + res.status);

        const reader  = res.body.getReader();
        const decoder = new TextDecoder('utf-8');

        // Xoá loading ngay khi bắt đầu nhận dữ liệu
        let started = false;

        // ── Hàm xử lý từng SSE line ─────────────────────────
        function processLine(line) {
            line = line.trim();
            if (!line) return;

            // Bỏ qua các event header (không phải data)
            if (line === 'event: meta') return;
            if (line === 'event: done') return;
            if (line === 'event: error') return;

            // ── data: {...} ──────────────────────────────────
            if (!line.startsWith('data: ')) return;

            let obj;
            try { obj = JSON.parse(line.slice(6)); }
            catch (_) { return; }

            // Meta (room, equipment, duration)
            if (obj.room !== undefined && !metaSent) {
                metaSent = true;
                if (typeof onMeta === 'function') onMeta(obj);
                return;
            }

            // Error từ server (Anthropic hoặc PHP)
            if (obj.error) {
                outputEl.innerHTML = `
                    <div style="color:#ef4444;padding:16px;font-size:13px;line-height:1.8">
                        ⚠️ ${obj.error}
                        <br><small style="color:#888;margin-top:8px;display:block">
                            Nếu lỗi liên quan đến API key, vui lòng liên hệ quản trị viên.
                        </small>
                    </div>`;
                return;
            }

            // Token thực sự → hiển thị dần
            if (typeof obj.token === 'string' && obj.token !== '') {
                if (!started) {
                    started = true;
                    outputEl.innerHTML = ''; // xoá loading
                    // Tạo vùng text streaming
                    outputEl.innerHTML = '<pre class="ai-stream-text"></pre>';
                }
                fullText += obj.token;

                // Cập nhật text trực tiếp (pre tag giữ whitespace/newline)
                const pre = outputEl.querySelector('.ai-stream-text');
                if (pre) pre.textContent = fullText;

                // Auto-scroll
                outputEl.scrollTop = outputEl.scrollHeight;
            }
        }

        // ── Xử lý từng chunk từ reader ───────────────────────
        function processChunk(value) {
            lineBuffer += decoder.decode(value, { stream: true });
            const parts = lineBuffer.split('\n');
            lineBuffer  = parts.pop(); // dòng chưa đủ, giữ lại

            for (const line of parts) processLine(line);
        }

        // ── Khi stream kết thúc (event:done hoặc reader done) ─
        function handleDone() {
            // Xử lý phần còn trong buffer
            if (lineBuffer.trim()) processLine(lineBuffer);

            if (!fullText.trim()) {
                outputEl.innerHTML = `
                    <div style="color:#f59e0b;padding:16px;font-size:13px">
                        ⚠️ Không nhận được nội dung từ AI. Vui lòng thử lại.
                    </div>`;
                return;
            }

            // Render markdown đẹp
            if (typeof aiMarkdownToHTML === 'function') {
                outputEl.innerHTML = '<div class="ai-plan">' + aiMarkdownToHTML(fullText) + '</div>';
            } else {
                // Fallback nếu aiMarkdownToHTML chưa load
                outputEl.innerHTML = `<pre style="white-space:pre-wrap;font-size:13px;color:#ccc;line-height:1.7">${fullText}</pre>`;
            }

            if (typeof onDone === 'function') onDone(fullText);
        }

        // ── Pump: đọc từng chunk cho đến hết ─────────────────
        function pump() {
            return reader.read().then(({ done, value }) => {
                if (done) { handleDone(); return; }
                processChunk(value);
                return pump();
            });
        }

        return pump();
    })
    .catch(err => {
        outputEl.innerHTML = `
            <div style="color:#ef4444;padding:16px;font-size:13px;line-height:1.8">
                ⚠️ Lỗi kết nối: ${err.message}
                <br><small style="color:#888;margin-top:8px;display:block">
                    Vui lòng kiểm tra kết nối mạng và thử lại.
                </small>
            </div>`;
    });
}

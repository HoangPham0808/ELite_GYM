<?php
ob_start();
require_once __DIR__ . '/../../../Internal/auth_check.php';
requireRole(['Admin', 'Employee']);
$_user_role = $_SESSION['role'] ?? 'Employee';
$_username  = $_SESSION['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Hóa đơn - Elite Gym</title>
    <link rel="stylesheet" href="Invoice_Management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="invoice-container">

    <!-- STATS STRIP -->
    <div class="stats-strip">
        <div class="strip-card blue">
            <div class="strip-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statTotal">—</div>
                <div class="strip-label">Tổng hóa đơn</div>
            </div>
        </div>
        <div class="strip-card green">
            <div class="strip-icon"><i class="fas fa-coins"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statRevenue">—</div>
                <div class="strip-label">Doanh thu tháng này</div>
            </div>
        </div>
        <div class="strip-card gold">
            <div class="strip-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statToday">—</div>
                <div class="strip-label">Hóa đơn hôm nay</div>
            </div>
        </div>
        <div class="strip-card pink">
            <div class="strip-icon"><i class="fas fa-tag"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statPromo">—</div>
                <div class="strip-label">Dùng khuyến mãi</div>
            </div>
        </div>
        <div class="strip-card purple">
            <div class="strip-icon"><i class="fas fa-chart-line"></i></div>
            <div class="strip-info">
                <div class="strip-value" id="statAvg">—</div>
                <div class="strip-label">Giá trị TB / đơn</div>
            </div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Tìm theo tên KH, SĐT, mã HĐ...">
        </div>
        <input type="month" class="filter-input" id="monthFilter" title="Lọc theo tháng">
        <select class="filter-select" id="sortFilter">
            <option value="id_desc">Mới nhất</option>
            <option value="id_asc">Cũ nhất</option>
            <option value="total_desc">Giá trị cao → thấp</option>
            <option value="total_asc">Giá trị thấp → cao</option>
        </select>
        <!-- Status filter: value = English ENUM, display = Vietnamese -->
        <select class="filter-select" id="statusFilter">
            <option value="">Tất cả trạng thái</option>
            <option value="Paid">✅ Đã thanh toán</option>
            <option value="Pending">⏳ Chờ thanh toán</option>
            <option value="Cancelled">❌ Đã hủy</option>
        </select>
        <button class="btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Tạo hóa đơn
        </button>
    </div>

    <!-- TABLE CARD -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-file-invoice-dollar" style="color:#d4a017;margin-right:8px;font-size:15px"></i>Danh sách hóa đơn</h3>
            <div class="table-header-right">
                <span class="table-meta" id="tableTotal">Đang tải...</span>
            </div>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Khách hàng</th>
                        <th>Ngày lập</th>
                        <th>Tiền gốc</th>
                        <th>Giảm giá</th>
                        <th>Thực thu</th>
                        <th>Khuyến mãi</th>
                        <th>Người tạo</th>
                        <th>Ghi chú</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="invoiceTbody">
                    <tr>
                        <td colspan="10" class="loading-cell"><i class="fas fa-spinner fa-spin"></i></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="pagination">
            <div class="pagination-info" id="paginationInfo">—</div>
            <div class="pagination-controls" id="paginationControls"></div>
        </div>
    </div>

</div>

<!-- ===== MODAL TẠO HÓA ĐƠN ===== -->
<div class="modal-overlay" id="invoiceModal">
    <div class="modal modal-large">
        <div class="modal-header">
            <h3><i class="fas fa-file-invoice" style="color:#d4a017;margin-right:8px"></i>Tạo hóa đơn mới</h3>
            <button class="btn-close" onclick="closeInvoiceModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="modal-two-col">

                <!-- LEFT: Thông tin hóa đơn -->
                <div class="modal-left">
                    <div class="section-label"><i class="fas fa-user"></i> Thông tin khách hàng</div>

                    <div class="form-group">
                        <label>Khách hàng <span class="required">*</span></label>
                        <div class="autocomplete-wrap">
                            <input type="text" id="fCustomerSearch" class="form-control"
                                   placeholder="Tìm tên hoặc số điện thoại..."
                                   oninput="searchCustomers(this.value)" autocomplete="off">
                            <div class="autocomplete-dropdown" id="customerDropdown"></div>
                        </div>
                        <input type="hidden" id="fCustomerId">
                        <div class="selected-customer" id="selectedCustomer" style="display:none"></div>
                    </div>

                    <div class="form-group">
                        <label>Ngày lập hóa đơn</label>
                        <input type="date" id="fNgayLap" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Ghi chú</label>
                        <textarea id="fGhiChu" class="form-control" rows="2" placeholder="Ghi chú thêm..."></textarea>
                    </div>

                    <!-- Status: value = English ENUM, display = Vietnamese -->
                    <div class="form-group">
                        <label>Trạng thái hóa đơn</label>
                        <select id="fTrangThaiHD" class="form-control">
                            <option value="Paid">✅ Đã thanh toán</option>
                            <option value="Pending" selected>⏳ Chờ thanh toán</option>
                            <option value="Cancelled">❌ Đã hủy</option>
                        </select>
                    </div>

                    <!-- Khuyến mãi -->
                    <div class="section-label" style="margin-top:16px"><i class="fas fa-tag"></i> Khuyến mãi</div>
                    <div class="promo-section">
                        <div id="promoPlaceholder" class="promo-placeholder">
                            <i class="fas fa-info-circle"></i>
                            Thêm sản phẩm trước để xem khuyến mãi áp dụng được
                        </div>
                        <div id="promoList" style="display:none">
                            <div class="promo-option none-option">
                                <label>
                                    <input type="radio" name="promoRadio" value="0" checked onchange="applyPromo(null)">
                                    <span>Không áp dụng khuyến mãi</span>
                                </label>
                            </div>
                            <div id="promoItems"></div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Sản phẩm -->
                <div class="modal-right">
                    <div class="section-label"><i class="fas fa-dumbbell"></i> Gói tập</div>

                    <div class="add-item-row">
                        <select id="fPackage" class="form-control" onchange="onPackageChange()">
                            <option value="">-- Chọn gói tập --</option>
                        </select>
                        <input type="number" id="fQty" class="form-control qty-input" value="1" min="1">
                        <button class="btn-add-item" onclick="addItem()">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div id="fPricePreview" class="price-preview" style="display:none"></div>

                    <div class="items-table-wrap">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Gói tập</th>
                                    <th>SL</th>
                                    <th>Đơn giá</th>
                                    <th>Thành tiền</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="itemsTbody">
                                <tr id="emptyItemsRow">
                                    <td colspan="5" class="empty-items">
                                        <i class="fas fa-shopping-cart"></i> Chưa có sản phẩm
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="invoice-summary">
                        <div class="summary-row">
                            <span>Tổng tiền gốc:</span>
                            <span id="summaryGoc">0 ₫</span>
                        </div>
                        <div class="summary-row discount-row" id="summaryDiscountRow" style="display:none">
                            <span id="summaryDiscountLabel">Giảm giá (0%):</span>
                            <span id="summaryDiscount" class="discount-value">- 0 ₫</span>
                        </div>
                        <div class="summary-row total-row">
                            <span>Thực thu:</span>
                            <span id="summaryTotal" class="total-value">0 ₫</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeInvoiceModal()">Hủy</button>
            <button class="btn-primary" onclick="saveInvoice()">
                <i class="fas fa-save"></i> Tạo hóa đơn
            </button>
        </div>
    </div>
</div>

<!-- ===== MODAL XEM CHI TIẾT ===== -->
<div class="modal-overlay" id="detailModal">
    <div class="modal modal-large">
        <div class="modal-header">
            <h3><i class="fas fa-file-alt" style="color:#d4a017;margin-right:8px"></i>Chi tiết hóa đơn</h3>
            <button class="btn-close" onclick="closeDetailModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="detailContent">
            <!-- Rendered by JS -->
        </div>
    </div>
</div>

<!-- ===== MODAL XÁC NHẬN XÓA ===== -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal confirm-modal">
        <div class="modal-body">
            <div class="confirm-icon"><i class="fas fa-trash-alt"></i></div>
            <h4>Xóa hóa đơn?</h4>
            <p>Bạn có chắc muốn xóa hóa đơn <strong id="deleteInvoiceName"></strong>?<br>
            Nếu hóa đơn dùng khuyến mãi, lượt sử dụng sẽ được hoàn lại.</p>
        </div>
        <div class="modal-footer" style="justify-content:center">
            <button class="btn-secondary" onclick="closeConfirmModal()">Hủy</button>
            <button class="btn-danger" onclick="doDelete()"><i class="fas fa-trash"></i> Xóa</button>
        </div>
    </div>
</div>

<!-- ===== MODAL THANH TOÁN QR ===== -->
<div class="modal-overlay" id="paymentModal">
    <div class="modal payment-modal">
        <div class="modal-header">
            <h3><i class="fas fa-qrcode" style="color:#d4a017;margin-right:8px"></i>Thanh toán hóa đơn</h3>
            <button class="btn-close" onclick="closePaymentModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body payment-modal-body">

            <!-- LEFT: QR + countdown -->
            <div class="payment-left">
                <div class="qr-bank-logo">
                    <i class="fas fa-university"></i>
                    <span id="payBankName">—</span>
                </div>
                <div id="qrSection" class="qr-section">
                    <div class="qr-loading"><i class="fas fa-spinner fa-spin"></i></div>
                </div>
                <div class="qr-countdown-row">
                    <i class="fas fa-clock"></i>
                    <span>QR hết hạn sau: <strong id="payCountdown">05:00</strong></span>
                    <button class="btn-refresh" onclick="refreshQR()" title="Làm mới QR">
                        <i class="fas fa-redo"></i>
                    </button>
                </div>
                <div class="qr-hint">
                    <i class="fas fa-mobile-alt"></i>
                    Mở app ngân hàng → Quét mã QR → Kiểm tra số tiền → Xác nhận
                </div>
            </div>

            <!-- RIGHT: Thông tin TT -->
            <div class="payment-right">
                <div class="pay-info-card">
                    <div class="pay-info-title"><i class="fas fa-file-invoice"></i> Thông tin hóa đơn</div>
                    <div class="pay-row">
                        <span class="pay-label">Mã hóa đơn</span>
                        <span class="pay-val" id="payInvoiceId">—</span>
                    </div>
                    <div class="pay-row">
                        <span class="pay-label">Khách hàng</span>
                        <span class="pay-val" id="payCustomer">—</span>
                    </div>
                    <div class="pay-row highlight">
                        <span class="pay-label">Số tiền</span>
                        <span class="pay-val gold" id="payAmount">—</span>
                    </div>
                </div>

                <div class="pay-info-card">
                    <div class="pay-info-title"><i class="fas fa-university"></i> Thông tin tài khoản</div>
                    <div class="pay-row">
                        <span class="pay-label">Ngân hàng</span>
                        <span class="pay-val" id="payBankName">—</span>
                    </div>
                    <div class="pay-row">
                        <span class="pay-label">Số tài khoản</span>
                        <span class="pay-val copyable" id="payAccNo" onclick="copyText(this)">—
                            <i class="fas fa-copy copy-icon"></i>
                        </span>
                    </div>
                    <div class="pay-row">
                        <span class="pay-label">Chủ tài khoản</span>
                        <span class="pay-val" id="payAccName">—</span>
                    </div>
                    <div class="pay-row">
                        <span class="pay-label">Nội dung CK</span>
                        <span class="pay-val copyable" id="payDesc" onclick="copyText(this)">—
                            <i class="fas fa-copy copy-icon"></i>
                        </span>
                    </div>
                </div>

                <div class="pay-info-card">
                    <div class="pay-info-title"><i class="fas fa-check-circle"></i> Xác nhận thanh toán</div>
                    <p class="pay-note">Sau khi khách đã chuyển tiền, chọn phương thức và xác nhận để cập nhật trạng thái hóa đơn.</p>
                    <div class="pay-method-row">
                        <select id="payMethod" class="form-control">
                            <option value="Chuyển khoản">🏦 Chuyển khoản ngân hàng</option>
                        </select>
                    </div>
                    <input type="hidden" id="payConfirmId">
                    <button class="btn-confirm-pay" onclick="confirmPayment()">
                        <i class="fas fa-check"></i> Xác nhận đã thanh toán
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="toast-container" id="toastContainer"></div>

<!-- ===== MODAL SỬA HÓA ĐƠN (Admin only) ===== -->
<div class="modal-overlay" id="editInvoiceModal">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h3><i class="fas fa-pen" style="color:#d4a017;margin-right:8px"></i>Sửa hóa đơn</h3>
            <button class="btn-close" onclick="closeEditInvoiceModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editInvId">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Ngày lập hóa đơn</label>
                    <input type="date" id="editInvDate" class="form-control">
                </div>
                <div class="form-group full">
                    <label>Trạng thái</label>
                    <select id="editInvStatus" class="form-control">
                        <option value="Paid">✅ Đã thanh toán</option>
                        <option value="Pending">⏳ Chờ thanh toán</option>
                        <option value="Cancelled">❌ Đã hủy</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Ghi chú</label>
                    <textarea id="editInvNote" class="form-control" rows="3" placeholder="Ghi chú..."></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeEditInvoiceModal()">Hủy</button>
            <button class="btn-primary" onclick="saveEditInvoice()">
                <i class="fas fa-save"></i> Lưu thay đổi
            </button>
        </div>
    </div>
</div>

<script>
function copyText(el) {
    const text = el.textContent.replace(/\s*\n\s*/g,' ').trim().replace(/\s*copy.*$/i,'').trim();
    navigator.clipboard.writeText(text).then(() => {
        const orig = el.innerHTML;
        el.innerHTML = '<i class="fas fa-check" style="color:#4ade80"></i> Đã sao chép!';
        setTimeout(() => el.innerHTML = orig, 1500);
    });
}
</script>
<script>const USER_ROLE = '<?php echo $_user_role; ?>';</script>
<script src="Invoice_Management.js"></script>
</body>
</html>

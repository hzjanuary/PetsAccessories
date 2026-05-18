<?php
// Bật session để kiểm tra nếu chưa bật
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nếu chưa đăng nhập, đá văng về trang login
if (!isset($_SESSION['user_id'])) {
    header('Location: /PetsAccessories/frontend/components/login.php');
    exit;
}
require_once __DIR__ . '/../../backend/src/cart.php';

$prefillName = '';
$prefillPhone = '';
$prefillAddress = '';
$prefillSpecificAddress = '';
$prefillProv = '';
$prefillDist = '';
$prefillWard = '';
$prefillEmail = '';

if (isset($_SESSION['user_id']) && ($db instanceof PDO)) {
    try {
        $stmt = $db->prepare('SELECT fullname, phone, address, email FROM users WHERE user_id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($profile)) {
            $prefillName = (string) ($profile['fullname'] ?? '');
            $prefillPhone = (string) ($profile['phone'] ?? '');
            $prefillAddress = (string) ($profile['address'] ?? '');
            $prefillEmail = (string) ($profile['email'] ?? '');

            if ($prefillAddress) {
                $parts = array_map('trim', explode(',', $prefillAddress));
                if (count($parts) >= 4) {
                    $prefillProv = array_pop($parts);
                    $prefillDist = array_pop($parts);
                    $prefillWard = array_pop($parts);
                    $prefillSpecificAddress = implode(', ', $parts);
                } elseif (count($parts) === 3) {
                    $prefillProv = $parts[2];
                    $prefillDist = $parts[1];
                    $prefillWard = $parts[0];
                } else {
                    $prefillSpecificAddress = $prefillAddress;
                }
            }
        }
    } catch (PDOException $e) {
        // Ignore profile prefill errors
    }
}

$shippingZones = [];
if (isset($db) && ($db instanceof PDO)) {
    try {
        $szStmt = $db->query("SELECT zone_name, shipping_fee, estimated_delivery FROM shipping_zones WHERE status = 1");
        $shippingZones = $szStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Ignore errors
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán - PetsAccessories</title>
    <link rel="stylesheet" href="../layout/style.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container .select2-selection--single {
            height: 45px;
            padding: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 43px;
        }
    </style>
</head>

<body>
    <?php require_once __DIR__ . '/../layout/Header.php'; ?>

    <main class="cart-page">
        <div class="cart-container">
            <h2>Thanh toán</h2>

            <?php if (!empty($error)): ?>
                <div class="cart-alert cart-alert--error"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif (!empty($notice)): ?>
                <div class="cart-alert cart-alert--notice"><?php echo htmlspecialchars($notice); ?></div>
            <?php endif; ?>

            <?php if (empty($cartItems)): ?>
                <p>Giỏ hàng của bạn đang trống.</p>
                <p><a href="/PetsAccessories/frontend/public/index.php" class="cart-link">Tiếp tục mua sắm</a></p>
            <?php else: ?>
                <div class="cart-grid">
                    <div class="cart-items">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item">
                                <div class="cart-item__thumb">
                                    <a href="/PetsAccessories/frontend/components/product_detail.php?id=<?php echo (int) $item['product_id']; ?>">
                                        <?php
                                        // Xử lý đường dẫn ảnh cho giỏ hàng
                                        $rawThumb = $item['thumbnail'] ?? '';
                                        if (!empty($rawThumb)) {
                                            if (strpos($rawThumb, '/') !== false) {
                                                $thumbnailUrl = $rawThumb;
                                            } else {
                                                // Nối thêm thư mục chứa ảnh. 
                                                // Lưu ý: Đổi thành '/PetsAccessories/upload/imgProduct/' nếu ảnh của bạn lưu ở đó
                                                $thumbnailUrl = '/PetsAccessories/admin/backend/uploads/products/' . $rawThumb;
                                            }
                                        } else {
                                            $thumbnailUrl = '/PetsAccessories/frontend/public/images/default-product.png';
                                        }
                                        ?>
                                        <img src="<?php echo htmlspecialchars($thumbnailUrl); ?>"
                                            alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                            style="width: 100%; height: 80px; object-fit: contain;"
                                            onerror="this.onerror=null; this.src='/PetsAccessories/frontend/public/images/default.jpg'">
                                    </a>
                                </div>

                                <div class="cart-item__info">
                                    <a class="cart-item__name" href="/PetsAccessories/frontend/components/product_detail.php?id=<?php echo (int) $item['product_id']; ?>">
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                    </a>
                                    <div class="cart-item__meta">
                                        <span>Đơn giá: <strong><?php echo number_format((float) $item['unit_price'], 0, ',', '.'); ?> đ</strong></span>
                                        <span>Số lượng: <strong><?php echo (int) $item['quantity']; ?></strong></span>
                                        <span>Tạm tính: <strong><?php echo number_format((float) $item['line_total'], 0, ',', '.'); ?> đ</strong></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div id="checkout-form-section" class="checkout-form-section" data-hidden="1" style="display: none; margin-top: 30px; background: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08);">
                            <!-- Trust Badges (giống hình Chiaki) -->
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px; padding-bottom: 25px; border-bottom: 1px solid #f1f5f9;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="font-size: 28px; color: #0284c7;">🛡️</div>
                                    <div>
                                        <div style="font-weight: 700; font-size: 14px; color: #0f172a;">An toàn</div>
                                        <div style="font-size: 13px; color: #64748b;">Bảo mật thanh toán</div>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="font-size: 28px; color: #0284c7;">🚚</div>
                                    <div>
                                        <div style="font-weight: 700; font-size: 14px; color: #0f172a;">Miễn phí giao hàng</div>
                                        <div style="font-size: 13px; color: #64748b;">Với đơn hàng từ 500k</div>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="font-size: 28px; color: #0284c7;">🔄</div>
                                    <div>
                                        <div style="font-weight: 700; font-size: 14px; color: #0f172a;">Miễn phí trả hàng</div>
                                        <div style="font-size: 13px; color: #64748b;">Lên đến 15 ngày</div>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="font-size: 28px; color: #0284c7;">✅</div>
                                    <div>
                                        <div style="font-weight: 700; font-size: 14px; color: #0f172a;">Đảm bảo giao hàng</div>
                                        <div style="font-size: 13px; color: #64748b;">Hoàn tiền bất kỳ lúc nào</div>
                                    </div>
                                </div>
                            </div>

                            <form action="/PetsAccessories/frontend/components/process_checkout.php" method="POST" id="checkoutForm" class="auth-form" style="text-align: left;">
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
                                    <!-- Form Thông tin (Nền xanh nhạt giống panel bên trái) -->
                                    <div style="background-color: #f0f9ff; padding: 25px; border-radius: 12px;">
                                        <h3 style="margin-bottom: 20px; color: #0f172a; font-size: 18px;">Thông tin nhận hàng</h3>

                                        <div class="form-group" style="margin-bottom: 18px;">
                                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155;">Họ và tên <span style="color:red;">*</span></label>
                                            <input type="text" name="fullname" value="<?php echo htmlspecialchars($prefillName); ?>" required style="width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; font-size: 15px; transition: border-color 0.3s;" onfocus="this.style.borderColor='#38bdf8'" onblur="this.style.borderColor='#cbd5e1'">
                                        </div>
                                        <div class="form-group" style="margin-bottom: 18px;">
                                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155;">Số điện thoại <span style="color:red;">*</span></label>
                                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($prefillPhone); ?>" required pattern="[0-9]{10,11}" title="Vui lòng nhập 10-11 số" style="width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; font-size: 15px; transition: border-color 0.3s;" onfocus="this.style.borderColor='#38bdf8'" onblur="this.style.borderColor='#cbd5e1'">
                                        </div>
                                        <div class="form-group" style="margin-bottom: 18px;">
                                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155;">Email (để nhận hóa đơn)</label>
                                            <input type="email" name="email" value="<?php echo htmlspecialchars($prefillEmail); ?>" placeholder="Nhập email của bạn" style="width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; font-size: 15px; transition: border-color 0.3s;" onfocus="this.style.borderColor='#38bdf8'" onblur="this.style.borderColor='#cbd5e1'">
                                        </div>
                                        <div class="form-group" style="margin-bottom: 18px;">
                                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155;">Tỉnh/Thành phố <span style="color:red;">*</span></label>
                                            <select name="province" id="province" required style="width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; font-size: 15px; transition: border-color 0.3s; background: #fff;" onfocus="this.style.borderColor='#38bdf8'" onblur="this.style.borderColor='#cbd5e1'">
                                                <option value="">Chọn Tỉnh/Thành phố</option>
                                            </select>
                                        </div>
                                        <div class="form-group" style="margin-bottom: 18px;">
                                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155;">Quận/Huyện <span style="color:red;">*</span></label>
                                            <select name="district" id="district" required style="width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; font-size: 15px; transition: border-color 0.3s; background: #fff;" onfocus="this.style.borderColor='#38bdf8'" onblur="this.style.borderColor='#cbd5e1'">
                                                <option value="">Chọn Quận/Huyện</option>
                                            </select>
                                        </div>
                                        <div class="form-group" style="margin-bottom: 18px;">
                                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155;">Phường/Xã <span style="color:red;">*</span></label>
                                            <select name="ward" id="ward" required style="width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; font-size: 15px; transition: border-color 0.3s; background: #fff;" onfocus="this.style.borderColor='#38bdf8'" onblur="this.style.borderColor='#cbd5e1'">
                                                <option value="">Chọn Phường/Xã</option>
                                            </select>
                                        </div>
                                        <div class="form-group" style="margin-bottom: 18px;">
                                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155;">Địa chỉ cụ thể (Số nhà, tên đường) <span style="color:red;">*</span></label>
                                            <textarea name="address" required rows="2" style="width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; font-size: 15px; transition: border-color 0.3s;" onfocus="this.style.borderColor='#38bdf8'" onblur="this.style.borderColor='#cbd5e1'"><?php echo htmlspecialchars($prefillSpecificAddress); ?></textarea>
                                        </div>
                                    </div>

                                    <!-- Phương thức vận chuyển và thanh toán (Giống panel bên phải) -->
                                    <div style="display: flex; flex-direction: column; gap: 20px;">
                                        <div style="border: 1px solid #e2e8f0; padding: 25px; border-radius: 12px;">
                                            <h3 style="margin-bottom: 15px; color: #0f172a; font-size: 16px;">Phương thức vận chuyển <span style="color:red;">*</span></h3>
                                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                                <label style="cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: normal; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; transition: all 0.2s;" onchange="document.querySelectorAll('input[name=shipping_method]').forEach(el => el.parentElement.style.borderColor='#e2e8f0'); this.style.borderColor='#38bdf8';">
                                                    <input type="radio" name="shipping_method" value="standard" checked> 🚚 Giao hàng tiêu chuẩn
                                                </label>
                                                <label style="cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: normal; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; transition: all 0.2s;" onchange="document.querySelectorAll('input[name=shipping_method]').forEach(el => el.parentElement.style.borderColor='#e2e8f0'); this.style.borderColor='#38bdf8';">
                                                    <input type="radio" name="shipping_method" value="pickup"> 🏪 Lấy tại cửa hàng
                                                </label>
                                            </div>
                                        </div>

                                        <div style="border: 1px solid #e2e8f0; padding: 25px; border-radius: 12px;">
                                            <h3 style="margin-bottom: 15px; color: #0f172a; font-size: 16px;">Phương thức thanh toán <span style="color:red;">*</span></h3>
                                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                                <label style="cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: normal; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; transition: all 0.2s;" onchange="document.querySelectorAll('input[name=payment_method]').forEach(el => el.parentElement.style.borderColor='#e2e8f0'); this.style.borderColor='#38bdf8';">
                                                    <input type="radio" name="payment_method" value="cod" checked> 💵 Thanh toán khi nhận hàng (COD)
                                                </label>
                                                <label style="cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: normal; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; transition: all 0.2s;" onchange="document.querySelectorAll('input[name=payment_method]').forEach(el => el.parentElement.style.borderColor='#e2e8f0'); this.style.borderColor='#38bdf8';">
                                                    <input type="radio" name="payment_method" value="bank_transfer"> 🏦 Chuyển khoản ngân hàng
                                                </label>
                                                <label style="cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: normal; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; transition: all 0.2s;" onchange="document.querySelectorAll('input[name=payment_method]').forEach(el => el.parentElement.style.borderColor='#e2e8f0'); this.style.borderColor='#38bdf8';">
                                                    <input type="radio" name="payment_method" value="ewallet"> 📱 Ví điện tử (Momo, ZaloPay, VNPAY)
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <aside class="cart-summary">
                        <h3>Tạm tính</h3>
                        <div class="cart-summary__row">
                            <span>Tổng tiền hàng</span>
                            <strong><?php echo number_format((float) $subtotal, 0, ',', '.'); ?> đ</strong>
                        </div>
                        <div class="cart-summary__row">
                            <span>Thuế (tạm tính)</span>
                            <strong><?php echo number_format((float) $tax, 0, ',', '.'); ?> đ</strong>
                        </div>
                        <div class="cart-summary__row">
                            <span>Phí vận chuyển (<span id="shipping-zone-name">tạm tính</span>)</span>
                            <strong id="shipping-fee-display"><?php echo number_format((float) $shipping, 0, ',', '.'); ?> đ</strong>
                        </div>
                        <div class="cart-summary__row" id="discount-row" style="display: none;">
                            <span>Giảm giá (<span id="coupon-code-display"></span>)</span>
                            <strong id="discount-display" style="color: #e74c3c;">-0 đ</strong>
                        </div>
                        <div class="cart-summary__row cart-summary__row--total">
                            <span>Tổng cộng</span>
                            <strong id="total-price-display"><?php echo number_format((float) $estimatedTotal, 0, ',', '.'); ?> đ</strong>
                        </div>

                        <div class="coupon-section" style="margin: 15px 0; padding-top: 15px; border-top: 1px dashed #cbd5e1;">
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <label for="coupon_input" style="font-size: 14px; font-weight: 500;">Mã giảm giá:</label>
                                <div style="display: flex; gap: 8px;">
                                    <input type="text" id="coupon_input" name="coupon_code" form="checkoutForm" placeholder="Nhập mã giảm giá..." style="flex: 1; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px;">
                                    <button type="button" id="btn-apply-coupon" style="padding: 10px 15px; background: #0f172a; color: white; border: none; border-radius: 8px; cursor: pointer; white-space: nowrap;">Áp dụng</button>
                                    <button type="button" id="btn-cancel-coupon" style="display: none; padding: 10px 15px; background: #e74c3c; color: white; border: none; border-radius: 8px; cursor: pointer; white-space: nowrap;">Hủy áp dụng</button>
                                </div>
                            </div>
                            <div id="coupon-message" style="margin-top: 8px; font-size: 13px;"></div>
                        </div>

                        <p class="cart-summary__hint">Các giá trị trên là tạm tính và có thể thay đổi khi thanh toán.</p>

                        <div class="cart-summary__actions" style="margin-top: 20px; display: flex; flex-direction: column; gap: 10px;">
                            <button type="button" id="place-order-btn" class="cart-btn" style="width: 100%; font-size: 16px; padding: 14px; background: #38bdf8; border-radius: 8px; color: #fff; font-weight: bold; border: none; cursor: pointer; transition: background 0.3s;">Đặt hàng</button>
                            <a href="/PetsAccessories/frontend/components/cart.php" class="cart-link" style="text-align: center; display: block; margin-top: 10px; color: #64748b; font-weight: 500;">Quay lại giỏ hàng</a>
                        </div>
                    </aside>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php require_once __DIR__ . '/../layout/Footer.php'; ?>

    <!-- Modal QR Code -->
    <div id="qr-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: #fff; padding: 40px; border-radius: 12px; text-align: center; max-width: 400px; width: 90%;">
            <h3 style="margin-bottom: 20px; color: #0f172a;">Quét mã QR để thanh toán</h3>
            <p style="color: #64748b; margin-bottom: 20px;">Đơn hàng của bạn sẽ được hoàn tất sau khi chuyển tiền.</p>
            <img id="qr-code-image" src="/PetsAccessories/backend/upload/qr/momo_qr.jpg" alt="QR Momo" style="width: 250px; height: auto; margin-bottom: 30px; border: 1px solid #e2e8f0; padding: 10px; border-radius: 8px;">
            <div style="display: flex; gap: 15px; justify-content: center;">
                <button type="button" id="btn-cancel-qr" style="padding: 10px 20px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #64748b; cursor: pointer;">Hủy</button>
                <button type="button" id="btn-paid-qr" style="padding: 10px 20px; border-radius: 8px; border: none; background: #38bdf8; color: #fff; font-weight: bold; cursor: pointer;">Đã chuyển tiền</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        (function() {
            const provinceSelect = document.getElementById('province');
            const districtSelect = document.getElementById('district');
            const wardSelect = document.getElementById('ward');

            // Initialize Select2
            $('#province').select2({
                placeholder: "Chọn Tỉnh/Thành phố"
            });
            $('#district').select2({
                placeholder: "Chọn Quận/Huyện"
            });
            $('#ward').select2({
                placeholder: "Chọn Phường/Xã"
            });

            const prefillProv = <?php echo json_encode($prefillProv ?? ''); ?>;
            const prefillDist = <?php echo json_encode($prefillDist ?? ''); ?>;
            const prefillWard = <?php echo json_encode($prefillWard ?? ''); ?>;

            // Fetch provinces
            const cartSubtotal = <?php echo json_encode($subtotal); ?>;
            const cartTax = <?php echo json_encode($tax); ?>;
            const freeShippingThreshold = <?php echo json_encode($freeShippingThreshold ?? 300000); ?>;
            const shippingZonesData = <?php echo json_encode($shippingZones); ?>;

            let currentShippingFee = 30000;
            let currentDiscount = 0;

            function formatCurrency(amount) {
                return new Intl.NumberFormat('vi-VN').format(amount) + ' đ';
            }

            function updateTotal() {
                const total = cartSubtotal + cartTax + currentShippingFee - currentDiscount;
                document.getElementById('total-price-display').textContent = formatCurrency(Math.max(0, total));
            }

            function updateShippingFee(provinceName) {
                const shippingMethod = document.querySelector('input[name="shipping_method"]:checked')?.value;

                if (shippingMethod === 'pickup' || provinceName.toLowerCase().includes('cần thơ')) {
                    currentShippingFee = 0;
                    let zoneNameMatch = shippingMethod === 'pickup' ? 'Lấy tại cửa hàng' : 'Cần Thơ (Miễn phí)';
                    document.getElementById('shipping-zone-name').textContent = zoneNameMatch;
                    document.getElementById('shipping-fee-display').textContent = formatCurrency(currentShippingFee);
                    updateTotal();
                    return;
                }

                currentShippingFee = 30000; // Default fee if no zone matches
                let zoneNameMatch = 'Ngoại thành'; // Default zone name

                if (shippingZonesData && shippingZonesData.length > 0) {
                    const matchedZone = shippingZonesData.find(zone => provinceName.includes(zone.zone_name));

                    if (matchedZone) {
                        currentShippingFee = parseFloat(matchedZone.shipping_fee);
                        zoneNameMatch = matchedZone.zone_name;
                    }
                }

                if (cartSubtotal >= freeShippingThreshold) {
                    currentShippingFee = 0;
                    zoneNameMatch = 'Miễn phí vận chuyển';
                }

                document.getElementById('shipping-zone-name').textContent = zoneNameMatch;
                document.getElementById('shipping-fee-display').textContent = formatCurrency(currentShippingFee);
                updateTotal();
            }

            // Xử lý áp dụng mã coupon
            const btnApplyCoupon = document.getElementById('btn-apply-coupon');
            const btnCancelCoupon = document.getElementById('btn-cancel-coupon');
            const couponInput = document.getElementById('coupon_input');
            const couponMessage = document.getElementById('coupon-message');
            const discountRow = document.getElementById('discount-row');
            const discountDisplay = document.getElementById('discount-display');
            const couponCodeDisplay = document.getElementById('coupon-code-display');

            btnApplyCoupon.addEventListener('click', function() {
                const code = couponInput.value.trim();
                if (!code) {
                    couponMessage.textContent = 'Vui lòng nhập một mã giảm giá.';
                    couponMessage.style.color = 'red';
                    return;
                }

                btnApplyCoupon.disabled = true;
                btnApplyCoupon.textContent = 'Đang xử lý...';

                fetch('/PetsAccessories/backend/src/apply_coupon.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'code=' + encodeURIComponent(code) + '&subtotal=' + encodeURIComponent(cartSubtotal)
                    })
                    .then(res => res.json())
                    .then(data => {
                        btnApplyCoupon.disabled = false;
                        btnApplyCoupon.textContent = 'Áp dụng';

                        if (data.status === 'success') {
                            currentDiscount = parseFloat(data.discount);
                            couponMessage.textContent = data.message;
                            couponMessage.style.color = 'green';

                            discountRow.style.display = 'flex';
                            couponCodeDisplay.textContent = data.code + ' (-' + (data.discountText || '') + ')';
                            discountDisplay.textContent = '-' + formatCurrency(currentDiscount);

                            let hiddenInput = document.getElementById('applied_coupon_code');
                            if (!hiddenInput) {
                                hiddenInput = document.createElement('input');
                                hiddenInput.type = 'hidden';
                                hiddenInput.name = 'coupon_code';
                                hiddenInput.id = 'applied_coupon_code';
                                document.getElementById('checkoutForm').appendChild(hiddenInput);
                            }
                            hiddenInput.value = data.code;
                            couponInput.disabled = true;
                            btnApplyCoupon.style.display = 'none';
                            btnCancelCoupon.style.display = 'block';

                            updateTotal();
                        } else {
                            couponMessage.textContent = data.message;
                            couponMessage.style.color = 'red';
                            currentDiscount = 0;
                            discountRow.style.display = 'none';
                            let hiddenInput = document.getElementById('applied_coupon_code');
                            if (hiddenInput) {
                                hiddenInput.value = '';
                            }
                            updateTotal();
                        }
                    })
                    .catch(err => {
                        btnApplyCoupon.disabled = false;
                        btnApplyCoupon.textContent = 'Áp dụng';
                        couponMessage.textContent = 'Đã xảy ra lỗi. Vui lòng thử lại sau.';
                        couponMessage.style.color = 'red';
                    });
            });

            btnCancelCoupon.addEventListener('click', function() {
                // Clear coupon logic
                currentDiscount = 0;
                discountRow.style.display = 'none';
                couponMessage.textContent = 'Đã hủy mã giảm giá.';
                couponMessage.style.color = '#64748b';

                let hiddenInput = document.getElementById('applied_coupon_code');
                if (hiddenInput) {
                    hiddenInput.value = '';
                }

                couponInput.value = '';
                couponInput.disabled = false;
                btnApplyCoupon.style.display = 'block';
                btnCancelCoupon.style.display = 'none';

                // Cũng cần xóa session ở backend để process_checkout không lấy coupon ảo
                fetch('/PetsAccessories/backend/src/apply_coupon.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'cancel=1'
                });

                updateTotal();
            });

            fetch('https://provinces.open-api.vn/api/?depth=3')
                .then(res => res.json())
                .then(data => {
                    let provinces = data;
                    provinces.forEach(p => {
                        let option = document.createElement('option');
                        option.value = p.name;
                        option.dataset.code = p.code;
                        option.textContent = p.name;
                        if (p.name === prefillProv) option.selected = true;
                        provinceSelect.appendChild(option);
                    });

                    if (prefillProv) {
                        const selectedProvince = provinces.find(p => p.name === prefillProv);
                        if (selectedProvince && selectedProvince.districts) {
                            selectedProvince.districts.forEach(d => {
                                let option = document.createElement('option');
                                option.value = d.name;
                                option.dataset.code = d.code;
                                option.textContent = d.name;
                                if (d.name === prefillDist) option.selected = true;
                                districtSelect.appendChild(option);
                            });

                            if (prefillDist) {
                                const selectedDistrict = selectedProvince.districts.find(d => d.name === prefillDist);
                                if (selectedDistrict && selectedDistrict.wards) {
                                    selectedDistrict.wards.forEach(w => {
                                        let option = document.createElement('option');
                                        option.value = w.name;
                                        option.dataset.code = w.code;
                                        option.textContent = w.name;
                                        if (w.name === prefillWard) option.selected = true;
                                        wardSelect.appendChild(option);
                                    });
                                }
                            }
                        }
                    }

                    $('#province').trigger('change.select2');
                    $('#district').trigger('change.select2');
                    $('#ward').trigger('change.select2');

                    $('#province').on('change', function() {
                        districtSelect.innerHTML = '<option value="">Chọn Quận/Huyện</option>';
                        wardSelect.innerHTML = '<option value="">Chọn Phường/Xã</option>';

                        const selectedProvince = provinces.find(p => p.name === this.value);
                        if (selectedProvince && selectedProvince.districts) {
                            selectedProvince.districts.forEach(d => {
                                let option = document.createElement('option');
                                option.value = d.name;
                                option.dataset.code = d.code;
                                option.textContent = d.name;
                                districtSelect.appendChild(option);
                            });
                        }

                        updateShippingFee(this.value || '');

                        $('#district').trigger('change.select2');
                        $('#ward').trigger('change.select2');
                    });

                    // Trigger lần đầu tiên để cập nhật phí cho tỉnh được prefill
                    updateShippingFee($('#province').val() || '');

                    $('#district').on('change', function() {
                        wardSelect.innerHTML = '<option value="">Chọn Phường/Xã</option>';

                        const selectedProvince = provinces.find(p => p.name === provinceSelect.value);
                        if (selectedProvince) {
                            const selectedDistrict = selectedProvince.districts.find(d => d.name === this.value);
                            if (selectedDistrict && selectedDistrict.wards) {
                                selectedDistrict.wards.forEach(w => {
                                    let option = document.createElement('option');
                                    option.value = w.name;
                                    option.dataset.code = w.code;
                                    option.textContent = w.name;
                                    wardSelect.appendChild(option);
                                });
                            }
                        }
                        $('#ward').trigger('change.select2');
                    });
                })
                .catch(err => console.error('Error fetching provinces:', err));

            const placeOrderBtn = document.getElementById('place-order-btn');
            const formSection = document.getElementById('checkout-form-section');
            const form = document.getElementById('checkoutForm');
            const qrModal = document.getElementById('qr-modal');
            const qrCodeImage = document.getElementById('qr-code-image');
            const btnCancelQr = document.getElementById('btn-cancel-qr');
            const btnPaidQr = document.getElementById('btn-paid-qr');

            if (!placeOrderBtn || !formSection || !form) return;

            placeOrderBtn.addEventListener('click', function() {
                const isHidden = formSection.getAttribute('data-hidden') === '1';

                if (isHidden) {
                    formSection.style.display = 'block';
                    formSection.setAttribute('data-hidden', '0');
                    placeOrderBtn.textContent = 'Xác nhận đặt hàng';

                    const firstInput = form.querySelector('input, textarea, select');
                    if (firstInput) {
                        firstInput.focus();
                    }

                    formSection.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    return;
                }

                form.requestSubmit();
            });

            // Xử lý sự kiện submit form
            form.addEventListener('submit', function(e) {
                const paymentMethod = document.querySelector('input[name="payment_method"]:checked');

                if (paymentMethod && (paymentMethod.value === 'bank_transfer' || paymentMethod.value === 'ewallet') && !form.dataset.qrConfirmed) {
                    e.preventDefault();
                    if (qrCodeImage) {
                        if (paymentMethod.value === 'bank_transfer') {
                            qrCodeImage.src = '/PetsAccessories/backend/upload/qr/vcb_qr.jpg';
                            qrCodeImage.alt = 'QR Vietcombank';
                        } else {
                            qrCodeImage.src = '/PetsAccessories/backend/upload/qr/momo_qr.jpg';
                            qrCodeImage.alt = 'QR Momo';
                        }
                    }
                    qrModal.style.display = 'flex';
                }
            });

            btnCancelQr.addEventListener('click', function() {
                qrModal.style.display = 'none';
                if (typeof showToast === 'function') {
                    showToast('Đã hủy thanh toán', true);
                }
            });

            btnPaidQr.addEventListener('click', function() {
                qrModal.style.display = 'none';
                form.dataset.qrConfirmed = '1';

                if (typeof showToast === 'function') {
                    showToast('Đã thanh toán thành công');
                }

                // Cập nhật form action để chuyển về trang chủ nhanh (nếu cần qua process để lưu DB, thì process_checkout phải redirect về index)
                // Nhưng vì process_checkout hiện tại đang hiển thị giao diện "Đặt hàng thành công", 
                // người dùng yêu cầu: "sau đó quay lại trang index"
                // Thêm 1 input flag báo cho process_checkout biết cần redirect về index.
                const redirectInput = document.createElement('input');
                redirectInput.type = 'hidden';
                redirectInput.name = 'redirect_to_index';
                redirectInput.value = '1';
                form.appendChild(redirectInput);

                // Chờ hiển thị lỗi 1.5s trước khi chuyển trang
                setTimeout(() => {
                    form.submit();
                }, 1500);
            });

            // Lắng nghe thay đổi phương thức vận chuyển
            document.querySelectorAll('input[name="shipping_method"]').forEach(radio => {
                radio.addEventListener('change', () => {
                    updateShippingFee($('#province').val() || '');
                });
            });
        })();
    </script>
</body>

</html>
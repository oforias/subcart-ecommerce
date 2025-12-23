<?php
/**
 * Checkout Page
 * 
 * Handles order processing and payment simulation for logged-in customers.
 * Calculates totals including tax and shipping, processes orders.
 */

session_start();
require_once 'controllers/cart_controller.php';
require_once 'settings/core.php';

// Require authentication for checkout
if (!isset($_SESSION['customer_id'])) {
    header("Location: login/login.php?redirect=checkout.php");
    exit();
}

// Get customer information
$customer_id = $_SESSION['customer_id'];
$customer_name = $_SESSION['customer_name'] ?? 'Customer';
$customer_email = $_SESSION['customer_email'] ?? '';
$ip_address = $_SERVER['REMOTE_ADDR'];

// Initialize checkout data
$cart_items = [];
$cart_total = 0;
$cart_count = 0;
$total_items = 0;
$error_message = '';
$checkout_ready = false;

// Load cart for checkout
$cart_result = get_cart_items_ctr($customer_id, $ip_address);

if ($cart_result['success']) {
    $cart_items = $cart_result['data']['items'];
    $cart_total = $cart_result['data']['total_amount'];
    $cart_count = $cart_result['data']['count'];
    $total_items = $cart_result['data']['total_items'];
    
    if ($cart_count > 0) {
        $checkout_ready = true;
    } else {
        $error_message = 'Your cart is empty. Please add items before checkout.';
    }
} else {
    $error_message = $cart_result['error'];
}

// Calculate additional checkout information
$tax_rate = 0.08; // 8% tax rate
$tax_amount = $cart_total * $tax_rate;
$shipping_cost = $cart_total >= 50 ? 0 : 5.99; // Free shipping over $50
$final_total = $cart_total + $tax_amount + $shipping_cost;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Sweetgreen</title>
    
    <!-- Sweetgreen Design System CSS -->
    <link rel="stylesheet" href="css/sweetgreen-style.css">
    <!-- Enhanced Button Styles -->
    <link rel="stylesheet" href="css/enhanced-buttons.css">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navigation Menu Tray -->
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>
    
    <!-- Sidebar Navigation -->
    <nav class="sidebar-nav" id="sidebar">
        <div class="sidebar-header">
            <h2>
                <i class="fas fa-store"></i>
                Our Store
            </h2>
        </div>
        
        <div class="sidebar-menu">
            <a href="index.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="all_product.php" class="nav-item">
                <i class="fas fa-leaf"></i>
                <span>Products</span>
            </a>
            <a href="cart.php" class="nav-item">
                <i class="fas fa-shopping-cart"></i>
                <span>Shopping Cart</span>
                <?php if ($total_items > 0): ?>
                    <span class="badge"><?php echo $total_items; ?></span>
                <?php endif; ?>
            </a>
            <a href="checkout.php" class="nav-item active">
                <i class="fas fa-credit-card"></i>
                <span>Checkout</span>
            </a>
        </div>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-name">Checkout</div>
                <div class="user-status">Secure Payment</div>
            </div>
            <a href="login/logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="main-content">
    <div style="max-width: 1200px; margin: 40px auto; padding: 0 var(--spacing-md);">
        </div>
        
        <!-- Page Header -->
        <div style="text-align: center; margin-bottom: var(--spacing-2xl);">
            <h1 style="color: var(--color-primary-green); margin-bottom: var(--spacing-sm);">
                <i class="fas fa-lock"></i> Secure Checkout
            </h1>
            <p style="color: var(--color-medium-gray); font-size: var(--font-size-body);">
                Review your order and complete your purchase
            </p>
        </div>

        <!-- Error Message Display -->
        <?php if (!empty($error_message)): ?>
            <div class="validation-message error" style="max-width: 800px; margin: 0 auto var(--spacing-lg);">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                <?php if (!$checkout_ready): ?>
                    <div style="margin-top: var(--spacing-md);">
                        <a href="all_product.php" class="btn btn-primary">
                            <i class="fas fa-leaf"></i> Browse Products
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Success/Info Messages (populated by JavaScript) -->
        <div id="checkout-message" style="max-width: 800px; margin: 0 auto var(--spacing-lg); display: none;"></div>

        <?php if ($checkout_ready): ?>
            <!-- Checkout Content -->
            <div style="display: grid; grid-template-columns: 1fr 400px; gap: var(--spacing-2xl); max-width: 1200px; margin: 0 auto;">
                
                <!-- Order Summary Section -->
                <div>
                    <div class="card" style="padding: var(--spacing-xl); margin-bottom: var(--spacing-lg);">
                        <h2 style="color: var(--color-primary-green); margin-bottom: var(--spacing-lg); font-size: var(--font-size-h3);">
                            <i class="fas fa-shopping-bag"></i> Order Summary
                        </h2>
                        
                        <!-- Order Items -->
                        <div style="margin-bottom: var(--spacing-lg);">
                            <?php foreach ($cart_items as $item): ?>
                                <div style="display: grid; grid-template-columns: 80px 1fr auto; gap: var(--spacing-md); align-items: center; padding: var(--spacing-md) 0; border-bottom: 1px solid var(--color-border-gray);">
                                    
                                    <!-- Product Image -->
                                    <div style="width: 80px; height: 80px; border-radius: var(--border-radius-md); overflow: hidden; background-color: var(--color-light-gray);">
                                        <?php if (!empty($item['product_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($item['product_image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product_title']); ?>"
                                                 style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: var(--color-medium-gray);">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Product Details -->
                                    <div>
                                        <h4 style="color: var(--color-dark-gray); margin-bottom: var(--spacing-xs); font-size: var(--font-size-body);">
                                            <?php echo htmlspecialchars($item['product_title']); ?>
                                        </h4>
                                        
                                        <?php if (!empty($item['cat_name']) || !empty($item['brand_name'])): ?>
                                            <p style="color: var(--color-medium-gray); font-size: var(--font-size-small); margin-bottom: var(--spacing-xs);">
                                                <?php 
                                                $details = [];
                                                if (!empty($item['cat_name'])) $details[] = htmlspecialchars($item['cat_name']);
                                                if (!empty($item['brand_name'])) $details[] = htmlspecialchars($item['brand_name']);
                                                echo implode(' • ', $details);
                                                ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <p style="color: var(--color-medium-gray); font-size: var(--font-size-small);">
                                            Quantity: <?php echo $item['qty']; ?> × $<?php echo number_format($item['product_price'], 2); ?>
                                        </p>
                                    </div>

                                    <!-- Item Subtotal -->
                                    <div style="text-align: right;">
                                        <p style="color: var(--color-primary-green); font-weight: var(--font-weight-semibold); font-size: var(--font-size-body);">
                                            $<?php echo number_format($item['subtotal'], 2); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Customer Information -->
                    <div class="card" style="padding: var(--spacing-xl);">
                        <h3 style="color: var(--color-primary-green); margin-bottom: var(--spacing-lg); font-size: var(--font-size-h4);">
                            <i class="fas fa-user"></i> Customer Information
                        </h3>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-lg);">
                            <div>
                                <label style="color: var(--color-dark-gray); font-weight: var(--font-weight-medium); margin-bottom: var(--spacing-xs); display: block;">
                                    Name:
                                </label>
                                <p style="color: var(--color-dark-gray); font-size: var(--font-size-body);">
                                    <?php echo htmlspecialchars($customer_name); ?>
                                </p>
                            </div>
                            
                            <div>
                                <label style="color: var(--color-dark-gray); font-weight: var(--font-weight-medium); margin-bottom: var(--spacing-xs); display: block;">
                                    Email:
                                </label>
                                <p style="color: var(--color-dark-gray); font-size: var(--font-size-body);">
                                    <?php echo htmlspecialchars($customer_email); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Summary Sidebar -->
                <div>
                    <div class="card card-elevated" style="padding: var(--spacing-xl); position: sticky; top: 20px;">
                        <h3 style="color: var(--color-primary-green); margin-bottom: var(--spacing-lg); font-size: var(--font-size-h4);">
                            <i class="fas fa-calculator"></i> Order Total
                        </h3>
                        
                        <!-- Order Breakdown -->
                        <div style="margin-bottom: var(--spacing-lg);">
                            <div style="display: flex; justify-content: space-between; margin-bottom: var(--spacing-sm);">
                                <span style="color: var(--color-dark-gray);">Subtotal (<?php echo $total_items; ?> items):</span>
                                <span style="color: var(--color-dark-gray); font-weight: var(--font-weight-medium);" id="checkout-subtotal">
                                    $<?php echo number_format($cart_total, 2); ?>
                                </span>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; margin-bottom: var(--spacing-sm);">
                                <span style="color: var(--color-dark-gray);">Tax (8%):</span>
                                <span style="color: var(--color-dark-gray); font-weight: var(--font-weight-medium);" id="checkout-tax">
                                    $<?php echo number_format($tax_amount, 2); ?>
                                </span>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; margin-bottom: var(--spacing-md);">
                                <span style="color: var(--color-dark-gray);">
                                    Shipping:
                                    <?php if ($shipping_cost == 0): ?>
                                        <small style="color: var(--color-success); font-weight: var(--font-weight-medium);">(FREE)</small>
                                    <?php endif; ?>
                                </span>
                                <span style="color: var(--color-dark-gray); font-weight: var(--font-weight-medium);" id="checkout-shipping">
                                    $<?php echo number_format($shipping_cost, 2); ?>
                                </span>
                            </div>
                            
                            <div style="border-top: 2px solid var(--color-primary-green); padding-top: var(--spacing-md);">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="color: var(--color-dark-gray); font-size: var(--font-size-h4); font-weight: var(--font-weight-semibold);">
                                        Total:
                                    </span>
                                    <span style="color: var(--color-primary-green); font-size: var(--font-size-h3); font-weight: var(--font-weight-semibold);" id="checkout-total">
                                        $<?php echo number_format($final_total, 2); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Section -->
                        <div style="margin-bottom: var(--spacing-lg);">
                            <h4 style="color: var(--color-primary-green); margin-bottom: var(--spacing-md); font-size: var(--font-size-body);">
                                <i class="fas fa-credit-card"></i> Payment Method
                            </h4>
                            
                            <div style="background-color: var(--color-light-gray); padding: var(--spacing-md); border-radius: var(--border-radius-md); margin-bottom: var(--spacing-md);">
                                <p style="color: var(--color-medium-gray); font-size: var(--font-size-small); text-align: center; margin: 0;">
                                    <i class="fas fa-info-circle"></i> This is a demo checkout with simulated payment processing
                                </p>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div style="display: flex; flex-direction: column; gap: var(--spacing-md);">
                            <button class="btn btn-primary btn-large btn-block" id="place-order-btn" 
                                    data-customer-id="<?php echo $customer_id; ?>"
                                    data-total="<?php echo $final_total; ?>">
                                <i class="fas fa-lock"></i> Place Order - $<?php echo number_format($final_total, 2); ?>
                            </button>
                            
                            <a href="cart.php" class="btn btn-secondary btn-block">
                                <i class="fas fa-arrow-left"></i> Back to Cart
                            </a>
                            
                            <a href="all_product.php" class="btn btn-secondary btn-block">
                                <i class="fas fa-shopping-cart"></i> Continue Shopping
                            </a>
                        </div>
                        
                        <!-- Security Notice -->
                        <div style="margin-top: var(--spacing-lg); padding: var(--spacing-md); background-color: var(--color-light-gray); border-radius: var(--border-radius-md);">
                            <p style="color: var(--color-medium-gray); font-size: var(--font-size-small); text-align: center; margin: 0;">
                                <i class="fas fa-shield-alt"></i> Your information is secure and protected
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payment Modal (populated by JavaScript) -->
    <div id="payment-modal" style="display: none;">
        <!-- Modal content will be inserted by JavaScript -->
    </div>

    <!-- Order Confirmation Modal (populated by JavaScript) -->
    <div id="confirmation-modal" style="display: none;">
        <!-- Modal content will be inserted by JavaScript -->
    </div>

    <!-- jQuery for AJAX operations -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Checkout JavaScript for payment simulation -->
    <script src="js/checkout.js"></script>
    
    <!-- Sidebar Toggle JavaScript -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        }
        
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        }
        
        // Close sidebar when clicking on nav items on mobile
        document.querySelectorAll('.sidebar-menu .nav-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            });
        });
        
        // Close sidebar on window resize if desktop
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
    </script>
    </div> <!-- Close main-content -->
</body>
</html>
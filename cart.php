<?php
/**
 * Shopping Cart Page
 * 
 * Displays cart contents with quantity management and checkout options.
 * Supports both logged-in users and guest sessions via IP tracking.
 */

session_start();
require_once 'controllers/cart_controller.php';
require_once 'settings/core.php';

// Get user identification (customer ID or IP address)
$customer_id = isset($_SESSION['customer_id']) ? $_SESSION['customer_id'] : null;
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// Initialize cart data
$cart_items = [];
$cart_total = 0;
$cart_count = 0;
$total_items = 0;
$error_message = '';

// Load cart contents
$cart_result = get_cart_items_ctr($customer_id, $ip_address);

if ($cart_result['success']) {
    $cart_items = $cart_result['data']['items'];
    $cart_total = $cart_result['data']['total_amount'];
    $cart_count = $cart_result['data']['count'];
    $total_items = $cart_result['data']['total_items'];
} else {
    $error_message = $cart_result['error'];
}

// User session info
$is_logged_in = isset($_SESSION['customer_id']);
$customer_name = isset($_SESSION['customer_name']) ? $_SESSION['customer_name'] : 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - SubCart</title>
    
    <!-- 🔥 Sexy Favicon -->
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    <meta name="theme-color" content="#667eea">
    
    <!-- Sweetgreen Design System CSS -->
    <link rel="stylesheet" href="css/sweetgreen-style.css">
    <!-- Enhanced Button Styles -->
    <link rel="stylesheet" href="css/enhanced-buttons.css">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
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
            <a href="cart.php" class="nav-item active">
                <i class="fas fa-shopping-cart"></i>
                <span>Shopping Cart</span>
                <?php if ($total_items > 0): ?>
                    <span class="badge"><?php echo $total_items; ?></span>
                <?php endif; ?>
            </a>
            
            <?php if ($is_logged_in): ?>
                <a href="checkout.php" class="nav-item">
                    <i class="fas fa-credit-card"></i>
                    <span>Checkout</span>
                </a>
            <?php endif; ?>
        </div>
        
        <div class="sidebar-footer">
            <?php if ($is_logged_in): ?>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($customer_name); ?></div>
                    <div class="user-status">Logged in</div>
                </div>
                <a href="login/logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            <?php else: ?>
                <a href="login/login.php" class="nav-item">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Login</span>
                </a>
                <a href="login/register.php" class="nav-item">
                    <i class="fas fa-user-plus"></i>
                    <span>Register</span>
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="main-content">
    <div style="max-width: 1200px; margin: 40px auto; padding: 0 var(--spacing-md);">
        </div>
        
        <!-- Page Header -->
        <div style="text-align: center; margin-bottom: var(--spacing-2xl);">
            <h1 style="color: var(--color-primary-green); margin-bottom: var(--spacing-sm);">
                <i class="fas fa-shopping-cart"></i> Shopping Cart
            </h1>
            <p style="color: var(--color-medium-gray); font-size: var(--font-size-body);">
                <?php echo $is_logged_in ? "Welcome back, " . htmlspecialchars($customer_name) : "Shopping as Guest"; ?>
            </p>
        </div>

        <!-- Error Message Display -->
        <?php if (!empty($error_message)): ?>
            <div class="validation-message error" style="max-width: 800px; margin: 0 auto var(--spacing-lg);">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Success/Info Messages (populated by JavaScript) -->
        <div id="cart-message" style="max-width: 800px; margin: 0 auto var(--spacing-lg); display: none;"></div>

        <?php if (empty($cart_items)): ?>
            <!-- Empty Cart State -->
            <div class="card" style="max-width: 600px; margin: 0 auto; text-align: center; padding: var(--spacing-3xl);">
                <i class="fas fa-shopping-cart" style="font-size: 4rem; color: var(--color-medium-gray); margin-bottom: var(--spacing-lg);"></i>
                <h2 style="color: var(--color-dark-gray); margin-bottom: var(--spacing-md);">Your cart is empty</h2>
                <p style="color: var(--color-medium-gray); margin-bottom: var(--spacing-xl);">
                    Start adding some delicious items to your cart!
                </p>
                <a href="all_product.php" class="btn btn-primary btn-large">
                    <i class="fas fa-leaf"></i> Browse Products
                </a>
            </div>
        <?php else: ?>
            <!-- Cart Items Display -->
            <div style="display: grid; grid-template-columns: 1fr; gap: var(--spacing-lg); max-width: 1000px; margin: 0 auto;">
                
                <!-- Cart Items List -->
                <div>
                    <?php foreach ($cart_items as $item): ?>
                        <div class="card cart-item" data-product-id="<?php echo $item['p_id']; ?>" style="margin-bottom: var(--spacing-lg); padding: var(--spacing-lg);">
                            <div style="display: grid; grid-template-columns: 120px 1fr auto; gap: var(--spacing-lg); align-items: center;">
                                
                                <!-- Product Image -->
                                <div style="width: 120px; height: 120px; border-radius: var(--border-radius-md); overflow: hidden; background-color: var(--color-light-gray);">
                                    <?php if (!empty($item['product_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['product_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['product_title']); ?>"
                                             style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: var(--color-medium-gray);">
                                            <i class="fas fa-image" style="font-size: 2rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Product Details -->
                                <div>
                                    <h3 style="color: var(--color-primary-green); margin-bottom: var(--spacing-sm); font-size: var(--font-size-h4);">
                                        <?php echo htmlspecialchars($item['product_title']); ?>
                                    </h3>
                                    
                                    <?php if (!empty($item['cat_name']) || !empty($item['brand_name'])): ?>
                                        <p style="color: var(--color-medium-gray); font-size: var(--font-size-small); margin-bottom: var(--spacing-sm);">
                                            <?php 
                                            $details = [];
                                            if (!empty($item['cat_name'])) $details[] = htmlspecialchars($item['cat_name']);
                                            if (!empty($item['brand_name'])) $details[] = htmlspecialchars($item['brand_name']);
                                            echo implode(' • ', $details);
                                            ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <p style="color: var(--color-primary-green); font-size: var(--font-size-h4); font-weight: var(--font-weight-semibold); margin-bottom: var(--spacing-md);">
                                        $<?php echo number_format($item['product_price'], 2); ?>
                                    </p>

                                    <!-- Quantity Controls -->
                                    <div style="display: flex; align-items: center; gap: var(--spacing-md);">
                                        <label style="color: var(--color-dark-gray); font-weight: var(--font-weight-medium); margin-bottom: 0;">
                                            Quantity:
                                        </label>
                                        <div class="quantity-controls">
                                            <button class="btn btn-secondary btn-small quantity-decrease" 
                                                    data-product-id="<?php echo $item['p_id']; ?>">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <input type="number" 
                                                   class="form-input quantity-input" 
                                                   data-product-id="<?php echo $item['p_id']; ?>"
                                                   value="<?php echo $item['qty']; ?>" 
                                                   min="1" 
                                                   max="999">
                                            <button class="btn btn-secondary btn-small quantity-increase" 
                                                    data-product-id="<?php echo $item['p_id']; ?>">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Item Actions and Subtotal -->
                                <div style="text-align: right;">
                                    <p style="color: var(--color-primary-green); font-size: var(--font-size-h3); font-weight: var(--font-weight-semibold); margin-bottom: var(--spacing-md);" class="item-subtotal">
                                        $<?php echo number_format($item['subtotal'], 2); ?>
                                    </p>
                                    <button class="btn btn-danger btn-small remove-item" 
                                            data-product-id="<?php echo $item['p_id']; ?>">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Cart Summary Card -->
                <div class="card card-elevated" style="padding: var(--spacing-xl); position: sticky; top: 20px;">
                    <h2 style="color: var(--color-primary-green); margin-bottom: var(--spacing-lg); font-size: var(--font-size-h3);">
                        Cart Summary
                    </h2>
                    
                    <div style="border-bottom: 1px solid var(--color-border-gray); padding-bottom: var(--spacing-md); margin-bottom: var(--spacing-md);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: var(--spacing-sm);">
                            <span style="color: var(--color-dark-gray);">Items:</span>
                            <span style="color: var(--color-dark-gray); font-weight: var(--font-weight-medium);" id="cart-item-count">
                                <?php echo $total_items; ?>
                            </span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--color-dark-gray);">Subtotal:</span>
                            <span style="color: var(--color-dark-gray); font-weight: var(--font-weight-medium);" id="cart-subtotal">
                                $<?php echo number_format($cart_total, 2); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: var(--spacing-xl);">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--color-dark-gray); font-size: var(--font-size-h4); font-weight: var(--font-weight-semibold);">
                                Total:
                            </span>
                            <span style="color: var(--color-primary-green); font-size: var(--font-size-h2); font-weight: var(--font-weight-semibold);" id="cart-total">
                                $<?php echo number_format($cart_total, 2); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: var(--spacing-md);">
                        <a href="checkout.php" class="btn btn-primary btn-large btn-block">
                            <i class="fas fa-lock"></i> Proceed to Checkout
                        </a>
                        <button class="btn btn-secondary btn-block" id="empty-cart-btn">
                            <i class="fas fa-trash-alt"></i> Empty Cart
                        </button>
                        <a href="all_product.php" class="btn btn-secondary btn-block">
                            <i class="fas fa-arrow-left"></i> Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- jQuery for AJAX operations -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Cart JavaScript for dynamic interactions -->
    <script src="js/cart_simple.js"></script>
    
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
</body>
</html>

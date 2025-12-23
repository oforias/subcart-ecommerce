<?php
/**
 * Product Detail Page
 * 
 * Displays detailed information for a single product including images,
 * description, pricing, and related products. Includes add to cart functionality.
 */

session_start();
require_once 'controllers/product_display_controller.php';
require_once 'settings/core.php';

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Initialize data
$product = null;
$error_message = '';
$related_products = [];
$categories = [];
$brands = [];

// Load navigation data
$categories_result = get_categories_for_filter_ctr();
if ($categories_result['success']) {
    $categories = $categories_result['data']['categories'];
}

$brands_result = get_brands_for_filter_ctr();
if ($brands_result['success']) {
    $brands = $brands_result['data']['brands'];
}

// Load product data
if (!$product_id) {
    $error_message = 'Invalid product ID provided.';
} else {
    $result = get_single_product_ctr($product_id);
    
    if ($result['success']) {
        $product = $result['data']['product'];
        
        // Load related products from same category
        if ($product && isset($product['category_id'])) {
            $related_result = filter_products_ctr(['category_id' => $product['category_id']], 1, 4);
            if ($related_result['success']) {
                // Filter out the current product from related products
                $related_products = array_filter($related_result['data']['products'], function($p) use ($product_id) {
                    return $p['product_id'] != $product_id;
                });
                // Limit to 3 related products
                $related_products = array_slice($related_products, 0, 3);
            }
        }
    } else {
        $error_message = $result['error'];
    }
}

// Check if user is logged in for navigation
$is_logged_in = isset($_SESSION['customer_id']);
$customer_name = $is_logged_in ? $_SESSION['customer_name'] : '';
$is_admin = $is_logged_in && has_admin_privileges();

// Build breadcrumb navigation
$breadcrumbs = [
    ['name' => 'Home', 'url' => 'index.php'],
    ['name' => 'All Products', 'url' => 'all_product.php']
];

if ($product) {
    $breadcrumbs[] = ['name' => htmlspecialchars($product['product_title']), 'url' => ''];
} else {
    $breadcrumbs[] = ['name' => 'Product Not Found', 'url' => ''];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $product ? htmlspecialchars($product['product_title']) . ' - Product Details' : 'Product Not Found'; ?> - Our Store</title>
    <link href="css/sweetgreen-style.css" rel="stylesheet">
    <link href="css/enhanced-buttons.css" rel="stylesheet">
    <!-- Include jQuery for AJAX functionality -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Include Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Include cart.js for cart functionality -->
    <script src="js/cart.js"></script>
    <meta name="description" content="<?php echo $product ? htmlspecialchars(substr($product['product_desc'], 0, 160)) : 'Product details page'; ?>">
    <style>
        /* Product Detail Styles */
        .product-detail-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .breadcrumb-nav {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius-md);
            padding: var(--spacing-md) var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-sm);
        }
        
        .breadcrumb-nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .breadcrumb-nav li {
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .breadcrumb-nav li:not(:last-child)::after {
            content: '›';
            margin: 0 var(--spacing-sm);
            color: var(--color-medium-gray);
            font-weight: bold;
        }
        
        .breadcrumb-nav a {
            color: var(--color-primary-green);
            text-decoration: none;
            font-size: var(--font-size-small);
        }
        
        .breadcrumb-nav a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb-nav span {
            color: var(--color-medium-gray);
            font-size: var(--font-size-small);
        }
        
        .product-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-3xl);
            margin-bottom: var(--spacing-3xl);
        }
        
        .product-image-section {
            background: var(--color-white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        
        .product-main-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            background-color: var(--color-light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-medium-gray);
            font-size: var(--font-size-body);
        }
        
        .product-info-section {
            background: var(--color-white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            padding: var(--spacing-2xl);
        }
        
        .product-title {
            font-size: var(--font-size-h2);
            font-weight: var(--font-weight-semibold);
            color: var(--color-dark-gray);
            margin-bottom: var(--spacing-md);
            line-height: var(--line-height-normal);
        }
        
        .product-meta-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-xl);
            padding: var(--spacing-lg);
            background: var(--color-light-green);
            border-radius: var(--border-radius-md);
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-size: var(--font-size-small);
            font-weight: var(--font-weight-medium);
            color: var(--color-medium-gray);
            margin-bottom: var(--spacing-xs);
        }
        
        .meta-value {
            font-size: var(--font-size-body);
            font-weight: var(--font-weight-semibold);
            color: var(--color-dark-gray);
        }
        
        .product-price {
            font-size: var(--font-size-h1);
            font-weight: var(--font-weight-semibold);
            color: var(--color-primary-green);
            margin-bottom: var(--spacing-xl);
        }
        
        .product-description {
            margin-bottom: var(--spacing-xl);
        }
        
        .product-description h3 {
            font-size: var(--font-size-h4);
            font-weight: var(--font-weight-semibold);
            color: var(--color-dark-gray);
            margin-bottom: var(--spacing-md);
        }
        
        .product-description p {
            font-size: var(--font-size-body);
            line-height: var(--line-height-relaxed);
            color: var(--color-dark-gray);
            margin-bottom: var(--spacing-md);
        }
        
        .product-keywords {
            margin-bottom: var(--spacing-xl);
        }
        
        .keywords-list {
            display: flex;
            flex-wrap: wrap;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-sm);
        }
        
        .keyword-tag {
            background: var(--color-light-green);
            color: var(--color-primary-green);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--border-radius-sm);
            font-size: var(--font-size-small);
            font-weight: var(--font-weight-medium);
        }
        
        .product-actions {
            display: flex;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-xl);
        }
        
        .social-sharing {
            padding-top: var(--spacing-lg);
            border-top: 1px solid var(--color-border-gray);
        }
        
        .social-sharing h4 {
            font-size: var(--font-size-body);
            font-weight: var(--font-weight-medium);
            color: var(--color-dark-gray);
            margin-bottom: var(--spacing-md);
        }
        
        .social-buttons {
            display: flex;
            gap: var(--spacing-sm);
        }
        
        .social-btn {
            padding: var(--spacing-sm);
            border-radius: var(--border-radius-md);
            background: var(--color-light-gray);
            color: var(--color-medium-gray);
            text-decoration: none;
            transition: var(--transition-all);
            font-size: var(--font-size-small);
        }
        
        .social-btn:hover {
            background: var(--color-primary-green);
            color: var(--color-white);
            text-decoration: none;
        }
        
        .related-products-section {
            background: var(--color-white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            padding: var(--spacing-2xl);
        }
        
        .related-products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-xl);
            margin-top: var(--spacing-xl);
        }
        
        .related-product-card {
            background: var(--color-off-white);
            border-radius: var(--border-radius-md);
            overflow: hidden;
            transition: var(--transition-all);
            cursor: pointer;
        }
        
        .related-product-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .related-product-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            background-color: var(--color-light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-medium-gray);
            font-size: var(--font-size-small);
        }
        
        .related-product-info {
            padding: var(--spacing-md);
        }
        
        .related-product-title {
            font-size: var(--font-size-body);
            font-weight: var(--font-weight-medium);
            color: var(--color-dark-gray);
            margin-bottom: var(--spacing-sm);
            line-height: var(--line-height-normal);
        }
        
        .related-product-price {
            font-size: var(--font-size-h4);
            font-weight: var(--font-weight-semibold);
            color: var(--color-primary-green);
        }
        
        .error-container {
            background: var(--color-white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            padding: var(--spacing-3xl);
            text-align: center;
        }
        
        .error-message {
            background-color: rgba(239, 83, 80, 0.1);
            border: 1px solid rgba(239, 83, 80, 0.2);
            border-left: 4px solid var(--color-error);
            color: var(--color-error);
            padding: var(--spacing-lg);
            border-radius: var(--border-radius-md);
            margin-bottom: var(--spacing-lg);
        }
        
        @media (max-width: 768px) {
            .product-detail-grid {
                grid-template-columns: 1fr;
                gap: var(--spacing-xl);
            }
            
            .product-meta-info {
                grid-template-columns: 1fr;
            }
            
            .product-actions {
                flex-direction: column;
            }
            
            .related-products-grid {
                grid-template-columns: 1fr;
            }
            
            .breadcrumb-nav ul {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .breadcrumb-nav li:not(:last-child)::after {
                display: none;
            }
            
            .breadcrumb-nav li {
                margin-bottom: var(--spacing-xs);
            }
            
            .quick-filters-sidebar div[style*="grid-template-columns"] {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
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
            
            <?php if (!$is_admin): ?>
                <a href="all_product.php" class="nav-item active">
                    <i class="fas fa-leaf"></i>
                    <span>Products</span>
                </a>
                <a href="cart.php" class="nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Shopping Cart</span>
                </a>
            <?php endif; ?>
            
            <?php if ($is_logged_in): ?>
                <?php if ($is_admin): ?>
                    <div style="margin: 16px 0; padding: 0 20px;">
                        <div style="font-size: 12px; color: #666; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Admin</div>
                    </div>
                    <a href="admin/category.php" class="nav-item">
                        <i class="fas fa-tags"></i>
                        <span>Categories</span>
                    </a>
                    <a href="admin/brand.php" class="nav-item">
                        <i class="fas fa-copyright"></i>
                        <span>Brands</span>
                    </a>
                    <a href="admin/product.php" class="nav-item">
                        <i class="fas fa-box"></i>
                        <span>Manage Products</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div class="sidebar-footer">
            <?php if ($is_logged_in): ?>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($customer_name); ?></div>
                    <div class="user-status"><?php echo $is_admin ? 'Administrator' : 'Customer'; ?></div>
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

    <!-- Main Content -->
    <div class="main-content">

    <div class="product-detail-container">
        <!-- Cart Message Container -->
        <div id="cart-message" class="validation-message" style="display: none; margin-bottom: var(--spacing-lg);"></div>
        
        <!-- Breadcrumb Navigation -->
        <nav class="breadcrumb-nav">
            <ul>
                <?php foreach ($breadcrumbs as $index => $breadcrumb): ?>
                    <li>
                        <?php if (!empty($breadcrumb['url'])): ?>
                            <a href="<?php echo htmlspecialchars($breadcrumb['url']); ?>">
                                <?php echo htmlspecialchars($breadcrumb['name']); ?>
                            </a>
                        <?php else: ?>
                            <span><?php echo htmlspecialchars($breadcrumb['name']); ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <!-- Quick Filter Sidebar -->
        <div class="quick-filters-sidebar" style="background: rgba(255, 255, 255, 0.95); border-radius: var(--border-radius-lg); padding: var(--spacing-lg); margin-bottom: var(--spacing-xl); box-shadow: var(--shadow-md);">
            <h3 style="font-size: var(--font-size-h4); color: var(--color-dark-gray); margin-bottom: var(--spacing-md);">Browse Products</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md);">
                <!-- Category Quick Filters -->
                <div>
                    <h4 style="font-size: var(--font-size-body); font-weight: var(--font-weight-semibold); color: var(--color-medium-gray); margin-bottom: var(--spacing-sm);">Categories</h4>
                    <div style="display: flex; flex-direction: column; gap: var(--spacing-xs);">
                        <a href="all_product.php" 
                           class="btn btn-secondary btn-small" 
                           style="text-align: left; justify-content: flex-start;">
                            All Categories
                        </a>
                        <?php foreach (array_slice($categories, 0, 5) as $category): ?>
                            <a href="all_product.php?category=<?php echo $category['cat_id']; ?>" 
                               class="btn btn-secondary btn-small" 
                               style="text-align: left; justify-content: flex-start;">
                                <?php echo htmlspecialchars($category['cat_name']); ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (count($categories) > 5): ?>
                            <small style="color: var(--color-medium-gray); font-size: var(--font-size-small);">
                                And <?php echo count($categories) - 5; ?> more available
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Brand Quick Filters -->
                <div>
                    <h4 style="font-size: var(--font-size-body); font-weight: var(--font-weight-semibold); color: var(--color-medium-gray); margin-bottom: var(--spacing-sm);">Brands</h4>
                    <div style="display: flex; flex-direction: column; gap: var(--spacing-xs);">
                        <a href="all_product.php" 
                           class="btn btn-secondary btn-small" 
                           style="text-align: left; justify-content: flex-start;">
                            All Brands
                        </a>
                        <?php foreach (array_slice($brands, 0, 5) as $brand): ?>
                            <a href="all_product.php?brand=<?php echo $brand['brand_id']; ?>" 
                               class="btn btn-secondary btn-small" 
                               style="text-align: left; justify-content: flex-start;">
                                <?php echo htmlspecialchars($brand['brand_name']); ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (count($brands) > 5): ?>
                            <small style="color: var(--color-medium-gray); font-size: var(--font-size-small);">
                                And <?php echo count($brands) - 5; ?> more available
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <!-- Error Display -->
            <div class="error-container">
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <h2 style="color: var(--color-medium-gray); margin-bottom: var(--spacing-md);">Product Not Found</h2>
                <p style="color: var(--color-medium-gray); margin-bottom: var(--spacing-lg);">
                    The product you're looking for doesn't exist or may have been removed.
                </p>
                <a href="all_product.php" class="btn btn-primary">Browse All Products</a>
            </div>
        <?php elseif ($product): ?>
            <!-- Product Detail Display -->
            <div class="product-detail-grid">
                <!-- Product Image Section -->
                <div class="product-image-section">
                    <div class="product-main-image">
                        <?php if (!empty($product['product_image'])): ?>
                            <img src="<?php echo htmlspecialchars($product['product_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['product_title']); ?>"
                                 style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; background-color: var(--color-light-gray);">
                                No Image Available
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Product Information Section -->
                <div class="product-info-section">
                    <h1 class="product-title"><?php echo htmlspecialchars($product['product_title']); ?></h1>
                    
                    <!-- Product Meta Information -->
                    <div class="product-meta-info">
                        <div class="meta-item">
                            <span class="meta-label">Product ID</span>
                            <span class="meta-value"><?php echo htmlspecialchars($product['product_id']); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Category</span>
                            <span class="meta-value"><?php echo htmlspecialchars($product['cat_name'] ?? 'No Category'); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Brand</span>
                            <span class="meta-value"><?php echo htmlspecialchars($product['brand_name'] ?? 'No Brand'); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Availability</span>
                            <span class="meta-value" style="color: var(--color-success);">In Stock</span>
                        </div>
                    </div>
                    
                    <!-- Product Price -->
                    <div class="product-price">
                        $<?php echo number_format($product['product_price'], 2); ?>
                    </div>
                    
                    <!-- Product Description -->
                    <div class="product-description">
                        <h3>Description</h3>
                        <p><?php echo nl2br(htmlspecialchars($product['product_desc'] ?? 'No description available.')); ?></p>
                    </div>
                    
                    <!-- Product Keywords -->
                    <?php if (!empty($product['product_keywords'])): ?>
                        <div class="product-keywords">
                            <h4 style="font-size: var(--font-size-body); font-weight: var(--font-weight-medium); color: var(--color-dark-gray); margin-bottom: var(--spacing-sm);">
                                Tags
                            </h4>
                            <div class="keywords-list">
                                <?php 
                                $keywords = explode(',', $product['product_keywords']);
                                foreach ($keywords as $keyword): 
                                    $keyword = trim($keyword);
                                    if (!empty($keyword)):
                                ?>
                                    <span class="keyword-tag"><?php echo htmlspecialchars($keyword); ?></span>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Product Actions -->
                    <div class="product-actions">
                        <!-- Quantity Selection -->
                        <div style="display: flex; align-items: center; gap: var(--spacing-md); margin-bottom: var(--spacing-md);">
                            <label for="product-quantity" style="font-weight: var(--font-weight-medium); color: var(--color-dark-gray);">
                                Quantity:
                            </label>
                            <div style="display: flex; align-items: center; border: 1px solid var(--color-border-gray); border-radius: var(--border-radius-md); overflow: hidden;">
                                <button type="button" 
                                        id="quantity-decrease" 
                                        class="btn btn-secondary" 
                                        style="border: none; border-radius: 0; padding: var(--spacing-sm) var(--spacing-md);"
                                        onclick="decreaseQuantity()">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" 
                                       id="product-quantity" 
                                       class="form-input" 
                                       value="1" 
                                       min="1" 
                                       max="999"
                                       style="border: none; text-align: center; width: 80px; border-radius: 0;"
                                       onchange="validateQuantity()">
                                <button type="button" 
                                        id="quantity-increase" 
                                        class="btn btn-secondary" 
                                        style="border: none; border-radius: 0; padding: var(--spacing-sm) var(--spacing-md);"
                                        onclick="increaseQuantity()">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div style="display: flex; gap: var(--spacing-md);">
                            <button id="add-to-cart-btn" 
                                    class="btn btn-primary btn-large" 
                                    onclick="addToCartWithQuantity(<?php echo $product['product_id']; ?>)">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                            <button class="btn btn-secondary" 
                                    onclick="window.history.back()">
                                <i class="fas fa-arrow-left"></i> Back to Products
                            </button>
                        </div>
                    </div>
                    
                    <!-- Social Sharing -->
                    <div class="social-sharing">
                        <h4>Share this product</h4>
                        <div class="social-buttons">
                            <a href="#" class="social-btn" onclick="shareProduct('facebook'); return false;">Facebook</a>
                            <a href="#" class="social-btn" onclick="shareProduct('twitter'); return false;">Twitter</a>
                            <a href="#" class="social-btn" onclick="shareProduct('email'); return false;">Email</a>
                            <a href="#" class="social-btn" onclick="copyProductLink(); return false;">Copy Link</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Products Section -->
            <?php if (!empty($related_products)): ?>
                <div class="related-products-section">
                    <h2 style="text-align: center; margin-bottom: var(--spacing-md); color: var(--color-primary-green);">
                        Related Products
                    </h2>
                    <p style="text-align: center; color: var(--color-medium-gray); margin-bottom: 0;">
                        You might also like these products from the same category
                    </p>
                    
                    <div class="related-products-grid">
                        <?php foreach ($related_products as $related_product): ?>
                            <div class="related-product-card" 
                                 onclick="window.location.href='single_product.php?id=<?php echo $related_product['product_id']; ?>'">
                                <div class="related-product-image">
                                    <?php if (!empty($related_product['product_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($related_product['product_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($related_product['product_title']); ?>"
                                             style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="display: flex; align-items: center; justify-content: center; height: 100%; background-color: var(--color-light-gray);">
                                            No Image
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="related-product-info">
                                    <h4 class="related-product-title">
                                        <?php echo htmlspecialchars($related_product['product_title']); ?>
                                    </h4>
                                    <div class="related-product-price">
                                        $<?php echo number_format($related_product['product_price'], 2); ?>
                                    </div>
                                    <div style="margin-top: var(--spacing-sm);">
                                        <button class="btn btn-primary btn-small" 
                                                onclick="event.stopPropagation(); window.addToCart(<?php echo $related_product['product_id']; ?>, 1)"
                                                style="width: 100%;">
                                            <i class="fas fa-shopping-cart"></i> Add to Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // Quantity control functions
        function increaseQuantity() {
            const quantityInput = document.getElementById('product-quantity');
            let currentValue = parseInt(quantityInput.value) || 1;
            if (currentValue < 999) {
                quantityInput.value = currentValue + 1;
            }
        }
        
        function decreaseQuantity() {
            const quantityInput = document.getElementById('product-quantity');
            let currentValue = parseInt(quantityInput.value) || 1;
            if (currentValue > 1) {
                quantityInput.value = currentValue - 1;
            }
        }
        
        function validateQuantity() {
            const quantityInput = document.getElementById('product-quantity');
            let value = parseInt(quantityInput.value) || 1;
            
            if (value < 1) {
                value = 1;
            } else if (value > 999) {
                value = 999;
            }
            
            quantityInput.value = value;
        }
        
        // Enhanced add to cart functionality with quantity
        function addToCartWithQuantity(productId) {
            const quantityInput = document.getElementById('product-quantity');
            const quantity = parseInt(quantityInput.value) || 1;
            const button = document.getElementById('add-to-cart-btn');
            const originalText = button.innerHTML;
            
            // Show loading state
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding to Cart...';
            
            // Get the global addToCart function from cart.js
            const globalAddToCart = window.addToCart;
            
            // Make sure we're calling the global function, not a local one
            if (typeof globalAddToCart === 'function' && globalAddToCart.toString().indexOf('$.ajax') > -1) {
                globalAddToCart(productId, quantity)
                    .then(response => {
                        // Success - show success state
                        button.innerHTML = '<i class="fas fa-check"></i> Added to Cart!';
                        button.style.backgroundColor = 'var(--color-success)';
                        
                        // Reset button after 3 seconds
                        setTimeout(() => {
                            button.disabled = false;
                            button.innerHTML = originalText;
                            button.style.backgroundColor = '';
                        }, 3000);
                    })
                    .catch(error => {
                        // Error - show error state
                        button.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Failed to Add';
                        button.style.backgroundColor = 'var(--color-error)';
                        
                        // Reset button after 4 seconds
                        setTimeout(() => {
                            button.disabled = false;
                            button.innerHTML = originalText;
                            button.style.backgroundColor = '';
                        }, 4000);
                    });
            } else {
                // Fallback if cart.js not loaded
                console.error('Cart.js not loaded or addToCart function not available');
                button.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Cart not available';
                button.style.backgroundColor = 'var(--color-error)';
                setTimeout(() => {
                    button.disabled = false;
                    button.innerHTML = originalText;
                    button.style.backgroundColor = '';
                }, 3000);
            }
        }
        
        // Social sharing functionality
        function shareProduct(platform) {
            const url = encodeURIComponent(window.location.href);
            const title = encodeURIComponent(document.title);
            
            let shareUrl = '';
            
            switch(platform) {
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
                    break;
                case 'email':
                    shareUrl = `mailto:?subject=${title}&body=Check out this product: ${url}`;
                    break;
            }
            
            if (shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        }
        
        // Copy product link to clipboard
        function copyProductLink() {
            const url = window.location.href;
            
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(() => {
                    showCartMessage('success', 'Product link copied to clipboard!');
                }).catch(() => {
                    fallbackCopyTextToClipboard(url);
                });
            } else {
                fallbackCopyTextToClipboard(url);
            }
        }
        
        // Fallback copy function for older browsers
        function fallbackCopyTextToClipboard(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";
            
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                showCartMessage('success', 'Product link copied to clipboard!');
            } catch (err) {
                showCartMessage('error', 'Unable to copy link. Please copy manually: ' + text);
            }
            
            document.body.removeChild(textArea);
        }
        
        // Handle browser back button for better UX
        window.addEventListener('popstate', function(event) {
            // Handle any cleanup if needed when user navigates back
        });
        
        // Add keyboard navigation support
        document.addEventListener('keydown', function(event) {
            // ESC key to go back
            if (event.key === 'Escape') {
                window.history.back();
            }
            
            // Plus/Minus keys for quantity adjustment
            if (event.target.id === 'product-quantity') {
                if (event.key === '+' || event.key === '=') {
                    event.preventDefault();
                    increaseQuantity();
                } else if (event.key === '-') {
                    event.preventDefault();
                    decreaseQuantity();
                }
            }
        });
        
        // Initialize page
        $(document).ready(function() {
            // Focus on quantity input for better UX
            $('#product-quantity').focus();
            
            // Add cart navigation if not present
            updateCartNavigation();
        });
        
        function updateCartNavigation() {
            // Add cart link to menu if it doesn't exist
            const menuTray = $('.menu-tray');
            if (menuTray.find('a[href*="cart.php"]').length === 0) {
                const cartLink = '<a href="cart.php" class="btn btn-secondary btn-small"><i class="fas fa-shopping-cart"></i> Cart</a>';
                menuTray.find('a[href="all_product.php"]').after(cartLink);
            }
        }
    </script>
</body>
</html>
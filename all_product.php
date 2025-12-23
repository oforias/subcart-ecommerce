<?php
/**
 * Product Catalog Page
 * 
 * Displays all products with search, filtering, and pagination functionality.
 * Supports filtering by category and brand with real-time search capabilities.
 */

session_start();
require_once 'controllers/product_display_controller.php';
require_once 'settings/core.php';

// Get request parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : null;
$brand_filter = isset($_GET['brand']) ? (int)$_GET['brand'] : null;

// Initialize data arrays
$products = [];
$pagination = [];
$error_message = '';
$categories = [];
$brands = [];

// Load filter options
$categories_result = get_categories_for_filter_ctr();
if ($categories_result['success']) {
    $categories = $categories_result['data']['categories'];
}

$brands_result = get_brands_for_filter_ctr();
if ($brands_result['success']) {
    $brands = $brands_result['data']['brands'];
}

// Load products based on search/filter criteria
if (!empty($search_query) || $category_filter || $brand_filter) {
    $search_params = [
        'query' => $search_query,
        'category_id' => $category_filter,
        'brand_id' => $brand_filter,
        'page' => $page,
        'limit' => 10
    ];
    $result = composite_search_ctr($search_params);
} else {
    // Get all products with pagination
    $result = get_all_products_ctr($page, 10);
}

// Process results
if ($result['success']) {
    $products = $result['data']['products'];
    $pagination = $result['data']['pagination'];
} else {
    $error_message = $result['error'];
}

// Check if user is logged in for navigation
$is_logged_in = isset($_SESSION['customer_id']);
$customer_name = $is_logged_in ? $_SESSION['customer_name'] : '';
$is_admin = $is_logged_in && has_admin_privileges();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>All Products - SubCart E-Commerce</title>
    
    <!-- 🔥 Sexy Favicon -->
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    <meta name="theme-color" content="#667eea">
    
    <link href="css/sweetgreen-style.css" rel="stylesheet">
    <link href="css/enhanced-buttons.css" rel="stylesheet">
    <style>
        /* Product Grid Styles */
        .products-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .search-filter-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-md);
        }
        
        .search-filter-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: var(--spacing-md);
            align-items: end;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: var(--spacing-xl);
            margin-bottom: var(--spacing-2xl);
        }
        
        .product-card {
            background: var(--color-white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            transition: var(--transition-all);
            cursor: pointer;
        }
        
        .product-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background-color: var(--color-light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-medium-gray);
            font-size: var(--font-size-small);
        }
        
        .product-info {
            padding: var(--spacing-lg);
        }
        
        .product-title {
            font-size: var(--font-size-h4);
            font-weight: var(--font-weight-semibold);
            color: var(--color-dark-gray);
            margin-bottom: var(--spacing-sm);
            line-height: var(--line-height-normal);
        }
        
        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
            font-size: var(--font-size-small);
            color: var(--color-medium-gray);
        }
        
        .product-price {
            font-size: var(--font-size-h4);
            font-weight: var(--font-weight-semibold);
            color: var(--color-primary-green);
            margin-bottom: var(--spacing-md);
        }
        
        .product-actions {
            display: flex;
            gap: var(--spacing-sm);
        }
        
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: var(--spacing-md);
            margin-top: var(--spacing-2xl);
            padding: var(--spacing-xl);
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
        }
        
        .pagination-info {
            font-size: var(--font-size-small);
            color: var(--color-medium-gray);
        }
        
        .no-products {
            text-align: center;
            padding: var(--spacing-3xl);
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
        }
        
        .error-message {
            background-color: rgba(239, 83, 80, 0.1);
            border: 1px solid rgba(239, 83, 80, 0.2);
            border-left: 4px solid var(--color-error);
            color: var(--color-error);
            padding: var(--spacing-md);
            border-radius: var(--border-radius-md);
            margin-bottom: var(--spacing-lg);
        }
        
        @media (max-width: 768px) {
            .search-filter-row {
                grid-template-columns: 1fr;
                gap: var(--spacing-md);
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: var(--spacing-lg);
            }
            
            .pagination-container {
                flex-direction: column;
                gap: var(--spacing-sm);
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
                <?php if (!$is_admin): ?>
                    <a href="checkout.php" class="nav-item">
                        <i class="fas fa-credit-card"></i>
                        <span>Checkout</span>
                    </a>
                <?php endif; ?>
                
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

    <!-- Main Content -->
    <div class="main-content">
        <div class="products-container">
            <!-- Cart Message Container -->
            <div id="cart-message" class="validation-message" style="display: none; margin-bottom: var(--spacing-lg);"></div>
            
            <h1 style="text-align: center; margin-bottom: var(--spacing-2xl); color: var(--color-primary-green);">
                All Products
            </h1>
        </h1>

        <!-- Search and Filter Section -->
        <div class="search-filter-section">
            <form method="GET" action="product_search_result.php" class="search-filter-row">
                <div class="form-group">
                    <label for="search" class="form-label">Search Products</label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           class="form-input" 
                           placeholder="Search by product name or keywords..."
                           value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                
                <div class="form-group">
                    <label for="category" class="form-label">Category</label>
                    <select id="category" name="category" class="form-input">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['cat_id']; ?>" 
                                    <?php echo ($category_filter == $category['cat_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['cat_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="brand" class="form-label">Brand</label>
                    <select id="brand" name="brand" class="form-input">
                        <option value="">All Brands</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?php echo $brand['brand_id']; ?>" 
                                    <?php echo ($brand_filter == $brand['brand_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($brand['brand_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </form>
            
            <?php if (!empty($search_query) || $category_filter || $brand_filter): ?>
                <div style="margin-top: var(--spacing-md); padding-top: var(--spacing-md); border-top: 1px solid var(--color-border-gray);">
                    <p style="margin: 0; font-size: var(--font-size-small); color: var(--color-medium-gray);">
                        Active filters: 
                        <?php if (!empty($search_query)): ?>
                            <span style="background: var(--color-light-green); padding: 2px 8px; border-radius: 4px; margin-right: 8px;">
                                Search: "<?php echo htmlspecialchars($search_query); ?>"
                            </span>
                        <?php endif; ?>
                        <?php if ($category_filter): ?>
                            <?php 
                            $selected_category = array_filter($categories, function($cat) use ($category_filter) {
                                return $cat['cat_id'] == $category_filter;
                            });
                            $selected_category = reset($selected_category);
                            ?>
                            <span style="background: var(--color-light-green); padding: 2px 8px; border-radius: 4px; margin-right: 8px;">
                                Category: <?php echo htmlspecialchars($selected_category['cat_name']); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($brand_filter): ?>
                            <?php 
                            $selected_brand = array_filter($brands, function($brand) use ($brand_filter) {
                                return $brand['brand_id'] == $brand_filter;
                            });
                            $selected_brand = reset($selected_brand);
                            ?>
                            <span style="background: var(--color-light-green); padding: 2px 8px; border-radius: 4px; margin-right: 8px;">
                                Brand: <?php echo htmlspecialchars($selected_brand['brand_name']); ?>
                            </span>
                        <?php endif; ?>
                        <a href="all_product.php" style="color: var(--color-primary-green); text-decoration: underline; font-size: var(--font-size-small);">
                            Clear all filters
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Filter Sidebar -->
        <div class="quick-filters-sidebar" style="background: rgba(255, 255, 255, 0.95); border-radius: var(--border-radius-lg); padding: var(--spacing-lg); margin-bottom: var(--spacing-xl); box-shadow: var(--shadow-md);">
            <h3 style="font-size: var(--font-size-h4); color: var(--color-dark-gray); margin-bottom: var(--spacing-md);">Quick Filters</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md);">
                <!-- Category Quick Filters -->
                <div>
                    <h4 style="font-size: var(--font-size-body); font-weight: var(--font-weight-semibold); color: var(--color-medium-gray); margin-bottom: var(--spacing-sm);">Categories</h4>
                    <div style="display: flex; flex-direction: column; gap: var(--spacing-xs);">
                        <a href="all_product.php" 
                           class="<?php echo !$category_filter ? 'btn btn-primary btn-small' : 'btn btn-secondary btn-small'; ?>" 
                           style="text-align: left; justify-content: flex-start;">
                            All Categories
                        </a>
                        <?php foreach (array_slice($categories, 0, 5) as $category): ?>
                            <a href="all_product.php?category=<?php echo $category['cat_id']; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo $brand_filter ? '&brand=' . $brand_filter : ''; ?>" 
                               class="<?php echo ($category_filter == $category['cat_id']) ? 'btn btn-primary btn-small' : 'btn btn-secondary btn-small'; ?>" 
                               style="text-align: left; justify-content: flex-start;">
                                <?php echo htmlspecialchars($category['cat_name']); ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (count($categories) > 5): ?>
                            <small style="color: var(--color-medium-gray); font-size: var(--font-size-small);">
                                And <?php echo count($categories) - 5; ?> more in dropdown above
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Brand Quick Filters -->
                <div>
                    <h4 style="font-size: var(--font-size-body); font-weight: var(--font-weight-semibold); color: var(--color-medium-gray); margin-bottom: var(--spacing-sm);">Brands</h4>
                    <div style="display: flex; flex-direction: column; gap: var(--spacing-xs);">
                        <a href="all_product.php<?php echo !empty($search_query) ? '?search=' . urlencode($search_query) : ''; ?><?php echo $category_filter ? (!empty($search_query) ? '&' : '?') . 'category=' . $category_filter : ''; ?>" 
                           class="<?php echo !$brand_filter ? 'btn btn-primary btn-small' : 'btn btn-secondary btn-small'; ?>" 
                           style="text-align: left; justify-content: flex-start;">
                            All Brands
                        </a>
                        <?php foreach (array_slice($brands, 0, 5) as $brand): ?>
                            <a href="all_product.php?brand=<?php echo $brand['brand_id']; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo $category_filter ? '&category=' . $category_filter : ''; ?>" 
                               class="<?php echo ($brand_filter == $brand['brand_id']) ? 'btn btn-primary btn-small' : 'btn btn-secondary btn-small'; ?>" 
                               style="text-align: left; justify-content: flex-start;">
                                <?php echo htmlspecialchars($brand['brand_name']); ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (count($brands) > 5): ?>
                            <small style="color: var(--color-medium-gray); font-size: var(--font-size-small);">
                                And <?php echo count($brands) - 5; ?> more in dropdown above
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Products Grid -->
        <?php if (!empty($products)): ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card" onclick="window.location.href='single_product.php?id=<?php echo $product['product_id']; ?>'">
                        <div class="product-image">
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
                        
                        <div class="product-info">
                            <h3 class="product-title"><?php echo htmlspecialchars($product['product_title']); ?></h3>
                            
                            <div class="product-meta">
                                <span>ID: <?php echo $product['product_id']; ?></span>
                                <span><?php echo htmlspecialchars($product['cat_name'] ?? 'No Category'); ?></span>
                            </div>
                            
                            <div class="product-meta">
                                <span>Brand: <?php echo htmlspecialchars($product['brand_name'] ?? 'No Brand'); ?></span>
                            </div>
                            
                            <div class="product-price">
                                $<?php echo number_format($product['product_price'], 2); ?>
                            </div>
                            
                            <div class="product-actions">
                                <button class="btn btn-primary btn-small" 
                                        onclick="event.stopPropagation(); addToCartLocal(<?php echo $product['product_id']; ?>)">
                                    Add to Cart
                                </button>
                                <button class="btn btn-secondary btn-small" 
                                        onclick="event.stopPropagation(); window.location.href='single_product.php?id=<?php echo $product['product_id']; ?>'">
                                    View Details
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <?php echo $pagination['start_item']; ?>-<?php echo $pagination['end_item']; ?> 
                        of <?php echo $pagination['total_items']; ?> products
                    </div>
                    
                    <div style="display: flex; gap: var(--spacing-sm); align-items: center;">
                        <?php if ($pagination['has_previous']): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] - 1])); ?>" 
                               class="btn btn-secondary btn-small">Previous</a>
                        <?php endif; ?>
                        
                        <?php
                        // Show page numbers (simplified - show current page and a few around it)
                        $start_page = max(1, $pagination['current_page'] - 2);
                        $end_page = min($pagination['total_pages'], $pagination['current_page'] + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <?php if ($i == $pagination['current_page']): ?>
                                <span class="btn btn-primary btn-small" style="cursor: default;"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="btn btn-secondary btn-small"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($pagination['has_next']): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] + 1])); ?>" 
                               class="btn btn-secondary btn-small">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-products">
                <h2 style="color: var(--color-medium-gray); margin-bottom: var(--spacing-md);">No Products Found</h2>
                <p style="color: var(--color-medium-gray); margin-bottom: var(--spacing-lg);">
                    <?php if (!empty($search_query) || $category_filter || $brand_filter): ?>
                        No products match your current search criteria. Try adjusting your filters or search terms.
                    <?php else: ?>
                        There are currently no products available.
                    <?php endif; ?>
                </p>
                <?php if (!empty($search_query) || $category_filter || $brand_filter): ?>
                    <a href="all_product.php" class="btn btn-primary">View All Products</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Include jQuery for AJAX functionality -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Include Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Include cart.js for cart functionality -->
    <script src="js/cart.js"></script>
    
    <script>
        // Auto-submit form when filters change (optional enhancement)
        document.getElementById('category').addEventListener('change', function() {
            // Optionally auto-submit the form when category changes
            // this.form.submit();
        });
        
        document.getElementById('brand').addEventListener('change', function() {
            // Optionally auto-submit the form when brand changes
            // this.form.submit();
        });
        
        // Enhanced add to cart functionality with loading states
        function addToCartLocal(productId) {
            const button = event.target;
            const originalText = button.textContent;
            
            // Show loading state
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            
            // Call the global addToCart function from cart.js
            window.addToCart(productId, 1)
                .then(response => {
                    // Success - show success state briefly
                    button.innerHTML = '<i class="fas fa-check"></i> Added!';
                    button.style.backgroundColor = 'var(--color-success)';
                    
                    // Reset button after 2 seconds
                    setTimeout(() => {
                        button.disabled = false;
                        button.innerHTML = originalText;
                        button.style.backgroundColor = '';
                    }, 2000);
                })
                .catch(error => {
                    // Error - show error state briefly
                    button.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error';
                    button.style.backgroundColor = 'var(--color-error)';
                    
                    // Reset button after 3 seconds
                    setTimeout(() => {
                        button.disabled = false;
                        button.innerHTML = originalText;
                        button.style.backgroundColor = '';
                    }, 3000);
                });
        }
        
        // Add cart link to navigation if user is logged in or has items
        $(document).ready(function() {
            // Check if we should show cart link in navigation
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
        </div> <!-- Close products-container -->
    </div> <!-- Close main-content -->
</body>
</html>
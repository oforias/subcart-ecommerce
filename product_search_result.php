<?php
/**
 * Product Search Results Page
 * Customer-facing page for displaying search results with refinement filters and sorting
 * Requirements: 3.2, 3.3, 3.4, 3.5, 5.5
 */

// Start session for potential user state
session_start();

// Include required files
require_once 'controllers/product_display_controller.php';
require_once 'settings/core.php';

// Get search parameters
$search_query = isset($_GET['query']) ? trim($_GET['query']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : null;
$brand_filter = isset($_GET['brand']) ? (int)$_GET['brand'] : null;
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'desc';

// Initialize variables
$products = [];
$pagination = [];
$error_message = '';
$categories = [];
$brands = [];
$search_suggestions = [];

// Validate sort parameters
$valid_sort_options = ['price', 'name', 'date'];
$valid_sort_orders = ['asc', 'desc'];
if (!in_array($sort_by, $valid_sort_options)) {
    $sort_by = 'date';
}
if (!in_array($sort_order, $valid_sort_orders)) {
    $sort_order = 'desc';
}

// Get categories for filter dropdown
$categories_result = get_categories_for_filter_ctr();
if ($categories_result['success']) {
    $categories = $categories_result['data']['categories'];
}

// Get brands for filter dropdown
$brands_result = get_brands_for_filter_ctr();
if ($brands_result['success']) {
    $brands = $brands_result['data']['brands'];
}

// Perform search if query is provided
if (!empty($search_query)) {
    // Use composite search for search with optional filters
    $search_params = [
        'query' => $search_query,
        'category_id' => $category_filter,
        'brand_id' => $brand_filter,
        'min_price' => $min_price,
        'max_price' => $max_price,
        'page' => $page,
        'limit' => 10,
        'sort_by' => $sort_by,
        'sort_order' => $sort_order
    ];
    $result = composite_search_ctr($search_params);
    
    // Process results
    if ($result['success']) {
        $products = $result['data']['products'];
        $pagination = $result['data']['pagination'];
    } else {
        $error_message = $result['error'];
        
        // Generate search suggestions for no results
        if ($result['error_type'] === 'not_found' || empty($products)) {
            $search_suggestions = [
                'Try different keywords',
                'Check your spelling',
                'Use more general terms',
                'Remove some filters',
                'Browse all products'
            ];
        }
    }
} else {
    // Redirect to all products if no search query
    header('Location: all_product.php');
    exit();
}

// Check if user is logged in for navigation
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $is_logged_in ? $_SESSION['user_name'] : '';
$is_admin = $is_logged_in && has_admin_privileges();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Search Results: <?php echo htmlspecialchars($search_query); ?> - Our Store</title>
    <link href="css/sweetgreen-style.css" rel="stylesheet">
    <link href="css/enhanced-buttons.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Search Results Styles */
        .search-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .search-header {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-md);
        }
        
        .search-title {
            font-size: var(--font-size-h2);
            color: var(--color-primary-green);
            margin-bottom: var(--spacing-md);
        }
        
        .search-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-lg);
            font-size: var(--font-size-small);
            color: var(--color-medium-gray);
        }
        
        .refinement-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-md);
        }
        
        .refinement-title {
            font-size: var(--font-size-h4);
            color: var(--color-dark-gray);
            margin-bottom: var(--spacing-md);
        }
        
        .refinement-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto;
            gap: var(--spacing-md);
            align-items: end;
            margin-bottom: var(--spacing-md);
        }
        
        .sort-section {
            display: flex;
            gap: var(--spacing-md);
            align-items: center;
            padding: var(--spacing-md) 0;
            border-top: 1px solid var(--color-border-gray);
        }
        
        .active-filters {
            margin-top: var(--spacing-md);
            padding-top: var(--spacing-md);
            border-top: 1px solid var(--color-border-gray);
        }
        
        .filter-tag {
            display: inline-block;
            background: var(--color-light-green);
            padding: 4px 12px;
            border-radius: var(--border-radius-sm);
            margin-right: var(--spacing-sm);
            margin-bottom: var(--spacing-sm);
            font-size: var(--font-size-small);
            color: var(--color-dark-gray);
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
        
        .no-results {
            text-align: center;
            padding: var(--spacing-3xl);
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
        }
        
        .no-results-icon {
            font-size: 4rem;
            color: var(--color-medium-gray);
            margin-bottom: var(--spacing-lg);
        }
        
        .suggestions-list {
            list-style: none;
            padding: 0;
            margin: var(--spacing-lg) 0;
        }
        
        .suggestions-list li {
            padding: var(--spacing-sm) 0;
            color: var(--color-medium-gray);
        }
        
        .suggestions-list li:before {
            content: "• ";
            color: var(--color-primary-green);
            font-weight: bold;
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
            .refinement-row {
                grid-template-columns: 1fr;
                gap: var(--spacing-md);
            }
            
            .sort-section {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--spacing-sm);
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
            <a href="all_product.php" class="nav-item active">
                <i class="fas fa-leaf"></i>
                <span>Products</span>
            </a>
            <a href="cart.php" class="nav-item">
                <i class="fas fa-shopping-cart"></i>
                <span>Shopping Cart</span>
            </a>
            
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
                        <i class="fas fa-plus"></i>
                        <span>Add Product</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div class="sidebar-footer">
            <?php if ($is_logged_in): ?>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
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

    <div class="search-container">
        <!-- Search Header -->
        <div class="search-header">
            <h1 class="search-title">Search Results</h1>
            <div class="search-meta">
                <span>
                    <?php if (!empty($products)): ?>
                        Showing <?php echo isset($pagination['start_item']) ? $pagination['start_item'] : 1; ?>-<?php echo isset($pagination['end_item']) ? $pagination['end_item'] : count($products); ?> 
                        of <?php echo isset($pagination['total_items']) ? $pagination['total_items'] : count($products); ?> results
                    <?php else: ?>
                        No results found
                    <?php endif; ?>
                    for "<?php echo htmlspecialchars($search_query); ?>"
                </span>
                <a href="all_product.php" class="btn btn-secondary btn-small">Browse All Products</a>
            </div>
        </div>

        <!-- Quick Filter Sidebar -->
        <div class="quick-filters-sidebar" style="background: rgba(255, 255, 255, 0.95); border-radius: var(--border-radius-lg); padding: var(--spacing-lg); margin-bottom: var(--spacing-xl); box-shadow: var(--shadow-md);">
            <h3 style="font-size: var(--font-size-h4); color: var(--color-dark-gray); margin-bottom: var(--spacing-md);">Browse by Category & Brand</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md);">
                <!-- Category Quick Filters -->
                <div>
                    <h4 style="font-size: var(--font-size-body); font-weight: var(--font-weight-semibold); color: var(--color-medium-gray); margin-bottom: var(--spacing-sm);">Categories</h4>
                    <div style="display: flex; flex-direction: column; gap: var(--spacing-xs);">
                        <a href="product_search_result.php?query=<?php echo urlencode($search_query); ?>" 
                           class="<?php echo !$category_filter ? 'btn btn-primary btn-small' : 'btn btn-secondary btn-small'; ?>" 
                           style="text-align: left; justify-content: flex-start;">
                            All Categories
                        </a>
                        <?php foreach (array_slice($categories, 0, 5) as $category): ?>
                            <a href="product_search_result.php?query=<?php echo urlencode($search_query); ?>&category=<?php echo $category['cat_id']; ?><?php echo $brand_filter ? '&brand=' . $brand_filter : ''; ?><?php echo $min_price !== null ? '&min_price=' . $min_price : ''; ?><?php echo $max_price !== null ? '&max_price=' . $max_price : ''; ?>" 
                               class="<?php echo ($category_filter == $category['cat_id']) ? 'btn btn-primary btn-small' : 'btn btn-secondary btn-small'; ?>" 
                               style="text-align: left; justify-content: flex-start;">
                                <?php echo htmlspecialchars($category['cat_name']); ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (count($categories) > 5): ?>
                            <small style="color: var(--color-medium-gray); font-size: var(--font-size-small);">
                                And <?php echo count($categories) - 5; ?> more in filters above
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Brand Quick Filters -->
                <div>
                    <h4 style="font-size: var(--font-size-body); font-weight: var(--font-weight-semibold); color: var(--color-medium-gray); margin-bottom: var(--spacing-sm);">Brands</h4>
                    <div style="display: flex; flex-direction: column; gap: var(--spacing-xs);">
                        <a href="product_search_result.php?query=<?php echo urlencode($search_query); ?><?php echo $category_filter ? '&category=' . $category_filter : ''; ?><?php echo $min_price !== null ? '&min_price=' . $min_price : ''; ?><?php echo $max_price !== null ? '&max_price=' . $max_price : ''; ?>" 
                           class="<?php echo !$brand_filter ? 'btn btn-primary btn-small' : 'btn btn-secondary btn-small'; ?>" 
                           style="text-align: left; justify-content: flex-start;">
                            All Brands
                        </a>
                        <?php foreach (array_slice($brands, 0, 5) as $brand): ?>
                            <a href="product_search_result.php?query=<?php echo urlencode($search_query); ?>&brand=<?php echo $brand['brand_id']; ?><?php echo $category_filter ? '&category=' . $category_filter : ''; ?><?php echo $min_price !== null ? '&min_price=' . $min_price : ''; ?><?php echo $max_price !== null ? '&max_price=' . $max_price : ''; ?>" 
                               class="<?php echo ($brand_filter == $brand['brand_id']) ? 'btn btn-primary btn-small' : 'btn btn-secondary btn-small'; ?>" 
                               style="text-align: left; justify-content: flex-start;">
                                <?php echo htmlspecialchars($brand['brand_name']); ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (count($brands) > 5): ?>
                            <small style="color: var(--color-medium-gray); font-size: var(--font-size-small);">
                                And <?php echo count($brands) - 5; ?> more in filters above
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Refinement and Sort Section -->
        <div class="refinement-section">
            <h3 class="refinement-title">Refine Your Search</h3>
            
            <form method="GET" action="product_search_result.php" class="refinement-row">
                <input type="hidden" name="query" value="<?php echo htmlspecialchars($search_query); ?>">
                
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
                    <label for="min_price" class="form-label">Min Price</label>
                    <input type="number" 
                           id="min_price" 
                           name="min_price" 
                           class="form-input" 
                           placeholder="0.00"
                           step="0.01"
                           min="0"
                           value="<?php echo $min_price !== null ? number_format($min_price, 2) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="max_price" class="form-label">Max Price</label>
                    <input type="number" 
                           id="max_price" 
                           name="max_price" 
                           class="form-input" 
                           placeholder="999.99"
                           step="0.01"
                           min="0"
                           value="<?php echo $max_price !== null ? number_format($max_price, 2) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
            </form>
            
            <!-- Sort Options -->
            <div class="sort-section">
                <span style="font-weight: var(--font-weight-semibold); color: var(--color-dark-gray);">Sort by:</span>
                <form method="GET" action="product_search_result.php" style="display: flex; gap: var(--spacing-sm); align-items: center;">
                    <input type="hidden" name="query" value="<?php echo htmlspecialchars($search_query); ?>">
                    <input type="hidden" name="category" value="<?php echo $category_filter; ?>">
                    <input type="hidden" name="brand" value="<?php echo $brand_filter; ?>">
                    <input type="hidden" name="min_price" value="<?php echo $min_price; ?>">
                    <input type="hidden" name="max_price" value="<?php echo $max_price; ?>">
                    
                    <select name="sort" class="form-input" style="width: auto;" onchange="this.form.submit()">
                        <option value="date" <?php echo ($sort_by === 'date') ? 'selected' : ''; ?>>Date Added</option>
                        <option value="name" <?php echo ($sort_by === 'name') ? 'selected' : ''; ?>>Product Name</option>
                        <option value="price" <?php echo ($sort_by === 'price') ? 'selected' : ''; ?>>Price</option>
                    </select>
                    
                    <select name="order" class="form-input" style="width: auto;" onchange="this.form.submit()">
                        <option value="asc" <?php echo ($sort_order === 'asc') ? 'selected' : ''; ?>>Ascending</option>
                        <option value="desc" <?php echo ($sort_order === 'desc') ? 'selected' : ''; ?>>Descending</option>
                    </select>
                </form>
            </div>
            
            <!-- Active Filters Display -->
            <?php if ($category_filter || $brand_filter || $min_price !== null || $max_price !== null): ?>
                <div class="active-filters">
                    <p style="margin: 0 0 var(--spacing-sm) 0; font-size: var(--font-size-small); color: var(--color-medium-gray);">
                        Active filters:
                    </p>
                    <div>
                        <?php if ($category_filter): ?>
                            <?php 
                            $selected_category = array_filter($categories, function($cat) use ($category_filter) {
                                return $cat['cat_id'] == $category_filter;
                            });
                            $selected_category = reset($selected_category);
                            ?>
                            <span class="filter-tag">
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
                            <span class="filter-tag">
                                Brand: <?php echo htmlspecialchars($selected_brand['brand_name']); ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($min_price !== null): ?>
                            <span class="filter-tag">
                                Min Price: $<?php echo number_format($min_price, 2); ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($max_price !== null): ?>
                            <span class="filter-tag">
                                Max Price: $<?php echo number_format($max_price, 2); ?>
                            </span>
                        <?php endif; ?>
                        
                        <a href="product_search_result.php?query=<?php echo urlencode($search_query); ?>" 
                           style="color: var(--color-primary-green); text-decoration: underline; font-size: var(--font-size-small);">
                            Clear all filters
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Error Message -->
        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Search Results -->
        <?php if (!empty($products)): ?>
            <div class="search-results products-grid">
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
                                        onclick="event.stopPropagation(); addToCart(<?php echo $product['product_id']; ?>)">
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
                        of <?php echo $pagination['total_items']; ?> results
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
            <!-- No Results Found -->
            <div class="no-results">
                <div class="no-results-icon">🔍</div>
                <h2 style="color: var(--color-medium-gray); margin-bottom: var(--spacing-md);">
                    No Results Found
                </h2>
                <p style="color: var(--color-medium-gray); margin-bottom: var(--spacing-lg);">
                    We couldn't find any products matching "<?php echo htmlspecialchars($search_query); ?>"
                    <?php if ($category_filter || $brand_filter || $min_price !== null || $max_price !== null): ?>
                        with your current filters
                    <?php endif; ?>.
                </p>
                
                <?php if (!empty($search_suggestions)): ?>
                    <div style="text-align: left; max-width: 400px; margin: 0 auto;">
                        <h4 style="color: var(--color-dark-gray); margin-bottom: var(--spacing-md);">
                            Try these suggestions:
                        </h4>
                        <ul class="suggestions-list">
                            <?php foreach ($search_suggestions as $suggestion): ?>
                                <li><?php echo htmlspecialchars($suggestion); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: var(--spacing-xl);">
                    <?php if ($category_filter || $brand_filter || $min_price !== null || $max_price !== null): ?>
                        <a href="product_search_result.php?query=<?php echo urlencode($search_query); ?>" 
                           class="btn btn-secondary" style="margin-right: var(--spacing-md);">
                            Remove Filters
                        </a>
                    <?php endif; ?>
                    <a href="all_product.php" class="btn btn-primary">Browse All Products</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Add to Cart functionality (placeholder)
        function addToCart(productId) {
            alert('Add to Cart functionality will be implemented in a future update. Product ID: ' + productId);
        }
        
        // Auto-submit form when filters change (optional enhancement)
        document.getElementById('category').addEventListener('change', function() {
            // Optionally auto-submit the form when category changes
            // this.form.submit();
        });
        
        document.getElementById('brand').addEventListener('change', function() {
            // Optionally auto-submit the form when brand changes
            // this.form.submit();
        });
        
        // Price range validation
        document.getElementById('min_price').addEventListener('change', function() {
            const minPrice = parseFloat(this.value);
            const maxPriceInput = document.getElementById('max_price');
            const maxPrice = parseFloat(maxPriceInput.value);
            
            if (minPrice && maxPrice && minPrice > maxPrice) {
                alert('Minimum price cannot be greater than maximum price');
                this.value = '';
            }
        });
        
        document.getElementById('max_price').addEventListener('change', function() {
            const maxPrice = parseFloat(this.value);
            const minPriceInput = document.getElementById('min_price');
            const minPrice = parseFloat(minPriceInput.value);
            
            if (minPrice && maxPrice && minPrice > maxPrice) {
                alert('Maximum price cannot be less than minimum price');
                this.value = '';
            }
        });
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
    </div> <!-- Close main-content -->
</body>
</html>
<?php
/**
 * Product Management - Admin Panel
 * 
 * Administrative interface for managing products with categories and brands.
 * Supports product creation, editing, image uploads, and organization.
 */

session_start();
require_once '../settings/core.php';

// Verify admin authentication
if (!is_logged_in()) {
    header('Location: ../login/login.php?error=' . urlencode('Please log in to access the admin panel'));
    exit();
}

if (!has_admin_privileges()) {
    header('Location: ../login/login.php?error=' . urlencode('Access denied. Administrator privileges required'));
    exit();
}

$user_id = get_current_user_id();
$customer_name = $_SESSION['customer_name'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - Admin Panel</title>
    <link href="../css/sweetgreen-style.css" rel="stylesheet">
    <link href="../css/enhanced-buttons.css" rel="stylesheet">
    <link href="../css/admin-elegant.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>
    
    <!-- Admin Sidebar Navigation -->
    <nav class="sidebar-nav" id="sidebar">
        <div class="sidebar-header">
            <h2>
                <i class="fas fa-cogs"></i>
                Admin Panel
            </h2>
        </div>
        
        <div class="sidebar-menu">
            <a href="../index.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            
            <div class="admin-section-header">
                <div class="admin-label">Admin</div>
            </div>
            
            <div class="admin-nav-section">
                <a href="category.php" class="admin-nav-item">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
                </a>
                <a href="brand.php" class="admin-nav-item">
                    <i class="fas fa-copyright"></i>
                    <span>Brands</span>
                </a>
                <a href="product.php" class="admin-nav-item active">
                    <i class="fas fa-box"></i>
                    <span>Manage Products</span>
                </a>
            </div>
        </div>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($customer_name); ?></div>
                <div class="user-status">Administrator</div>
            </div>
            <a href="../login/logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="main-content">
        <div class="admin-page-container">
            <div class="admin-header">
                <h1><i class="fa fa-box"></i> Product Management</h1>
                <p>Manage products organized by categories and brands for your e-commerce platform</p>
            </div>

        <!-- Add Product Form -->
        <div class="card card-form">
            <div class="card-header">
                <h4><i class="fa fa-plus-circle"></i> Add New Product</h4>
            </div>
            <div class="card-body">
                <form id="add-product-form" class="form" enctype="multipart/form-data">
                    <?php echo csrf_token_field(); ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="product_title" class="form-label">
                                Product Title <i class="fa fa-box"></i>
                            </label>
                            <input 
                                type="text" 
                                class="form-input" 
                                id="product_title" 
                                name="product_title" 
                                placeholder="Enter product title (e.g., iPhone 15 Pro, Nike Air Max)"
                                maxlength="255"
                                required
                            >
                            <small class="form-help">Product titles must be unique and between 1-255 characters</small>
                        </div>
                        <div class="form-group">
                            <label for="product_price" class="form-label">
                                Price <i class="fa fa-dollar-sign"></i>
                            </label>
                            <input 
                                type="number" 
                                class="form-input" 
                                id="product_price" 
                                name="product_price" 
                                placeholder="0.00"
                                min="0"
                                step="0.01"
                                required
                            >
                            <small class="form-help">Enter price in decimal format (e.g., 29.99)</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category_id" class="form-label">
                                Category <i class="fa fa-tag"></i>
                            </label>
                            <select 
                                class="form-input" 
                                id="category_id" 
                                name="category_id" 
                                required
                            >
                                <option value="">Select a category...</option>
                                <!-- Categories will be loaded via JavaScript -->
                            </select>
                            <small class="form-help">Select the category this product belongs to</small>
                        </div>
                        <div class="form-group">
                            <label for="brand_id" class="form-label">
                                Brand <i class="fa fa-copyright"></i>
                            </label>
                            <select 
                                class="form-input" 
                                id="brand_id" 
                                name="brand_id" 
                                required
                            >
                                <option value="">Select a brand...</option>
                                <!-- Brands will be loaded via JavaScript based on category selection -->
                            </select>
                            <small class="form-help">Select the brand for this product</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="product_description" class="form-label">
                            Description <i class="fa fa-align-left"></i>
                        </label>
                        <textarea 
                            class="form-input" 
                            id="product_description" 
                            name="product_description" 
                            rows="4"
                            placeholder="Enter detailed product description..."
                        ></textarea>
                        <small class="form-help">Optional detailed description of the product</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="product_keywords" class="form-label">
                                Keywords <i class="fa fa-search"></i>
                            </label>
                            <input 
                                type="text" 
                                class="form-input" 
                                id="product_keywords" 
                                name="product_keywords" 
                                placeholder="Enter search keywords (e.g., smartphone, electronics, apple)"
                                maxlength="255"
                            >
                            <small class="form-help">Optional keywords for search (max 255 characters)</small>
                        </div>
                        <div class="form-group">
                            <label for="product_image" class="form-label">
                                Product Image <i class="fa fa-image"></i>
                            </label>
                            <input 
                                type="file" 
                                class="form-input" 
                                id="product_image" 
                                name="product_image" 
                                accept="image/*"
                            >
                            <small class="form-help">Optional product image (JPG, PNG, GIF)</small>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="add-product-btn">
                            <span id="add-text">
                                <i class="fa fa-plus"></i> Add Product
                            </span>
                            <span id="add-loading" style="display: none;">
                                <i class="fa fa-spinner fa-spin"></i> Adding...
                            </span>
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fa fa-times"></i> Clear
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Products List -->
        <div class="card">
            <div class="card-header">
                <h4><i class="fa fa-list"></i> Your Products</h4>
                <div class="card-actions">
                    <button id="refresh-products" class="btn btn-secondary btn-small">
                        <i class="fa fa-refresh"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="products-loading" class="loading-state">
                    <i class="fa fa-spinner fa-spin"></i> Loading products...
                </div>
                <div id="products-empty" class="empty-state" style="display: none;">
                    <i class="fa fa-box-open"></i>
                    <h3>No Products Yet</h3>
                    <p>You haven't created any products yet. Use the form above to add your first product.</p>
                </div>
                <div id="products-list" class="products-container" style="display: none;">
                    <!-- Products will be loaded here via JavaScript, organized by categories and brands -->
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="edit-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h4><i class="fa fa-edit"></i> Edit Product</h4>
                <button class="modal-close" id="close-edit-modal">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="edit-product-form" class="form" enctype="multipart/form-data">
                    <?php echo csrf_token_field(); ?>
                    <input type="hidden" id="edit_product_id" name="product_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_product_title" class="form-label">
                                Product Title <i class="fa fa-box"></i>
                            </label>
                            <input 
                                type="text" 
                                class="form-input" 
                                id="edit_product_title" 
                                name="product_title" 
                                maxlength="255"
                                required
                            >
                            <small class="form-help">Product titles must be unique and between 1-255 characters</small>
                        </div>
                        <div class="form-group">
                            <label for="edit_product_price" class="form-label">
                                Price <i class="fa fa-dollar-sign"></i>
                            </label>
                            <input 
                                type="number" 
                                class="form-input" 
                                id="edit_product_price" 
                                name="product_price" 
                                min="0"
                                step="0.01"
                                required
                            >
                            <small class="form-help">Enter price in decimal format (e.g., 29.99)</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_category_id" class="form-label">
                                Category <i class="fa fa-tag"></i>
                            </label>
                            <select 
                                class="form-input" 
                                id="edit_category_id" 
                                name="category_id" 
                                required
                            >
                                <option value="">Select a category...</option>
                                <!-- Categories will be loaded via JavaScript -->
                            </select>
                            <small class="form-help">Select the category this product belongs to</small>
                        </div>
                        <div class="form-group">
                            <label for="edit_brand_id" class="form-label">
                                Brand <i class="fa fa-copyright"></i>
                            </label>
                            <select 
                                class="form-input" 
                                id="edit_brand_id" 
                                name="brand_id" 
                                required
                            >
                                <option value="">Select a brand...</option>
                                <!-- Brands will be loaded via JavaScript based on category selection -->
                            </select>
                            <small class="form-help">Select the brand for this product</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_product_description" class="form-label">
                            Description <i class="fa fa-align-left"></i>
                        </label>
                        <textarea 
                            class="form-input" 
                            id="edit_product_description" 
                            name="product_description" 
                            rows="4"
                        ></textarea>
                        <small class="form-help">Optional detailed description of the product</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_product_keywords" class="form-label">
                                Keywords <i class="fa fa-search"></i>
                            </label>
                            <input 
                                type="text" 
                                class="form-input" 
                                id="edit_product_keywords" 
                                name="product_keywords" 
                                maxlength="255"
                            >
                            <small class="form-help">Optional keywords for search (max 255 characters)</small>
                        </div>
                        <div class="form-group">
                            <label for="edit_product_image" class="form-label">
                                Product Image <i class="fa fa-image"></i>
                            </label>
                            <input 
                                type="file" 
                                class="form-input" 
                                id="edit_product_image" 
                                name="product_image" 
                                accept="image/*"
                            >
                            <small class="form-help">Optional new product image (JPG, PNG, GIF)</small>
                            <div id="current-image-info" style="margin-top: 5px; display: none;">
                                <small class="form-help">Current image: <span id="current-image-name"></span></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="update-product-btn">
                            <span id="update-text">
                                <i class="fa fa-save"></i> Update Product
                            </span>
                            <span id="update-loading" style="display: none;">
                                <i class="fa fa-spinner fa-spin"></i> Updating...
                            </span>
                        </button>
                        <button type="button" class="btn btn-secondary" id="cancel-edit">
                            <i class="fa fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h4><i class="fa fa-trash"></i> Delete Product</h4>
                <button class="modal-close" id="close-delete-modal">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fa fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action cannot be undone.
                </div>
                <p>Are you sure you want to delete the product "<strong id="delete-product-name"></strong>"?</p>
                <input type="hidden" id="delete_product_id">
                <div class="form-actions">
                    <button type="button" class="btn btn-error" id="confirm-delete-btn">
                        <span id="delete-text">
                            <i class="fa fa-trash"></i> Delete Product
                        </span>
                        <span id="delete-loading" style="display: none;">
                            <i class="fa fa-spinner fa-spin"></i> Deleting...
                        </span>
                    </button>
                    <button type="button" class="btn btn-secondary" id="cancel-delete">
                        <i class="fa fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

        </div>
    </div>

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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../js/product.js"></script>
</body>
</html>
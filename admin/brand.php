<?php
/**
 * Brand Management - Admin Panel
 * 
 * Administrative interface for managing product brands.
 * Brands are organized by categories and support full CRUD operations.
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
    <title>Brand Management - Admin Panel</title>
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
                <a href="brand.php" class="admin-nav-item active">
                    <i class="fas fa-copyright"></i>
                    <span>Brands</span>
                </a>
                <a href="product.php" class="admin-nav-item">
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
                <h1><i class="fa fa-copyright"></i> Brand Management</h1>
                <p>Manage brands organized by categories for your e-commerce platform</p>
            </div>

        <!-- Add Brand Form -->
        <div class="card card-form">
            <div class="card-header">
                <h4><i class="fa fa-plus-circle"></i> Add New Brand</h4>
            </div>
            <div class="card-body">
                <form id="add-brand-form" class="form">
                    <?php echo csrf_token_field(); ?>
                    <div class="form-group">
                        <label for="brand_name" class="form-label">
                            Brand Name <i class="fa fa-copyright"></i>
                        </label>
                        <input 
                            type="text" 
                            class="form-input" 
                            id="brand_name" 
                            name="brand_name" 
                            placeholder="Enter brand name (e.g., Nike, Adidas, Apple)"
                            maxlength="255"
                            required
                        >
                        <small class="form-help">Brand names must be unique within each category (1-255 characters)</small>
                    </div>
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
                        <small class="form-help">Select the category this brand belongs to</small>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="add-brand-btn">
                            <span id="add-text">
                                <i class="fa fa-plus"></i> Add Brand
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

        <!-- Brands List -->
        <div class="card">
            <div class="card-header">
                <h4><i class="fa fa-list"></i> Your Brands</h4>
                <div class="card-actions">
                    <button id="refresh-brands" class="btn btn-secondary btn-small">
                        <i class="fa fa-refresh"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="brands-loading" class="loading-state">
                    <i class="fa fa-spinner fa-spin"></i> Loading brands...
                </div>
                <div id="brands-empty" class="empty-state" style="display: none;">
                    <i class="fa fa-folder-open"></i>
                    <h3>No Brands Yet</h3>
                    <p>You haven't created any brands yet. Use the form above to add your first brand.</p>
                </div>
                <div id="brands-list" class="brands-container" style="display: none;">
                    <!-- Brands will be loaded here via JavaScript, organized by categories -->
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Brand Modal -->
    <div id="edit-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h4><i class="fa fa-edit"></i> Edit Brand</h4>
                <button class="modal-close" id="close-edit-modal">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="edit-brand-form" class="form">
                    <?php echo csrf_token_field(); ?>
                    <input type="hidden" id="edit_brand_id" name="brand_id">
                    <input type="hidden" id="edit_category_id" name="category_id">
                    <div class="form-group">
                        <label for="edit_brand_name" class="form-label">
                            Brand Name <i class="fa fa-copyright"></i>
                        </label>
                        <input 
                            type="text" 
                            class="form-input" 
                            id="edit_brand_name" 
                            name="brand_name" 
                            maxlength="255"
                            required
                        >
                        <small class="form-help">Brand names must be unique within each category (1-255 characters)</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            Category <i class="fa fa-tag"></i>
                        </label>
                        <input 
                            type="text" 
                            class="form-input" 
                            id="edit_category_name" 
                            readonly
                            disabled
                        >
                        <small class="form-help">Category cannot be changed when editing a brand</small>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="update-brand-btn">
                            <span id="update-text">
                                <i class="fa fa-save"></i> Update Brand
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
                <h4><i class="fa fa-trash"></i> Delete Brand</h4>
                <button class="modal-close" id="close-delete-modal">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fa fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action cannot be undone.
                </div>
                <p>Are you sure you want to delete the brand "<strong id="delete-brand-name"></strong>"?</p>
                <input type="hidden" id="delete_brand_id">
                <div class="form-actions">
                    <button type="button" class="btn btn-error" id="confirm-delete-btn">
                        <span id="delete-text">
                            <i class="fa fa-trash"></i> Delete Brand
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
    <script src="../js/brand.js"></script>
</body>
</html>

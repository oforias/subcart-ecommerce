<?php
/**
 * E-Commerce Store - Home Page
 * 
 * Main landing page displaying navigation options based on user role.
 * Customers see shopping options, admins see management tools.
 */

session_start();
require_once 'settings/core.php';

// Get user authentication status and role
$is_logged_in = isset($_SESSION['customer_id']);
$customer_name = $is_logged_in ? $_SESSION['customer_name'] : '';
$is_admin = $is_logged_in && has_admin_privileges();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Home - Our Store</title>
	<link href="css/sweetgreen-style.css" rel="stylesheet">
	<link href="css/enhanced-buttons.css" rel="stylesheet">
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
			<a href="index.php" class="nav-item active">
				<i class="fas fa-home"></i>
				<span>Home</span>
			</a>
			
			<?php if (!$is_admin): ?>
				<a href="all_product.php" class="nav-item">
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
					<div class="admin-section-header">
						<div class="admin-label">Admin</div>
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

	<div class="container content-section">
		<div class="text-center" style="max-width: 800px; margin: 0 auto; padding: 60px 20px;">
			<h1 style="font-size: 3rem; margin-bottom: 24px; color: var(--color-primary-green);">Welcome to Our Store</h1>
			
			<!-- Prominent Search Box -->
			<div style="max-width: 500px; margin: 0 auto 40px auto;">
				<form method="GET" action="product_search_result.php" style="display: flex; gap: 8px;">
					<input type="text" 
						   name="query" 
						   placeholder="Search for products..." 
						   class="form-input" 
						   style="flex: 1; padding: 16px; font-size: 1.1rem; border-radius: var(--border-radius-lg);"
						   required>
					<button type="submit" 
							class="btn btn-primary" 
							style="padding: 16px 24px; font-size: 1.1rem; border-radius: var(--border-radius-lg);">
						Search
					</button>
				</form>
			</div>
			
			<?php if ($is_logged_in): ?>
				<p style="font-size: 1.25rem; margin-bottom: 40px; color: var(--color-dark-gray);">
					Welcome back, <strong><?php echo htmlspecialchars($customer_name); ?></strong>! 
					<?php if ($is_admin): ?>
						You have administrator access.
					<?php endif; ?>
				</p>
				
				<?php if ($is_admin): ?>
					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; margin-top: 48px;">
						<a href="admin/category.php" class="card card-interactive" style="text-decoration: none; padding: 32px; text-align: center;">
							<div style="font-size: 3rem; margin-bottom: 16px;">📁</div>
							<h3 style="color: var(--color-primary-green); margin-bottom: 8px;">Categories</h3>
							<p style="color: var(--color-medium-gray); margin: 0;">Manage product categories</p>
						</a>
						
						<a href="admin/brand.php" class="card card-interactive" style="text-decoration: none; padding: 32px; text-align: center;">
							<div style="font-size: 3rem; margin-bottom: 16px;">🏷️</div>
							<h3 style="color: var(--color-primary-green); margin-bottom: 8px;">Brands</h3>
							<p style="color: var(--color-medium-gray); margin: 0;">Manage product brands</p>
						</a>
						
						<a href="admin/product.php" class="card card-interactive" style="text-decoration: none; padding: 32px; text-align: center;">
							<div style="font-size: 3rem; margin-bottom: 16px;">📦</div>
							<h3 style="color: var(--color-primary-green); margin-bottom: 8px;">Products</h3>
							<p style="color: var(--color-medium-gray); margin: 0;">Add and manage products</p>
						</a>
					</div>
				<?php else: ?>
					<div class="card" style="max-width: 600px; margin: 48px auto; padding: 40px; text-align: center;">
						<div style="font-size: 4rem; margin-bottom: 24px;">🛍️</div>
						<h2 style="color: var(--color-primary-green); margin-bottom: 16px;">Start Shopping</h2>
						<p style="color: var(--color-medium-gray); margin-bottom: 24px;">Browse our collection of products and find what you need.</p>
						<a href="all_product.php" class="btn btn-primary btn-large">Browse Products</a>
					</div>
				<?php endif; ?>
			<?php else: ?>
				<p style="font-size: 1.25rem; margin-bottom: 40px; color: var(--color-dark-gray);">
					Your one-stop shop for quality products
				</p>
				
				<div style="display: flex; gap: 16px; justify-content: center; margin-bottom: 60px;">
					<a href="login/register.php" class="btn btn-primary btn-large">Get Started</a>
					<a href="login/login.php" class="btn btn-secondary btn-large">Sign In</a>
				</div>
				
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; margin-top: 60px;">
					<div class="card" style="padding: 32px; text-align: center;">
						<div style="font-size: 3rem; margin-bottom: 16px;">✨</div>
						<h3 style="color: var(--color-primary-green); margin-bottom: 8px;">Quality Products</h3>
						<p style="color: var(--color-medium-gray); margin: 0;">Curated selection of the best items</p>
					</div>
					
					<div class="card" style="padding: 32px; text-align: center;">
						<div style="font-size: 3rem; margin-bottom: 16px;">🚀</div>
						<h3 style="color: var(--color-primary-green); margin-bottom: 8px;">Fast Delivery</h3>
						<p style="color: var(--color-medium-gray); margin: 0;">Quick and reliable shipping</p>
					</div>
					
					<div class="card" style="padding: 32px; text-align: center;">
						<div style="font-size: 3rem; margin-bottom: 16px;">💯</div>
						<h3 style="color: var(--color-primary-green); margin-bottom: 8px;">Satisfaction Guaranteed</h3>
						<p style="color: var(--color-medium-gray); margin: 0;">100% customer satisfaction</p>
					</div>
				</div>
			<?php endif; ?>
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


</body>
</html>

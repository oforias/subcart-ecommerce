/**
 * Enhanced Product Display JavaScript
 * Task 11 - Enhanced user experience and performance
 * Handles dynamic filter updates, asynchronous search, pagination, and user interaction
 */

// Global variables for enhanced functionality
let searchTimeout;
let currentPage = 1;
let isLoading = false;
let searchCache = new Map();

// Initialize enhanced functionality when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeEnhancedFeatures();
    setupResponsiveDesign();
    setupImageLazyLoading();
    setupSearchCaching();
    setupHoverEffects();
});

/**
 * Initialize all enhanced features
 */
function initializeEnhancedFeatures() {
    // Setup responsive design breakpoints
    handleResponsiveLayout();
    
    // Setup image lazy loading
    if ('IntersectionObserver' in window) {
        setupIntersectionObserver();
    }
    
    // Setup search result caching
    initializeSearchCache();
    
    // Setup hover effects for better UX
    addHoverEffects();
    
    // Setup mobile compatibility
    setupMobileOptimizations();
}

/**
 * Responsive design handling
 */
function setupResponsiveDesign() {
    const mediaQuery = window.matchMedia('(max-width: 768px)');
    
    function handleResponsiveChange(e) {
        if (e.matches) {
            // Mobile layout
            adjustForMobile();
        } else {
            // Desktop layout
            adjustForDesktop();
        }
    }
    
    // Initial check
    handleResponsiveChange(mediaQuery);
    
    // Listen for changes
    mediaQuery.addListener(handleResponsiveChange);
}

function adjustForMobile() {
    const productGrid = document.querySelector('.product-grid');
    if (productGrid) {
        productGrid.classList.add('mobile-grid');
        productGrid.classList.remove('desktop-grid');
    }
    
    // Adjust filter dropdowns for mobile
    const filterContainer = document.querySelector('.filter-container');
    if (filterContainer) {
        filterContainer.classList.add('mobile-filters');
    }
    
    // Make search box full width on mobile
    const searchBox = document.querySelector('#search_query');
    if (searchBox) {
        searchBox.style.width = '100%';
    }
}

function adjustForDesktop() {
    const productGrid = document.querySelector('.product-grid');
    if (productGrid) {
        productGrid.classList.add('desktop-grid');
        productGrid.classList.remove('mobile-grid');
    }
    
    const filterContainer = document.querySelector('.filter-container');
    if (filterContainer) {
        filterContainer.classList.remove('mobile-filters');
    }
    
    const searchBox = document.querySelector('#search_query');
    if (searchBox) {
        searchBox.style.width = '';
    }
}

/**
 * Image lazy loading with placeholder support
 */
function setupImageLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    loadImage(img);
                    observer.unobserve(img);
                }
            });
        });
        
        images.forEach(img => imageObserver.observe(img));
    } else {
        // Fallback for older browsers
        images.forEach(loadImage);
    }
}

function loadImage(img) {
    // Show loading placeholder
    img.classList.add('loading');
    
    const actualImg = new Image();
    actualImg.onload = function() {
        img.src = this.src;
        img.classList.remove('loading');
        img.classList.add('loaded');
    };
    
    actualImg.onerror = function() {
        // Use placeholder image on error
        img.src = 'images/placeholder-product.jpg';
        img.classList.remove('loading');
        img.classList.add('error');
    };
    
    actualImg.src = img.dataset.src;
}

function setupIntersectionObserver() {
    const lazyImages = document.querySelectorAll('.product-image[data-src]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                observer.unobserve(img);
            }
        });
    });
    
    lazyImages.forEach(img => {
        imageObserver.observe(img);
    });
}

/**
 * Search result caching for performance
 */
function setupSearchCaching() {
    // Cache frequently accessed search queries
    const maxCacheSize = 50;
    
    window.getFromCache = function(key) {
        return searchCache.get(key);
    };
    
    window.addToCache = function(key, data) {
        if (searchCache.size >= maxCacheSize) {
            // Remove oldest entry
            const firstKey = searchCache.keys().next().value;
            searchCache.delete(firstKey);
        }
        searchCache.set(key, {
            data: data,
            timestamp: Date.now()
        });
    };
    
    window.isCacheValid = function(key, maxAge = 300000) { // 5 minutes default
        const cached = searchCache.get(key);
        if (!cached) return false;
        return (Date.now() - cached.timestamp) < maxAge;
    };
}

function initializeSearchCache() {
    // Pre-cache common searches if available
    const commonSearches = ['nike', 'adidas', 'shoes', 'clothing'];
    
    commonSearches.forEach(term => {
        // This would be called after initial page load
        setTimeout(() => {
            if (!searchCache.has(term)) {
                // Pre-fetch common search results
                performCachedSearch(term, true);
            }
        }, 2000);
    });
}

/**
 * Enhanced hover effects and visual feedback
 */
function setupHoverEffects() {
    // Add hover effects to product cards
    const productCards = document.querySelectorAll('.product-card');
    
    productCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.classList.add('hover-effect');
            
            // Add subtle animation
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
            this.style.transition = 'all 0.3s ease';
        });
        
        card.addEventListener('mouseleave', function() {
            this.classList.remove('hover-effect');
            
            // Reset animation
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
        });
    });
    
    // Add hover effects to buttons
    const buttons = document.querySelectorAll('.btn, .add-to-cart-btn');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
            this.style.transition = 'transform 0.2s ease';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
}

function addHoverEffects() {
    // Add CSS classes for enhanced hover effects
    const style = document.createElement('style');
    style.textContent = `
        .product-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .product-image {
            transition: transform 0.3s ease;
        }
        
        .product-card:hover .product-image {
            transform: scale(1.05);
        }
        
        .btn:hover, .add-to-cart-btn:hover {
            transform: scale(1.05);
            transition: transform 0.2s ease;
        }
        
        .loading {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        @media (max-width: 768px) {
            .mobile-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 10px;
            }
            
            .mobile-filters {
                flex-direction: column;
                gap: 10px;
            }
            
            .mobile-filters select {
                width: 100%;
            }
        }
        
        @media (min-width: 769px) {
            .desktop-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
            }
        }
    `;
    document.head.appendChild(style);
}

/**
 * Mobile optimizations
 */
function setupMobileOptimizations() {
    // Touch-friendly interactions
    if ('ontouchstart' in window) {
        document.body.classList.add('touch-device');
        
        // Optimize touch interactions
        const productCards = document.querySelectorAll('.product-card');
        productCards.forEach(card => {
            card.addEventListener('touchstart', function() {
                this.classList.add('touch-active');
            });
            
            card.addEventListener('touchend', function() {
                setTimeout(() => {
                    this.classList.remove('touch-active');
                }, 150);
            });
        });
    }
    
    // Optimize for mobile viewport
    const viewport = document.querySelector('meta[name="viewport"]');
    if (!viewport) {
        const meta = document.createElement('meta');
        meta.name = 'viewport';
        meta.content = 'width=device-width, initial-scale=1.0, user-scalable=yes';
        document.head.appendChild(meta);
    }
}

/**
 * Enhanced search with caching
 */
function performCachedSearch(query, isPreload = false) {
    const cacheKey = `search_${query}`;
    
    // Check cache first
    if (window.isCacheValid && window.isCacheValid(cacheKey) && !isPreload) {
        const cached = window.getFromCache(cacheKey);
        if (cached) {
            displaySearchResults(cached.data);
            return;
        }
    }
    
    // Perform actual search
    if (!isPreload) {
        showLoadingIndicator();
    }
    
    fetch('actions/search_product_action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `search_query=${encodeURIComponent(query)}`
    })
    .then(response => response.json())
    .then(data => {
        if (window.addToCache) {
            window.addToCache(cacheKey, data);
        }
        
        if (!isPreload) {
            hideLoadingIndicator();
            displaySearchResults(data);
        }
    })
    .catch(error => {
        console.error('Search error:', error);
        if (!isPreload) {
            hideLoadingIndicator();
            showErrorMessage('Search failed. Please try again.');
        }
    });
}

/**
 * Loading indicators
 */
function showLoadingIndicator() {
    const existingLoader = document.querySelector('.loading-indicator');
    if (existingLoader) return;
    
    const loader = document.createElement('div');
    loader.className = 'loading-indicator';
    loader.innerHTML = `
        <div class="spinner"></div>
        <p>Loading products...</p>
    `;
    
    const productContainer = document.querySelector('.product-container') || document.body;
    productContainer.appendChild(loader);
    
    // Add spinner styles
    if (!document.querySelector('#spinner-styles')) {
        const style = document.createElement('style');
        style.id = 'spinner-styles';
        style.textContent = `
            .loading-indicator {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(255, 255, 255, 0.9);
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                text-align: center;
                z-index: 1000;
            }
            
            .spinner {
                width: 40px;
                height: 40px;
                border: 4px solid #f3f3f3;
                border-top: 4px solid #3498db;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto 10px;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    }
}

function hideLoadingIndicator() {
    const loader = document.querySelector('.loading-indicator');
    if (loader) {
        loader.remove();
    }
}

/**
 * Error handling
 */
function showErrorMessage(message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = message;
    errorDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #e74c3c;
        color: white;
        padding: 15px;
        border-radius: 5px;
        z-index: 1001;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(errorDiv);
    
    setTimeout(() => {
        errorDiv.remove();
    }, 5000);
}

/**
 * Handle responsive layout changes
 */
function handleResponsiveLayout() {
    const resizeHandler = debounce(() => {
        const width = window.innerWidth;
        
        if (width <= 768) {
            adjustForMobile();
        } else {
            adjustForDesktop();
        }
        
        // Recalculate lazy loading if needed
        if (window.IntersectionObserver) {
            setupImageLazyLoading();
        }
    }, 250);
    
    window.addEventListener('resize', resizeHandler);
}

/**
 * Utility function for debouncing
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Display search results (placeholder - integrate with existing search functionality)
 */
function displaySearchResults(data) {
    // This would integrate with the existing search result display logic
    console.log('Displaying search results:', data);
    
    // Update product grid with new results
    const productGrid = document.querySelector('.product-grid');
    if (productGrid && data.products) {
        // Clear existing products
        productGrid.innerHTML = '';
        
        // Add new products
        data.products.forEach(product => {
            const productCard = createProductCard(product);
            productGrid.appendChild(productCard);
        });
        
        // Re-initialize lazy loading for new images
        setupImageLazyLoading();
        
        // Re-add hover effects
        setupHoverEffects();
    }
}

/**
 * Create product card element
 */
function createProductCard(product) {
    const card = document.createElement('div');
    card.className = 'product-card';
    card.innerHTML = `
        <div class="product-image-container">
            <img class="product-image lazy" data-src="uploads/${product.product_image}" alt="${product.product_title}">
        </div>
        <div class="product-info">
            <h3 class="product-title">${product.product_title}</h3>
            <p class="product-price">$${product.product_price}</p>
            <p class="product-category">${product.category_name}</p>
            <p class="product-brand">${product.brand_name}</p>
            <button class="add-to-cart-btn" onclick="addToCart(${product.product_id})">Add to Cart</button>
        </div>
    `;
    
    // Add click handler for product details
    card.addEventListener('click', (e) => {
        if (!e.target.classList.contains('add-to-cart-btn')) {
            window.location.href = `single_product.php?id=${product.product_id}`;
        }
    });
    
    return card;
}

// Export functions for global access
window.performCachedSearch = performCachedSearch;
window.showLoadingIndicator = showLoadingIndicator;
window.hideLoadingIndicator = hideLoadingIndicator;
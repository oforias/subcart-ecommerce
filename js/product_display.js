/**
 * Product Display JavaScript
 * Handles dynamic filter updates, asynchronous search, pagination, and user interactions
 * for customer-facing product display pages
 * Requirements: 8.1, 8.2, 6.4, 7.5
 */

$(document).ready(function() {
    // Configuration constants
    const CONFIG = {
        SEARCH_DEBOUNCE_DELAY: 300,
        PAGINATION_LIMIT: 10,
        IMAGE_LAZY_LOAD_THRESHOLD: 100,
        MAX_SEARCH_LENGTH: 255,
        FILTER_UPDATE_DELAY: 100,
        CACHE_EXPIRY_TIME: 5 * 60 * 1000, // 5 minutes
        MAX_CACHE_SIZE: 50,
        PERFORMANCE_MONITORING: true
    };

    // Global state management
    let state = {
        currentPage: 1,
        currentQuery: '',
        currentFilters: {
            category_id: null,
            brand_id: null,
            min_price: null,
            max_price: null
        },
        isLoading: false,
        searchTimeout: null,
        filterOptions: {
            categories: [],
            brands: []
        },
        lastSearchTime: 0,
        cache: new Map(),
        performanceMetrics: {
            searchTimes: [],
            loadTimes: []
        }
    };

    // Initialize the page
    initializePage();

    /**
     * Initialize page functionality
     */
    function initializePage() {
        loadFilterOptions();
        bindEventHandlers();
        initializeLazyLoading();
        initializePerformanceMonitoring();
        initializeSearchCache();
        restoreStateFromURL();
        
        // Load initial products if no filters are set
        if (!hasActiveFilters() && !state.currentQuery) {
            loadProducts();
        }
    }

    /**
     * Bind all event handlers for dynamic interactions
     */
    function bindEventHandlers() {
        // Search input with debouncing
        $('#product-search').on('input', handleSearchInput);
        
        // Search form submission
        $('#search-form').on('submit', handleSearchSubmit);
        
        // Filter dropdowns
        $('#category-filter').on('change', handleCategoryFilterChange);
        $('#brand-filter').on('change', handleBrandFilterChange);
        
        // Price range filters
        $('#min-price-filter').on('input', debounce(handlePriceFilterChange, CONFIG.FILTER_UPDATE_DELAY));
        $('#max-price-filter').on('input', debounce(handlePriceFilterChange, CONFIG.FILTER_UPDATE_DELAY));
        
        // Clear filters button
        $('#clear-filters').on('click', handleClearFilters);
        
        // Pagination controls (delegated events for dynamic content)
        $(document).on('click', '.pagination-btn', handlePaginationClick);
        $(document).on('click', '.page-number', handlePageNumberClick);
        
        // Product interactions (delegated events)
        $(document).on('click', '.product-card', handleProductClick);
        $(document).on('click', '.add-to-cart-btn', handleAddToCartClick);
        
        // Browser back/forward navigation
        $(window).on('popstate', handlePopState);
        
        // Scroll-based lazy loading
        $(window).on('scroll', throttle(handleScroll, 100));
        
        // Form validation
        $('#price-range-form').on('submit', handlePriceRangeSubmit);
    }

    /**
     * Handle search input with debouncing for better performance
     */
    function handleSearchInput(e) {
        const query = $(this).val().trim();
        
        // Clear previous timeout
        if (state.searchTimeout) {
            clearTimeout(state.searchTimeout);
        }
        
        // Validate search length
        if (query.length > CONFIG.MAX_SEARCH_LENGTH) {
            showError('Search Error', 'Search query cannot exceed 255 characters.');
            return;
        }
        
        // Set new timeout for debounced search
        state.searchTimeout = setTimeout(() => {
            performSearch(query);
        }, CONFIG.SEARCH_DEBOUNCE_DELAY);
        
        // Update search query preservation (Requirement 7.5)
        state.currentQuery = query;
        updateURL();
    }

    /**
     * Handle search form submission
     */
    function handleSearchSubmit(e) {
        e.preventDefault();
        const query = $('#product-search').val().trim();
        
        // Clear timeout and perform immediate search
        if (state.searchTimeout) {
            clearTimeout(state.searchTimeout);
        }
        
        performSearch(query);
    }

    /**
     * Perform asynchronous search with loading states
     * Requirement: 8.2 - Asynchronous search functionality
     */
    function performSearch(query) {
        // Reset pagination for new search
        state.currentPage = 1;
        state.currentQuery = query;
        
        if (query === '') {
            // Empty search - load all products
            loadProducts();
        } else {
            // Perform search
            searchProducts(query);
        }
        
        updateURL();
    }

    /**
     * Handle category filter change with dynamic brand updates
     * Requirement: 8.1 - Dynamic filter updates without page reload
     */
    function handleCategoryFilterChange(e) {
        const categoryId = $(this).val();
        state.currentFilters.category_id = categoryId || null;
        state.currentPage = 1; // Reset pagination
        
        // Update brand dropdown based on category selection
        updateBrandOptions(categoryId);
        
        // Apply filters
        setTimeout(() => {
            applyFilters();
        }, CONFIG.FILTER_UPDATE_DELAY);
    }

    /**
     * Handle brand filter change
     */
    function handleBrandFilterChange(e) {
        const brandId = $(this).val();
        state.currentFilters.brand_id = brandId || null;
        state.currentPage = 1; // Reset pagination
        
        setTimeout(() => {
            applyFilters();
        }, CONFIG.FILTER_UPDATE_DELAY);
    }

    /**
     * Handle price filter changes
     */
    function handlePriceFilterChange(e) {
        const minPrice = parseFloat($('#min-price-filter').val()) || null;
        const maxPrice = parseFloat($('#max-price-filter').val()) || null;
        
        // Validate price range
        if (minPrice !== null && maxPrice !== null && minPrice > maxPrice) {
            showError('Filter Error', 'Minimum price cannot be greater than maximum price.');
            return;
        }
        
        state.currentFilters.min_price = minPrice;
        state.currentFilters.max_price = maxPrice;
        state.currentPage = 1; // Reset pagination
        
        applyFilters();
    }

    /**
     * Handle price range form submission
     */
    function handlePriceRangeSubmit(e) {
        e.preventDefault();
        handlePriceFilterChange();
    }

    /**
     * Handle clear filters action
     */
    function handleClearFilters(e) {
        e.preventDefault();
        
        // Reset all filters
        state.currentFilters = {
            category_id: null,
            brand_id: null,
            min_price: null,
            max_price: null
        };
        state.currentQuery = '';
        state.currentPage = 1;
        
        // Reset form elements
        $('#product-search').val('');
        $('#category-filter').val('');
        $('#brand-filter').val('');
        $('#min-price-filter').val('');
        $('#max-price-filter').val('');
        
        // Reload brand options (remove category filter)
        updateBrandOptions(null);
        
        // Load all products
        loadProducts();
        updateURL();
    }

    /**
     * Apply current filters with composite search
     */
    function applyFilters() {
        if (hasActiveFilters() || state.currentQuery) {
            if (state.currentQuery) {
                // Use search if query exists
                searchProducts(state.currentQuery);
            } else {
                // Use filter API
                filterProducts();
            }
        } else {
            // No filters - load all products
            loadProducts();
        }
        
        updateURL();
        updateActiveFiltersDisplay(); // Requirement 6.4
    }

    /**
     * Check if any filters are currently active
     */
    function hasActiveFilters() {
        return state.currentFilters.category_id !== null ||
               state.currentFilters.brand_id !== null ||
               state.currentFilters.min_price !== null ||
               state.currentFilters.max_price !== null;
    }

    /**
     * Update active filters display for user feedback
     * Requirement: 6.4 - Show applied filters clearly to user
     */
    function updateActiveFiltersDisplay() {
        const activeFilters = [];
        
        // Add active filters to display
        if (state.currentFilters.category_id) {
            const category = state.filterOptions.categories.find(c => c.cat_id == state.currentFilters.category_id);
            if (category) {
                activeFilters.push(`Category: ${category.cat_name}`);
            }
        }
        
        if (state.currentFilters.brand_id) {
            const brand = state.filterOptions.brands.find(b => b.brand_id == state.currentFilters.brand_id);
            if (brand) {
                activeFilters.push(`Brand: ${brand.brand_name}`);
            }
        }
        
        if (state.currentFilters.min_price !== null) {
            activeFilters.push(`Min Price: $${state.currentFilters.min_price.toFixed(2)}`);
        }
        
        if (state.currentFilters.max_price !== null) {
            activeFilters.push(`Max Price: $${state.currentFilters.max_price.toFixed(2)}`);
        }
        
        if (state.currentQuery) {
            activeFilters.push(`Search: "${state.currentQuery}"`);
        }
        
        // Update display
        const $activeFiltersContainer = $('#active-filters');
        if (activeFilters.length > 0) {
            $activeFiltersContainer.html(`
                <div class="active-filters-header">
                    <strong>Active Filters:</strong>
                    <button id="clear-all-filters" class="btn btn-small btn-secondary">Clear All</button>
                </div>
                <div class="active-filters-list">
                    ${activeFilters.map(filter => `<span class="filter-tag">${escapeHtml(filter)}</span>`).join('')}
                </div>
            `).show();
            
            // Bind clear all button
            $('#clear-all-filters').on('click', handleClearFilters);
        } else {
            $activeFiltersContainer.hide();
        }
    }

    /**
     * Handle pagination button clicks
     */
    function handlePaginationClick(e) {
        e.preventDefault();
        const action = $(this).data('action');
        
        if (action === 'prev' && state.currentPage > 1) {
            state.currentPage--;
            reloadCurrentView();
        } else if (action === 'next') {
            state.currentPage++;
            reloadCurrentView();
        }
    }

    /**
     * Handle page number clicks
     */
    function handlePageNumberClick(e) {
        e.preventDefault();
        const page = parseInt($(this).data('page'));
        
        if (page && page !== state.currentPage) {
            state.currentPage = page;
            reloadCurrentView();
        }
    }

    /**
     * Reload current view (search, filter, or all products)
     */
    function reloadCurrentView() {
        if (state.currentQuery) {
            searchProducts(state.currentQuery);
        } else if (hasActiveFilters()) {
            filterProducts();
        } else {
            loadProducts();
        }
        
        updateURL();
    }

    /**
     * Handle product card clicks for navigation
     */
    function handleProductClick(e) {
        // Don't navigate if clicking on buttons
        if ($(e.target).closest('.add-to-cart-btn, .product-actions').length > 0) {
            return;
        }
        
        const productId = $(this).data('product-id');
        if (productId) {
            // Navigate to single product page
            window.location.href = `single_product.php?id=${productId}`;
        }
    }

    /**
     * Handle add to cart button clicks (placeholder)
     */
    function handleAddToCartClick(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const productId = $(this).data('product-id');
        const productTitle = $(this).data('product-title');
        
        // Shopping cart integration placeholder
        showSuccess('Added to Cart', `${productTitle} has been added to your cart!`);
        
        // TODO: Implement actual cart functionality
        console.log('Add to cart:', { productId, productTitle });
    }

    /**
     * Load all products with pagination
     */
    function loadProducts() {
        if (state.isLoading) return;
        
        setLoadingState(true);
        
        $.ajax({
            url: 'actions/fetch_product_display_action.php',
            type: 'GET',
            data: {
                page: state.currentPage,
                limit: CONFIG.PAGINATION_LIMIT
            },
            dataType: 'json',
            success: function(response) {
                setLoadingState(false);
                
                if (response.status === 'success') {
                    displayProducts(response.data.products, response.data.pagination);
                } else {
                    showError('Error', response.message || 'Failed to load products');
                    displayEmptyState('Failed to load products');
                }
            },
            error: function(xhr, status, error) {
                setLoadingState(false);
                console.error('AJAX Error loading products:', status, error);
                showError('Connection Error', 'Failed to load products. Please check your connection and try again.');
                displayEmptyState('Connection error');
            }
        });
    }

    /**
     * Search products with current query
     */
    function searchProducts(query) {
        if (state.isLoading) return;
        
        setLoadingState(true);
        state.lastSearchTime = Date.now();
        
        const searchData = {
            query: query,
            page: state.currentPage,
            limit: CONFIG.PAGINATION_LIMIT
        };
        
        // Add filters to search if they exist
        if (hasActiveFilters()) {
            Object.assign(searchData, {
                category_id: state.currentFilters.category_id,
                brand_id: state.currentFilters.brand_id,
                min_price: state.currentFilters.min_price,
                max_price: state.currentFilters.max_price
            });
        }
        
        $.ajax({
            url: 'actions/search_product_action.php',
            type: 'GET',
            data: searchData,
            dataType: 'json',
            success: function(response) {
                setLoadingState(false);
                
                if (response.status === 'success') {
                    displayProducts(response.data.products, response.data.pagination);
                    
                    // Show search metadata
                    if (response.search_metadata) {
                        displaySearchMetadata(response.search_metadata);
                    }
                } else {
                    if (response.error_type === 'not_found') {
                        displayEmptyState('No products found matching your search', response.suggestions);
                    } else {
                        showError('Search Error', response.message || 'Search failed');
                        displayEmptyState('Search failed');
                    }
                }
            },
            error: function(xhr, status, error) {
                setLoadingState(false);
                console.error('AJAX Error searching products:', status, error);
                showError('Connection Error', 'Search failed. Please check your connection and try again.');
                displayEmptyState('Search connection error');
            }
        });
    }

    /**
     * Filter products with current filters
     */
    function filterProducts() {
        if (state.isLoading) return;
        
        setLoadingState(true);
        
        const filterData = {
            page: state.currentPage,
            limit: CONFIG.PAGINATION_LIMIT
        };
        
        // Add active filters
        Object.assign(filterData, state.currentFilters);
        
        $.ajax({
            url: 'actions/filter_product_action.php',
            type: 'GET',
            data: filterData,
            dataType: 'json',
            success: function(response) {
                setLoadingState(false);
                
                if (response.status === 'success') {
                    displayProducts(response.data.products, response.data.pagination);
                    
                    // Show filter metadata
                    if (response.filter_metadata) {
                        displayFilterMetadata(response.filter_metadata);
                    }
                } else {
                    if (response.error_type === 'not_found') {
                        displayEmptyState('No products found matching your filters', response.suggestions);
                    } else {
                        showError('Filter Error', response.message || 'Filter failed');
                        displayEmptyState('Filter failed');
                    }
                }
            },
            error: function(xhr, status, error) {
                setLoadingState(false);
                console.error('AJAX Error filtering products:', status, error);
                showError('Connection Error', 'Filter failed. Please check your connection and try again.');
                displayEmptyState('Filter connection error');
            }
        });
    }

    /**
     * Load filter options (categories and brands)
     */
    function loadFilterOptions() {
        $.ajax({
            url: 'actions/get_filter_options_action.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    state.filterOptions.categories = response.data.categories || [];
                    state.filterOptions.brands = response.data.brands || [];
                    
                    populateFilterDropdowns();
                } else {
                    console.error('Failed to load filter options:', response.message);
                    showError('Filter Error', 'Failed to load filter options. Some features may not work properly.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error loading filter options:', status, error);
                showError('Connection Error', 'Failed to load filter options. Please refresh the page.');
            }
        });
    }

    /**
     * Populate filter dropdowns with options
     */
    function populateFilterDropdowns() {
        // Populate category dropdown
        const $categoryFilter = $('#category-filter');
        $categoryFilter.empty().append('<option value="">All Categories</option>');
        
        state.filterOptions.categories.forEach(category => {
            $categoryFilter.append(`<option value="${category.cat_id}">${escapeHtml(category.cat_name)}</option>`);
        });
        
        // Populate brand dropdown (initially all brands)
        updateBrandOptions(null);
    }

    /**
     * Update brand options based on category selection
     */
    function updateBrandOptions(categoryId) {
        const $brandFilter = $('#brand-filter');
        $brandFilter.empty().append('<option value="">All Brands</option>');
        
        let brandsToShow = state.filterOptions.brands;
        
        // Filter brands by category if specified
        if (categoryId) {
            brandsToShow = state.filterOptions.brands.filter(brand => 
                brand.category_id == categoryId
            );
        }
        
        brandsToShow.forEach(brand => {
            $brandFilter.append(`<option value="${brand.brand_id}">${escapeHtml(brand.brand_name)}</option>`);
        });
        
        // Reset brand selection if current brand is not available
        if (state.currentFilters.brand_id) {
            const brandExists = brandsToShow.some(brand => brand.brand_id == state.currentFilters.brand_id);
            if (!brandExists) {
                state.currentFilters.brand_id = null;
                $brandFilter.val('');
            }
        }
    }

    /**
     * Display products in grid layout
     */
    function displayProducts(products, pagination) {
        const $container = $('#products-container');
        
        if (!products || products.length === 0) {
            displayEmptyState('No products found');
            return;
        }
        
        // Generate product grid HTML
        const productsHtml = products.map(product => `
            <div class="product-card" data-product-id="${product.product_id}">
                <div class="product-image-container">
                    ${product.product_image ? `
                        <img class="product-image lazy-load" 
                             data-src="${escapeHtml(product.product_image)}" 
                             alt="${escapeHtml(product.product_title)}"
                             loading="lazy">
                    ` : `
                        <div class="product-image-placeholder">
                            <i class="fa fa-image"></i>
                            <span>No Image</span>
                        </div>
                    `}
                </div>
                <div class="product-info">
                    <h3 class="product-title">${escapeHtml(product.product_title)}</h3>
                    <p class="product-price">$${parseFloat(product.product_price).toFixed(2)}</p>
                    <div class="product-meta">
                        <span class="product-category">${escapeHtml(product.category_name || 'Unknown Category')}</span>
                        <span class="product-brand">${escapeHtml(product.brand_name || 'Unknown Brand')}</span>
                    </div>
                    ${product.product_description ? `
                        <p class="product-description">${escapeHtml(product.product_description.substring(0, 100))}${product.product_description.length > 100 ? '...' : ''}</p>
                    ` : ''}
                </div>
                <div class="product-actions">
                    <button class="btn btn-primary add-to-cart-btn" 
                            data-product-id="${product.product_id}"
                            data-product-title="${escapeHtml(product.product_title)}">
                        <i class="fa fa-shopping-cart"></i> Add to Cart
                    </button>
                </div>
            </div>
        `).join('');
        
        $container.html(`
            <div class="products-grid">
                ${productsHtml}
            </div>
        `);
        
        // Display pagination
        displayPagination(pagination);
        
        // Initialize lazy loading for new images
        initializeLazyLoading();
        
        // Show results count
        displayResultsCount(pagination);
    }

    /**
     * Display pagination controls
     */
    function displayPagination(pagination) {
        if (!pagination || pagination.total_pages <= 1) {
            $('#pagination-container').hide();
            return;
        }
        
        const currentPage = pagination.current_page;
        const totalPages = pagination.total_pages;
        
        let paginationHtml = '<div class="pagination">';
        
        // Previous button
        if (currentPage > 1) {
            paginationHtml += `<button class="btn btn-secondary pagination-btn" data-action="prev">
                <i class="fa fa-chevron-left"></i> Previous
            </button>`;
        }
        
        // Page numbers (show up to 5 pages around current)
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);
        
        if (startPage > 1) {
            paginationHtml += `<button class="btn btn-secondary page-number" data-page="1">1</button>`;
            if (startPage > 2) {
                paginationHtml += '<span class="pagination-ellipsis">...</span>';
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const isActive = i === currentPage ? 'btn-primary' : 'btn-secondary';
            paginationHtml += `<button class="btn ${isActive} page-number" data-page="${i}">${i}</button>`;
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                paginationHtml += '<span class="pagination-ellipsis">...</span>';
            }
            paginationHtml += `<button class="btn btn-secondary page-number" data-page="${totalPages}">${totalPages}</button>`;
        }
        
        // Next button
        if (currentPage < totalPages) {
            paginationHtml += `<button class="btn btn-secondary pagination-btn" data-action="next">
                Next <i class="fa fa-chevron-right"></i>
            </button>`;
        }
        
        paginationHtml += '</div>';
        
        $('#pagination-container').html(paginationHtml).show();
    }

    /**
     * Display results count information
     */
    function displayResultsCount(pagination) {
        if (!pagination) return;
        
        const start = pagination.start_item || 1;
        const end = pagination.end_item || pagination.total_items;
        const total = pagination.total_items || 0;
        
        $('#results-count').html(`
            Showing ${start}-${end} of ${total} products
        `).show();
    }

    /**
     * Display empty state with optional suggestions
     */
    function displayEmptyState(message, suggestions = null) {
        let emptyHtml = `
            <div class="empty-state">
                <i class="fa fa-search fa-3x"></i>
                <h3>${escapeHtml(message)}</h3>
        `;
        
        if (suggestions && suggestions.length > 0) {
            emptyHtml += `
                <div class="suggestions">
                    <p>Try:</p>
                    <ul>
                        ${suggestions.map(suggestion => `<li>${escapeHtml(suggestion)}</li>`).join('')}
                    </ul>
                </div>
            `;
        }
        
        emptyHtml += '</div>';
        
        $('#products-container').html(emptyHtml);
        $('#pagination-container').hide();
        $('#results-count').hide();
    }

    /**
     * Display search metadata
     */
    function displaySearchMetadata(metadata) {
        if (metadata.search_time) {
            $('#search-time').html(`Search completed in ${(metadata.search_time * 1000).toFixed(0)}ms`).show();
        }
    }

    /**
     * Display filter metadata
     */
    function displayFilterMetadata(metadata) {
        if (metadata.filter_time) {
            $('#filter-time').html(`Filter applied in ${(metadata.filter_time * 1000).toFixed(0)}ms`).show();
        }
    }

    /**
     * Initialize lazy loading for images
     * Requirement: Image lazy loading for performance
     */
    function initializeLazyLoading() {
        const lazyImages = document.querySelectorAll('.lazy-load');
        
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy-load');
                        imageObserver.unobserve(img);
                    }
                });
            }, {
                rootMargin: `${CONFIG.IMAGE_LAZY_LOAD_THRESHOLD}px`
            });
            
            lazyImages.forEach(img => imageObserver.observe(img));
        } else {
            // Fallback for browsers without IntersectionObserver
            lazyImages.forEach(img => {
                img.src = img.dataset.src;
                img.classList.remove('lazy-load');
            });
        }
    }

    /**
     * Handle scroll events for additional lazy loading
     */
    function handleScroll() {
        // Additional scroll-based functionality can be added here
        // Currently handled by IntersectionObserver
    }

    /**
     * Handle browser back/forward navigation
     */
    function handlePopState(e) {
        if (e.originalEvent.state) {
            // Restore state from browser history
            Object.assign(state, e.originalEvent.state);
            restoreUIFromState();
            reloadCurrentView();
        }
    }

    /**
     * Update URL with current state for bookmarking and navigation
     */
    function updateURL() {
        const params = new URLSearchParams();
        
        if (state.currentQuery) {
            params.set('q', state.currentQuery);
        }
        
        if (state.currentFilters.category_id) {
            params.set('category', state.currentFilters.category_id);
        }
        
        if (state.currentFilters.brand_id) {
            params.set('brand', state.currentFilters.brand_id);
        }
        
        if (state.currentFilters.min_price !== null) {
            params.set('min_price', state.currentFilters.min_price);
        }
        
        if (state.currentFilters.max_price !== null) {
            params.set('max_price', state.currentFilters.max_price);
        }
        
        if (state.currentPage > 1) {
            params.set('page', state.currentPage);
        }
        
        const newURL = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
        
        // Update browser history
        history.pushState(state, '', newURL);
    }

    /**
     * Restore state from URL parameters
     */
    function restoreStateFromURL() {
        const params = new URLSearchParams(window.location.search);
        
        state.currentQuery = params.get('q') || '';
        state.currentFilters.category_id = params.get('category') ? parseInt(params.get('category')) : null;
        state.currentFilters.brand_id = params.get('brand') ? parseInt(params.get('brand')) : null;
        state.currentFilters.min_price = params.get('min_price') ? parseFloat(params.get('min_price')) : null;
        state.currentFilters.max_price = params.get('max_price') ? parseFloat(params.get('max_price')) : null;
        state.currentPage = params.get('page') ? parseInt(params.get('page')) : 1;
        
        // Restore UI elements
        setTimeout(() => {
            restoreUIFromState();
        }, 100); // Wait for filter options to load
    }

    /**
     * Restore UI elements from current state
     */
    function restoreUIFromState() {
        $('#product-search').val(state.currentQuery);
        $('#category-filter').val(state.currentFilters.category_id || '');
        $('#brand-filter').val(state.currentFilters.brand_id || '');
        $('#min-price-filter').val(state.currentFilters.min_price || '');
        $('#max-price-filter').val(state.currentFilters.max_price || '');
        
        // Update brand options if category is selected
        if (state.currentFilters.category_id) {
            updateBrandOptions(state.currentFilters.category_id);
        }
        
        updateActiveFiltersDisplay();
    }

    /**
     * Set loading state for UI feedback
     */
    function setLoadingState(isLoading) {
        state.isLoading = isLoading;
        
        if (isLoading) {
            $('#products-container').html(`
                <div class="loading-state">
                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                    <p>Loading products...</p>
                </div>
            `);
            $('#pagination-container').hide();
            $('#results-count').hide();
        }
    }

    /**
     * Form validation helper
     */
    function validateForm(formData) {
        // Add form validation logic here
        return { isValid: true };
    }

    /**
     * Utility function: Debounce
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
     * Utility function: Throttle
     */
    function throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    /**
     * Show success message using SweetAlert2
     */
    function showSuccess(title, message) {
        Swal.fire({
            icon: 'success',
            title: title,
            text: message,
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }

    /**
     * Show error message using SweetAlert2
     */
    function showError(title, message) {
        Swal.fire({
            icon: 'error',
            title: title,
            text: message,
            confirmButtonText: 'OK'
        });
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Initialize search result caching
     * Requirement: Add search result caching for frequently accessed queries
     */
    function initializeSearchCache() {
        // Clear expired cache entries on initialization
        cleanExpiredCache();
        
        // Set up periodic cache cleanup
        setInterval(cleanExpiredCache, 60000); // Clean every minute
    }

    /**
     * Generate cache key for search/filter parameters
     */
    function generateCacheKey(params) {
        const keyParts = [
            params.query || '',
            params.category_id || '',
            params.brand_id || '',
            params.min_price || '',
            params.max_price || '',
            params.page || 1,
            params.limit || CONFIG.PAGINATION_LIMIT
        ];
        return keyParts.join('|');
    }

    /**
     * Get cached result if available and not expired
     */
    function getCachedResult(cacheKey) {
        const cached = state.cache.get(cacheKey);
        if (cached && (Date.now() - cached.timestamp) < CONFIG.CACHE_EXPIRY_TIME) {
            return cached.data;
        }
        return null;
    }

    /**
     * Store
 */
    function setCachedResult(cacheKeya) {
        // Implement LRU cach
        if (state.cache.size >= CONE) {
            const firstKey = state.cachue;
            state.cache.delete(firstKey);
        }
        
      cheKey, {
       }

        });    }
        error');Connection mptyState('    displayE       ');
     try again.nd on atiyour connecse check ducts. Pleaload proiled to ror', 'Fannection Er'CoshowError(          se);
      alngState(foadi  setL              
             rror);
   s, e, statums:`)}(2.toFixedr ${loadTimefted aoad faile(`Le.error   consol           
                  e;
Timme - startendTi=  loadTime nst  co             w();
 .no performance =ndTime     const e          ror) {
 ertatus, on(xhr, sror: functi          er         },
    }
               cts');
   produo loadFailed ttate('mptySsplayE di            
       ; products')led to loadage || 'Faisponse.messor', rer('Err  showErro              e {
     els           });
     tionpaginae.data.cts, responsduprota.e.daponsroducts(resdisplayP                      
                   });
              ation
     .data.paginresponseion:     paginat               s,
     productponse.data.roducts: res  p                     y, {
 t(cacheKeesultCachedR          se          t
ulssful res the succe   // Cache          
       ess') {s === 'succse.statuf (respon  i                   
      e);
     (falsLoadingState set                       
 }
                 `);
      2)}msme.toFixed( in ${loadTioadedducts lProle.log(`nso  co                Time);
  load.push(esims.loadTmanceMetrictate.perfor          s          ITORING) {
NCE_MONFIG.PERFORMA   if (CON          
              me;
     tTidTime - staradTime = ent lo       cons       w();
  nce.noperformame =  const endTi              ) {
 esponse: function(rssce       suc     'json',
dataType:             ta,
: loadDa     data      GET',
  'ype:           t',
 .phpionay_actt_displch_producactions/fet: 'rl     ux({
        $.aja             
true);
  ate(LoadingSt    set     
            }
 ;
     return       n);
  natioResult.pagi cachedcts,sult.products(cachedReisplayProdu   d
         t');uct resulhed prodg('Using cacnsole.loco            Result) {
if (cached             
);
   cheKeyesult(caetCachedRt = gchedResul  const ca
      oadData);CacheKey(l= generateeKey t cach    cons    
        };
    T
    ION_LIMIATNFIG.PAGINimit: CO     l     
  ntPage,urrete.cage: sta   p  {
       dData = nst loa        co.now();
ce= performan startTime  const
               eturn;
g) rdin(state.isLoa   if () {
     oductstion loadPr/
    funching
     *cacoducts with d load pr   * Enhance
  
    /**
    }
        });        }
   ');
 ection erroronnilter ctate('FEmptyS   display           n.');
  try againd on anectir concheck youPlease er failed. Filton Error', 'Connecti showError('            false);
   adingState(     setLo
                          r);
 s, erro:`, statud(2)}msTime.toFixelterfi${ter  afailedilter fole.error(`F   cons      
                       me;
Ti - startTimeendlterTime =  const fi         
      e.now();erformancime = pnst endT       co    ) {
     roratus, err, stn(xhunctio error: f           },
             }
         }
                         ed');
 lter failFiptyState('  displayEm                
      iled');ter fa || 'Filmessage, response.rror'('Filter EwError sho                    else {
             }        ons);
   suggestiponse.ters', resng your filtchid maounucts f prodte('NolayEmptyStaisp d                    
   ') {nd=== 'not_foutype or_se.err  if (respon                  {
     } else           
      }              data);
 ilter_meta(response.fMetadatasplayFilter    di                   
 adata) {er_met.filtresponse    if (            
    er metadatafilt   // Show            
                  );
        aginationa.presponse.datucts, odnse.data.prts(resposplayProduc         di          
                      });
            ta
       etadanse.filter_m respoer_metadata:filt                    
    tion,data.paginan: response.ginatio     pa                 oducts,
  e.data.pr: responsuctsrod     p              {
     t(cacheKey, chedResulCa  set                result
  uccessful Cache the s         //         {
   s')  'succese.status ===if (respons          
                    );
  e(falseStatLoading   set        
                       }
       ;
       2)}ms`)ime.toFixed(lterTeted in ${fipler com`Filte.log(onsol  c        
          G) {ORINMONITMANCE_G.PERFOR   if (CONFI               
         ;
     rtTime sta= endTime -me lterTifi    const        ;
     .now()rmanceme = perfo endTi const              {
  e)on(respons functis:  succes   ,
       on' 'jsaType:        datrData,
    te data: fil    T',
       : 'GE    type',
        .phpduct_action/filter_proactions     url: '({
          $.ajax   
     ue);
     ngState(trdi setLoa  
                   }
n;
  tur          re }
           ta);
  da.filter_metaedResultta(cachtadayFilterMe   displa          {
    ta)er_metadailtResult.ff (cached           iation);
 aginchedResult.ps, caproductult.Reshedcts(cacProdulay       dispt');
     filter resul cached og('Usingole.l     cons{
       edResult)  if (cach   
    
        acheKey);(cachedResult getCult =hedRes cac   const     Data);
erey(filtrateCacheKheKey = genecacnst         co        
rs);
currentFiltete., stailterDatassign(f   Object.a   lters
  d active fi       // Ad
        
     };   
 _LIMITG.PAGINATIONt: CONFI   limi     e,
    .currentPagatee: st     pag{
       ilterData = st f con();
       .nowmanceerfortTime = pconst star 
              eturn;
 ng) rLoadi(state.isif  {
        ducts()lterProfunction fi  */
    
    cachings withter productEnhanced fil    * *
   /* }

  
      });    }
     ;
        n error')ioconnect('Search atelayEmptySt  disp          
    ');ain.d try ag anconnectionyour ease check  Pliled. 'Search far',n Erronectiorror('Con    showE          false);
  te(oadingSta  setL             
                or);
  status, err}ms:`,oFixed(2)earchTime.t{s $fterfailed ah rce.error(`Seaonsol  c          
                   tTime;
 - starme  = endTiTimerchnst sea      co          ow();
ance.n= performnst endTime           co
      , error) {atusr, ston(xh functi   error:             },
    }
                     }
                   );
d'Search failetyState('yEmpispla      d              ;
    iled')Search fa || 'se.message', responErrorror('Search    showEr             
          } else {                  estions);
sponse.suggarch', re your sed matchings foun productNoptyState('yEmdispla                   d') {
     t_foun== 'noor_type =onse.err(resp      if               {
se    } el                  }
       );
        h_metadataarcponse.seesadata(rMetrchaySea  displ                    {
   ata)tadarch_meponse.se   if (res              
   ch metadataar Show se          //                   
          ation);
 e.data.pagincts, respons.data.produresponseProducts(laydisp                         
         });
                          etadata
earch_mesponse.setadata: rsearch_m                    tion,
    data.paginaponse.: res  pagination                 
     ucts,se.data.products: responod   pr             {
        cheKey, t(caCachedResul      set            ult
  esful rsuccess/ Cache the        /          ess') {
   us === 'succsponse.statf (re    i            
               alse);
 e(ftLoadingStat        se             
                }
        ms`);
   2)}ed(oFixarchTime.teted in ${seompl`Search cole.log(  cons         ;
         (searchTime)hTimes.push.searcricsformanceMetate.per   st            
     G) {E_MONITORING.PERFORMANC if (CONFI       
                
        ime;tartTime - sime = endTst searchT        con      );
  now(ormance.= perfme ndTi e   const              {
sponse)nction(ress: fuce     suc  ,
     Type: 'json'     data       
archData,data: se         GET',
   pe: '        ty
    tion.php',_product_acearchons/s 'acti      url:
        $.ajax({  
            );
 Date.now(SearchTime =aste.lstat;
        gState(true)din      setLoa
  
               }rn;
         retu
           };
     ta)h_metadaearcult.schedResa(caadatySearchMet      displa  
        tadata) {.search_meResult(cached     if n);
       paginatioachedResult.cts, c.produltedResu(cachoductsdisplayPr           );
 t'ch resulearched ssing cansole.log('U  co          Result) {
ched if (ca  
            
 y);cheKehedResult(cault = getCacchedRes    const ca
    archData);cheKey(seCaateerKey = genacheonst c
        c        }
     });
               ax_price
ters.mFilntstate.curre_price: max               ice,
 s.min_prlterentFie.currprice: stat      min_
          id,and_rs.brentFilte: state.curr brand_id            ,
   .category_iderscurrentFilt: state.ory_id  categ          ta, {
    hDan(searcsigObject.as     ) {
       lters()ActiveFi     if (hasst
    if they exiearchlters to s   // Add fi     
     ;
        }N_LIMIT
   PAGINATIONFIG.it: CO       limPage,
     rente.curat: stpage       ry,
     ry: que        que = {
    rchDatast sea        conw();
rmance.nome = perfostartTi   const      
       eturn;
 isLoading) r if (state.    y) {
   cts(querearchProduction s fun  */
   caching
   h with archanced se
     * En /**  }

   });
          });
         00);
        }, 6  ;
         e.remove()       rippl             () => {
Timeout(    set              
           pple);
   Child(riis.appendth          
                      le');
'rippadd(.classList. ripple               
= y + 'px';.style.top pple      ri         px';
  + 'e.left = xipple.styl          r
      e + 'px';ht = sizeigple.style.hh = riptyle.widt ripple.s                   
      2;
      - size / ect.top clientY - ronst y = e.         c;
       - size / 2eft ct.l retX - e.cliennst x =co            ;
    eight)idth, rect.hrect.wx(ze = Math.ma  const si            t();
  ngClientRecgetBoundiis.ect = th    const r      
      n');ment('spa.createEle= documentipple t rons  c       
       nction(e) {'click', fur(enedEventList.ad   button  {
       => on Each(buttple').for-riptnorAll('.berySelectent.qu   docum     {
 leEffect() addRipp function  */
      experience
ser  ubetteredback for al fe and visufectsover ef: Add hntequireme R   *ttons
  fect to buripple ef   * Add 
    /**
  
    }
();EffectRipple
        addonso buttple effect td rip     // Ad    
   ;
    ion)unt(paginatlayResultsCo     dispunt
   esults co/ Show r
        / ;
       yLoading()tializeLaz   inimages
     new iloading for azy ize lnitial // I 
       n);
       atioon(paginplayPaginati
        disiony paginatpla// Dis            
 `);
        </div>
           sHtml}
      ${product             -grid">
 "productsass= <div cl          
 er.html(`contain   $  
     ;
      join('') `).     v>
        </didiv>
           </          on>
 tt     </bu               Cart
 /i> Add to-cart">< fa-shopping"fai class=           <          
   tle)}">ct_tiroduct.produapeHtml(p${esctitle="product- data-                           }"
duct_idproroduct.="${product-id    data-p                   
     tn-ripple" t-btn bardd-to-crimary a"btn btn-pton class= <but             
      ctions">oduct-a"prss=<div cla           /div>
             <     
   ` : ''}                     ''}</p>
' : ? '...ngth > 100scription.leroduct_deoduct.p{pr(0, 100))}$ing.substrptionduct_descriproduct.proescapeHtml(">${-descriptionductroass="pp cl       <            ? `
      iptiont_descr.producct   ${produ                
     </div>          >
      /spand')}<known Bran|| 'Uname duct.brand_nl(pro${escapeHtmd">oduct-bran"prs=clas     <span                >
    ory')}</spannown Categ || 'Unkategory_namet.ceHtml(producscapory">${et-categss="produc<span cla                      >
  -meta"s="product  <div clas                 >
 </ptoFixed(2)}ce)._priuctodproduct.prseFloat(ar${pprice">$product-lass=" <p c            h3>
       itle)}</oduct_tl(product.pr>${escapeHtmuct-title"="prodssh3 cla   <                ">
 roduct-info"piv class=          <d    </div>
                    `}
         >
         div   </            
         </span>No Image    <span>                        "></i>
agea fa-imss="f cla    <i               
         ">lderceho-image-pla="productv class       <di           : `
          `         
        ="lazy">ding loa                            }"
le)t_titproducuct.prodl(capeHtmes"${  alt=                          }" 
 _image)t.productHtml(produc${escaperc="  data-s                        
    lazy-load"oduct-image class="prg im     <                   iv>
        </d            >
    panoading...</s     <span>L                      ></i>
 mage"="fa fa-issla        <i c                 lder">
   ge-placehomaiv class="i <d                      `
 ? duct_image .pro{product     $              
 r">containe-image-ctoduss="pr<div cla             id}">
   roduct_{product.p"$product-id=data-" product-card"ass=div cl         <t => `
   producap(oducts.m = prproductsHtml     const   ading
  lonced lazyL with enha HTMroduct gride p/ Generat
        /}
          
      rn;        retud');
    oun products ftyState('NoEmpdisplay            = 0) {
length ==|| products.ts (!produc     if   
   
      r');containe#products-er = $('nst $contain   con) {
     , paginatioductsucts(proProdion display
    funct*/    t
 ing supporzy loadh lawitplay roduct disnced p   * Enha**
  

    /   }src;
 g.dataset.c = imImg.sr       new  
 
            };oad');
  azy-lt.remove('l.classLis   img         }
            ('error');
ist.addlassLholder.c      place
          `;          
      span> to load</ledfaian>Image         <sp   
         e"></i>nglation-triaa fa-exclam"f <i class=               ML = `
    der.innerHT  placehol           er) {
   eholdac    if (pler
        aceholdrror pl   // Show e     ) {
    n(ctioror = fung.oner  newIm 
                   };
     }
  ;
       = 'none'lay le.dispty.sceholder pla              lder) {
 (placehof   i       
              -load');
 ('lazymoveList.relass     img.c;
       ')('loadedist.addmg.classL     irc;
       .s img.datasetimg.src =           {
   function() =Img.onload       new       
  Image();
  = new newImg       constading
  loo testw image tte ne    // Crea     
    ');
   eholdermage-plac.ilector('querySeentElement.parr = img.oldest placehcon        
der(img) {eholageWithPlacadImlounction     f  */

   ghandlinnd error lder ath placehowiimage Load *
     * 
    /*}
}
    
        });      ;
      r(img)eholdegeWithPlac    loadIma          g => {
  h(imad').forEaclazy-loorAll('..querySelectdocument        erver
    ectionObsut Intersowsers withock for br Fallba       //    e {
       } elsserver;
   imageObserver =eObwindow.imag          er use
  r for lattore observe       // S             
    });
       
     erve(img);er.obs imageObserv            mg => {
   Each(iload').for('.lazy-SelectorAllcument.query        doimages
    zy load lae all // Observ      
                      });
.1
        hold: 0es  thr              `,
HRESHOLD}pxY_LOAD_TIG.IMAGE_LAZ{CONF `$argin:  rootM             
  {      },});
             
                 }          img);
  r.unobserve(mageObserve        i           
     mg);ceholder(ihPlaloadImageWit                 t;
       ge= entry.taronst img      c               
    {ng) tiersecentry.isInt   if (                 try => {
rEach(enfotries.   en            => {
 observer) es, ((entriObserverersectionew IntObserver = nt imagecons     
       dow) {n win ionObserver'('Intersecti  if 
      Loading() {Lazynitializeon iuncti f  */
      r images
holdeng and placeh lazy loadiloading witge ma i: Optimizentuireme   * Req images
  derlacehold pr antion observentersec iing withzy load laced * Enhan  /**
    }

           }
 conds
  ery 30 seevk 0); // Chec }, 3000                    }
       
);e.clear( state.cach                  
 ng cache');ted, cleariece dety usagorn('High memsole.war       con       {
      * 0.9) izeLimit jsHeapSry.Size > memoeapedJSHf (memory.us      i      emory;
    nce.mperformary = window.memo  const               l(() => {
nterva    setI
        memory) {e.performancow.ind wformance && (window.perif      le)
  if availabe (ag memory us // Monitor       
   }
       
           });       e}ms`);
 ${loadTim load time:`Pagee.log(      consol      
    art;ionStming.navigatrmance.tirfondow.petEnd - wienloadEvtiming.nce.ormaow.perf windt loadTime =    cons          ) => {
  'load', (ntListener(Eveow.add  wind
           {g)intimance.rmperfo && window.performancew.   if (windoance
     d perform page loa Monitor   //      
  turn;
     RING) re_MONITORFORMANCECONFIG.PEif (! {
        ()inganceMonitorormlizePerf initia    function
     */
oring monitations andmiz optirmancerfo PeRequirement:   * ng
   monitoriceormanze perfnitiali
     * I/**  }

     }
  }
                   (key);
he.deletetate.cac    s    {
        PIRY_TIME) CHE_EX.CAFIGCONstamp) >= melue.tiw - va    if ((no {
        e.entries())tate.cach] of s [key, value for (const();
       w = Date.now   const no
     e() {chxpiredCation cleanE
    func/     *entries
pired cache  ex     * Clean   /**
 }

 ;
    })w()
       e.noDatimestamp:            tta,
 da data:         he.set(cate.cacsta  al().v().nexte.keys_CACHE_SIZFIG.MAXche is fullf caies ildest entry removing oe b, dat    in cachet  resul

    // Public API for external access
    window.ProductDisplay = {
        loadProducts: loadProducts,
        searchProducts: searchProducts,
        filterProducts: filterProducts,
        clearFilters: handleClearFilters,
        state: state
    };
});
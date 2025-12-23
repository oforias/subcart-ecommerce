/**
 * Cart Management JavaScript
 * Handles dynamic cart interactions without page reload
 * Requirements: 5.1, 5.2, 5.3
 */

$(document).ready(function() {
    
    // Debug logging
    console.log('Cart.js loaded');
    console.log('jQuery version:', $.fn.jquery);
    
    // Wait a bit for DOM to be fully rendered, then initialize
    setTimeout(function() {
        initializeCartHandlers();
    }, 100);
    
    /**
     * Initialize all cart event handlers
     */
    function initializeCartHandlers() {
        // Debug: Check if elements exist
        console.log('Initializing cart handlers...');
        console.log('Quantity increase buttons found:', $('.quantity-increase').length);
        console.log('Quantity decrease buttons found:', $('.quantity-decrease').length);
        console.log('Quantity inputs found:', $('.quantity-input').length);
        console.log('Remove buttons found:', $('.remove-item').length);
        console.log('Empty cart button found:', $('#empty-cart-btn').length);
        
        // Test if buttons are clickable
        $('.quantity-increase').each(function(index) {
            const $btn = $(this);
            console.log(`Button ${index}: disabled=${$btn.prop('disabled')}, visible=${$btn.is(':visible')}, css-pointer-events=${$btn.css('pointer-events')}`);
        });
        
        // Use event delegation for better reliability - try both document and body
        $(document).off('click.cart');
        $('body').off('click.cart');
        
        // Attach to body instead of document for better compatibility
        $('body').on('click.cart', '.quantity-increase', handleQuantityIncrease);
        $('body').on('click.cart', '.quantity-decrease', handleQuantityDecrease);
        $('body').on('change.cart', '.quantity-input', handleQuantityChange);
        $('body').on('blur.cart', '.quantity-input', handleQuantityChange);
        $('body').on('click.cart', '.remove-item', handleRemoveItem);
        $('body').on('click.cart', '#empty-cart-btn', handleEmptyCart);
        
        // Also try direct event binding as fallback
        $('.quantity-increase').off('click.direct').on('click.direct', handleQuantityIncrease);
        $('.quantity-decrease').off('click.direct').on('click.direct', handleQuantityDecrease);
        $('.remove-item').off('click.direct').on('click.direct', handleRemoveItem);
        $('#empty-cart-btn').off('click.direct').on('click.direct', handleEmptyCart);
        
        // Prevent form submission on enter in quantity inputs
        $('.quantity-input').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                $(this).blur(); // Trigger change event
            }
        });
    }
    
    /**
     * Handle quantity increase button click
     */
    function handleQuantityIncrease(e) {
        console.log('Quantity increase clicked');
        e.preventDefault();
        const productId = $(this).data('product-id');
        console.log('Product ID:', productId);
        const quantityInput = $(`.quantity-input[data-product-id="${productId}"]`);
        const currentQuantity = parseInt(quantityInput.val()) || 1;
        const newQuantity = Math.min(currentQuantity + 1, 999);
        
        console.log('Current quantity:', currentQuantity, 'New quantity:', newQuantity);
        
        if (newQuantity !== currentQuantity) {
            quantityInput.val(newQuantity);
            updateCartQuantity(productId, newQuantity);
        }
    }
    
    /**
     * Handle quantity decrease button click
     */
    function handleQuantityDecrease(e) {
        console.log('Quantity decrease clicked');
        e.preventDefault();
        const productId = $(this).data('product-id');
        console.log('Product ID:', productId);
        const quantityInput = $(`.quantity-input[data-product-id="${productId}"]`);
        const currentQuantity = parseInt(quantityInput.val()) || 1;
        const newQuantity = Math.max(currentQuantity - 1, 1);
        
        console.log('Current quantity:', currentQuantity, 'New quantity:', newQuantity);
        
        if (newQuantity !== currentQuantity) {
            quantityInput.val(newQuantity);
            updateCartQuantity(productId, newQuantity);
        }
    }
    
    /**
     * Handle direct quantity input change
     */
    function handleQuantityChange(e) {
        const productId = $(this).data('product-id');
        let newQuantity = parseInt($(this).val()) || 1;
        
        // Validate quantity bounds
        if (newQuantity < 1) {
            newQuantity = 1;
            $(this).val(newQuantity);
        } else if (newQuantity > 999) {
            newQuantity = 999;
            $(this).val(newQuantity);
        }
        
        updateCartQuantity(productId, newQuantity);
    }
    
    /**
     * Handle remove item button click
     */
    function handleRemoveItem(e) {
        console.log('Remove item clicked');
        e.preventDefault();
        const productId = $(this).data('product-id');
        console.log('Product ID to remove:', productId);
        const productTitle = $(this).closest('.cart-item').find('h3').text().trim();
        
        // Show confirmation dialog
        if (confirm(`Are you sure you want to remove "${productTitle}" from your cart?`)) {
            console.log('User confirmed removal');
            removeFromCart(productId);
        } else {
            console.log('User cancelled removal');
        }
    }
    
    /**
     * Handle empty cart button click
     */
    function handleEmptyCart(e) {
        e.preventDefault();
        
        // Show confirmation dialog
        if (confirm('Are you sure you want to empty your entire cart? This action cannot be undone.')) {
            emptyCart();
        }
    }
    
    /**
     * Update cart item quantity via AJAX
     */
    function updateCartQuantity(productId, quantity) {
        // Show loading state
        showLoadingState(productId);
        
        $.ajax({
            url: 'actions/update_quantity_action.php',
            type: 'POST',
            dataType: 'json',
            data: {
                product_id: productId,
                quantity: quantity
            },
            success: function(response) {
                hideLoadingState(productId);
                
                if (response.status === 'success') {  // Fixed: check for 'status' not 'success'
                    // Update cart totals (this will recalculate subtotals from DOM)
                    updateCartTotals();
                    
                    // Show success message
                    showMessage('success', 'Cart updated successfully');
                    
                    // Update menu cart count if it exists
                    updateMenuCartCount();
                    
                } else {
                    // Revert quantity input to previous value
                    revertQuantityInput(productId);
                    
                    // Show error message
                    showMessage('error', response.message || 'Failed to update cart quantity');
                }
            },
            error: function(xhr, status, error) {
                hideLoadingState(productId);
                revertQuantityInput(productId);
                showMessage('error', 'Network error occurred while updating cart');
                console.error('Cart update error:', error);
            }
        });
    }
    
    /**
     * Remove item from cart via AJAX
     */
    function removeFromCart(productId) {
        // Show loading state
        showLoadingState(productId);
        
        $.ajax({
            url: 'actions/remove_from_cart_action.php',
            type: 'POST',
            dataType: 'json',
            data: {
                product_id: productId
            },
            success: function(response) {
                hideLoadingState(productId);
                
                if (response.status === 'success') {  // Fixed: check for 'status' not 'success'
                    // Remove item from DOM with animation
                    const cartItem = $(`.cart-item[data-product-id="${productId}"]`);
                    cartItem.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if cart is now empty
                        if ($('.cart-item').length === 0) {
                            // Reload page to show empty cart state
                            location.reload();
                        } else {
                            // Update cart totals
                            updateCartTotals();
                            
                            // Update menu cart count
                            updateMenuCartCount();
                        }
                    });
                    
                    // Show success message
                    showMessage('success', 'Item removed from cart');
                    
                } else {
                    showMessage('error', response.message || 'Failed to remove item from cart');
                }
            },
            error: function(xhr, status, error) {
                hideLoadingState(productId);
                showMessage('error', 'Network error occurred while removing item');
                console.error('Remove item error:', error);
            }
        });
    }
    
    /**
     * Empty entire cart via AJAX
     */
    function emptyCart() {
        // Show loading overlay
        showGlobalLoading();
        
        $.ajax({
            url: 'actions/empty_cart_action.php',
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                hideGlobalLoading();
                
                if (response.status === 'success') {  // Fixed: check for 'status' not 'success'
                    // Reload page to show empty cart state
                    location.reload();
                } else {
                    showMessage('error', response.message || 'Failed to empty cart');
                }
            },
            error: function(xhr, status, error) {
                hideGlobalLoading();
                showMessage('error', 'Network error occurred while emptying cart');
                console.error('Empty cart error:', error);
            }
        });
    }
    
    /**
     * Update item subtotal display
     */
    function updateItemSubtotal(productId, productPrice, quantity) {
        const subtotal = productPrice * quantity;
        const subtotalElement = $(`.cart-item[data-product-id="${productId}"] .item-subtotal`);
        subtotalElement.text('$' + subtotal.toFixed(2));
        
        // Add subtle animation to highlight the change
        subtotalElement.addClass('updated');
        setTimeout(() => {
            subtotalElement.removeClass('updated');
        }, 1000);
    }
    
    /**
     * Update cart totals by recalculating from all items
     */
    function updateCartTotals() {
        let totalItems = 0;
        let totalAmount = 0;
        
        $('.cart-item').each(function() {
            const quantity = parseInt($(this).find('.quantity-input').val()) || 0;
            const subtotalText = $(this).find('.item-subtotal').text().replace('$', '');
            const subtotal = parseFloat(subtotalText) || 0;
            
            totalItems += quantity;
            totalAmount += subtotal;
        });
        
        // Update display elements
        $('#cart-item-count').text(totalItems);
        $('#cart-subtotal').text('$' + totalAmount.toFixed(2));
        $('#cart-total').text('$' + totalAmount.toFixed(2));
        
        // Add animation to highlight changes
        $('#cart-item-count, #cart-subtotal, #cart-total').addClass('updated');
        setTimeout(() => {
            $('#cart-item-count, #cart-subtotal, #cart-total').removeClass('updated');
        }, 1000);
    }
    
    /**
     * Update menu cart count (if menu exists on page)
     */
    function updateMenuCartCount() {
        const totalItems = parseInt($('#cart-item-count').text()) || 0;
        const menuCartLink = $('.menu-tray a[href*="cart.php"]');
        
        if (menuCartLink.length > 0) {
            // Update cart count in menu
            const currentText = menuCartLink.text();
            const newText = currentText.replace(/\(\d+\)/, `(${totalItems})`);
            menuCartLink.text(newText);
        }
    }
    
    /**
     * Show loading state for specific item
     */
    function showLoadingState(productId) {
        const cartItem = $(`.cart-item[data-product-id="${productId}"]`);
        cartItem.addClass('loading');
        // Don't disable buttons completely, just add loading class
        cartItem.find('button, input').addClass('loading-state');
        
        // Add loading spinner to quantity controls
        const quantityControls = cartItem.find('.quantity-input').parent();
        if (quantityControls.find('.loading-spinner').length === 0) {
            quantityControls.append('<i class="fas fa-spinner fa-spin loading-spinner" style="margin-left: 8px; color: var(--color-medium-gray);"></i>');
        }
    }
    
    /**
     * Hide loading state for specific item
     */
    function hideLoadingState(productId) {
        const cartItem = $(`.cart-item[data-product-id="${productId}"]`);
        cartItem.removeClass('loading');
        cartItem.find('button, input').removeClass('loading-state');
        cartItem.find('.loading-spinner').remove();
    }
    
    /**
     * Show global loading overlay
     */
    function showGlobalLoading() {
        if ($('#global-loading').length === 0) {
            $('body').append(`
                <div id="global-loading" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(255, 255, 255, 0.8);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 9999;
                    backdrop-filter: blur(2px);
                ">
                    <div style="text-align: center; color: var(--color-primary-green);">
                        <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 16px;"></i>
                        <p style="margin: 0; font-weight: 500;">Updating cart...</p>
                    </div>
                </div>
            `);
        }
    }
    
    /**
     * Hide global loading overlay
     */
    function hideGlobalLoading() {
        $('#global-loading').remove();
    }
    
    /**
     * Revert quantity input to previous value (stored in data attribute)
     */
    function revertQuantityInput(productId) {
        const quantityInput = $(`.quantity-input[data-product-id="${productId}"]`);
        const previousValue = quantityInput.data('previous-value') || 1;
        quantityInput.val(previousValue);
    }
    
    /**
     * Store current quantity as previous value before changes
     */
    $('.quantity-input').on('focus', function() {
        $(this).data('previous-value', $(this).val());
    });
    
    /**
     * Show success or error message
     */
    function showMessage(type, message) {
        const messageContainer = $('#cart-message');
        const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        const messageClass = type === 'success' ? 'success' : 'error';
        
        messageContainer.removeClass('success error')
                       .addClass(`validation-message ${messageClass}`)
                       .html(`<i class="fas ${iconClass}"></i> ${message}`)
                       .show();
        
        // Auto-hide success messages after 3 seconds
        if (type === 'success') {
            setTimeout(() => {
                messageContainer.fadeOut();
            }, 3000);
        }
        
        // Scroll to message if it's not visible
        if (messageContainer.offset().top < $(window).scrollTop()) {
            $('html, body').animate({
                scrollTop: messageContainer.offset().top - 20
            }, 300);
        }
    }
    
    /**
     * Add CSS for loading and update animations
     */
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .cart-item.loading {
                opacity: 0.7;
            }
            
            /* Ensure cart buttons are always clickable unless explicitly disabled */
            .cart-item .quantity-increase,
            .cart-item .quantity-decrease,
            .cart-item .remove-item {
                pointer-events: auto !important;
                cursor: pointer !important;
                position: relative !important;
                z-index: 10 !important;
            }
            
            .cart-item .quantity-increase:disabled,
            .cart-item .quantity-decrease:disabled,
            .cart-item .remove-item:disabled {
                pointer-events: none !important;
                cursor: not-allowed !important;
            }
            
            .updated {
                background-color: var(--color-light-green) !important;
                transition: background-color 0.3s ease;
            }
            
            .cart-item {
                transition: opacity 0.3s ease;
            }
            
            .quantity-input:focus {
                border-color: var(--color-primary-green);
                box-shadow: 0 0 0 3px rgba(91, 140, 90, 0.15);
            }
            
            .remove-item:hover {
                background-color: var(--color-error) !important;
                color: var(--color-white) !important;
                border-color: var(--color-error) !important;
            }
            
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
            
            .updated {
                animation: pulse 0.5s ease-in-out;
            }
        `)
        .appendTo('head');
});

/**
 * Global function to add item to cart (can be called from product pages)
 */
function addToCart(productId, quantity = 1) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: 'actions/add_to_cart_action.php',
            type: 'POST',
            dataType: 'json',
            data: {
                product_id: productId,
                quantity: quantity
            },
            success: function(response) {
                console.log('addToCart AJAX success:', response);
                
                if (response.status === 'success') {
                    // Show success message
                    try {
                        showCartMessage('success', 'Item added to cart successfully');
                    } catch (e) {
                        console.warn('showCartMessage error:', e);
                    }
                    
                    // Update cart count in menu if present (with error handling)
                    try {
                        updateMenuCartCount();
                    } catch (e) {
                        console.warn('updateMenuCartCount error:', e);
                    }
                    
                    resolve(response);
                } else {
                    console.log('addToCart response error:', response);
                    try {
                        showCartMessage('error', response.message || 'Failed to add item to cart');
                    } catch (e) {
                        console.warn('showCartMessage error:', e);
                    }
                    reject(response);
                }
            },
            error: function(xhr, status, error) {
                console.error('addToCart AJAX error:', xhr, status, error);
                console.log('Response text:', xhr.responseText);
                
                try {
                    showCartMessage('error', 'Network error occurred while adding to cart');
                } catch (e) {
                    console.warn('showCartMessage error:', e);
                }
                reject({ 
                    error: error, 
                    status: status, 
                    responseText: xhr.responseText 
                });
            }
        });
    });
}

/**
 * Show cart message (can be used from other pages)
 */
function showCartMessage(type, message) {
    // Try to find existing message container
    let messageContainer = $('#cart-message');
    
    // If no container exists, create a temporary one
    if (messageContainer.length === 0) {
        messageContainer = $(`
            <div id="cart-message" style="
                position: fixed;
                top: 20px;
                right: 20px;
                max-width: 400px;
                z-index: 9999;
                display: none;
            "></div>
        `);
        $('body').append(messageContainer);
    }
    
    const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    const messageClass = type === 'success' ? 'success' : 'error';
    
    messageContainer.removeClass('success error')
                   .addClass(`validation-message ${messageClass}`)
                   .html(`<i class="fas ${iconClass}"></i> ${message}`)
                   .show();
    
    // Auto-hide after 4 seconds
    setTimeout(() => {
        messageContainer.fadeOut();
    }, 4000);
}
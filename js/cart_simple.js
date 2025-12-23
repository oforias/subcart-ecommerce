/**
 * Simplified Cart Management JavaScript
 * Focus on getting buttons to work
 */

console.log('Simple cart.js loaded');

$(document).ready(function() {
    console.log('DOM ready, initializing cart...');
    
    // Wait for everything to load
    setTimeout(function() {
        console.log('Starting cart initialization...');
        
        // Check what we have
        console.log('Quantity + buttons:', $('.quantity-increase').length);
        console.log('Quantity - buttons:', $('.quantity-decrease').length);
        console.log('Remove buttons:', $('.remove-item').length);
        console.log('Empty cart button:', $('#empty-cart-btn').length);
        
        // Test each button individually
        $('.quantity-increase').each(function(i) {
            console.log(`Button ${i} product ID:`, $(this).data('product-id'));
        });
        
        // Simple direct event binding
        $('.quantity-increase').off().on('click', function(e) {
            e.preventDefault();
            console.log('INCREASE CLICKED!');
            const productId = $(this).data('product-id');
            console.log('Product ID:', productId);
            
            const input = $(`.quantity-input[data-product-id="${productId}"]`);
            const currentQty = parseInt(input.val()) || 1;
            const newQty = currentQty + 1;
            
            console.log(`Changing quantity from ${currentQty} to ${newQty}`);
            input.val(newQty);
            
            // Call update function
            updateCartQuantitySimple(productId, newQty);
        });
        
        $('.quantity-decrease').off().on('click', function(e) {
            e.preventDefault();
            console.log('DECREASE CLICKED!');
            const productId = $(this).data('product-id');
            console.log('Product ID:', productId);
            
            const input = $(`.quantity-input[data-product-id="${productId}"]`);
            const currentQty = parseInt(input.val()) || 1;
            const newQty = Math.max(currentQty - 1, 1);
            
            console.log(`Changing quantity from ${currentQty} to ${newQty}`);
            input.val(newQty);
            
            // Call update function
            updateCartQuantitySimple(productId, newQty);
        });
        
        $('.remove-item').off().on('click', function(e) {
            e.preventDefault();
            console.log('REMOVE CLICKED!');
            const productId = $(this).data('product-id');
            console.log('Product ID:', productId);
            
            if (confirm('Remove this item from cart?')) {
                removeFromCartSimple(productId);
            }
        });
        
        $('#empty-cart-btn').off().on('click', function(e) {
            e.preventDefault();
            console.log('EMPTY CART CLICKED!');
            
            if (confirm('Empty entire cart?')) {
                emptyCartSimple();
            }
        });
        
        console.log('Cart initialization complete');
        
    }, 200);
});

function updateCartQuantitySimple(productId, quantity) {
    console.log(`Updating cart: Product ${productId} to quantity ${quantity}`);
    
    $.ajax({
        url: 'actions/update_quantity_action.php',
        type: 'POST',
        dataType: 'json',
        data: {
            product_id: productId,
            quantity: quantity
        },
        success: function(response) {
            console.log('Update response:', response);
            if (response.status === 'success') {
                console.log('✅ Quantity updated successfully');
                // Update subtotal
                updateSubtotalSimple(productId);
            } else {
                console.log('❌ Update failed:', response.message);
                alert('Failed to update quantity: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.log('❌ AJAX error:', error);
            console.log('Response text:', xhr.responseText);
            alert('Network error occurred');
        }
    });
}

function removeFromCartSimple(productId) {
    console.log(`Removing product ${productId} from cart`);
    
    $.ajax({
        url: 'actions/remove_from_cart_action.php',
        type: 'POST',
        dataType: 'json',
        data: {
            product_id: productId
        },
        success: function(response) {
            console.log('Remove response:', response);
            if (response.status === 'success') {
                console.log('✅ Item removed successfully');
                // Remove the item from DOM
                $(`.cart-item[data-product-id="${productId}"]`).fadeOut(300, function() {
                    $(this).remove();
                    // Check if cart is empty
                    if ($('.cart-item').length === 0) {
                        location.reload();
                    }
                });
            } else {
                console.log('❌ Remove failed:', response.message);
                alert('Failed to remove item: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.log('❌ AJAX error:', error);
            alert('Network error occurred');
        }
    });
}

function emptyCartSimple() {
    console.log('Emptying entire cart');
    
    $.ajax({
        url: 'actions/empty_cart_action.php',
        type: 'POST',
        dataType: 'json',
        success: function(response) {
            console.log('Empty response:', response);
            if (response.status === 'success') {
                console.log('✅ Cart emptied successfully');
                location.reload();
            } else {
                console.log('❌ Empty failed:', response.message);
                alert('Failed to empty cart: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.log('❌ AJAX error:', error);
            alert('Network error occurred');
        }
    });
}

function updateSubtotalSimple(productId) {
    // Simple subtotal update - just recalculate from DOM
    const cartItem = $(`.cart-item[data-product-id="${productId}"]`);
    const quantity = parseInt(cartItem.find('.quantity-input').val()) || 1;
    const priceText = cartItem.find('p:contains("$")').first().text();
    const price = parseFloat(priceText.replace('$', '').replace(',', '')) || 0;
    const subtotal = price * quantity;
    
    cartItem.find('.item-subtotal').text('$' + subtotal.toFixed(2));
    
    // Update cart totals
    let totalItems = 0;
    let totalAmount = 0;
    
    $('.cart-item').each(function() {
        const qty = parseInt($(this).find('.quantity-input').val()) || 0;
        const subtotalText = $(this).find('.item-subtotal').text().replace('$', '').replace(',', '');
        const subtotal = parseFloat(subtotalText) || 0;
        
        totalItems += qty;
        totalAmount += subtotal;
    });
    
    $('#cart-item-count').text(totalItems);
    $('#cart-subtotal').text('$' + totalAmount.toFixed(2));
    $('#cart-total').text('$' + totalAmount.toFixed(2));
}
/**
 * Checkout JavaScript
 * Handles payment simulation and checkout workflow
 * Requirements: 7.1, 7.2, 7.4
 */

$(document).ready(function() {
    // Initialize checkout functionality
    initializeCheckout();
});

/**
 * Initialize checkout page functionality
 */
function initializeCheckout() {
    // Bind event handlers
    bindCheckoutEvents();
    
    // Initialize payment modal
    initializePaymentModal();
    
    // Initialize confirmation modal
    initializeConfirmationModal();
    
    console.log('Checkout functionality initialized');
}

/**
 * Bind event handlers for checkout interactions
 */
function bindCheckoutEvents() {
    // Place Order button click
    $(document).on('click', '#place-order-btn', function(e) {
        e.preventDefault();
        
        const customerId = $(this).data('customer-id');
        const total = $(this).data('total');
        
        if (!customerId || !total) {
            showCheckoutMessage('error', 'Invalid order information. Please refresh the page.');
            return;
        }
        
        // Show payment modal
        showPaymentModal(customerId, total);
    });
    
    // Payment confirmation button
    $(document).on('click', '#confirm-payment-btn', function(e) {
        e.preventDefault();
        processPayment();
    });
    
    // Payment cancellation button
    $(document).on('click', '#cancel-payment-btn', function(e) {
        e.preventDefault();
        cancelPayment();
    });
    
    // Modal close buttons
    $(document).on('click', '.modal-close, .modal-overlay', function(e) {
        if (e.target === this) {
            closeAllModals();
        }
    });
    
    // Escape key to close modals
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
    
    // Continue shopping after order confirmation
    $(document).on('click', '#continue-shopping-btn', function(e) {
        e.preventDefault();
        window.location.href = 'all_product.php';
    });
    
    // View order details after confirmation
    $(document).on('click', '#view-order-btn', function(e) {
        e.preventDefault();
        // In a real application, this would redirect to order details page
        showCheckoutMessage('info', 'Order details page would be available in a full implementation.');
    });
}

/**
 * Initialize payment modal structure
 */
function initializePaymentModal() {
    const modalHtml = `
        <div class="modal-overlay">
            <div class="modal-content" style="max-width: 500px;">
                <div class="modal-header">
                    <h3 style="color: var(--color-primary-green); margin: 0;">
                        <i class="fas fa-credit-card"></i> Payment Simulation
                    </h3>
                    <button class="modal-close" style="background: none; border: none; font-size: 1.5rem; color: var(--color-medium-gray); cursor: pointer;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="modal-body" style="padding: var(--spacing-lg);">
                    <div style="background-color: var(--color-light-gray); padding: var(--spacing-md); border-radius: var(--border-radius-md); margin-bottom: var(--spacing-lg);">
                        <p style="color: var(--color-medium-gray); font-size: var(--font-size-small); text-align: center; margin: 0;">
                            <i class="fas fa-info-circle"></i> This is a simulated payment process for demonstration purposes
                        </p>
                    </div>
                    
                    <div id="payment-details">
                        <!-- Payment details will be populated dynamically -->
                    </div>
                    
                    <div style="margin-bottom: var(--spacing-lg);">
                        <h4 style="color: var(--color-dark-gray); margin-bottom: var(--spacing-md);">Simulated Payment Options:</h4>
                        
                        <div style="display: flex; flex-direction: column; gap: var(--spacing-sm);">
                            <label style="display: flex; align-items: center; padding: var(--spacing-md); border: 2px solid var(--color-border-gray); border-radius: var(--border-radius-md); cursor: pointer;">
                                <input type="radio" name="payment-method" value="success" checked style="margin-right: var(--spacing-sm);">
                                <div>
                                    <strong style="color: var(--color-success);">
                                        <i class="fas fa-check-circle"></i> Successful Payment
                                    </strong>
                                    <p style="color: var(--color-medium-gray); font-size: var(--font-size-small); margin: 0;">
                                        Simulate a successful payment transaction
                                    </p>
                                </div>
                            </label>
                            
                            <label style="display: flex; align-items: center; padding: var(--spacing-md); border: 2px solid var(--color-border-gray); border-radius: var(--border-radius-md); cursor: pointer;">
                                <input type="radio" name="payment-method" value="failure" style="margin-right: var(--spacing-sm);">
                                <div>
                                    <strong style="color: var(--color-error);">
                                        <i class="fas fa-times-circle"></i> Failed Payment
                                    </strong>
                                    <p style="color: var(--color-medium-gray); font-size: var(--font-size-small); margin: 0;">
                                        Simulate a payment failure (card declined, insufficient funds, etc.)
                                    </p>
                                </div>
                            </label>
                            
                            <label style="display: flex; align-items: center; padding: var(--spacing-md); border: 2px solid var(--color-border-gray); border-radius: var(--border-radius-md); cursor: pointer;">
                                <input type="radio" name="payment-method" value="timeout" style="margin-right: var(--spacing-sm);">
                                <div>
                                    <strong style="color: var(--color-warning);">
                                        <i class="fas fa-clock"></i> Payment Timeout
                                    </strong>
                                    <p style="color: var(--color-medium-gray); font-size: var(--font-size-small); margin: 0;">
                                        Simulate a payment processing timeout scenario
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer" style="padding: var(--spacing-lg); border-top: 1px solid var(--color-border-gray); display: flex; gap: var(--spacing-md); justify-content: flex-end;">
                    <button class="btn btn-secondary" id="cancel-payment-btn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button class="btn btn-primary" id="confirm-payment-btn">
                        <i class="fas fa-lock"></i> Process Payment
                    </button>
                </div>
            </div>
        </div>
    `;
    
    $('#payment-modal').html(modalHtml);
}

/**
 * Initialize order confirmation modal structure
 */
function initializeConfirmationModal() {
    const modalHtml = `
        <div class="modal-overlay">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h3 style="color: var(--color-success); margin: 0;">
                        <i class="fas fa-check-circle"></i> Order Confirmed
                    </h3>
                    <button class="modal-close" style="background: none; border: none; font-size: 1.5rem; color: var(--color-medium-gray); cursor: pointer;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="modal-body" style="padding: var(--spacing-lg);">
                    <div id="confirmation-details">
                        <!-- Confirmation details will be populated dynamically -->
                    </div>
                </div>
                
                <div class="modal-footer" style="padding: var(--spacing-lg); border-top: 1px solid var(--color-border-gray); display: flex; gap: var(--spacing-md); justify-content: center;">
                    <button class="btn btn-primary" id="continue-shopping-btn">
                        <i class="fas fa-shopping-cart"></i> Continue Shopping
                    </button>
                    <button class="btn btn-secondary" id="view-order-btn">
                        <i class="fas fa-receipt"></i> View Order Details
                    </button>
                </div>
            </div>
        </div>
    `;
    
    $('#confirmation-modal').html(modalHtml);
}

/**
 * Show payment modal with order details
 * Requirements: 7.1
 */
function showPaymentModal(customerId, total) {
    // Populate payment details
    const paymentDetailsHtml = `
        <div style="background-color: var(--color-light-green); padding: var(--spacing-lg); border-radius: var(--border-radius-md); margin-bottom: var(--spacing-lg);">
            <h4 style="color: var(--color-primary-green); margin-bottom: var(--spacing-md);">Order Summary</h4>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="color: var(--color-dark-gray); font-weight: var(--font-weight-medium);">Total Amount:</span>
                <span style="color: var(--color-primary-green); font-size: var(--font-size-h3); font-weight: var(--font-weight-semibold);">
                    $${parseFloat(total).toFixed(2)}
                </span>
            </div>
        </div>
    `;
    
    $('#payment-details').html(paymentDetailsHtml);
    
    // Store order data for processing
    $('#payment-modal').data('customer-id', customerId);
    $('#payment-modal').data('total', total);
    
    // Show modal
    $('#payment-modal').fadeIn(300);
    $('body').addClass('modal-open');
    
    console.log('Payment modal shown for customer:', customerId, 'total:', total);
}

/**
 * Process payment simulation
 * Requirements: 7.2
 */
function processPayment() {
    const customerId = $('#payment-modal').data('customer-id');
    const total = $('#payment-modal').data('total');
    const paymentMethod = $('input[name="payment-method"]:checked').val();
    
    if (!customerId || !total || !paymentMethod) {
        showCheckoutMessage('error', 'Invalid payment information. Please try again.');
        return;
    }
    
    // Disable payment button and show processing state
    const $confirmBtn = $('#confirm-payment-btn');
    const originalText = $confirmBtn.html();
    $confirmBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
    
    // Disable cancel button during processing
    const $cancelBtn = $('#cancel-payment-btn');
    $cancelBtn.prop('disabled', true);
    
    console.log('Processing payment:', { customerId, total, paymentMethod });
    
    // Determine processing time based on payment method
    let processingTime = 2000; // Default 2 seconds
    if (paymentMethod === 'timeout') {
        processingTime = 8000; // 8 seconds for timeout simulation
    } else if (paymentMethod === 'failure') {
        processingTime = 3000; // 3 seconds for failure simulation
    }
    
    // Show processing feedback
    if (paymentMethod === 'timeout') {
        setTimeout(() => {
            if ($confirmBtn.prop('disabled')) { // Still processing
                $confirmBtn.html('<i class="fas fa-spinner fa-spin"></i> Processing (this may take a moment)...');
            }
        }, 4000);
    }
    
    // Simulate payment processing delay
    setTimeout(() => {
        // Re-enable buttons
        $confirmBtn.prop('disabled', false).html(originalText);
        $cancelBtn.prop('disabled', false);
        
        // Process based on payment method
        if (paymentMethod === 'success') {
            // Process successful payment
            processSuccessfulPayment(customerId, total);
        } else if (paymentMethod === 'timeout') {
            // Process timeout scenario
            processPaymentTimeout();
        } else {
            // Process failed payment
            processFailedPayment(paymentMethod);
        }
    }, processingTime);
}

/**
 * Process successful payment and create order
 * Requirements: 7.2, 7.3
 */
function processSuccessfulPayment(customerId, total) {
    // Make AJAX call to process checkout
    $.ajax({
        url: 'actions/process_checkout_action.php',
        type: 'POST',
        dataType: 'json',
        data: {
            customer_id: customerId,
            total_amount: total,
            payment_method: 'simulated_success'
        },
        success: function(response) {
            console.log('Checkout response:', response);
            
            if (response.success) {
                // Close payment modal
                closeAllModals();
                
                // Show order confirmation
                showOrderConfirmation(response.data);
                
                // Show success message
                showCheckoutMessage('success', 'Order placed successfully! Your cart has been cleared.');
                
            } else {
                // Handle checkout failure
                closeAllModals();
                showCheckoutMessage('error', response.error || 'Order processing failed. Please try again.');
            }
        },
        error: function(xhr, status, error) {
            console.error('Checkout AJAX error:', { xhr, status, error });
            closeAllModals();
            showCheckoutMessage('error', 'Network error occurred. Please check your connection and try again.');
        }
    });
}

/**
 * Process failed payment simulation
 * Requirements: 7.5
 */
function processFailedPayment(paymentMethod = 'failure') {
    // Make AJAX call to simulate payment failure
    $.ajax({
        url: 'actions/process_checkout_action.php',
        type: 'POST',
        dataType: 'json',
        data: {
            customer_id: $('#payment-modal').data('customer-id'),
            total_amount: $('#payment-modal').data('total'),
            payment_method: 'simulated_failure'
        },
        success: function(response) {
            console.log('Payment failure response:', response);
            
            // Close payment modal
            closeAllModals();
            
            // Show failure message with specific error if available
            const errorMessage = response.error || 'Payment failed. Your cart has been preserved. Please try a different payment method.';
            showCheckoutMessage('error', errorMessage);
            
            console.log('Payment simulation failed - cart preserved');
        },
        error: function(xhr, status, error) {
            console.error('Payment failure AJAX error:', { xhr, status, error });
            closeAllModals();
            showCheckoutMessage('error', 'Payment processing failed due to network error. Your cart has been preserved.');
        }
    });
}

/**
 * Process payment timeout simulation
 * Requirements: 7.5
 */
function processPaymentTimeout() {
    // Close payment modal
    closeAllModals();
    
    // Show timeout message
    showCheckoutMessage('warning', 'Payment processing timed out. Your cart has been preserved. Please try again or contact support if the issue persists.');
    
    console.log('Payment simulation timed out - cart preserved');
}

/**
 * Cancel payment and return to checkout
 * Requirements: 7.4
 */
function cancelPayment() {
    // Close payment modal
    closeAllModals();
    
    // Show cancellation message
    showCheckoutMessage('info', 'Payment cancelled. You can continue shopping or try checkout again.');
    
    console.log('Payment cancelled by user');
}

/**
 * Show order confirmation modal
 * Requirements: 3.5
 */
function showOrderConfirmation(orderData) {
    const confirmationHtml = `
        <div style="text-align: center; margin-bottom: var(--spacing-lg);">
            <div style="background-color: var(--color-success); color: white; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto var(--spacing-lg);">
                <i class="fas fa-check" style="font-size: 2rem;"></i>
            </div>
            <h4 style="color: var(--color-success); margin-bottom: var(--spacing-md);">
                Thank you for your order!
            </h4>
            <p style="color: var(--color-medium-gray); margin-bottom: var(--spacing-lg);">
                Your order has been successfully placed and is being processed.
            </p>
        </div>
        
        <div style="background-color: var(--color-light-gray); padding: var(--spacing-lg); border-radius: var(--border-radius-md); margin-bottom: var(--spacing-lg);">
            <h5 style="color: var(--color-primary-green); margin-bottom: var(--spacing-md);">Order Details</h5>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md); margin-bottom: var(--spacing-md);">
                <div>
                    <strong style="color: var(--color-dark-gray);">Order ID:</strong>
                    <p style="color: var(--color-medium-gray); margin: 0;">#${orderData.order_id || 'N/A'}</p>
                </div>
                <div>
                    <strong style="color: var(--color-dark-gray);">Invoice Number:</strong>
                    <p style="color: var(--color-medium-gray); margin: 0;">${orderData.invoice_no || 'N/A'}</p>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md); margin-bottom: var(--spacing-md);">
                <div>
                    <strong style="color: var(--color-dark-gray);">Order Date:</strong>
                    <p style="color: var(--color-medium-gray); margin: 0;">${orderData.order_date || new Date().toLocaleDateString()}</p>
                </div>
                <div>
                    <strong style="color: var(--color-dark-gray);">Total Amount:</strong>
                    <p style="color: var(--color-primary-green); font-weight: var(--font-weight-semibold); margin: 0; font-size: var(--font-size-body);">
                        $${parseFloat(orderData.total_amount || 0).toFixed(2)} ${orderData.currency || 'USD'}
                    </p>
                </div>
            </div>
            
            <div>
                <strong style="color: var(--color-dark-gray);">Items:</strong>
                <p style="color: var(--color-medium-gray); margin: 0;">${orderData.items_count || 0} items ordered</p>
            </div>
        </div>
        
        <div style="background-color: var(--color-light-green); padding: var(--spacing-md); border-radius: var(--border-radius-md); text-align: center;">
            <p style="color: var(--color-primary-green); font-size: var(--font-size-small); margin: 0;">
                <i class="fas fa-envelope"></i> A confirmation email will be sent to your registered email address
            </p>
        </div>
    `;
    
    $('#confirmation-details').html(confirmationHtml);
    $('#confirmation-modal').fadeIn(300);
    $('body').addClass('modal-open');
    
    console.log('Order confirmation shown:', orderData);
}

/**
 * Close all modals
 */
function closeAllModals() {
    $('#payment-modal, #confirmation-modal').fadeOut(300);
    $('body').removeClass('modal-open');
    
    console.log('All modals closed');
}

/**
 * Show checkout messages
 */
function showCheckoutMessage(type, message) {
    const iconMap = {
        'success': 'fas fa-check-circle',
        'error': 'fas fa-exclamation-circle',
        'info': 'fas fa-info-circle',
        'warning': 'fas fa-exclamation-triangle'
    };
    
    const icon = iconMap[type] || iconMap['info'];
    
    const messageHtml = `
        <div class="validation-message ${type}" style="margin-bottom: var(--spacing-lg);">
            <i class="${icon}"></i> ${message}
        </div>
    `;
    
    const $messageContainer = $('#checkout-message');
    $messageContainer.html(messageHtml).fadeIn(300);
    
    // Auto-hide success and info messages after 5 seconds
    if (type === 'success' || type === 'info') {
        setTimeout(() => {
            $messageContainer.fadeOut(300);
        }, 5000);
    }
    
    // Scroll to message
    $('html, body').animate({
        scrollTop: $messageContainer.offset().top - 100
    }, 300);
    
    console.log('Checkout message shown:', { type, message });
}

/**
 * Update checkout totals (for future dynamic updates)
 */
function updateCheckoutTotals(subtotal, tax, shipping, total) {
    $('#checkout-subtotal').text(`$${parseFloat(subtotal).toFixed(2)}`);
    $('#checkout-tax').text(`$${parseFloat(tax).toFixed(2)}`);
    $('#checkout-shipping').text(`$${parseFloat(shipping).toFixed(2)}`);
    $('#checkout-total').text(`$${parseFloat(total).toFixed(2)}`);
    
    // Update place order button
    $('#place-order-btn').data('total', total).html(`
        <i class="fas fa-lock"></i> Place Order - $${parseFloat(total).toFixed(2)}
    `);
    
    console.log('Checkout totals updated:', { subtotal, tax, shipping, total });
}

/**
 * Validate checkout form (for future enhancements)
 */
function validateCheckoutForm() {
    // This function can be expanded to validate customer information
    // shipping addresses, etc. in a full implementation
    return true;
}

/**
 * Handle checkout errors gracefully
 */
function handleCheckoutError(error, context = '') {
    console.error('Checkout error:', error, 'Context:', context);
    
    let errorMessage = 'An unexpected error occurred. Please try again.';
    
    if (typeof error === 'string') {
        errorMessage = error;
    } else if (error && error.message) {
        errorMessage = error.message;
    }
    
    showCheckoutMessage('error', errorMessage);
}

// Add CSS for modal functionality
$(document).ready(function() {
    // Add modal styles to head if not already present
    if (!$('#checkout-modal-styles').length) {
        $('<style id="checkout-modal-styles">')
            .text(`
                .modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, 0.5);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 1000;
                }
                
                .modal-content {
                    background: white;
                    border-radius: var(--border-radius-lg);
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                    max-height: 90vh;
                    overflow-y: auto;
                    width: 90%;
                    max-width: 500px;
                }
                
                .modal-header {
                    padding: var(--spacing-lg);
                    border-bottom: 1px solid var(--color-border-gray);
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .modal-close:hover {
                    color: var(--color-dark-gray);
                }
                
                body.modal-open {
                    overflow: hidden;
                }
                
                input[type="radio"]:checked + div {
                    color: var(--color-primary-green);
                }
                
                label:has(input[type="radio"]:checked) {
                    border-color: var(--color-primary-green) !important;
                    background-color: var(--color-light-green);
                }
                
                .validation-message.warning {
                    background-color: #fff3cd;
                    border-color: #ffeaa7;
                    color: #856404;
                }
                
                .validation-message.warning i {
                    color: #f39c12;
                }
            `)
            .appendTo('head');
    }
});
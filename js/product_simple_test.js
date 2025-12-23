/**
 * Simplified Product JavaScript for Testing
 * Minimal version to test if AJAX calls work
 */

$(document).ready(function() {
    console.log('Product Simple Test - Starting...');
    
    // Test AJAX call
    loadProductsTest();
    
    function loadProductsTest() {
        console.log('Making AJAX call to fetch products...');
        
        $.ajax({
            url: '../actions/fetch_product_action.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('Product AJAX Success:', response);
                
                if (response.status === 'success') {
                    if (response.data && response.data.products && response.data.products.length > 0) {
                        console.log('✅ SUCCESS: Found ' + response.data.products.length + ' products');
                        displayProductsTest(response.data.products);
                    } else {
                        console.log('⚠️ SUCCESS but no products found');
                        $('#products-list').html('<p>No products found</p>');
                    }
                } else {
                    console.log('❌ ERROR:', response.message);
                    $('#products-list').html('<p>Error: ' + response.message + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                $('#products-list').html('<p>AJAX Error: ' + error + '</p>');
            }
        });
    }
    
    function displayProductsTest(products) {
        let html = '<h4>Products Found (' + products.length + '):</h4><ul>';
        products.forEach(function(product) {
            html += '<li>' + product.product_title + ' - $' + product.product_price + ' (Brand: ' + product.brand_name + ')</li>';
        });
        html += '</ul>';
        $('#products-list').html(html);
    }
});
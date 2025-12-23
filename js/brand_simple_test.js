/**
 * Simplified Brand JavaScript for Testing
 * Minimal version to test if AJAX calls work
 */

$(document).ready(function() {
    console.log('Brand Simple Test - Starting...');
    
    // Test AJAX call
    loadBrandsTest();
    
    function loadBrandsTest() {
        console.log('Making AJAX call to fetch brands...');
        
        $.ajax({
            url: '../actions/fetch_brand_action.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('Brand AJAX Success:', response);
                
                if (response.status === 'success') {
                    if (response.data && response.data.brands && response.data.brands.length > 0) {
                        console.log('✅ SUCCESS: Found ' + response.data.brands.length + ' brands');
                        displayBrandsTest(response.data.brands);
                    } else {
                        console.log('⚠️ SUCCESS but no brands found');
                        $('#brands-list').html('<p>No brands found</p>');
                    }
                } else {
                    console.log('❌ ERROR:', response.message);
                    $('#brands-list').html('<p>Error: ' + response.message + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                $('#brands-list').html('<p>AJAX Error: ' + error + '</p>');
            }
        });
    }
    
    function displayBrandsTest(brands) {
        let html = '<h4>Brands Found (' + brands.length + '):</h4><ul>';
        brands.forEach(function(brand) {
            html += '<li>' + brand.brand_name + ' (Category: ' + brand.cat_name + ')</li>';
        });
        html += '</ul>';
        $('#brands-list').html(html);
    }
});
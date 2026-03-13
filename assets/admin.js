/**
 * Admin JavaScript for Auto Product Import
 * 
 * Handles single product import form
 * 
 * @version 2.2.2
 */

jQuery(document).ready(function($) {
    
    // Single Product Import Form Handler
    $('#apm-import-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $('#apm-import-submit');
        var $spinner = $form.find('.spinner');
        var $message = $('#apm-import-message');
        var $result = $('#apm-import-result');
        var productUrl = $('#apm-product-url').val();
        
        // Disable submit button and show spinner
        $submitBtn.prop('disabled', true);
        $spinner.addClass('is-active');
        $message.hide();
        $result.hide();
        
        // Make AJAX request
        $.ajax({
            url: autoProductImportAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'import_product_from_url',
                nonce: autoProductImportAdmin.nonce,
                url: productUrl
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $message.removeClass('notice-error').addClass('notice-success');
                    $message.html('<p>' + response.data.message + '</p>').show();
                    
                    // Show result with links
                    var resultHtml = '<p><strong>Product ID:</strong> ' + response.data.product_id + '</p>';
                    resultHtml += '<p><a href="' + response.data.edit_link + '" class="button" target="_blank">Edit Product</a> ';
                    resultHtml += '<a href="' + response.data.view_link + '" class="button" target="_blank">View Product</a></p>';
                    
                    $('#apm-import-result-content').html(resultHtml);
                    $result.show();
                    
                    // Clear the URL field
                    $('#apm-product-url').val('');
                } else {
                    // Show error message
                    $message.removeClass('notice-success').addClass('notice-error');
                    $message.html('<p>' + response.data.message + '</p>').show();
                }
            },
            error: function(xhr, status, error) {
                $message.removeClass('notice-success').addClass('notice-error');
                $message.html('<p>An error occurred during import. Please try again.</p>').show();
            },
            complete: function() {
                // Re-enable submit button and hide spinner
                $submitBtn.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
    
    // PDF Documents toggle handler removed in v2.2.2
    // PDFs are still uploaded to media library automatically
});

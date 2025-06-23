jQuery(document).ready(function($) {
    // Single product translate button on edit page
    $('.wcait-translate-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var productId = $btn.data('product-id');
        var $status = $btn.siblings('.wcait-status');
        
        $btn.prop('disabled', true).text(wcait_ajax.translating);
        
        $.post(wcait_ajax.ajax_url, {
            action: 'wcait_translate_title',
            product_id: productId,
            nonce: wcait_ajax.nonce
        }, function(response) {
            if (response.success) {
                $status.html('<span style="color: green;">' + response.data.message + '</span>');
                // Update the title field if it exists
                $('#title').val(response.data.title);
            } else {
                $status.html('<span style="color: red;">' + (response.data || wcait_ajax.error) + '</span>');
            }
            $btn.prop('disabled', false).text(wcait_ajax.translate);
        }).fail(function() {
            $status.html('<span style="color: red;">' + wcait_ajax.error + '</span>');
            $btn.prop('disabled', false).text(wcait_ajax.translate);
        });
    });
    
    // Product list single translate button
    $(document).on('click', '.wcait-translate-single', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var productId = $btn.data('product-id');
        var originalText = $btn.text();
        
        $btn.prop('disabled', true).text(wcait_ajax.translating);
        
        $.post(wcait_ajax.ajax_url, {
            action: 'wcait_translate_title',
            product_id: productId,
            nonce: wcait_ajax.nonce
        }, function(response) {
            if (response.success) {
                $btn.text('✓ Translated').css('color', 'green');
                // Refresh the product title in the list
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                $btn.text(wcait_ajax.error).css('color', 'red');
                setTimeout(function() {
                    $btn.text(originalText).css('color', '').prop('disabled', false);
                }, 2000);
            }
        }).fail(function() {
            $btn.text(wcait_ajax.error).css('color', 'red');
            setTimeout(function() {
                $btn.text(originalText).css('color', '').prop('disabled', false);
            }, 2000);
        });
    });
    
    // Bulk translate handling
    if (getUrlParameter('wcait_bulk_translate')) {
        var productIdsParam = getUrlParameter('wcait_product_ids');
        
        if (productIdsParam) {
            var productIds = productIdsParam.split(',').map(function(id) {
                return parseInt(id.trim());
            }).filter(function(id) {
                return !isNaN(id) && id > 0;
            });
            
            if (productIds.length > 0) {
                // Create progress modal
                var progressHtml = '<div id="wcait-bulk-progress" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.2); z-index: 100000; min-width: 300px;">' +
                    '<h3 style="margin-top: 0;">Translating Products to Bahasa Malaysia</h3>' +
                    '<div class="wcait-progress-bar" style="background: #f0f0f0; height: 20px; border-radius: 10px; overflow: hidden; margin: 10px 0;">' +
                    '<div class="wcait-progress-fill" style="background: #0073aa; height: 100%; width: 0%; transition: width 0.3s;"></div>' +
                    '</div>' +
                    '<p class="wcait-progress-text">Starting translation...</p>' +
                    '<p class="wcait-progress-count" style="text-align: center; font-size: 18px; font-weight: bold;">0 / ' + productIds.length + '</p>' +
                    '<button type="button" class="button wcait-cancel-bulk" style="width: 100%; margin-top: 10px;">Cancel</button>' +
                    '</div>' +
                    '<div id="wcait-bulk-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999;"></div>';
                
                $('body').append(progressHtml);
                
                var cancelled = false;
                
                // Cancel button handler
                $('.wcait-cancel-bulk').on('click', function() {
                    cancelled = true;
                    $('#wcait-bulk-progress .wcait-progress-text').text('Cancelling...');
                    $(this).prop('disabled', true);
                });
                
                // Start processing immediately
                processBulkTranslations(productIds, cancelled);
            }
        }
    }
    
    function processBulkTranslations(productIds, cancelFlag) {
        var total = productIds.length;
        var completed = 0;
        var succeeded = 0;
        var failed = 0;
        var cancelled = false;
        
        function updateProgress() {
            var percentage = Math.round((completed / total) * 100);
            $('#wcait-bulk-progress .wcait-progress-fill').css('width', percentage + '%');
            $('#wcait-bulk-progress .wcait-progress-count').text(completed + ' / ' + total);
            
            if (completed === total || cancelled) {
                var message = cancelled ? 'Translation cancelled!' : 'Translation complete!';
                $('#wcait-bulk-progress .wcait-progress-text').html(
                    '<span style="color: ' + (cancelled ? 'orange' : 'green') + ';">' + message + '</span><br>' +
                    'Success: ' + succeeded + ' | Failed: ' + failed
                );
                $('.wcait-cancel-bulk').hide();
                setTimeout(function() {
                    $('#wcait-bulk-progress, #wcait-bulk-overlay').fadeOut(function() {
                        $(this).remove();
                        // Remove URL parameters and reload
                        window.location.href = window.location.pathname + '?post_type=product';
                    });
                }, 2000);
            }
        }
        
        // Process one product at a time
        function processNext(index) {
            if (index >= productIds.length || cancelled) {
                return;
            }
            
            // Check if cancelled
            if ($('.wcait-cancel-bulk').prop('disabled')) {
                cancelled = true;
                updateProgress();
                return;
            }
            
            var productId = productIds[index];
            $('#wcait-bulk-progress .wcait-progress-text').text('Translating product ID: ' + productId + '...');
            
            $.ajax({
                url: wcait_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcait_translate_title',
                    product_id: productId,
                    nonce: wcait_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        succeeded++;
                    } else {
                        failed++;
                        console.error('Translation failed for product ' + productId + ':', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    failed++;
                    console.error('AJAX error for product ' + productId + ':', error);
                },
                complete: function() {
                    completed++;
                    updateProgress();
                    
                    // Process next product with a small delay
                    setTimeout(function() {
                        processNext(index + 1);
                    }, 500); // 500ms delay between products to avoid overwhelming the server
                }
            });
        }
        
        // Start processing
        processNext(0);
    }
    
    // Helper function to get URL parameters
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }
    
    // Settings page model info update
    $('#ai_provider').on('change', function() {
        var provider = $(this).val();
        var modelInfo = '';
        
        switch(provider) {
            case 'openai':
                modelInfo = 'Using model: <strong>gpt-4o-mini</strong>';
                break;
            case 'claude':
                modelInfo = 'Using model: <strong>claude-3-haiku-20240307</strong>';
                break;
            case 'gemini':
                modelInfo = 'Using model: <strong>gemini-pro</strong>';
                break;
            case 'mesolitica':
                modelInfo = 'Using model: <strong>base (Malay-focused)</strong>';
                break;
        }
        
        $('#model-info').html(modelInfo);
    });
});
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
    
    // Handle bulk translate trigger
    if (getUrlParameter('wcait_bulk_translate')) {
        var productCount = getUrlParameter('wcait_count');
        
        if (productCount && parseInt(productCount) > 0) {
            // Show confirmation dialog
            var confirmed = confirm(wcait_ajax.bulk_confirm + '\n\nProducts to translate: ' + productCount);
            
            if (confirmed) {
                startBulkTranslation(parseInt(productCount));
            } else {
                // Remove the URL parameters if user cancels
                var newUrl = window.location.pathname + '?post_type=product';
                history.replaceState({}, document.title, newUrl);
            }
        }
    }
    
    function startBulkTranslation(totalProducts) {
        // Create progress modal
        var progressHtml = createProgressModal(totalProducts);
        $('body').append(progressHtml);
        
        var cancelled = false;
        var processed = 0;
        var successCount = 0;
        var errorCount = 0;
        var batchSize = 3; // Process 3 products at a time to avoid overwhelming APIs
        
        // Cancel button handler
        $('.wcait-cancel-bulk').on('click', function() {
            cancelled = true;
            updateProgressModal(processed, totalProducts, successCount, errorCount, true);
            $(this).prop('disabled', true).text('Cancelling...');
            
            setTimeout(function() {
                closeProgressModal();
            }, 2000);
        });
        
        // Start processing
        processBatch(0, batchSize, totalProducts, cancelled, processed, successCount, errorCount);
    }
    
    function processBatch(offset, batchSize, totalProducts, cancelled, processed, successCount, errorCount) {
        if (cancelled) {
            return;
        }
        
        updateProgressText('Processing products ' + (offset + 1) + ' to ' + Math.min(offset + batchSize, totalProducts) + '...');
        
        $.ajax({
            url: wcait_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wcait_bulk_translate',
                batch_size: batchSize,
                offset: offset,
                nonce: wcait_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    processed = response.data.processed;
                    successCount += response.data.success_count;
                    errorCount += response.data.error_count;
                    
                    updateProgressModal(processed, totalProducts, successCount, errorCount, false);
                    
                    // Log results for debugging
                    if (response.data.results) {
                        response.data.results.forEach(function(result) {
                            if (result.success) {
                                console.log('Translated product ' + result.id + ': "' + result.original_title + '" -> "' + result.translated_title + '"');
                            } else {
                                console.error('Failed to translate product ' + result.id + ': ' + result.error);
                            }
                        });
                    }
                    
                    if (response.data.has_more && !cancelled) {
                        // Continue with next batch after a short delay
                        setTimeout(function() {
                            processBatch(processed, batchSize, totalProducts, cancelled, processed, successCount, errorCount);
                        }, 1000);
                    } else {
                        // All done
                        finishBulkTranslation(totalProducts, successCount, errorCount);
                    }
                } else {
                    console.error('Bulk translation error:', response.data);
                    updateProgressText('Error: ' + (response.data || 'Unknown error occurred'));
                    
                    setTimeout(function() {
                        closeProgressModal();
                    }, 3000);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error during bulk translation:', error);
                updateProgressText('Network error occurred. Please try again.');
                
                setTimeout(function() {
                    closeProgressModal();
                }, 3000);
            }
        });
    }
    
    function finishBulkTranslation(totalProducts, successCount, errorCount) {
        updateProgressModal(totalProducts, totalProducts, successCount, errorCount, false);
        updateProgressText(wcait_ajax.completed);
        $('.wcait-cancel-bulk').hide();
        
        // Create result summary
        var resultSummary = {
            total: totalProducts,
            success: successCount,
            failed: errorCount
        };
        
        setTimeout(function() {
            // Redirect with success message
            var newUrl = window.location.pathname + '?post_type=product&wcait_bulk_success=' + encodeURIComponent(JSON.stringify(resultSummary));
            window.location.href = newUrl;
        }, 3000);
    }
    
    function createProgressModal(totalProducts) {
        return '<div id="wcait-bulk-progress" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 100000; min-width: 400px; max-width: 500px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">' +
            '<h3 style="margin-top: 0; color: #1d2327; font-size: 18px;">Translating Products to Bahasa Malaysia</h3>' +
            '<div class="wcait-progress-bar" style="background: #f0f0f0; height: 24px; border-radius: 12px; overflow: hidden; margin: 15px 0; border: 1px solid #ddd;">' +
            '<div class="wcait-progress-fill" style="background: linear-gradient(45deg, #0073aa, #005177); height: 100%; width: 0%; transition: width 0.5s ease; position: relative;">' +
            '<div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); animation: shine 2s infinite;"></div>' +
            '</div>' +
            '</div>' +
            '<p class="wcait-progress-text" style="margin: 10px 0; color: #50575e;">Starting translation...</p>' +
            '<div style="display: flex; justify-content: space-between; margin: 15px 0;">' +
            '<div style="text-align: center; flex: 1;">' +
            '<div class="wcait-progress-count" style="font-size: 24px; font-weight: bold; color: #1d2327;">0 / ' + totalProducts + '</div>' +
            '<div style="font-size: 12px; color: #646970; text-transform: uppercase; letter-spacing: 0.5px;">PROCESSED</div>' +
            '</div>' +
            '<div style="text-align: center; flex: 1;">' +
            '<div class="wcait-success-count" style="font-size: 20px; font-weight: bold; color: #00a32a;">0</div>' +
            '<div style="font-size: 12px; color: #646970; text-transform: uppercase; letter-spacing: 0.5px;">SUCCESS</div>' +
            '</div>' +
            '<div style="text-align: center; flex: 1;">' +
            '<div class="wcait-error-count" style="font-size: 20px; font-weight: bold; color: #d63638;">0</div>' +
            '<div style="font-size: 12px; color: #646970; text-transform: uppercase; letter-spacing: 0.5px;">FAILED</div>' +
            '</div>' +
            '</div>' +
            '<button type="button" class="button wcait-cancel-bulk" style="width: 100%; margin-top: 15px; padding: 8px 16px; border: 1px solid #c3c4c7; background: #f6f7f7;">Cancel Translation</button>' +
            '</div>' +
            '<div id="wcait-bulk-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 99999;"></div>' +
            '<style>' +
            '@keyframes shine { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }' +
            '</style>';
    }
    
    function updateProgressModal(processed, total, successCount, errorCount, cancelled) {
        var percentage = Math.round((processed / total) * 100);
        $('#wcait-bulk-progress .wcait-progress-fill').css('width', percentage + '%');
        $('#wcait-bulk-progress .wcait-progress-count').text(processed + ' / ' + total);
        $('#wcait-bulk-progress .wcait-success-count').text(successCount);
        $('#wcait-bulk-progress .wcait-error-count').text(errorCount);
        
        if (cancelled) {
            $('#wcait-bulk-progress .wcait-progress-fill').css('background', 'linear-gradient(45deg, #f56500, #d63638)');
            updateProgressText(wcait_ajax.cancelled);
        } else if (processed === total) {
            $('#wcait-bulk-progress .wcait-progress-fill').css('background', 'linear-gradient(45deg, #00a32a, #007317)');
        }
    }
    
    function updateProgressText(text) {
        $('#wcait-bulk-progress .wcait-progress-text').text(text);
    }
    
    function closeProgressModal() {
        $('#wcait-bulk-progress, #wcait-bulk-overlay').fadeOut(function() {
            $(this).remove();
            // Clean up URL
            var newUrl = window.location.pathname + '?post_type=product';
            history.replaceState({}, document.title, newUrl);
        });
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

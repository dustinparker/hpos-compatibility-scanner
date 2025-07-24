jQuery(document).ready(function ($) {
    // Tab navigation
    function switchTab(tabId) {
        // Hide all tab contents
        $('.hpos-tab-content').hide();
        
        // Remove active class from all tabs
        $('.nav-tab').removeClass('nav-tab-active');
        
        // Show the selected tab content
        $('#' + tabId).show();
        
        // Add active class to the clicked tab
        $('#' + tabId + '-link').addClass('nav-tab-active');
        
        // If switching to overview tab, load the data
        if (tabId === 'overview-tab' && $('#hpos-overview-table tbody tr').length === 0) {
            loadPluginsOverview();
        }
    }
    
    // Handle tab clicks
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        const tabId = $(this).attr('href').substring(1);
        switchTab(tabId);
    });
    
    // Handle scan button click
    $('#hpos-scan-button').on('click', function () {
        scanPlugin($('#hpos-plugin-selector').val());
    });
    
    // Function to scan a plugin
    function scanPlugin(plugin) {
        if (!plugin) {
            alert(HPOSScanner.i18n.select_plugin);
            return;
        }
        
        // Switch to scan tab if not already active
        switchTab('scan-tab');
        
        // Set the dropdown to the selected plugin
        $('#hpos-plugin-selector').val(plugin);
        
        $('#hpos-scan-results').html('<p>' + HPOSScanner.i18n.scanning + '</p>');

        $.post(HPOSScanner.ajax_url, {
            action: 'hpos_scan_plugin',
            plugin: plugin,
            nonce: HPOSScanner.nonce
        }, function (response) {
            if (response.success) {
                let html = '';
                
                // Display HPOS compatibility status at the top
                const isHPOSCompatible = response.data.hpos_compatible;
                const compatibilityClass = isHPOSCompatible ? 'hpos-compatible' : 'hpos-not-compatible';
                const compatibilityIcon = isHPOSCompatible ? '✓' : '✗';
                const compatibilityText = isHPOSCompatible 
                    ? HPOSScanner.i18n.hpos_compatible 
                    : HPOSScanner.i18n.hpos_not_compatible;
                
                html += '<div class="hpos-compatibility-status ' + compatibilityClass + '">' +
                    '<span class="hpos-compatibility-icon">' + compatibilityIcon + '</span> ' +
                    '<span class="hpos-compatibility-text">' + compatibilityText + '</span>' +
                    '</div>';
                
                // Check if there are any issues
                const issues = response.data.issues || [];
                
                if (issues.length === 0 && response.data.message) {
                    html += '<p>' + response.data.message + '</p>';
                    $('#hpos-scan-results').html(html);
                    return;
                }
                
                html += '<table class="hpos-results-table"><thead><tr>' + 
                    '<th>' + HPOSScanner.i18n.file + '</th>' + 
                    '<th>' + HPOSScanner.i18n.term + '</th>' + 
                    '<th>' + HPOSScanner.i18n.line + '</th>' + 
                    '<th>' + HPOSScanner.i18n.code + '</th>' + 
                    '</tr></thead><tbody>';
                
                issues.forEach(function (result, index) {
                    const rowId = 'hpos-result-' + index;
                    html += '<tr class="hpos-result-row" data-result-id="' + rowId + '">' + 
                        '<td>' + result.file + '</td>' + 
                        '<td>' + result.term + '</td>' + 
                        '<td>' + result.line + '</td>' + 
                        '<td class="hpos-code-cell">' + 
                            '<div class="hpos-code-preview">' + result.code + '</div>' + 
                            '<button class="hpos-toggle-snippet button button-small" data-target="' + rowId + '-snippet">' + 
                                HPOSScanner.i18n.view_snippet + 
                            '</button>' + 
                        '</td>' + 
                    '</tr>' + 
                    '<tr id="' + rowId + '-snippet" class="hpos-snippet-row" style="display: none;">' + 
                        '<td colspan="4">' + 
                            '<pre class="hpos-code-snippet">' + result.snippet + '</pre>' + 
                        '</td>' + 
                    '</tr>';
                });
                
                html += '</tbody></table>';
                
                if (issues.length > 0) {
                    html += '<button id="download-csv" class="button">' + HPOSScanner.i18n.download_csv + '</button>';
                }
                
                $('#hpos-scan-results').html(html);

                // Handle toggle snippet buttons
                $('.hpos-toggle-snippet').on('click', function() {
                    const targetId = $(this).data('target');
                    $('#' + targetId).toggle();
                    
                    // Toggle button text
                    const buttonText = $(this).text() === HPOSScanner.i18n.view_snippet 
                        ? HPOSScanner.i18n.hide_snippet 
                        : HPOSScanner.i18n.view_snippet;
                    $(this).text(buttonText);
                });

                $('#download-csv').on('click', function () {
                    const isHPOSCompatible = response.data.hpos_compatible;
                    const compatibilityText = isHPOSCompatible 
                        ? HPOSScanner.i18n.hpos_compatible 
                        : HPOSScanner.i18n.hpos_not_compatible;
                    
                    let csvContent = 'data:text/csv;charset=utf-8,' + 
                        'HPOS Compatibility,' + 
                        '"' + compatibilityText + '"' + 
                        '\n\n' + 
                        HPOSScanner.i18n.file + ',' + 
                        HPOSScanner.i18n.term + ',' + 
                        HPOSScanner.i18n.line + ',' + 
                        HPOSScanner.i18n.code + ',' + 
                        HPOSScanner.i18n.snippet + '\n';
                    
                    const issues = response.data.issues || [];
                    issues.forEach(function (result) {
                        // Escape quotes in code and snippet to avoid CSV issues
                        const code = '"' + (result.code || '').replace(/"/g, '""') + '"';
                        const snippet = '"' + (result.snippet || '').replace(/"/g, '""') + '"';
                        
                        csvContent += result.file + ',' + 
                            result.term + ',' + 
                            result.line + ',' + 
                            code + ',' + 
                            snippet + '\n';
                    });
                    
                    const encodedUri = encodeURI(csvContent);
                    const link = document.createElement('a');
                    link.setAttribute('href', encodedUri);
                    link.setAttribute('download', 'hpos_scan_results.csv');
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                });
            } else {
                $('#hpos-scan-results').html('<p>' + HPOSScanner.i18n.error + response.data.message + '</p>');
            }
        }).fail(function () {
            $('#hpos-scan-results').html('<p>' + HPOSScanner.i18n.unable_to_complete + '</p>');
        });
    }
    
    // Function to load plugins overview
    function loadPluginsOverview(forceRefresh = false) {
        $('#hpos-overview-loading').show();
        $('#hpos-overview-table').hide();
        $('#hpos-refresh-cache').prop('disabled', true).addClass('hpos-refreshing');
        
        $.post(HPOSScanner.ajax_url, {
            action: 'hpos_get_all_plugins_compatibility',
            force_refresh: forceRefresh ? 'true' : 'false',
            nonce: HPOSScanner.nonce
        }, function(response) {
            if (response.success) {
                const data = response.data;
                const plugins = data.plugins;
                let tableHtml = '';
                
                // Update last updated timestamp
                if (data.last_updated) {
                    $('#hpos-last-updated-time').text(formatDateTime(data.last_updated));
                }
                
                // Sort plugins by name
                const sortedPlugins = Object.keys(plugins).sort(function(a, b) {
                    return plugins[a].name.localeCompare(plugins[b].name);
                });
                
                sortedPlugins.forEach(function(pluginPath) {
                    const plugin = plugins[pluginPath];
                    const compatibilityClass = plugin.compatible ? 'compatible' : 'not-compatible';
                    const compatibilityIcon = plugin.compatible ? '✓' : '✗';
                    const compatibilityText = plugin.compatible 
                        ? HPOSScanner.i18n.hpos_compatible 
                        : HPOSScanner.i18n.hpos_not_compatible;
                    
                    tableHtml += '<tr>' +
                        '<td class="column-plugin">' + plugin.name + '</td>' +
                        '<td class="column-version">' + plugin.version + '</td>' +
                        '<td class="column-author">' + plugin.author + '</td>' +
                        '<td class="column-compatible">' + 
                            '<span class="hpos-compatibility-indicator ' + compatibilityClass + '">' +
                                compatibilityIcon + ' ' + compatibilityText +
                            '</span>' +
                        '</td>' +
                        '<td class="column-actions">' +
                            '<button class="button hpos-scan-overview-button" data-plugin="' + pluginPath + '">' + 
                                HPOSScanner.i18n.scan_plugin + 
                            '</button>' +
                        '</td>' +
                    '</tr>';
                });
                
                $('#hpos-overview-table-body').html(tableHtml);
                $('#hpos-overview-loading').hide();
                $('#hpos-overview-table').show();
                $('#hpos-refresh-cache').prop('disabled', false).removeClass('hpos-refreshing');
                
                // Handle scan button clicks in the overview table
                $('.hpos-scan-overview-button').on('click', function() {
                    const pluginPath = $(this).data('plugin');
                    scanPlugin(pluginPath);
                });
            } else {
                $('#hpos-overview-loading').html('<p>' + HPOSScanner.i18n.error + response.data.message + '</p>');
                $('#hpos-refresh-cache').prop('disabled', false).removeClass('hpos-refreshing');
            }
        }).fail(function() {
            $('#hpos-overview-loading').html('<p>' + HPOSScanner.i18n.unable_to_complete + '</p>');
            $('#hpos-refresh-cache').prop('disabled', false).removeClass('hpos-refreshing');
        });
    }
    
    // Function to refresh the compatibility cache
    function refreshCompatibilityCache() {
        $('#hpos-refresh-cache').prop('disabled', true).addClass('hpos-refreshing');
        $('#hpos-overview-loading').show().text(HPOSScanner.i18n.refreshing_cache);
        $('#hpos-overview-table').hide();
        
        $.post(HPOSScanner.ajax_url, {
            action: 'hpos_refresh_compatibility_cache',
            nonce: HPOSScanner.nonce
        }, function(response) {
            if (response.success) {
                // Update last updated timestamp
                if (response.data.last_updated) {
                    $('#hpos-last-updated-time').text(formatDateTime(response.data.last_updated));
                }
                
                // Reload the plugins overview with fresh data
                loadPluginsOverview(true);
            } else {
                $('#hpos-overview-loading').html('<p>' + HPOSScanner.i18n.error + response.data.message + '</p>');
                $('#hpos-refresh-cache').prop('disabled', false).removeClass('hpos-refreshing');
            }
        }).fail(function() {
            $('#hpos-overview-loading').html('<p>' + HPOSScanner.i18n.unable_to_complete + '</p>');
            $('#hpos-refresh-cache').prop('disabled', false).removeClass('hpos-refreshing');
        });
    }
    
    // Helper function to format date and time
    function formatDateTime(dateTimeString) {
        const date = new Date(dateTimeString);
        return date.toLocaleString();
    }
    
    // Handle refresh cache button click
    $('#hpos-refresh-cache').on('click', function() {
        refreshCompatibilityCache();
    });
});

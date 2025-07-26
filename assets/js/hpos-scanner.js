/**
 * HPOS Compatibility Scanner JavaScript.
 *
 * This file contains the JavaScript functionality for the HPOS Compatibility Scanner plugin.
 * It handles tab navigation, plugin scanning, and displaying results.
 *
 * @package DPWD\HPOSCompatPlugin
 */

/**
 * HPOS Scanner Application.
 */
class HPOSScannerApp {
    /**
     * Initialize the application.
     */
    constructor() {
        // Store DOM elements.
        this.elements = {
            // Tab navigation.
            tabLinks: document.querySelectorAll('.nav-tab'),
            tabContents: document.querySelectorAll('.hpos-tab-content'),
            
            // Scan tab.
            pluginSelector: document.getElementById('hpos-plugin-selector'),
            scanButton: document.getElementById('hpos-scan-button'),
            scanResults: document.getElementById('hpos-scan-results'),
            
            // Overview tab.
            overviewTab: document.getElementById('overview-tab'),
            overviewTable: document.getElementById('hpos-overview-table'),
            overviewTableBody: document.getElementById('hpos-overview-table-body'),
            overviewLoading: document.getElementById('hpos-overview-loading'),
            refreshCacheButton: document.getElementById('hpos-refresh-cache'),
            lastUpdatedTime: document.getElementById('hpos-last-updated-time')
        };
        
        // Bind event handlers.
        this.bindEvents();
    }
    
    /**
     * Bind event handlers to DOM elements.
     */
    bindEvents() {
        // Tab navigation.
        this.elements.tabLinks.forEach(tabLink => {
            tabLink.addEventListener('click', (e) => {
                e.preventDefault();
                const tabId = tabLink.getAttribute('href').substring(1);
                this.switchTab(tabId);
            });
        });
        
        // Scan button.
        if (this.elements.scanButton) {
            this.elements.scanButton.addEventListener('click', () => {
                this.scanPlugin(this.elements.pluginSelector.value);
            });
        }
        
        // Refresh cache button.
        if (this.elements.refreshCacheButton) {
            this.elements.refreshCacheButton.addEventListener('click', () => {
                this.refreshCompatibilityCache();
            });
        }
    }
    
    /**
     * Switch between tabs.
     * 
     * @param {string} tabId - The ID of the tab to switch to.
     */
    switchTab(tabId) {
        // Hide all tab contents.
        this.elements.tabContents.forEach(content => {
            content.style.display = 'none';
        });
        
        // Remove active class from all tabs.
        this.elements.tabLinks.forEach(link => {
            link.classList.remove('nav-tab-active');
        });
        
        // Show the selected tab content.
        const selectedTab = document.getElementById(tabId);
        if (selectedTab) {
            selectedTab.style.display = 'block';
        }
        
        // Add active class to the clicked tab.
        const activeTabLink = document.getElementById(tabId + '-link');
        if (activeTabLink) {
            activeTabLink.classList.add('nav-tab-active');
        }
        
        // If switching to overview tab, load the data.
        if (tabId === 'overview-tab' && this.elements.overviewTableBody && 
            this.elements.overviewTableBody.querySelectorAll('tr').length === 0) {
            this.loadPluginsOverview();
        }
    }
    
    /**
     * Scan a plugin for HPOS compatibility.
     * 
     * @param {string} plugin - The plugin path to scan.
     */
    scanPlugin(plugin) {
        if (!plugin) {
            alert(HPOSScanner.i18n.select_plugin);
            return;
        }
        
        // Switch to scan tab if not already active.
        this.switchTab('scan-tab');
        
        // Set the dropdown to the selected plugin.
        if (this.elements.pluginSelector) {
            this.elements.pluginSelector.value = plugin;
        }
        
        if (this.elements.scanResults) {
            this.elements.scanResults.innerHTML = '<p>' + HPOSScanner.i18n.scanning + '</p>';
        }

        // Prepare form data.
        const formData = new FormData();
        formData.append('action', 'hpos_scan_plugin');
        formData.append('plugin', plugin);
        formData.append('nonce', HPOSScanner.nonce);

        // Make the fetch request.
        fetch(HPOSScanner.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(response => {
            if (response.success) {
                this.displayScanResults(response.data);
            } else {
                if (this.elements.scanResults) {
                    this.elements.scanResults.innerHTML = '<p>' + HPOSScanner.i18n.error + response.data.message + '</p>';
                }
            }
        })
        .catch(() => {
            if (this.elements.scanResults) {
                this.elements.scanResults.innerHTML = '<p>' + HPOSScanner.i18n.unable_to_complete + '</p>';
            }
        });
    }
    
    /**
     * Display scan results in the UI.
     * 
     * @param {Object} data - The scan results data.
     */
    displayScanResults(data) {
        if (!this.elements.scanResults) {
            return;
        }
        
        let html = '';
        
        // Display HPOS compatibility status at the top.
        const isHPOSCompatible = data.hpos_compatible;
        const compatibilityClass = isHPOSCompatible ? 'hpos-compatible' : 'hpos-not-compatible';
        const compatibilityIcon = isHPOSCompatible ? '✓' : '✗';
        const compatibilityText = isHPOSCompatible 
            ? HPOSScanner.i18n.hpos_compatible 
            : HPOSScanner.i18n.hpos_not_compatible;
        
        html += '<div class="hpos-compatibility-status ' + compatibilityClass + '">' +
            '<span class="hpos-compatibility-icon">' + compatibilityIcon + '</span> ' +
            '<span class="hpos-compatibility-text">' + compatibilityText + '</span>' +
            '</div>';
        
        // Check if there are any issues.
        const issues = data.issues || [];
        
        if (issues.length === 0 && data.message) {
            html += '<p>' + data.message + '</p>';
            this.elements.scanResults.innerHTML = html;
            return;
        }
        
        html += '<table class="hpos-results-table"><thead><tr>' + 
            '<th>' + HPOSScanner.i18n.file + '</th>' + 
            '<th>' + HPOSScanner.i18n.term + '</th>' + 
            '<th>' + (HPOSScanner.i18n.description || 'Description') + '</th>' + 
            '<th>' + HPOSScanner.i18n.line + '</th>' + 
            '<th>' + HPOSScanner.i18n.code + '</th>' + 
            '</tr></thead><tbody>';
        
        issues.forEach((result, index) => {
            const rowId = 'hpos-result-' + index;
            // Use description if available, otherwise fall back to term.
            const description = result.description || result.term;
            
            html += '<tr class="hpos-result-row" data-result-id="' + rowId + '">' + 
                '<td>' + result.file + '</td>' + 
                '<td>' + result.term + '</td>' + 
                '<td>' + description + '</td>' + 
                '<td>' + result.line + '</td>' + 
                '<td class="hpos-code-cell">' + 
                    '<div class="hpos-code-preview">' + result.code + '</div>' + 
                    '<button class="hpos-toggle-snippet button button-small" data-target="' + rowId + '-snippet">' + 
                        HPOSScanner.i18n.view_snippet + 
                    '</button>' + 
                '</td>' + 
            '</tr>' + 
            '<tr id="' + rowId + '-snippet" class="hpos-snippet-row" style="display: none;">' + 
                '<td colspan="5">' + 
                    '<pre class="hpos-code-snippet">' + result.snippet + '</pre>' + 
                '</td>' + 
            '</tr>';
        });
        
        html += '</tbody></table>';
        
        if (issues.length > 0) {
            // Get our separator to display to the user.
            const csvSeparator = this.getCSVSeparator();
            html += '<div class="hpos-csv-download-container">' +
                '<button id="download-csv" class="button">' + HPOSScanner.i18n.download_csv + '</button>' +
                '<span class="hpos-csv-separator-info"> (CSV uses "' + csvSeparator + '" as separator)</span>' +
            '</div>';
        }
        
        this.elements.scanResults.innerHTML = html;

        // Add event listeners to the newly created elements.
        this.addScanResultsEventListeners(data);
    }
    
    /**
     * Add event listeners to scan results elements.
     * 
     * @param {Object} data - The scan results data.
     */
    addScanResultsEventListeners(data) {
        // Handle toggle snippet buttons.
        const toggleButtons = document.querySelectorAll('.hpos-toggle-snippet');
        toggleButtons.forEach(button => {
            button.addEventListener('click', () => {
                const targetId = button.dataset.target;
                const targetElement = document.getElementById(targetId);
                
                if (targetElement) {
                    // Toggle display.
                    targetElement.style.display = targetElement.style.display === 'none' ? 'table-row' : 'none';
                    
                    // Toggle button text.
                    button.textContent = button.textContent === HPOSScanner.i18n.view_snippet 
                        ? HPOSScanner.i18n.hide_snippet 
                        : HPOSScanner.i18n.view_snippet;
                }
            });
        });

        // Handle CSV download button.
        const downloadButton = document.getElementById('download-csv');
        if (downloadButton) {
            downloadButton.addEventListener('click', () => {
                this.downloadCSV(data);
            });
        }
    }
    
    /**
     * Generate and download CSV file with scan results.
     * 
     * @param {Object} data - The scan results data.
     */
    downloadCSV(data) {
        // Get the plugin name from the selected dropdown option.
        const pluginPath = this.elements.pluginSelector.value;
        const pluginSelect = this.elements.pluginSelector;
        const selectedOption = pluginSelect.options[pluginSelect.selectedIndex];
        const pluginName = selectedOption ? selectedOption.text : '';
        
        const isHPOSCompatible = data.hpos_compatible;
        const compatibilityText = isHPOSCompatible 
            ? HPOSScanner.i18n.hpos_compatible 
            : HPOSScanner.i18n.hpos_not_compatible;
        
        // Create CSV content.
        let csvContent = '';
        
        // Get our unique separator.
        const separator = this.getCSVSeparator();
        
        // Add plugin name and compatibility status.
        csvContent += this.escapeCSVField('Plugin Name') + separator + this.escapeCSVField(pluginName) + '\n';
        csvContent += this.escapeCSVField('HPOS Compatibility') + separator + this.escapeCSVField(compatibilityText) + '\n\n';
        
        // Add column headers.
        csvContent += this.escapeCSVField(HPOSScanner.i18n.file) + separator + 
            this.escapeCSVField(HPOSScanner.i18n.term) + separator + 
            this.escapeCSVField(HPOSScanner.i18n.description || 'Description') + separator + 
            this.escapeCSVField(HPOSScanner.i18n.line) + separator + 
            this.escapeCSVField(HPOSScanner.i18n.code) + separator + 
            this.escapeCSVField(HPOSScanner.i18n.snippet) + '\n';
        
        // Add data rows with proper escaping.
        const issues = data.issues || [];
        issues.forEach(result => {
            // Use description if available, otherwise fall back to term.
            const description = result.description || result.term;
            
            csvContent += this.escapeCSVField(result.file) + separator + 
                this.escapeCSVField(result.term) + separator + 
                this.escapeCSVField(description) + separator + 
                this.escapeCSVField(result.line) + separator + 
                this.escapeCSVField(result.code) + separator + 
                this.escapeCSVField(result.snippet) + '\n';
        });
        
        // Create sanitized filename from plugin name.
        const sanitizedPluginName = pluginName.replace(/[^a-z0-9]/gi, '_').toLowerCase();
        const filename = 'hpos_scan_' + sanitizedPluginName + '.csv';
        
        // Use Blob API for more reliable CSV generation and download.
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        
        // Create a URL for the Blob.
        const url = URL.createObjectURL(blob);
        
        // Create a link element and trigger the download.
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        
        // Clean up.
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }
    
    /**
     * Load plugins overview data.
     * 
     * @param {boolean} forceRefresh - Whether to force a refresh of the cache.
     */
    loadPluginsOverview(forceRefresh = false) {
        if (this.elements.overviewLoading) {
            this.elements.overviewLoading.style.display = 'block';
        }
        
        if (this.elements.overviewTable) {
            this.elements.overviewTable.style.display = 'none';
        }
        
        if (this.elements.refreshCacheButton) {
            this.elements.refreshCacheButton.disabled = true;
            this.elements.refreshCacheButton.classList.add('hpos-refreshing');
        }
        
        // Prepare form data.
        const formData = new FormData();
        formData.append('action', 'hpos_get_all_plugins_compatibility');
        formData.append('force_refresh', forceRefresh ? 'true' : 'false');
        formData.append('nonce', HPOSScanner.nonce);

        // Make the fetch request.
        fetch(HPOSScanner.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(response => {
            if (response.success) {
                this.displayPluginsOverview(response.data);
            } else {
                if (this.elements.overviewLoading) {
                    this.elements.overviewLoading.innerHTML = '<p>' + HPOSScanner.i18n.error + response.data.message + '</p>';
                }
                
                if (this.elements.refreshCacheButton) {
                    this.elements.refreshCacheButton.disabled = false;
                    this.elements.refreshCacheButton.classList.remove('hpos-refreshing');
                }
            }
        })
        .catch(() => {
            if (this.elements.overviewLoading) {
                this.elements.overviewLoading.innerHTML = '<p>' + HPOSScanner.i18n.unable_to_complete + '</p>';
            }
            
            if (this.elements.refreshCacheButton) {
                this.elements.refreshCacheButton.disabled = false;
                this.elements.refreshCacheButton.classList.remove('hpos-refreshing');
            }
        });
    }
    
    /**
     * Display plugins overview in the UI.
     * 
     * @param {Object} data - The plugins overview data.
     */
    displayPluginsOverview(data) {
        const plugins = data.plugins;
        let tableHtml = '';
        
        // Update last updated timestamp.
        if (data.last_updated && this.elements.lastUpdatedTime) {
            this.elements.lastUpdatedTime.textContent = this.formatDateTime(data.last_updated);
        }
        
        // Sort plugins by name.
        const sortedPlugins = Object.keys(plugins).sort((a, b) => {
            return plugins[a].name.localeCompare(plugins[b].name);
        });
        
        sortedPlugins.forEach(pluginPath => {
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
        
        if (this.elements.overviewTableBody) {
            this.elements.overviewTableBody.innerHTML = tableHtml;
        }
        
        if (this.elements.overviewLoading) {
            this.elements.overviewLoading.style.display = 'none';
        }
        
        if (this.elements.overviewTable) {
            this.elements.overviewTable.style.display = 'table';
        }
        
        if (this.elements.refreshCacheButton) {
            this.elements.refreshCacheButton.disabled = false;
            this.elements.refreshCacheButton.classList.remove('hpos-refreshing');
        }
        
        // Add event listeners to scan buttons.
        const scanButtons = document.querySelectorAll('.hpos-scan-overview-button');
        scanButtons.forEach(button => {
            button.addEventListener('click', () => {
                const pluginPath = button.dataset.plugin;
                this.scanPlugin(pluginPath);
            });
        });
    }
    
    /**
     * Refresh the compatibility cache.
     */
    refreshCompatibilityCache() {
        if (this.elements.refreshCacheButton) {
            this.elements.refreshCacheButton.disabled = true;
            this.elements.refreshCacheButton.classList.add('hpos-refreshing');
        }
        
        if (this.elements.overviewLoading) {
            this.elements.overviewLoading.style.display = 'block';
            this.elements.overviewLoading.textContent = HPOSScanner.i18n.refreshing_cache;
        }
        
        if (this.elements.overviewTable) {
            this.elements.overviewTable.style.display = 'none';
        }
        
        // Prepare form data.
        const formData = new FormData();
        formData.append('action', 'hpos_refresh_compatibility_cache');
        formData.append('nonce', HPOSScanner.nonce);

        // Make the fetch request.
        fetch(HPOSScanner.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(response => {
            if (response.success) {
                // Update last updated timestamp.
                if (response.data.last_updated && this.elements.lastUpdatedTime) {
                    this.elements.lastUpdatedTime.textContent = this.formatDateTime(response.data.last_updated);
                }
                
                // Reload the plugins overview with fresh data.
                this.loadPluginsOverview(true);
            } else {
                if (this.elements.overviewLoading) {
                    this.elements.overviewLoading.innerHTML = '<p>' + HPOSScanner.i18n.error + response.data.message + '</p>';
                }
                
                if (this.elements.refreshCacheButton) {
                    this.elements.refreshCacheButton.disabled = false;
                    this.elements.refreshCacheButton.classList.remove('hpos-refreshing');
                }
            }
        })
        .catch(() => {
            if (this.elements.overviewLoading) {
                this.elements.overviewLoading.innerHTML = '<p>' + HPOSScanner.i18n.unable_to_complete + '</p>';
            }
            
            if (this.elements.refreshCacheButton) {
                this.elements.refreshCacheButton.disabled = false;
                this.elements.refreshCacheButton.classList.remove('hpos-refreshing');
            }
        });
    }
    
    /**
     * Helper function to properly escape CSV fields.
     * 
     * @param {*} field - The field to escape.
     * @returns {string} The escaped field.
     */
    escapeCSVField(field) {
        if (field === null || field === undefined) {
            return '';
        }
        
        // Convert to string.
        field = String(field);
        
        // Decode HTML entities to prevent issues with & characters.
        field = this.decodeHTMLEntities(field);
        
        // Define our unique separator.
        const uniqueSeparator = this.getCSVSeparator();
        
        // If the field contains quotes or our unique separator or newlines, it needs to be quoted.
        if (field.includes('"') || field.includes(uniqueSeparator) || field.includes('\n') || field.includes('\r')) {
            // Double up any quotes and wrap the whole thing in quotes.
            return '"' + field.replace(/"/g, '""') + '"';
        }
        return field;
    }
    
    /**
     * Helper function to decode HTML entities.
     * 
     * @param {string} text - The text to decode.
     * @returns {string} The decoded text.
     */
    decodeHTMLEntities(text) {
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        return textarea.value;
    }
    
    /**
     * Helper function to get our unique CSV separator.
     * 
     * @returns {string} The CSV separator.
     */
    getCSVSeparator() {
        return '|~|';
    }
    
    /**
     * Helper function to format date and time.
     * 
     * @param {string} dateTimeString - The date/time string to format.
     * @returns {string} The formatted date/time string.
     */
    formatDateTime(dateTimeString) {
        const date = new Date(dateTimeString);
        return date.toLocaleString();
    }
}

// Initialize the app when the DOM is fully loaded.
document.addEventListener('DOMContentLoaded', () => {
    // Create and initialize the HPOS Scanner app.
    const hposScanner = new HPOSScannerApp();
});
jQuery(document).ready(function ($) {
    $('#hpos-scan-button').on('click', function () {
        const plugin = $('#hpos-plugin-selector').val();
        if (!plugin) {
            alert('Please select a plugin to scan.');
            return;
        }

        $('#hpos-scan-results').html('<p>Scanning...</p>');

        $.post(HPOSScanner.ajax_url, {
            action: 'hpos_scan_plugin',
            plugin: plugin
        }, function (response) {
            if (response.success) {
                let html = '<table><thead><tr><th>File</th><th>Term</th></tr></thead><tbody>';
                response.data.forEach(function (result) {
                    html += '<tr><td>' + result.file + '</td><td>' + result.term + '</td></tr>';
                });
                html += '</tbody></table>';
                html += '<button id="download-csv" class="button">Download CSV</button>';
                $('#hpos-scan-results').html(html);

                $('#download-csv').on('click', function () {
                    let csvContent = 'data:text/csv;charset=utf-8,File,Term\n';
                    response.data.forEach(function (result) {
                        csvContent += result.file + ',' + result.term + '\n';
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
                $('#hpos-scan-results').html('<p>Error: ' + response.data.message + '</p>');
            }
        }).fail(function () {
            $('#hpos-scan-results').html('<p>Error: Unable to complete the scan. Please check the server logs for details.</p>');
        });
    });
});
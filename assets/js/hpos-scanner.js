jQuery(document).ready(function ($) {
    $('#hpos-scan-button').on('click', function () {
        const plugin = $('#hpos-plugin-selector').val();
        if (!plugin) {
            alert(HPOSScanner.i18n.select_plugin);
            return;
        }

        $('#hpos-scan-results').html('<p>' + HPOSScanner.i18n.scanning + '</p>');

        $.post(HPOSScanner.ajax_url, {
            action: 'hpos_scan_plugin',
            plugin: plugin
        }, function (response) {
            if (response.success) {
                let html = '<table><thead><tr><th>' + HPOSScanner.i18n.file + '</th><th>' + HPOSScanner.i18n.term + '</th></tr></thead><tbody>';
                response.data.forEach(function (result) {
                    html += '<tr><td>' + result.file + '</td><td>' + result.term + '</td></tr>';
                });
                html += '</tbody></table>';
                html += '<button id="download-csv" class="button">' + HPOSScanner.i18n.download_csv + '</button>';
                $('#hpos-scan-results').html(html);

                $('#download-csv').on('click', function () {
                    let csvContent = 'data:text/csv;charset=utf-8,' + HPOSScanner.i18n.file + ',' + HPOSScanner.i18n.term + '\n';
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
                $('#hpos-scan-results').html('<p>' + HPOSScanner.i18n.error + response.data.message + '</p>');
            }
        }).fail(function () {
            $('#hpos-scan-results').html('<p>' + HPOSScanner.i18n.unable_to_complete + '</p>');
        });
    });
});

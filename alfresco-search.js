jQuery(document).ready(function($) {
    var $resultsContainer = $('#alfresco-search-results');
    var $form = $('#alfresco-search-form');

    function applyMobileCardLabels($context) {
        $context = $context || $(document);
        $context.find('.results-table table').each(function() {
            var $table = $(this);
            var headers = [];

            $table.find('thead th').each(function(index) {
                headers[index] = $.trim($(this).text());
            });

            $table.find('tbody tr').each(function() {
                $(this).find('td').each(function(index) {
                    if (headers[index]) {
                        $(this).attr('data-label', headers[index]);
                    }
                });
            });
        });
    }

    function setBusyState(isBusy) {
        $resultsContainer.attr('aria-busy', isBusy ? 'true' : 'false');
    }

    function renderLoading(requestUrl) {
        setBusyState(true);
        var extraInfo = '';
        if (alfrescoSearch.debug && requestUrl) {
            extraInfo = '<p class="mt-2 text-xs break-all text-blue-700"><strong>Request URL:</strong> ' + $('<div/>').text(requestUrl).html() + '</p>';
        }
        var safeMessage = $('<div/>').text(alfrescoSearch.loadingMessage || 'Loading...').html();
        var html = '<div class="mb-4 rounded border border-blue-300 bg-blue-50 p-4 text-blue-700" role="status">' +
            '<p class="font-semibold">' + safeMessage + '</p>' + extraInfo +
            '</div>';
        $resultsContainer.html(html);
    }

    function renderError(message, requestUrl) {
        setBusyState(false);
        var safeMessage = $('<div/>').text(message).html();
        var html = '<div class="mb-4 rounded border border-red-300 bg-red-50 p-4 text-red-700" role="alert">' +
            '<p class="font-semibold">' + safeMessage + '</p>';
        if (alfrescoSearch.debug && requestUrl) {
            var safeUrl = $('<div/>').text(requestUrl).html();
            html += '<p class="mt-2 text-xs break-all text-red-800"><strong>Request URL:</strong> ' + safeUrl + '</p>';
        }
        html += '</div>';
        $resultsContainer.html(html);
    }

    function loadNodeDetails() {
        $('.node-details[data-node-id]').each(function(){
            var $container = $(this);
            var nodeId = $container.data('node-id');
            if(nodeId){
                $.ajax({
                    url: alfrescoSearch.ajax_url,
                    type: 'GET',
                    data: {
                        action: 'alfresco_node_details',
                        node_id: nodeId,
                        nonce: alfrescoSearch.nonce
                    },
                    success: function(response) {
                        if(response.success && response.data){
                            var props = response.data.properties || {};
                            var title = props['cm:title'] ? props['cm:title'] : $container.closest('tr').find('td:first').text();
                            var description = props['cm:description'] ? props['cm:description'] : 'No description';
                            $container.find('.node-title').text(title);
                            $container.find('.node-description').text(description);
                        } else {
                            $container.find('.node-title').text($container.closest('tr').find('td:first').text());
                            $container.find('.node-description').text('No description');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Node Details Error:', error);
                        $container.find('.node-title').text($container.closest('tr').find('td:first').text());
                        $container.find('.node-description').text('Error loading details');
                    }
                });
            }
        });
    }

    function requestResults(options) {
        var requestUrl = options.url || alfrescoSearch.ajax_url;
        renderLoading(requestUrl);
        $.ajax({
            url: requestUrl,
            type: 'GET',
            data: options.data || {},
            success: function(data) {
                setBusyState(false);
                $resultsContainer.html(data);
                applyMobileCardLabels($resultsContainer);
                loadNodeDetails();
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                renderError(alfrescoSearch.genericError, requestUrl);
            }
        });
    }

    $resultsContainer.on('click', '.pagination a', function(e) {
        e.preventDefault();
        var url = $(this).attr('href');
        requestResults({ url: url });
    });

    if ($form.length) {
        $form.on('submit', function(e) {
            e.preventDefault();
            var formData = { action: 'alfresco_search_results', page: 1 };
            $.each($form.serializeArray(), function(_, field) {
                formData[field.name] = field.value;
            });
            formData.page = 1;
            requestResults({ data: formData });
        });
    }

    applyMobileCardLabels($resultsContainer);
    loadNodeDetails();
});

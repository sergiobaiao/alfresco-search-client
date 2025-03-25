jQuery(document).ready(function($) {
    // Intercept clicks on pagination links.
    $('#alfresco-search-results').on('click', '.pagination a', function(e) {
        e.preventDefault();
        var url = $(this).attr('href');
        $.ajax({
            url: url,
            type: 'GET',
            success: function(data) {
                $('#alfresco-search-results').html(data);
                loadNodeDetails();
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
            }
        });
    });
    
    // Function to load node details (title and description) asynchronously.
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
                        node_id: nodeId
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
                        console.error("AJAX Node Details Error:", error);
                        $container.find('.node-title').text($container.closest('tr').find('td:first').text());
                        $container.find('.node-description').text('Error loading details');
                    }
                });
            }
        });
    }
    // Trigger node details loading on page load.
    loadNodeDetails();
});

jQuery(document).ready(function($) {
    // Check if the button exists on the profile page
    if ($('#retrieve-data-button').length > 0) {
        $('#retrieve-data-button').click(function() {
            $.ajax({
                url: custom_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'custom_action',
                },
                success: function(response) {
                    // Create a modal with the user data
                    let modalHtml = '<div id="custom-modal" class="custom-modal-summary">';
                    modalHtml += '<div class="modal-content-summary">';
                    modalHtml += '<span class="close-modal-summary">&times;</span>';
                    modalHtml += '<h2>User Details Summary</h2>';
                    modalHtml += '<div id="data-container">' + response + '</div>';
                    modalHtml += '</div>';
                    modalHtml += '</div>';

                    // Append the modal HTML to the body
                    $('body').append(modalHtml);

                    // Show the modal
                    $('#custom-modal').fadeIn();
                },
            });
        });

        // Close the modal when clicking the close button
        $(document).on('click', '.close-modal-summary', function() {
            $('#custom-modal').fadeOut(function() {
                $(this).remove();
            });
        });
    }
});

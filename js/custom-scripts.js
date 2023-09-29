jQuery(document).ready(function($) {
    // Attach a click event handler to the button
    $('#retrieve-data-button').click(function() {
        // Get the user ID from the data attribute on the button
        const userId = $(this).data('user-id');
        
        window.location.href = `/user-summary/?user_id=${userId}`;
            })
});



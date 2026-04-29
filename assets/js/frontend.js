jQuery(function($){

    const modal = $('#sc-modal');

    $('#sc-open-modal').on('click', function(){
        modal.fadeIn(200);
    });

    $('.sc-close').on('click', function(){
        modal.fadeOut(200);
    });

    $(window).on('click', function(e){
        if($(e.target).is('#sc-modal')){
            modal.fadeOut(200);
        }
    });

    $('#sc-event-form').on('submit', function(e){
        e.preventDefault();

        $.ajax({
            url: SCFrontend.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_create_event',
                title: $('input[name="title"]').val(),
                description: $('textarea[name="description"]').val(),
                date: $('input[name="date"]').val(),
                start_time: $('input[name="start_time"]').val(),
                end_time: $('input[name="end_time"]').val(),
                category: $('input[name="category"]').val()
            },
            success: function(response){
                if(response.success){
                    $('#sc-response').html('<p>Event Created</p>');
                    $('#sc-event-form')[0].reset();
                }else{
                    $('#sc-response').html('<p>Error creating event</p>');
                }
            }
        });
    });

});

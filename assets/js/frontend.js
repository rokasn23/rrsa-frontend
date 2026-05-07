jQuery(function($){

    const modal = $('#rrsa-modal');

    $('#rrsa-open-modal').on('click', function(){
        modal.fadeIn(200);
    });

    $('.rrsa-close').on('click', function(){
        modal.fadeOut(200);
    });

    $(window).on('click', function(e){
        if($(e.target).is('#rrsa-modal')){
            modal.fadeOut(200);
        }
    });

    $('#rrsa-event-form').on('submit', function(e){
        e.preventDefault();

        $.ajax({
            url: RRSAFrontend.ajaxurl,
            type: 'POST',
            data: $(this).serialize() + '&action=rrsa_create_event',
            success: function(response){
                if(response.success){
                    $('#rrsa-response').html('<p>Event Created</p>');
                    $('#rrsa-event-form')[0].reset();
                    setTimeout(function(){
                        modal.fadeOut(200);
                        $('#rrsa-response').html('');
                    }, 500);
                    location.reload(true);
                }else{
                    $('#rrsa-response').html('<p>Error creating event</p>');
                }
            }
        });
    });

});

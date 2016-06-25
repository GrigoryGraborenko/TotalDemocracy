/**
 * Created by Grigory on 24/06/2016.
 */

$(document).ready(function(){
    $("form.once-only").submit(function () {
        $(this).find(':button[type=submit]').attr('disabled', 'disabled');
    });
});
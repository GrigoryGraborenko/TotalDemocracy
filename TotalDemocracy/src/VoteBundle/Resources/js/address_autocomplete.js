/**
 * Created by Grigory on 18/03/2016.
 */

function createAddressAutoComplete($postcode_input, $suburb_select, $street_input, $all_street_inputs) {

    var acceptable_suburbs = [];
    var acceptable_streets = [];

    $postcode_input.on("input", function() {
        var post = $(this).val();
        $suburb_select.empty().prop("disabled", true);
        $all_street_inputs.prop("disabled", true);
        if((post.length < 4) || (post.length > 4)) {
            $suburb_select.append('<option value="">- Enter Valid Postcode First -</option>');
            return;
        }
        $suburb_select.append('<option value="">Please Wait, Loading...</option>');

        $.ajax({
            url: Routing.generate('verify_autocomplete')
            ,method: 'POST'
            ,dataType:"json"
            ,data: { "postcode": post }
        }).done(function(data) {

            if(data.suburbs.length < 1) {
                return;
            }
            acceptable_suburbs = data.suburbs;
            $suburb_select.prop("disabled", false).empty().append('<option value="">- Select Suburb -</option>');
            $all_street_inputs.prop("disabled", false);

            data.suburbs.forEach(function(sub) {
                var $op = $('<option value="' + sub + '">' + sub + '</option>');
                $suburb_select.append($op);
                if(g_InitialData.suburb === sub){
                    $suburb_select.val(sub);
                }
            });
            if(data.suburbs.length === 1) {
                $suburb_select.val(data.suburbs[0]);
            }
        }).error(function(response) {
            console.log(response.responseJSON.result);
        });
    });

    if($postcode_input.val().length === 4) {
        $postcode_input.trigger("input");
    }

    $street_input.autocomplete({
        minLength: 1
        ,source: function(request, response) {

            $.ajax({
                url: Routing.generate('verify_autocomplete')
                ,method: 'POST'
                ,dataType:"json"
                ,data: { "prefix": request.term, "context": ($suburb_select.val() + ";" + $postcode_input.val()) }
            }).done(function(data) {
                if(data.streets.length > 0) {
                    acceptable_streets = data.streets;
                }
                response(data.streets);
            }).error(function(response) {
                console.log(response.responseJSON.result);
            });

        }
    });

    return function() {

        if($postcode_input.val().length < 1) {
            return "Enter your postcode";
        }
        if(acceptable_suburbs.length < 1) {
            return "Enter a valid postcode";
        }
        if($("#suburb-select option:selected").prop("value").length < 1) {
            return "Select a suburb/locality";
        }
        if($("#street-number-input").val().length < 1) {
            return "Enter a street number";
        }
        var street_name = $street_input.val().toUpperCase();
        if(street_name.length < 1) {
            return "Enter your street name";
        }
        if((acceptable_streets.length > 0) && (acceptable_streets.indexOf(street_name) === -1)) {
            return "Enter a valid street";
        }
        return true;
    };
}
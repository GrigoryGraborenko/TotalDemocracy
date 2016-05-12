/**
 * Created by Grigory on 8/05/2016.
 */

/*
 * utility to strip params off the current URL
 */
var decodeURLParams = function() {
    var result = {};
    var params = window.location.search.replace('?', '').split("&");
    params.forEach(function(param) {
        var keyval = param.split("=");
        if(keyval.length !== 2) {
            return;
        }
        result[keyval[0]] = keyval[1];
    });
    return result;
};
var encodeURLParams = function(params) {
    var param_list = [];
    for(var name in params) {
        param_list.push(name + "=" + params[name]);
    }
    if(param_list.length > 0) {
        return "?" + param_list.join("&");
    }
    return "";
};

$(document).ready(function() {

    $('.domain-select').change(function() {

        $select = $(this);
        var val = $select.val();

        var params = decodeURLParams();
        if(val === "") {
            delete params[$select.data().level];
        } else {
            params[$select.data().level] = val;
        }
        window.location.search = encodeURLParams(params);
    });

    $('.vote-on').click(function() {

        var $button = $(this);
        var $both_buttons = $button.parent().find("button");

        $both_buttons.prop("disabled", false);
        $button.prop("disabled", true);

        var data = $button.data();
        $.ajax({
            url: Routing.generate('vote_on')
            ,method: 'POST'
            ,dataType: "json"
            ,data: data
        }).done(function(data) {

            $head_div = $('div[data-doc-block="' + data.doc_id + '"]');
            $head_div.find(".yes-count").html(data.total_yes);
            $head_div.find(".no-count").html(data.total_no);

        }).error(function(response) {

            console.log(response.responseJSON.result);

        });
    });
});
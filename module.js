M.mod_attendance = {}

M.mod_attendance.init_manage = function(Y) {

    Y.on('click', function(e) {
        if (e.target.get('checked')) {
            checkall();
        } else {
            checknone();
        }
    }, '#cb_selector' );
};

M.mod_attendance.set_preferences_action = function(action) {
    var item = document.getElementById('preferencesaction');
    if (item) {
        item.setAttribute('value', action);
    } else {
        item = document.getElementById('preferencesform');
        var input = document.createElement("input");
        input.setAttribute("type", "hidden");
        input.setAttribute("name", "action");
        input.setAttribute("value", action);
        item.appendChild(input);
    }
};

/***
 * Begin LiveTake JS module.
 ***/ 

M.mod_attendance.livetake = {};

/**
 * Handles the event in which a user's name is directly selected.
 */ 
M.mod_attendance.livetake.handle_selection = function(e) {

    // If we're already busy with an AJAX call, 
    if($('#menuuser').prop('disabled')) {
        return true;
    }

    // Read the User ID of the selected user.
    var uid = $("#menuuser").val();

    //If we don't have a valid selection, return.
    if(uid.length < 1) {
        return false;
    }

    // Process the user's check-off.
    M.mod_attendance.livetake.check_off_user_id(uid[0]);

    // Clear the barcode bar.
    M.mod_attendance.livetake.clear_select(true);
}

/**
 * Handles the event in which a user's name is directly selected.
 */ 
M.mod_attendance.livetake.handle_keypress = function(e) {

    // If the key wasn't the enter key; or Chosen has found results, abort.
    // This is sort of an ugly hack, which is necessitated as Chosen doesn't
    // provide a nice API to determine if a search entry matches. 
    //
    // This should probably be fixed via a mod to Chosen.
    if((e.keyCode != 13) || ($('#menuuser_chosen li.no-results').length == 0)) {
        return true;
    }

    // Prevent further actions until this one has been completed.
    $('#menuuser').prop('disabled', true);

    //Log the recorded value.
    M.mod_attendance.livetake.check_off_user_id_number($('#menuuser_chosen input').val());

    // Clear the barcode bar.
    M.mod_attendance.livetake.clear_select(true);

    //Handle the event here, and stop.
    e.stopPropagation();
    e.preventDefault();
    return false;
}


/**
 * Returns a success message appropriate for the given response.
 */ 
M.mod_attendance.livetake.success_message = function(response) {
    var message = '<div class="success">';
    message += '<b>' + response.firstname + ' ' + response.lastname + '</b>';
    message += ' was successfully checked off on ';  //FIXME: Language strings!
    message += response.userdate;
    message +=  '.</div>';

    console.log(response);

    return message;
}


/**
 * Returns an error message appropriate for the given response.
 */ 
M.mod_attendance.livetake.error_message = function(response) {
    var message = '<div class="error">';
    message += '<b>' + response.uid + '</b>';
    message += ' did not correspond to any known student.';
    message +=  '</div>';
    return message;
}


/**
 * Event handler for a "checkoff result" event, which occurs after a checkoff is completed
 * or fails.
 */ 
M.mod_attendance.livetake.handle_checkoff_result = function(response) {

    var message;

    //Re-enable the select box, so we can process the next barcode,
    //if it's disabled.
    $("#menuuser").prop('disabled', false);
    M.mod_attendance.livetake.clear_select();


    //Get a message appropriate for the result of the query.
    if(response.status == "success") {
        message = M.mod_attendance.livetake.success_message(response);
    } else {
        message = M.mod_attendance.livetake.error_message(response);
    }

    //Report the result.
    $(message).prependTo('.result-area').hide().slideDown(250);
}

/**
 * Handles the back-end request to make a check-off. 
 * @param data A plain-object including the data to be sent as GET data
 *  to the given page.
 */
M.mod_attendance.livetake.perform_ajax_check_off = function(data) {

    //Add in the cmid, and the currently selected session number.
    data.id = M.mod_attendance.livetake.cmid;
    data.csession = parseInt($("#menusession").val()); //TODO: Abstract away!
    data.sesskey = M.cfg.sesskey;

    //And send the data to the AJAX backend.
    $.getJSON(M.cfg.wwwroot + '/mod/attendance/ajax/livetake.php', data, M.mod_attendance.livetake.handle_checkoff_result);
}

/**
 * Marks a user as present in the currently selected section via their id number ("barcode").
 */ 
M.mod_attendance.livetake.check_off_user_id_number = function(idnumber) {
    M.mod_attendance.livetake.perform_ajax_check_off({mode: 'idnumber', user: idnumber});
}

/**
 * Marks the user as present in the currently selected section via their Moodle
 * database User ID-- used by manual check off.
 */ 
M.mod_attendance.livetake.check_off_user_id = function(uid) {
    M.mod_attendance.livetake.perform_ajax_check_off({mode: 'userid', user: uid});
}

/**
 * Clears the active student selcetion.
 */ 
M.mod_attendance.livetake.clear_select = function(refocus) {
    $('#menuuser').val('');
    $('#menuuser_chosen input').val('');
    $('#menuuser_chosen li.search-choice').remove();

    // If we want to refocus the bar, do so.
    if(refocus) {
        $('#menuuser_chosen input').trigger('mousedown');
    }
}


/**
 * Registers the event handlers for student selection. 
 * Should be called once Chosen has been initialized.
 */
M.mod_attendance.livetake.register_student_selection_events = function() {

    // Register the change event handler, which will handle the event that a user is manually selected.
    $('#menuuser').on('change', M.mod_attendance.livetake.handle_selection);

    // Start off with the user menu expanded.
    $('#menuuser_chosen').trigger('mousedown');

    // Register the "enter key" event handler, which will handle ID card entry.
    $('#menuuser_chosen input').on('keyup', M.mod_attendance.livetake.handle_keypress);

    //Ensure we start out with no selected elements.
    M.mod_attendance.livetake.clear_select();
}


/**
 * Initializes the LiveTake instance.
 */ 
M.mod_attendance.livetake.initialize = function(Y, cmid) {

    // Store the course-module which is actively using the ("singleton") LiveTake instance.
    M.mod_attendance.livetake.cmid = cmid;

    // Register a callback function which should occur once chosen has been initialized.
    $('#menuuser').on('chosen:ready', M.mod_attendance.livetake.register_student_selection_events);

    // Apply Chosen over the included selection boxes-- this enables easier selection, and powers
    // barcode reader compatibility.
    $('#menusession').chosen({width: '100%'});
    $('#menuuser').chosen({allow_single_deselect: true, no_results_text: "Interpreting as ID number:", width: "100%", placeholder_text_multiple: false});

}

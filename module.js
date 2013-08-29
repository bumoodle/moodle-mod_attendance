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


M.mod_attendance.livetake = {}

/**
 * Handles the event in which a user's name is directly selected.
 */ 
M.mod_attendance.livetake.handle_selection = function(e) {
    
    // Read the User ID of the selected user.
    var uid = $(e.currentTarget).val();

    //If we don't have a valid selection, return.
    if(uid.length < 2) {
        return;
    }

    //TODO: Process the target value.
    M.mod_attendance.livetake.check_off_user_id(uid[1]);

    //Clear the barcode bar.
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

    //Log the recorded value.
    M.mod_attendance.livetake.check_off_user_barcode($('#menuuser_chosen input').val());

}

M.mod_attendance.livetake.check_off_user_barcode = function(barcode) {
    console.log("Barcode:" + barcode);
}

/**
 * Marks the user with the given ID as checked off.
 */ 
M.mod_attendance.livetake.check_off_user_id = function(uid) {
    console.log("User ID:" + uid);
    M.mod_attendance.livetake.clear_select();
}

M.mod_attendance.livetake.clear_select = function(refocus) {
    $('#menuuser').val('');
    $('#menuuser').trigger('chosen:updated');

    // If we want to refocus the bar, do so.
    if(refocus) {
        $('#menuuser_chosen').trigger('mousedown');
    }
}

M.mod_attendance.livetake.register_chosen_events = function() {

    // Register the change event handler, which will handle the event that a user is manually selected.
    $('#menuuser').on('change', M.mod_attendance.livetake.handle_selection);

    // Start off with the user menu expanded.
    $('#menuuser_chosen').trigger('mousedown');

    // Register the "enter key" event handler, which will handle ID card entry.
    $('#menuuser_chosen input').on('keydown', M.mod_attendance.livetake.handle_keypress);
}


/**
 * Initializes the LiveTake instance.
 */ 
M.mod_attendance.livetake.initialize = function() {

    // Register a callback function which should occur once chosen has been initialized.
    $('#menuuser').on('chosen:ready', M.mod_attendance.livetake.register_chosen_events);

    $('#menusession').chosen();
    $('#menuuser').chosen({allow_single_deselect: true, no_results_text: "Interpreting as ID number:"});

}

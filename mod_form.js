M.mod_coursework = {}

M.mod_coursework.init   =   function()      {

    M.mod_coursework.elementEnable();

    $('#id_deadline_enabled').on('change',function () {

        M.mod_coursework.elementEnable();
    });

    $('#id_personaldeadlineenabled').on('change',function () {

        M.mod_coursework.elementEnable();
    });



    $('#id_markingdeadlineenabled').on('change',function () {

        M.mod_coursework.elementEnable();
    });


    $('#id_numberofmarkers').on('change',function () {

        M.mod_coursework.elementEnable();
    });

    // Candidate number setting toggle
    M.mod_coursework.toggleCandidateNumberSetting();
    $('#id_renamefiles, #id_blindmarking').on('change', function() {
        M.mod_coursework.toggleCandidateNumberSetting();
    });
}


M.mod_coursework.elementEnable      =   function()      {
    const deadlineEnabledCheckbox = $('#id_deadline_enabled');
    const deadlineEnabled = deadlineEnabledCheckbox.length === 0 || deadlineEnabledCheckbox.is(':checked');
    // The deadline enabled checkbox will be hidden on the form if the deadline date field is forced on.
    // This would happen if an extension has already been granted to a user in this coursework.
    if (!deadlineEnabled) {
        M.mod_coursework.initialGradeDisable(true);
        M.mod_coursework.agreedGradeDisable(true);
        M.mod_coursework.personalDeadlineDisable(true);
        M.mod_coursework.relativeInitalGradeDisable(false);
        M.mod_coursework.relativeAgreedGradeDisable(false);
    } else {
        M.mod_coursework.initialGradeDisable(false);
        M.mod_coursework.agreedGradeDisable(false);
        M.mod_coursework.personalDeadlineDisable(false);
        M.mod_coursework.relativeInitalGradeDisable(true);
        M.mod_coursework.relativeAgreedGradeDisable(true);

    }


    if($( "#id_personaldeadlineenabled" ).is(':disabled') == false ){

        if(    $( "#id_personaldeadlineenabled" ).val() == 1) {
            M.mod_coursework.relativeInitalGradeDisable(false);
            M.mod_coursework.relativeAgreedGradeDisable(false);
            M.mod_coursework.initialGradeDisable(true);
            M.mod_coursework.agreedGradeDisable(true);
        } else {
            M.mod_coursework.relativeInitalGradeDisable(true);
            M.mod_coursework.relativeAgreedGradeDisable(true);
            M.mod_coursework.initialGradeDisable(false);
            M.mod_coursework.agreedGradeDisable(false);
        }
    }

    if(    $( "#id_markingdeadlineenabled" ).val() == 0) {
        M.mod_coursework.initialGradeDisable(true);
        M.mod_coursework.agreedGradeDisable(true);
        M.mod_coursework.relativeInitalGradeDisable(true);
        M.mod_coursework.relativeAgreedGradeDisable(true);
    }

    if(    $( "#id_numberofmarkers" ).val() == 1) {
        M.mod_coursework.agreedGradeDisable(true);
        M.mod_coursework.relativeAgreedGradeDisable(true);
    }



}

M.mod_coursework.toggleElementVisibility = function(elementId, disabled) {
    if(disabled === undefined) {
        disabled = true;
    }
    $('#' + elementId)[disabled ? 'addClass' : 'removeClass']('d-none');
}

M.mod_coursework.initialGradeDisable = function(disabled) {
    M.mod_coursework.toggleElementVisibility('fitem_id_initialmarkingdeadline', disabled);
}

M.mod_coursework.agreedGradeDisable = function(disabled) {
    M.mod_coursework.toggleElementVisibility('fitem_id_agreedgrademarkingdeadline', disabled);
}

M.mod_coursework.personalDeadlineDisable = function(disabled) {
    M.mod_coursework.toggleElementVisibility('fitem_id_personaldeadlineenabled', disabled);
}

M.mod_coursework.relativeInitalGradeDisable = function(disabled) {
    M.mod_coursework.toggleElementVisibility('fitem_id_relativeinitialmarkingdeadline', disabled);
}

M.mod_coursework.relativeAgreedGradeDisable = function(disabled) {
    M.mod_coursework.toggleElementVisibility('fitem_id_relativeagreedmarkingdeadline', disabled);
}

M.mod_coursework.toggleCandidateNumberSetting = function() {
    let blindMarking = $('#id_blindmarking').val();

    // Show when blindmarking=1.
    if (blindMarking === '1') {
        M.mod_coursework.toggleElementVisibility('fitem_id_usecandidate', false);
        M.mod_coursework.toggleElementVisibility('fitem_id_usecandidaterequires', false);
    } else {
        $('#id_usecandidate').val(0);
        M.mod_coursework.toggleElementVisibility('fitem_id_usecandidate', true);
        M.mod_coursework.toggleElementVisibility('fitem_id_usecandidaterequires', true);
    }
};

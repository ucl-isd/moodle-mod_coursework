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
}


M.mod_coursework.elementEnable      =   function()      {

    console.log($('#id_deadline_enabled').is(':checked'));



    if ($('#id_deadline_enabled').is(':checked') == false) {

        M.mod_coursework.initialGradeDisable(true);
        M.mod_coursework.agreedGradeDisable(true);
        M.mod_coursework.personalDeadlineDisable(true);
        M.mod_coursework.relativeInitalGradeDisable(false);
        M.mod_coursework.relativeAgreedGradeDisable(false);

    } else if ($('#id_deadline_enabled').is(':checked') == true) {

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

M.mod_coursework.initialGradeDisable  =   function(disabled) {

    if(disabled === undefined) {
        disabled = true;
    }

    $('#fitem_id_initialmarkingdeadline').addClass('d-none');

}



M.mod_coursework.agreedGradeDisable  =   function(disabled) {

    if(disabled === undefined) {
        disabled = true;
    }

    $('#fitem_id_agreedgrademarkingdeadline').addClass('d-none');
}

M.mod_coursework.personalDeadlineDisable  =   function(disabled) {

    if(disabled === undefined) {
        disabled = true;
    }

    $('#fitem_id_personaldeadlineenabled').addClass('d-none');

}



M.mod_coursework.relativeInitalGradeDisable  =   function(disabled) {

    if(disabled === undefined) {
        disabled = true;
    }

    $('#fitem_id_relativeinitialmarkingdeadline').addClass('d-none');

}

M.mod_coursework.relativeAgreedGradeDisable  =   function(disabled) {

    if(disabled === undefined) {
        disabled = true;
    }

    $('#fitem_id_relativeagreedmarkingdeadline').addClass('d-none');

}
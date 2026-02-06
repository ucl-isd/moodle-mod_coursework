// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This defines all javascript needed in the coursework module.
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2011 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
M.mod_coursework = {
    /**
     * This is to set up the listeners etc for the page elements on the allocations page.
     */
    init_allocate_page: function (e, wwwroot, coursemoduleid) {
        "use strict";

        // default select
        var $menuassessorallocationstrategy = $('#assessorallocationstrategy');
        var $selected = $menuassessorallocationstrategy.val();

        /// var $newname = '.assessor-strategy-options #assessor-strategy-' + $selected;
        var $newname = '#assessor-strategy-' + $selected;
        $($newname).css('display', 'block');

        // when page was refreshed, display current selection

        $(window).on('unload', function () {
            $menuassessorallocationstrategy.val($selected);
        })

        // Show the form elements that allow us to configure the allocatons
        $menuassessorallocationstrategy.on('change', function (e) {

            var newname = 'assessor-strategy-' + $(this).val();
            $('.assessor-strategy-options').each(function () {
                var $div = $(this);
                var divid = $div.attr('id');
                if (divid === newname) {
                    $div.css('display', 'block');
                } else {
                    $div.css('display', 'none');
                }
            });
        });

        $('#menumoderatorallocationstrategy').on('change', function (e) {
            var newname = 'moderator-strategy-' + $(this).val();

            $('.moderator-strategy-options').each(function () {
                var $div = $(this);
                var divid = $div.attr('id');
                if (divid === newname) {
                    $div.css('display', 'block');
                } else {
                    $div.css('display', 'none');
                }
            });
        });

        // Moderation set rules
        $('input[name=addmodsetruletype]').on('click', function (e) {
            var formdivname = 'rule-config-' + $(this).val();

            $('.rule-config').each(function () {

                var $div = $(this);
                var divid = $div.attr('id');
                if (divid === formdivname) {
                    $div.css('display', 'block');
                } else {
                    $div.css('display', 'none');
                }
            });
        });


        var AUTOMATIC_SAMPLING = 1;

        //assessor sampling strategy drop down
        $('.assessor_sampling_strategy').each(function (e, element) {
            var ele_id = $(this).attr('id').split('_');
            if ($(this).val() != AUTOMATIC_SAMPLING) {
                $('.' + ele_id[0] + '_' + ele_id[1]).each(function (n, ele) {

                    $(ele).attr('disabled', true);
                });
            }

            if ($(this).val() == AUTOMATIC_SAMPLING) {
                $('#' + ele_id[0] + '_' + ele_id[1] + "_automatic_rules").show();
            } else {
                $('#' + ele_id[0] + '_' + ele_id[1] + "_automatic_rules").hide();
            }

            $(element).on('change', function () {

                var ele_id = $(this).attr('id').split('_');

                var disabled = $(this).val() != AUTOMATIC_SAMPLING;

                var eleid = '.' + ele_id[0] + '_' + ele_id[1];

                $('.' + ele_id[0] + '_' + ele_id[1]).each(function (n, ele) {

                    $(ele).attr('disabled', disabled);
                });

                if ($(this).val() == AUTOMATIC_SAMPLING) {
                    $('#' + ele_id[0] + '_' + ele_id[1] + "_automatic_rules").show();
                } else {
                    $('#' + ele_id[0] + '_' + ele_id[1] + "_automatic_rules").hide();
                }
            })
        })


        $('#save_assessor_allocation_strategy').click(function (e) {
            e.preventDefault();
            console.log('reallocating allocatables');

            var customElement = $("<div>", {
                id: "countdown",
                css: {
                    "font-size": "15px",
                    "display": "block",
                    "padding-top": "90px"
                },
                text: 'The allocation strategy is being applied this may take some time please wait'
            });
            $("#coursework_input_buttons").append(customElement);
            $("#coursework_input_buttons").toggleClass('my_overlay');

            var allocationformdata = $('#allocation_form').serialize();
            allocationformdata = allocationformdata + "&coursemoduleid=" + coursemoduleid;
            $.ajax({
                url: wwwroot + "/mod/coursework/actions/processallocation.php",
                data: allocationformdata,
                method: 'POST',
                xhrFields: {
                    onprogress: function (e) {

                    }
                },
                success: function (text) {
                    $("#coursework_input_buttons").toggleClass('my_overlay');

                    location.reload(true);
                }
            });
        });

        $('#save_and_exit_assessor_allocation_strategy').click(function (e) {

            e.preventDefault();

            var customElement = $("<div>", {
                id: "countdown",
                css: {
                    "font-size": "15px",
                    "display": "block",
                    "padding-top": "90px"
                },
                text: 'The allocation strategy is being saved the page will exit shortly. Depending on the number of participants on this course you may not see the results of the allocations straight away in this event refresh the page'
            });
            $("#coursework_input_buttons").append(customElement);
            $("#coursework_input_buttons").toggleClass('my_overlay');

            var allocationformdata = $('#allocation_form').serialize();
            allocationformdata = allocationformdata + "&coursemoduleid=" + coursemoduleid;
            $.ajax({
                url: wwwroot + "/mod/coursework/actions/processallocation.php",
                data: allocationformdata,
                method: 'POST',
                xhrFields: {
                    onprogress: function (e) {


                    }
                },
                success: function (text) {

                }
            });

            /*  a 5 second delay has been placed before the window location is changed as exiting a page after ajax call
            *  can sometime lead to the call to the page being aborted. 5 seconds should be enought to allow the page call to occur*/

            setTimeout(function () {
                window.location = wwwroot + "/mod/coursework/view.php?id=" + coursemoduleid;
            }, 5000);
        });
    },


    /**
     * This is to set up the listeners etc for the page elements on the allocations page.
     */
    init_personaldeadlines_page: function () {

        $('#selectall').change(function () {

            if ($(this).is(":checked")) {

                $('.date_select').prop('checked', true);

            } else {

                $('.date_select').prop('checked', false);

            }

        });




        $('#selected_dates').click(function () {

            var dateselected = false;

            $('.date_select').each(function (n, element) {

                if ($(element).is(":checked")) {
                    dateselected = true;
                }

            })

            if (dateselected == true) {
                $('#selectedtype').val('date');
                $('#coursework_personaldeadline_form').submit();
            } else {
                alert('You must make at least one selection');
            }


        });

        $('#selected_unfinalise').click( function () {

            var unfinaliseselected = false;

            $('.date_select').each(function (n, element) {

                if ($(element).is(":checked")) {
                    unfinaliseselected = true;
                }

            })

            if (unfinaliseselected == true) {
                $('#selectedtype').val('unfinalise');


              $('#coursework_personaldeadline_form').submit();
            } else {
                alert('You must make at least one selection');
            }


        });
    },

    /**
     * Override core M.gradingform_rubric.levelclick as it does not allow clicks directly in radio buttons.
     * Used on actions/feedback/edit.php and actions/feedback/new.php.
     * See core grade/grading/form/rubric/js/rubric.js.
     */
    init_rubric_grading_workaround: function () {

        const originalInitFunc = M.gradingform_rubric.init;
        M.gradingform_rubric.init = function(Y, options) {
            originalInitFunc(Y, options);
            // Now reverse the inline 'display: none;' applied by core JS in previous line.
            Y.all('#fitem_id_advancedgrading #rubric-advancedgrading .level-wrapper div.radio').each( div => {
                div.setStyle('display', '')
            });
        }

        /**
         * The original levelclick function has funny stuff going on to cover the fact that the radio button is hidden.
         * Our version is simpler because the radio button is visible and clickable.
         * @param e
         * @param Y
         * @param name
         */
        M.gradingform_rubric.levelclick = function(e, Y, name) {
            var el = e.target

            // Get the parent "level" node for whatever element is clicked.
            while (el && !el.hasClass('level')) {
                el = el.get('parentNode')
            }

            if (!el) return
            el.siblings().removeClass('checked');
            el.addClass('checked')

            // Set aria-checked attribute for siblings to false.
            el.siblings().setAttribute('aria-checked', 'false');
            el.setAttribute('aria-checked', 'true');

            // If the radio button itself was not clicked (but the surrounding div), check the radio button.
            let chb = el.one('input[type=radio]')
            if (!chb.get('checked')) {
                chb.set('checked', true)
            }
        };
    }
};

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
 * @copyright  2011 University of London Computer Centre {@link http://ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
M.mod_coursework = {


    /**
     * This is to set up the listeners etc for the page elements on the allocations page.
     */
    init_allocate_page : function () {
        "use strict";


        // Make the changes to the moderation set dropdowns set the 'in moderation set'
        // checkboxes automatically.
        $('.assessor_id_dropdown').change(function() {

            var $dropdown = $(this);
            var $checkbox = $dropdown.prevAll('.sampling_set_checkbox');

           var $currentselection = $dropdown.attr('id');

            if ($checkbox.length) {
                if ($dropdown.val() === '') {
                    $checkbox.prop('checked', false);
                } else {
                    $checkbox.prop('checked', true);
                }
            }

            // warning if same assessors are selected in the same row
            var $row = $dropdown.closest('tr');
            var $selected_val = $dropdown.val();

            //compare each element in the row
            $row.find('td').each(function() {

                // dropdown
                var $celldropdown = $(this).find('.assessor_id_dropdown');
                var $celldropdown_id = $celldropdown.attr('id');
                var $celldropdown_val = $celldropdown.val();
                // link
                var $atag = $(this).find('a');
                var $id_from_label = $atag.data('assessorid');

               if ($currentselection != $celldropdown_id && ($celldropdown_val == $selected_val || $id_from_label == $selected_val)){
                  // alert('Assessor already allocated. \n Choose different assessor.');
                  $( '<div id="same_assessor" class="alert">'+M.util.get_string('sameassessorerror', 'coursework')+'</div>' ).insertAfter($('#'+$currentselection));
                   $dropdown.val('');
               } else if($dropdown.val() != ''){
                   $( "#same_assessor" ).remove();
               }
            });
        });


        // Unchecked 'Include in sample' checkbox disables
        // dropdown automatically.
        $('.sampling_set_checkbox').click(function() {

            var $checkbox = $(this);
            var $dropdown = $checkbox.nextAll('.assessor_id_dropdown');
            var $pinned = $checkbox.nextAll('.existing-assessor');
            var $child = $pinned.children('.pinned');

            if ($dropdown.length) {
                if ($checkbox.is(":checked")) {
                    $dropdown.prop("disabled", false);
                    $child.prop("disabled", false);

                } else {
                    $dropdown.val('');
                    $dropdown.prop("disabled", true);
                    $child.prop("disabled", true);
                    $("#same_assessor" ).remove();
                }
            }
        });

        // default select
        var $menuassessorallocationstrategy = $('#menuassessorallocationstrategy');
        var $selected = $menuassessorallocationstrategy.val();

       /// var $newname = '.assessor-strategy-options #assessor-strategy-' + $selected;
        var $newname = '#assessor-strategy-' + $selected;
        $($newname).css('display', 'block');

        // when page was refreshed, display current selection

        $(window).on('unload',function()    {
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

        // Allocation widgets
        var allPanels = $('.accordion > div').hide();
        $('.accordion > h3').click(function () {
            if ($(this).next().is(":visible")) {
                allPanels.slideUp();
            } else {
                allPanels.slideUp();

                $(this).next().slideDown();
            }
            return false;
        });


        var AUTOMATIC_SAMPLING  =   1;







        //assessor sampling strategy drop down
        $('.assessor_sampling_strategy').each(function(e,element) {
            var ele_id = $(this).attr('id').split('_');
            if ($(this).val() != AUTOMATIC_SAMPLING) {



                $('.'+ele_id[0]+'_'+ele_id[1]).each(function(n,ele)  {

                    $(ele).attr('disabled',true);
                });

            }

            if ($(this).val() == AUTOMATIC_SAMPLING) {
                $('#' + ele_id[0] + '_' + ele_id[1] + "_automatic_rules").show();
            } else {
                $('#' + ele_id[0] + '_' + ele_id[1] + "_automatic_rules").hide();
            }


            $(element).on('change',function()   {

                var ele_id = $(this).attr('id').split('_');

                var disabled =  $(this).val() != AUTOMATIC_SAMPLING;

                var eleid = '.'+ele_id[0]+'_'+ele_id[1];

                $('.'+ele_id[0]+'_'+ele_id[1]).each(function(n,ele)  {

                    $(ele).attr('disabled',disabled);
                });

                if ($(this).val() == AUTOMATIC_SAMPLING) {
                    $('#' + ele_id[0] + '_' + ele_id[1] + "_automatic_rules").show();
                } else {
                    $('#' + ele_id[0] + '_' + ele_id[1] + "_automatic_rules").hide();
                }



            })
        })





    },


    /**
     * This is to set up the listeners etc for the page elements on the allocations page.
     */
    init_personal_deadlines_page : function () {

        $('#selectall').change(function() {

            if($(this).is(":checked")) {

                $('.date_select').prop('checked',true);

            } else {

                $('.date_select').prop('checked',false);

            }

        })


        $('#selected_dates').click( function () {

            var dateselected = false;

            $('.date_select').each(function (n, element) {

                if ($(element).is(":checked")) {
                    dateselected = true;
                }

            })

            if (dateselected == true) {
                $('#coursework_personal_deadline_form').submit();
            } else {
                alert('You must make at least one selection');
            }


        });




    }
};





define('mod_coursework/coursework_edit',
    ['jquery', 'core/notification', 'core/modal'], function($, Notification, Modal) {
    return {
        init: function() {
// Add the init function:
            /**
             *
             * @param {object} row
             * @returns {boolean}
             */
            function compare_row(row) {
                return (this == row.DT_RowId);
            }


            var now = new Date();
            var extension_form_change = false;
            window.addEventListener('beforeunload', (table_obj_list) => {
                if (table_obj_list) {
                    for (var table_id in table_obj_list) {
                        table_obj_list[table_id].state.save();
                    }
                }
            });
            $(document).ready(function() {
                /**
                 *
                 * @param {object} tr
                 */
                function log_datatable_navigate(tr) {
                    var row_id = tr.attr('id');
                    var tableid = tr.closest('table').attr('id');
                    var key = 'datatable_navigate_' + window.location.href + tableid;
                    localStorage.setItem(key, row_id);
                }

                /* plagiarism flag */
                $('.datatabletest').on('click', '.new_plagiarism_flag', function() {
                    log_datatable_navigate($(this).closest('tr'));
                });

                $('.datatabletest').on('click', '.edit_plagiarism_flag', function() {
                    log_datatable_navigate($(this).closest('tr'));
                });

                /* feedback */
                $('.datatabletest').on('click', '.new_final_feedback', function() {
                    log_datatable_navigate($(this).closest('tr'));
                });

                $('.datatabletest').on('click', '.edit_final_feedback', function() {
                    log_datatable_navigate($(this).closest('tr'));
                });

                $('.datatabletest').on('click', '.show_feedback', function() {
                    log_datatable_navigate($(this).closest('tr'));
                });

                /* assessor feedback */
                $('.datatabletest').on('click', '.assessor_feedback_grade .new_feedback', function() {
                    log_datatable_navigate($(this).closest('td.assessors').closest('table.assessors').closest('tr').prev());
                });

                $('.datatabletest').on('click', '.assessor_feedback_grade .show_feedback', function() {
                    log_datatable_navigate($(this).closest('td.assessors').closest('table.assessors').closest('tr').prev());
                });

                $('.datatabletest').on('click', '.assessor_feedback_grade .edit_feedback', function() {
                    log_datatable_navigate($(this).closest('td.assessors').closest('table.assessors').closest('tr').prev());
                });

                /* deadline extension */
                $('.datatabletest').on('click', '.new_deadline_extension', function() {
                    log_datatable_navigate($(this).closest('tr'));
                });

                $('.datatabletest').on('click', '.edit_deadline_extension', function() {
                    log_datatable_navigate($(this).closest('tr'));
                });

                /* submission */
                $('.datatabletest').on('click', '.new_submission', function() {
                    log_datatable_navigate($(this).closest('tr'));
                });

                // Prepare Message
                var datatables_lang_messages = JSON.parse($('#datatables_lang_messages').attr('data-lang'));

                /**
                 * Personal Deadline
                 */
                $('.datatabletest').on('click', '.edit_personal_deadline',function(e) {
                    e.preventDefault();
                    var parent = $(this).closest('.personal_deadline_cell');
                    parent.children('.show_personal_dealine').addClass('display-none');
                    var change_form = parent.children('.show_edit_personal_dealine');
                    var data_get = $(this).attr('data-get');
                    var data_time = $(this).attr('data-time-iso-8601');
                    if (change_form.html().length === 1) {
                        var form = '<input type="datetime-local" step="300" class="input-personal-deadline"' +
                            ' value="' + data_time + '">';
                        form += '<div class="personal_deadline_button"><a href="/" class="approve-personal-deadline" data-get=';
                        form += data_get;
                        form += '><i class="fa fa-check" aria-hidden="true"></i></a>';
                        form += '<a href="/" class="cancel-personal-deadline"><i class="fa fa-times" aria-hidden="true">' +
                            '</i></a></div>';
                        $(change_form).html(form);
                    }
                    $(change_form).removeClass('display-none');
                });

                $('.datatabletest').on('click', '.cancel-personal-deadline', function(e) {
                    e.preventDefault();
                    $(this).closest('.show_edit_personal_dealine').addClass('display-none');
                    $(this).closest('.personal_deadline_cell').children('.show_personal_dealine').removeClass('display-none');
                });

                $('.datatabletest').on('click', '.approve-personal-deadline', function(e) {
                    e.preventDefault();
                    var deadline = $(this);
                    var data_get = $(deadline).attr('data-get');
                    var value = $(deadline).closest('.show_edit_personal_dealine').children('.input-personal-deadline').val();
                    var input_date = new Date(value);
                    if (input_date <= Date.parse(now)) {
                        Modal.create({
                            title: datatables_lang_messages.notification_info,
                            body: datatables_lang_messages.alert_validate_deadline.replace(/\_/g, ' '),
                            show: true,
                            removeOnClose: true,
                        });
                    }

                    var url = datatables_lang_messages.url_root + "/mod/coursework/actions/personal_deadline.php";
                    var param = JSON.parse(data_get);
                    param.personal_deadline_time = value;

                    $.ajax({
                        type: "POST",
                        url: url,
                        data: param,
                        beforeSend: function() {
                            $('html, body').css("cursor", "wait");
                            $(self).prev('img').css('visibility', 'visible');
                        },
                        success: function(response, table_obj_list) {
                            $('html, body').css("cursor", "auto");
                            var data_response = JSON.parse(response);
                            if (data_response.error === 1) {
                                Modal.create({
                                    title: datatables_lang_messages.notification_info,
                                    body: data_response.message,
                                    show: true,
                                    removeOnClose: true,
                                });
                            } else {
                                var parent = $(deadline).closest('.personal_deadline_cell');
                                $(parent).attr('data-order', data_response.timestamp);
                                var table = table_obj_list[Object.keys(table_obj_list)[0]];
                                table.row('#' + $(parent).closest('tr').attr('id')).invalidate();

                                $(parent).children('.show_personal_dealine').children('.content_personal_deadline').
                                html(data_response.time);
                                $(parent).children('.show_edit_personal_dealine').addClass('display-none');
                                $(parent).children('.show_personal_dealine').removeClass('display-none');
                                Modal.create({
                                    title: datatables_lang_messages.notification_info,
                                    body: datatables_lang_messages.alert_personaldeadline_save_successful.replace(/\_/g, ' '),
                                    show: true,
                                    removeOnClose: true,
                                });
                            }
                        },
                        error: function() {
                            $('html, body').css("cursor", "auto");
                        },
                        complete: function() {
                            $('html, body').css("cursor", "auto");
                        }
                    });

                });

                /***************************
                 * Extensions
                 */

                /**
                 * Add new extension
                 */
                $('.datatabletest').on('click', '.new_deadline_extension', function(e) {
                    e.preventDefault();
                    var data_name = $(this).attr('data-name');
                    var data_params = JSON.parse($(this).attr('data-params'));
                    var data_time = JSON.parse($(this).attr('data-time'));
                    var current_rowid = $(this).closest('tr').attr('id');
                    extension_new_change_data_form(data_name, data_params, data_time, current_rowid);
                    $('#modal-ajax').modal('show');
                });

                /**
                 * Edit extensions
                 */
                $('.datatabletest').on('click', '.edit_deadline_extension', function(e) {

                    e.preventDefault();
                    var data_name = $(this).attr('data-name');
                    var data_params = JSON.parse($(this).attr('data-params'));
                    var data_time = JSON.parse($(this).attr('data-time'));
                    var current_rowid = $(this).closest('tr').attr('id');
                    extension_edit_change_data_form(data_name, data_params, data_time, current_rowid);
                    $('#modal-ajax').modal('show');
                });

                /**
                 * Submit save extension
                 */
                $('.modal-footer').on('click', '#extension-submit', function(e) {
                    e.preventDefault();
                    var params = {};
                    params.allocatabletype = $('#extension-allocatabletype').val();
                    params.allocatableid = $('#extension-allocatableid').val();
                    params.courseworkid = $('#extension-courseworkid').val();
                    params.id = $('#extension-id').val();
                    params.extended_deadline = $('#extension-extend-deadline').val();
                    params.editor = $('#extension-time-content').html();
                    params.text = $('#id_extra_information').val();
                    params.submissionid = $('#extension-submissionid').val();
                    params.pre_defined_reason = $('#extension-reason-select').val();
                    params.requesttype = 'submit';
                    var current_rowid = $('#button-id').val();
                    var url = datatables_lang_messages.url_root;
                    $.ajax({
                        type: "POST",
                        url: url + "/mod/coursework/actions/ajax/deadline_extension/submit.php",
                        data: params,
                        beforeSend: function() {
                            $('html, body').css("cursor", "wait");
                            $('.modal-footer').children('img').css('visibility', 'visible');
                        },
                        success: function(response, table_obj_list) {
                            var data_response = JSON.parse(response);
                            $('html, body').css("cursor", "auto");
                            $('.modal-footer').children('img').css('visibility', 'hidden');
                            if (data_response.error == 1) {
                                Modal.create({
                                    title: datatables_lang_messages.notification_info,
                                    body: data_response.messages,
                                    show: true,
                                    removeOnClose: true,
                                });
                            } else {
                                if (Object.keys(table_obj_list).length > 0) {
                                    // Get the first datatable object.
                                    var table = table_obj_list[Object.keys(table_obj_list)[0]];
                                    if (table.row) {
                                        var current_row_data = table.row('#' + current_rowid).data();
                                        var submissiondateindex = table.column('.tableheaddate').index();
                                        var current_moderation_cell_data = data_response.content;
                                        current_row_data[submissiondateindex] = current_moderation_cell_data;
                                        var table_row = table.row('#' + current_rowid);
                                        table_row.data(current_row_data);
                                        var dom_row = $('#' + current_rowid);
                                        dom_row.find('.time_submitted_cell').attr('data-order',
                                            current_moderation_cell_data['@data-order']);
                                        dom_row.find('.edit_personal_deadline').remove();
                                        table_row.invalidate();
                                        $('#extension-id').val(data_response.data.id);
                                    }
                                }

                                change__status_extension_submit_button(true);
                                save_extension_form_data();

                                Modal.create({
                                    title: datatables_lang_messages.notification_info,
                                    body: datatables_lang_messages.alert_extension_save_successful.replace(/\_/g, ' '),
                                    show: true,
                                    removeOnClose: true,
                                });

                            }
                        },
                        error: function() {
                            $('html, body').css("cursor", "auto");
                        },
                        complete: function() {
                            $('html, body').css("cursor", "auto");
                        }
                    });
                });


                /**
                 * Function close button
                 */
                $('#modal-ajax').on('hide.bs.modal', function() {
                    var self = this;
                    if (is_data_extension_form_change()) {
                        var confirm = new M.core.confirm({
                            title: datatables_lang_messages.notification_leave_form_title.replace(/\_/g, ' '),
                            question: datatables_lang_messages.notification_leave_form_message.replace(/\_/g, ' '),
                            yesLabel: datatables_lang_messages.notification_yes_label,
                            noLabel: datatables_lang_messages.notification_no_label,
                        });

                        confirm.on('complete-yes',function() {
                            save_extension_form_data();
                            confirm.hide();
                            confirm.destroy();
                            $(self).modal('hide');
                        });

                        confirm.on('complete-no',function() {
                            confirm.hide();
                            confirm.destroy();
                            return false;
                        });

                        confirm.show();
                        return false;
                    }
                    return true;
                });

                /**
                 * Function next button
                 */
                $('.modal-footer').on('click', '#extension-next', function(e, table_obj_list) {
                    e.preventDefault();

                    if (is_data_extension_form_change()) {
                        var confirm = new M.core.confirm({
                            title: datatables_lang_messages.notification_leave_form_title.replace(/\_/g, ' '),
                            question: datatables_lang_messages.notification_leave_form_message.replace(/\_/g, ' '),
                            yesLabel: datatables_lang_messages.notification_yes_label,
                            noLabel: datatables_lang_messages.notification_no_label,
                        });

                        confirm.on('complete-yes', function(table_obj_list) {
                            confirm.hide();
                            confirm.destroy();
                            if (Object.keys(table_obj_list).length > 0) {

                                var prev_rowid = $('#button-id').val();

                                // Get the first datatable object.
                                var table = table_obj_list[Object.keys(table_obj_list)[0]];

                                var prev_row_index = table.row('#' + prev_rowid).index();


                                var current_row_index = prev_row_index + 1;

                                if (table.row(current_row_index)) {
                                    var current_row_data = table.row(current_row_index).data();
                                    if (current_row_data) {
                                        var current_rowid = table.row(current_row_index).id();

                                        var submissiondateindex = table.column('.tableheaddate').index();
                                        var current_cell_data = current_row_data[submissiondateindex];
                                        if (current_cell_data) {
                                            var tmp_node = $('<div/>').html(current_cell_data.display);
                                            var submisiondate = $(tmp_node).find('.new_deadline_extension');
                                            if (submisiondate.length > 0) {
                                                var data_params = JSON.parse(submisiondate.attr('data-params'));
                                                var data_name = submisiondate.attr('data-name');
                                                var data_time = JSON.parse(submisiondate.attr('data-time'));
                                                extension_new_change_data_form(data_name, data_params, data_time, current_rowid);
                                            } else {
                                                submisiondate = $(tmp_node).find('.edit_deadline_extension');
                                                var data_params = JSON.parse(submisiondate.attr('data-params'));
                                                var data_name = submisiondate.attr('data-name');
                                                var data_time = JSON.parse(submisiondate.attr('data-time'));
                                                extension_edit_change_data_form(data_name, data_params, data_time, current_rowid);
                                            }
                                        }
                                    }
                                    else {
                                        $('#extension-next').prop('disabled', true);
                                        Modal.create({
                                            title: datatables_lang_messages.notification_info,
                                            body: datatables_lang_messages.alert_no_extension.replace(/\_/g, ' '),
                                            show: true,
                                            removeOnClose: true,
                                        });
                                    }
                                }
                            }
                        });

                        confirm.on('complete-no', function() {
                            confirm.hide();
                            confirm.destroy();

                        });

                        confirm.show();
                    } else {
                        if (Object.keys(table_obj_list).length > 0) {

                            var prev_rowid = $('#button-id').val();

                            // Get the first datatable object.
                            var table = table_obj_list[Object.keys(table_obj_list)[0]];

                            var ordereddata = table.rows({order: 'applied', search: 'applied'}).data().toArray();
                            var prev_row_index = ordereddata.findIndex(compare_row, prev_rowid);


                            var current_row_index = prev_row_index + 1;

                            if (table.row(current_row_index)) {
                                var current_row_data = ordereddata[current_row_index];
                                if (typeof current_row_data != 'undefined') {
                                    var current_rowid = current_row_data.DT_RowId;

                                    var submissiondateindex = table.column('.tableheaddate').index();
                                    var current_cell_data = current_row_data[submissiondateindex];
                                    if (current_cell_data) {
                                        var tmp_node = $('<div/>').html(current_cell_data.display);
                                        var submisiondate = $(tmp_node).find('.new_deadline_extension');
                                        if (submisiondate.length > 0) {
                                            var data_params = JSON.parse(submisiondate.attr('data-params'));
                                            var data_name = submisiondate.attr('data-name');
                                            var data_time = JSON.parse(submisiondate.attr('data-time'));
                                            extension_new_change_data_form(data_name, data_params, data_time, current_rowid);
                                        } else {
                                            submisiondate = $(tmp_node).find('.edit_deadline_extension');
                                            var data_params = JSON.parse(submisiondate.attr('data-params'));
                                            var data_name = submisiondate.attr('data-name');
                                            var data_time = JSON.parse(submisiondate.attr('data-time'));
                                            extension_edit_change_data_form(data_name, data_params, data_time, current_rowid);
                                        }
                                    }
                                }
                                else {
                                    $('#extension-next').prop('disabled', true);
                                    Modal.create({
                                        title: datatables_lang_messages.notification_info,
                                        body: datatables_lang_messages.alert_no_mitigation.replace(/\_/g, ' '),
                                        show: true,
                                        removeOnClose: true,
                                    });
                                }
                            }
                        }
                    }


                });

                /**
                 * Function back button
                 */
                $('.modal-footer').on('click', '#extension-back', function(e, table_obj_list) {
                    e.preventDefault();
                    if (is_data_extension_form_change()) {
                        var confirm = new M.core.confirm({
                            title: datatables_lang_messages.notification_leave_form_title.replace(/\_/g, ' '),
                            question: datatables_lang_messages.notification_leave_form_message.replace(/\_/g, ' '),
                            yesLabel: datatables_lang_messages.notification_yes_label,
                            noLabel: datatables_lang_messages.notification_no_label,
                        });

                        confirm.on('complete-yes', function(table_obj_list) {
                            confirm.hide();
                            confirm.destroy();
                            if (Object.keys(table_obj_list).length > 0) {

                                var prev_rowid = $('#button-id').val();

                                // Get the first datatable object.
                                var table = table_obj_list[Object.keys(table_obj_list)[0]];

                                var prev_row_index = table.row('#' + prev_rowid).index();


                                var current_row_index = prev_row_index - 1;

                                if (table.row(current_row_index)) {
                                    var current_row_data = table.row(current_row_index).data();
                                    if (current_row_data) {
                                        var current_rowid = table.row(current_row_index).id();

                                        var submissiondateindex = table.column('.tableheaddate').index();
                                        var current_cell_data = current_row_data[submissiondateindex];
                                        if (current_cell_data) {
                                            var tmp_node = $('<div/>').html(current_cell_data.display);
                                            var submisiondate = $(tmp_node).find('.new_deadline_extension');
                                            if (submisiondate.length > 0) {
                                                var data_params = JSON.parse(submisiondate.attr('data-params'));
                                                var data_name = submisiondate.attr('data-name');
                                                var data_time = JSON.parse(submisiondate.attr('data-time'));
                                                extension_new_change_data_form(data_name, data_params, data_time, current_rowid);
                                            } else {
                                                submisiondate = $(tmp_node).find('.edit_deadline_extension');
                                                var data_params = JSON.parse(submisiondate.attr('data-params'));
                                                var data_name = submisiondate.attr('data-name');
                                                var data_time = JSON.parse(submisiondate.attr('data-time'));
                                                extension_edit_change_data_form(data_name, data_params, data_time, current_rowid);
                                            }
                                        }
                                    }
                                    else {
                                        $('#extension-back').prop('disabled', true);
                                        Modal.create({
                                            title: datatables_lang_messages.notification_info,
                                            body: datatables_lang_messages.alert_no_mitigation.replace(/\_/g, ' '),
                                            show: true,
                                            removeOnClose: true,
                                        });
                                    }
                                }
                            }
                        });

                        confirm.on('complete-no', function() {
                            confirm.hide();
                            confirm.destroy();
                        });

                        confirm.show();
                    } else {
                        if (Object.keys(table_obj_list).length > 0) {

                            var prev_rowid = $('#button-id').val();

                            // Get the first datatable object.
                            var table = table_obj_list[Object.keys(table_obj_list)[0]];

                            var ordereddata = table.rows( { order: 'applied', search: 'applied' } ).data().toArray();
                            var prev_row_index = ordereddata.findIndex(compare_row, prev_rowid);


                            var current_row_index = prev_row_index - 1;

                            if (table.row(current_row_index)) {
                                var current_row_data = ordereddata[current_row_index];
                                if (typeof current_row_data != 'undefined') {
                                    var current_rowid = current_row_data.DT_RowId;

                                    var submissiondateindex = table.column('.tableheaddate').index();
                                    var current_cell_data = current_row_data[submissiondateindex];
                                    if (current_cell_data) {
                                        var tmp_node = $('<div/>').html(current_cell_data.display);
                                        var submisiondate = $(tmp_node).find('.new_deadline_extension');
                                        if (submisiondate.length > 0) {
                                            var data_params = JSON.parse(submisiondate.attr('data-params'));
                                            var data_name = submisiondate.attr('data-name');
                                            var data_time = JSON.parse(submisiondate.attr('data-time'));
                                            extension_new_change_data_form(data_name, data_params, data_time, current_rowid);
                                        } else {
                                            submisiondate = $(tmp_node).find('.edit_deadline_extension');
                                            var data_params = JSON.parse(submisiondate.attr('data-params'));
                                            var data_name = submisiondate.attr('data-name');
                                            var data_time = JSON.parse(submisiondate.attr('data-time'));
                                            extension_edit_change_data_form(data_name, data_params, data_time, current_rowid);
                                        }
                                    }
                                }
                                else {
                                    $('#extension-back').prop('disabled', true);
                                    Modal.create({
                                        title: datatables_lang_messages.notification_info,
                                        body: datatables_lang_messages.alert_no_mitigation.replace(/\_/g, ' '),
                                        show: true,
                                        removeOnClose: true,
                                    });
                                }
                            }
                        }
                    }


                });

                /**
                 *
                 * @param {string} data_name
                 * @param {object} data_params
                 * @param {string} data_time
                 * @param {string} current_rowid
                 */
                function extension_edit_change_data_form(data_name, data_params, data_time, current_rowid) {
                    var title = 'Editing the extension for ' + data_name;
                    var time_content = 'Default deadline: ' + data_time.time_content;
                    $('#extension-modal-title').html(title);
                    $('#form-extension').find('input[type=hidden]').val("");
                    $('#form-extension').find('textarea').val("");
                    $('#button-id').val(current_rowid);
                    $('#extension-submissionid').val(data_params.submissionid);
                    $('#extension-name').val(data_name);
                    data_params.requesttype = 'edit';
                    var url = datatables_lang_messages.url_root;
                    $.ajax({
                        type: "GET",
                        url: url + "/mod/coursework/actions/ajax/deadline_extension/edit.php",
                        data: data_params,
                        beforeSend: function() {
                            change__status_extension_submit_button(true);
                            $('html, body').css("cursor", "wait");
                            $('.modal-footer').children('img').css('visibility', 'visible');
                        },
                        success: function(response) {
                            var data_response = JSON.parse(response);
                            $('html, body').css("cursor", "auto");
                            $('.modal-footer').children('img').css('visibility', 'hidden');
                            if (data_response.error == 1) {
                                Modal.create({
                                    title: datatables_lang_messages.notification_info,
                                    body: data_response.message + ' .Please reload the page!',
                                    show: true,
                                    removeOnClose: true,
                                });
                            } else {
                                var extension = data_response.data;
                                if (extension.time_content) {
                                    $('#extension-time-content').html(extension.time_content);
                                } else {
                                    $('#extension-time-content').html(time_content);
                                }
                                document.getElementById('extension-extend-deadline').
                                    value = data_response.data.time_iso_8601.slice(0, 16);
                                $('#extension-reason-select').val(extension.pre_defined_reason);
                                $('#extension-allocatabletype').val(extension.allocatabletype);
                                $('#extension-allocatableid').val(extension.allocatableid);
                                $('#extension-courseworkid').val(extension.courseworkid);
                                $('#extension-id').val(extension.id);

                                $('#id_extra_information').val(extension.text);

                                $('#id_extra_information').prop('disabled', false);
                                $('#extension-extend-deadline').prop('disabled', false);
                                $('#extension-reason-select').prop('disabled', false);
                                save_extension_form_data();
                            }
                        },
                        error: function() {
                            $('html, body').css("cursor", "auto");
                            change__status_extension_submit_button(false);
                        },
                        complete: function() {
                            $('html, body').css("cursor", "auto");
                            change__status_extension_submit_button(false);
                        }
                    });
                }

                /**
                 *
                 * @param {string} data_name
                 * @param {object} data_params
                 * @param {string} data_time
                 * @param {string} current_rowid
                 */
                function extension_new_change_data_form(data_name, data_params, data_time, current_rowid) {
                    var title = 'New extension for ' + data_name;
                    $('#extension-modal-title').html(title);
                    $('#form-extension').find('input[type=hidden]').val('');
                    $('#form-extension').find('textarea').val('');

                    if (data_time.is_have_deadline == '1') {
                        var url = datatables_lang_messages.url_root;
                        $.ajax({
                            type: "GET",
                            url: url + "/mod/coursework/actions/ajax/deadline_extension/new.php",
                            data: data_params,
                            beforeSend: function() {
                                change__status_extension_submit_button(true);
                                $('html, body').css("cursor", "wait");
                                $('.modal-footer').children('img').css('visibility', 'visible');
                            },
                            success: function(response) {
                                $('html, body').css("cursor", "auto");
                                $('.modal-footer').children('img').css('visibility', 'hidden');
                                var data_response = JSON.parse(response);
                                $('#extension-time-content').html(data_response.data.time_content);
                                document.getElementById('extension-extend-deadline').
                                    value = data_response.data.time_iso_8601.slice(0,16);
                                save_extension_form_data();
                            },
                            error: function() {
                                $('html, body').css("cursor", "auto");
                            },
                            complete: function() {
                                $('html, body').css("cursor", "auto");
                            }
                        });
                    } else {
                        save_extension_form_data();
                    }
                    $('#extension-reason-select').val('');
                    $('#extension-allocatabletype').val(data_params.allocatabletype);
                    $('#extension-allocatableid').val(data_params.allocatableid);
                    $('#extension-courseworkid').val(data_params.courseworkid);
                    $('#extension-submissionid').val(data_params.submissionid);
                    $('#extension-name').val(data_name);
                    $('#button-id').val(current_rowid);

                    $('#id_extra_information').prop('disabled', false);
                    $('#extension-extend-deadline').prop('disabled', false);
                    $('#extension-reason-select').prop('disabled', false);
                }


                $("#form-extension :input").change(function() {
                    extension_form_change = true;
                    change__status_extension_submit_button(false);
                });

                /**
                 *
                 * @param {string} status
                 */
                function change__status_extension_submit_button(status) {
                    $('#extension-submit').prop('disabled', status);
                }

                /**
                 *
                 */
                function save_extension_form_data() {
                    extension_form_change = false;
                }

                /**
                 *
                 */
                function is_data_extension_form_change() {
                    return extension_form_change;
                }

                /**
                 * Feedback
                 */
                $('.datatabletest').on('click', '.new_final_feedback, .new_feedback,' +
                    ' .edit_final_feedback, .edit_feedback, .show_feedback', function(e) {
                    e.preventDefault();
                    var url = $(this).attr('href');
                    $.ajax({
                        type: "GET",
                        url: url + '&ajax=1'
                    }).done(function(response) {
                        response = $.parseJSON(response);
                        var modalbody = $('#modal-grading').find('.modal-body');
                        // Careful as not all requests return a response.success value.  Only if it's false, show error.
                        if ((response.success ?? true) === false && (response.message ?? null)) {
                            modalbody.html(response.message);
                        } else {
                            modalbody.html(response.formhtml);
                            var filemanager = modalbody.find('.filemanager');
                            if (response.filemanageroptions && filemanager.length) {
                                var elementid = filemanager.attr('id');
                                var clientid = elementid.substr(12);
                                if (clientid) {
                                    response.filemanageroptions.client_id = clientid;
                                    M.form_filemanager.init(Y, response.filemanageroptions);
                                }
                            }
                            if (response.editoroptions) {
                                require(['editor_tiny/editor'], (Tiny) => {
                                    Tiny.setupForElementId({
                                        elementId: 'id_feedbackcomment',
                                        options: JSON.parse(response.editoroptions),
                                    });
                                });
                            }
                            if (response.gdata) {
                                const gdata = response.gdata;
                                const rows = Object.values(gdata);
                                rows.forEach(function(row) {
                                    const elementName = "advancedgrading-criteria-" + row.criterionid ;
                                    const elementLevel = document.getElementById(elementName + "-levels-" + row.avglevel).classList;
                                    if (elementLevel) {
                                        elementLevel.add('currentchecked');
                                    }
                                    document.getElementById(elementName + "-levels-" + row.avglevel + "-definition").checked = true;
                                    document.getElementById(elementName + "-grade").value = row.avggrade;
                                });
                            }
                            if (response.commentoptions) {
                                M.util.js_pending('gradingform_guide/comment_chooser');
                                require(['gradingform_guide/comment_chooser'], function(amd) {
                                    $(".remark").each(function(i, ele) {
                                        var buttonele = $(ele).find(".commentchooser");
                                        var textele = $(ele).find(".markingguideremark");
                                        var buttonid = $(buttonele).attr("id");
                                        var textid = $(textele).attr("id");
                                        amd.initialise(1, buttonid, textid, response.commentoptions);
                                        M.util.js_complete('gradingform_guide/comment_chooser');

                                    });

                                });
                            }
                        }
                    });
                    var cell_td = $(this).closest('td');
                    var cell_selector = get_td_cell_selector(cell_td);
                    var cell_type = cell_td.attr('data-class-name');
                    show_loading_modal_grading(cell_selector, cell_type);
                });

                /**
                 *
                 * @param {object} td_cell
                 * @returns {string}
                 */
                function get_td_cell_selector(td_cell) {
                    var result = '.' + td_cell.attr('class').replaceAll(' ', '.');
                    var tr_cell = td_cell.closest('tr');
                    if (tr_cell.attr('id')) {
                        result = '#' + tr_cell.attr('id') + ' ' + result;
                    } else {
                        result = '.' + tr_cell.attr('class').replaceAll(' ', '.') + ' ' + result;
                    }
                    return result;
                }

                /**
                 *
                 * @param {object} cell_selector
                 * @param {object} cell_type
                 */
                function show_loading_modal_grading(cell_selector, cell_type) {
                    // Set row id.
                    var modal = $('#modal-grading');
                    modal.find('#cell_selector').val(cell_selector);
                    modal.find('#cell_type').val(cell_type);
                    modal.find('.modal-body').html('<i class="fa fa-spin fa-spinner"></i> loading');
                    $('#modal-grading').modal('show');
                }

                $('#modal-grading').on('click', '#id_submitfeedbackbutton, #id_submitbutton', function(e) {
                    e.preventDefault();
                    var button = $(this);
                    button.prop('disabled', true);
                    var submitbutton = (button.attr('id') == 'id_submitbutton') ? 1 : 0;
                    var removefeedbackbutton = (button.attr('id') == 'id_removefeedbackbutton') ? 1 : 0;
                    var submitfeedbackbutton = (button.attr('id') == 'id_submitfeedbackbutton') ? 1 : 0;
                    var modal = $('#modal-grading');
                    var url = '/mod/coursework/actions/feedbacks/create.php';
                    var form_data = modal.find('form').serializeArray();
                    for (var i = 0, length = form_data.length; i < length; i++) {
                        if (form_data[i].name == 'feedbackid' && !isNaN(parseInt(form_data[i].value)) &&
                                form_data[i].value != '0') {
                            url = '/mod/coursework/actions/feedbacks/update.php';
                            break;
                        }
                    }
                    var cell_type = modal.find('#cell_type').val();
                    update_feedback(form_data, url, cell_type, submitbutton, removefeedbackbutton, submitfeedbackbutton, 0, button);
                    /*
                    form_data = form_data.concat({name: 'ajax', value: 1},
                        {name: 'cell_type', value: cell_type},
                        {name: 'submitbutton', value: submitbutton},
                        {name: 'removefeedbackbutton', value: removefeedbackbutton});
                    $.ajax({
                        type: 'POST',
                        data: form_data,
                        url: url,
                        dataType: 'json'
                    }).done(function(response) {
                        console.log(response);
                        if (response.success) {
                            var cell_selector = modal.find('#cell_selector').val();
                            $(cell_selector).html(response.html);
                            $('#modal-grading').modal('hide');
                            if (submitbutton == 1) {
                                alert('Your data has been saved.');
                            } else {
                                alert('The feedback has been removed.');
                            }
                        } else {
                            alert('Sorry! There was an error with your request.');
                        }
                    }).always(function() {
                        me.prop('disabled', false);
                    });*/
                });

                $('#modal-grading').on('click', '#id_cancel', function(e) {
                    e.preventDefault();
                    $('#modal-grading').modal('hide');
                });

                $('#modal-grading').on('click', '#id_removefeedbackbutton', function(e) {
                    e.preventDefault();
                    var button = $(this);
                    button.prop('disabled', true);
                    if (confirm('do you want to remove feedback')) {
                        var submitbutton = (button.attr('id') == 'id_submitbutton') ? 1 : 0;
                        var removefeedbackbutton = (button.attr('id') == 'id_removefeedbackbutton') ? 1 : 0;
                        var submitfeedbackbutton = (button.attr('id') == 'id_submitfeedbackbutton') ? 1 : 0;
                        var modal = $('#modal-grading');
                        var url = '/mod/coursework/actions/feedbacks/update.php';
                        var form_data = modal.find('form').serializeArray();
                        var cell_type = modal.find('#cell_type').val();
                        update_feedback(form_data, url, cell_type, submitbutton, removefeedbackbutton,
                            submitfeedbackbutton, 1, button);
                    }
                });

                /**
                 *
                 * @param {object} form_data
                 * @param {string} url
                 * @param {string} celltype
                 * @param {object} submitbutton
                 * @param {object} removefeedbackbutton
                 * @param {object} submitfeedbackbutton
                 * @param {object} confirm
                 * @param {object} button
                 */
                function update_feedback(form_data, url, celltype, submitbutton, removefeedbackbutton,
                                         submitfeedbackbutton, confirm, button) {

                    form_data = form_data.concat({name: 'ajax', value: 1},
                        {name: 'cell_type', value: celltype},
                        {name: 'submitbutton', value: submitbutton},
                        {name: 'submitfeedbackbutton', value: submitfeedbackbutton},
                        {name: 'removefeedbackbutton', value: removefeedbackbutton},
                        {name: 'confirm', value: confirm});

                    $.ajax({
                        type: 'POST',
                        data: form_data,
                        url: url,
                        dataType: 'json'
                    }).done(function(response) {
                        if ((response.success ?? true) === false && (response.message ?? null)) {
                            // Could be an error like "Please provide a valid grade for each criterion".
                            $('#modal-grading').find('.modal-body').html(
                                '<div class="alert alert-danger">' + response.message + '</div>'
                            );
                        } else if (response.success) {
                            var cell_selector = $('#modal-grading').find('#cell_selector').val();
                            $(cell_selector).html(response.html);

                            if (typeof response.extrahtml !== 'undefined' && response.extrahtml != '') {
                                $(cell_selector).next('td').html(response.extrahtml);
                            }
                            if (typeof response.assessdate !== 'undefined' && response.assessdate != '') {
                                $(cell_selector).next('td').html(response.assessdate);
                            }

                            if (typeof response.assessorname !== 'undefined' && response.assessorname != '') {
                                $(cell_selector).prev('td').html(response.assessorname);
                            }

                            if (typeof response.assessortwo !== 'undefined' && response.assessortwo != '') {
                                var tdcell = $(cell_selector).closest('tr').next().find('td')[1];
                                $(tdcell).html(response.assessortwo);
                            }

                            if (typeof response.finalhtml !== 'undefined' && response.assessortwo != '') {
                                var tablerowid = 'allocatable_' + response.allocatableid;
                                var tdcell2 = $('#' + tablerowid).find('.multiple_agreed_grade_cell')[0];
                                $(tdcell2).html(response.finalhtml);
                            }
                            var datatables_lang_messages_two = JSON.parse($('#datatables_lang_messages').attr('data-lang'));
                            $('#modal-grading').modal('hide');
                            if (submitbutton === 1) {
                                Modal.create({
                                    title: datatables_lang_messages_two.notification_info,
                                    body: datatables_lang_messages_two.alert_feedback_save_successful.replace(/\_/g, ' '),
                                    show: true,
                                    removeOnClose: true,
                                });

                            } else if (submitfeedbackbutton === 1) {
                                Modal.create({
                                    title: datatables_lang_messages_two.notification_info,
                                    body: datatables_lang_messages_two.alert_feedback_draft_save_successful.replace(/\_/g, ' '),
                                    show: true,
                                    removeOnClose: true,
                                });

                            } else {
                                Modal.create({
                                    title: datatables_lang_messages_two.notification_info,
                                    body: datatables_lang_messages_two.alert_feedback_remove_successful.replace(/\_/g, ' '),
                                    show: true,
                                    removeOnClose: true,
                                });
                            }
                        } else {
                            Modal.create({
                                title: datatables_lang_messages_two.notification_info,
                                body: response.message,
                                show: true,
                                removeOnClose: true,
                            });
                        }
                    }).always(function() {
                        button.prop('disabled', false);
                    });
                }
            });
        }
    };
});


define('mod_coursework/coursework_edit',
    ['jquery', 'core/notification', 'core/modal', 'core/str'], function($, Notification, Modal, str) {
    return {
        init: function() {
            var now = new Date();
            var extension_form_change = false;

            $(document).ready(function() {
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

                $('.coursework-actions').on('click', '.new_deadline_extension', function(e) {
                    e.preventDefault();
                    var data_name = $(this).attr('data-name');
                    var data_params = JSON.parse($(this).attr('data-params'));
                    var data_time = JSON.parse($(this).attr('data-time'));
                    var current_rowid = $(this).closest('tr').attr('id');
                    extension_new_change_data_form(data_name, data_params, data_time, current_rowid);
                    $('#modal-ajax').modal('show');
                });

                $('.coursework-actions').on('click', '.edit_deadline_extension', function(e) {
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
                        success: function(response) {
                            var data_response = JSON.parse(response);
                            $('html, body').css("cursor", "auto");
                            $('.modal-footer').children('img').css('visibility', 'hidden');
                            if (data_response.error == 1) {
                                Modal.create({
                                    title: str.getString('notification_info', 'mod_coursework'),
                                    body: data_response.messages,
                                    show: true,
                                    removeOnClose: true,
                                });
                            } else {
                                // Update extension row data.
                                update_extension_row_data(current_rowid, data_response);
                                change__status_extension_submit_button(true);
                                save_extension_form_data();

                                Modal.create({
                                    title: str.getString('notification_info', 'mod_coursework'),
                                    body: str.getString('alert_extension_save_successful', 'mod_coursework'),
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
                 * Update extension row data
                 *
                 * @param {string} rowid
                 * @param {object} response
                 */
                function update_extension_row_data(rowid, response) {
                    const row = $('#' + rowid);
                    const datastatus = row.attr('data-status');

                    // Update row's filter status.
                    if (datastatus && datastatus.length > 0) {
                        row.attr('data-status', datastatus + ',extension-granted');
                    } else {
                        row.attr('data-status', 'extension-granted');
                    }

                    // Get string and update row's data.
                    str.getString('due', 'mod_coursework').then(function(duestr) {
                        const deadline = duestr + ': ' + response.data.extended_deadline_formatted;
                        row.find('.extensiongranted-container').removeClass('d-none').end()
                           .find('.actions-extension-date-container').addClass('d-block').removeClass('d-none').end()
                           .find('.extensiongranteddate, .actions-extension-date').html(deadline).end()
                           .find('.duedate-container').addClass('d-none').end()
                           .find('.new_deadline_extension').attr('data-params', JSON.stringify(response.data))
                           .removeClass('new_deadline_extension').addClass('edit_deadline_extension');
                    }).catch(function(error) {
                        window.console.log('Error getting string:', error);
                    });
                }

                /**
                 * Function close button
                 */
                $('#modal-ajax').on('hide.bs.modal', function() {
                    if (is_data_extension_form_change()) {
                        Notification.confirm(
                            str.getString('notification_leave_form_title', 'mod_coursework'),
                            str.getString('notification_leave_form_message', 'mod_coursework'),
                            str.getString('notification_yes_label', 'mod_coursework'),
                            str.getString('notification_no_label', 'mod_coursework'),
                            function() {
                                save_extension_form_data();
                                $('#modal-ajax').modal('hide');
                            }
                        );
                        return false;
                    }
                    return true;
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
            });
        }
    };
});

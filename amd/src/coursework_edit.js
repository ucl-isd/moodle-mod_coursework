define('mod_coursework/coursework_edit',
    ['jquery', 'core/notification', 'core/modal', 'core/str'], function($, Notification, Modal, str) {
    return {
        init: function() {
            var now = new Date();

            $(document).ready(function() {
                // Prepare Message
                const messages = $('#datatables_lang_messages');
                var datatables_lang_messages = messages.length ? JSON.parse(messages.attr('data-lang')) : [];

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

                // Submission finalisation
                $('.coursework-actions').on('click', '.submission-finalisation', function(e) {
                    e.preventDefault();
                    const action = $(this).attr('data-action');
                    const studentname = $(this).attr('data-student');
                    const url = $(this).attr('href');
                    Notification.confirm(
                        str.getString('notification_confirm_label', 'mod_coursework'),
                        str.getString('confirm_finalise', 'mod_coursework', {'action': action, 'studentname': studentname}),
                        str.getString('notification_yes_label', 'mod_coursework'),
                        str.getString('notification_no_label', 'mod_coursework'),
                        function() {
                            // Redirect to URL.
                            window.location.href = url;
                        }
                    );
                });
            });
        }
    };
});

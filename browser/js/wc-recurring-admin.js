/**
 * WC Recurring Invoice class
 */
function WcRecuringAdminClass() {
    var $ = jQuery;
    var self = this;

    var mediaFrame;
    /**
     * 
     * @param {JSON} data data parameters
     * @param {String} action action
     */
    var jsonRequest = function (data, action) {
        if (!action) {
            alert('No hook action defined');
            return;
        }
        $.extend(data, { action: action });
        return jQuery.post(ajaxurl, data, null, 'json');
    };

    /** 
    * confirm deletion
    * @param {Object} obj sender object
    */
    this.ConfirmDelete = function (obj) {
        var invoice = $(obj).data('name');
        var ok = confirm("Really delete invoice " + invoice + "?");
        if (!ok) event.preventDefault();
    };

    /**
     * Edit due date through ajax from the invoice list
     * @param {Object} obj sender object
     */
    this.EditDueDate = function (obj) {
        var d = $(obj).text();
        var invoice_id = parseInt($(obj).data('id'));

        $(obj).hide();

        var container = openDateInput(d, function (newDate) {
            jsonRequest({ invoice_id: invoice_id, due_date: newDate }, 'Invoice').done(function (resp) {
                $(obj).text(resp);
                $(obj).show();
            });
        }, function () {
            $(obj).show();
        });

        $(obj).after(container);
    }

    /**
     * Edit paid date though ajax from the invoice list
     * @param {Object} obj sender object
     */
    this.EditPaidDate = function (obj) {
        var d = $(obj).text();
        var invoice_id = parseInt($(obj).data('id'));

        $(obj).hide();

        var container = openDateInput(d, function (newDate) {
            jsonRequest({ invoice_id: invoice_id, paid_date: newDate }, 'Invoice').done(function (resp) {
                $(obj).text(resp);
                $(obj).show();
            });
        }, function () {
            $(obj).show();
        });

        $(obj).after(container);
    }

    this.UpdateB2C = function (obj) {
        var order_id = parseInt($(obj).data('id'));
        var value = $(obj).prop('checked');

        var loading = $('<img />');
        loading.attr('src', '/wp-admin/images/loading.gif');

        $(obj).after(loading);

        jsonRequest({ order_id: order_id, b2c: value }, 'InvoiceMetabox').done(function (resp) {

        }).fail(function () {
            alert('An error occured');
        }).always(function () { loading.remove(); });
    }
    /**
     * allow changing the recurring period from the invoice metabox in any order
     * @param {Object} obj sender object
     */
    this.UpdatePeriod = function (obj) {
        var order_id = parseInt($(obj).data('id'));
        var value = $(obj).val();

        var loading = $('<img />');
        loading.attr('src', '/wp-admin/images/loading.gif');

        $(obj).after(loading);

        $('.ispconfig_scheduler_info').hide();

        jsonRequest({ order_id: order_id, period: value }, 'InvoiceMetabox').done(function (resp) {
            if (value === '') value = 's';
            if (resp !== '')
                $('.periodinfo-' + value).show();

            $(obj).val(resp);
        }).fail(function () {
            alert('An error occured');
        }).always(function () { loading.remove(); });
    }

    this.ResetOrderPaidStatus = function (obj) {
        var order_id = parseInt($(obj).data('id'));
        var loading = $('<img />');

        loading.attr('src', '/wp-admin/images/loading.gif');

        $(obj).after(loading);

        jsonRequest({ order_id: order_id, resetpaid: true }, 'InvoiceMetabox').done(function (resp) {
            document.location.reload();
        }).fail(function () {
            alert('An error occured');
        }).always(function () { loading.remove(); });
    }

    this.RunTask = function (obj, name) {
        var tmp = $(obj).text();
        $(obj).text('Loading...');

        jsonRequest({ name: name }, 'InvoiceTask').done(function (resp) {
            if (name === 'reminder' && resp < -1) {
                self.ShowNotice("Failed to send the payment reminder due to an invalid email address", 'warning');
                return;
            }
            if (name === 'reminder' && resp < 0) {
                self.ShowNotice("The payment reminder is disabled. Please enable before using it", 'warning');
                return;
            }
            if (name === 'recurring' && resp < -1) {
                self.ShowNotice("Please select 'Test Recurring' first.", 'warning');
                return;
            }
            if (name === 'recurring' && resp < 0) {
                self.ShowNotice("Recurring payments is disabled", 'warning');
                return;
            }
            if (name === 'recurring_reminder' && resp < 0) {
                self.ShowNotice("Recurring reminder is disabled", 'warning');
                return;
            }

            self.ShowNotice("Task " + name + " successfully executed | Return code: " + resp, 'success');
        }).always(function () { $(obj).text(tmp); });
    }

    this.OpenMedia = function (event, name) {

        if (mediaFrame) {
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media({
            title: 'Select PDF document',
            button: {
                text: 'Use this PDF',
            },
            library: {
                type: ['application/pdf']
            },
            multiple: false	// Set to true to allow multiple files to be selected
        })
        mediaFrame.open();

        mediaFrame.on('select', function () {
            // Get media attachment details from the frame state
            var att = mediaFrame.state().get('selection').first().toJSON();
            console.log(att);
            $('#' + name + "-preview").text(att.title);
            $('#' + name).val(att.id);
        });
    }

    this.ClearMedia = function (event, name) {
        $('#' + name).val('');
        $('#' + name + "-preview").text('');
    }

    this.ShowNotice = function (message, type, ondismiss) {
        $button = $('<button />', { type: 'button', class: 'notice-dismiss' });
        $notice = $('<div />', { class: 'notice is-dismissible notice-' + type });

        $button.click(function () { $(this).parent().remove(); });

        $notice.html('<p>' + message + '</p>');
        $notice.append($button);

        $('#wpbody-content > .wc-recurring-settings > :first-child').after($notice);
    }

    var openDateInput = function (defaultValue, onSaveCallback, onCancelCallback) {
        var container = $('<div />');

        var $input = $('<input type="text" style="width: 150px;" />');
        $input.val(defaultValue);

        var btnSave = $('<a />', { href: 'javascript:void(0)', text: 'Save' }).click(function () {
            onSaveCallback($input.val());
            container.remove();
        });
        var btnCancel = $('<a />', { style: 'margin-left: 1em;', href: 'javascript:void(0)', text: 'Cancel' }).click(function () {
            container.remove();
            onCancelCallback();
        });

        container.append($input);
        container.append($('<br />'));
        container.append(btnSave);
        container.append(btnCancel);
        return container;
    }

    var hideTabs = function () {
        $('#wcinvoicepdf-tabs a').each(function () {
            var other_id = $(this).attr('href');
            $(other_id).hide();
        })
        $('#wcinvoicepdf-tabs > a').removeClass('nav-tab-active');
    }

    var initTabs = function () {
        $('#wcinvoicepdf-tabs > a').click(function (event) {
            event.preventDefault();

            var id = $(this).attr('href');

            hideTabs();

            $(this).addClass('nav-tab-active');
            $(id).show();
        })

        hideTabs();
        $('#wcinvoicepdf-tabs a:first').trigger('click');
    }

    $(function () {
        initTabs();
    })
}

window['WcRecuringAdmin'] = new WcRecuringAdminClass();

$(function () {
    if (!document.getElementById) {
        return;
    }
    if (document.getElementById('edit-entry')) {
        var formatField = $('#post_format').get(0);
        var last_post_format = $(formatField).val();
        $(formatField).change(function () {
            if (window.confirm(dotclear.msg.confirm_change_post_format_noconvert)) {
                excerptTb.switchMode(this.value);
                contentTb.switchMode(this.value);
                last_post_format = $(this).val();
            } else {
                $(this).val(last_post_format);
            }
            $('.format_control > *').addClass('hide');
            $('.format_control:not(.control_no_' + $(this).val() + ') > *').removeClass('hide');
        });

        var contentTb = new jsToolBar(document.getElementById('post_content'));
        contentTb.context = 'post';
    }

    $('#edit-entry').onetabload(function () {
        dotclear.hideLockable();
        var post_dtPick = new datePicker($('#post_dt').get(0));
        post_dtPick.img_top = '1.5em';
        post_dtPick.draw();
        $('input[name="delete"]').click(function () {
            return window.confirm(dotclear.msg.confirm_delete_post);
        });
        var v = $('<div class="format_control"><p><a id="a-validator"></a></p><div/>').get(0);
        $('.format_control').before(v);
        var a = $('#a-validator').get(0);
        a.href = '#';
        a.className = 'button ';
        $(a).click(function () {
            excerpt_content = $('#post_excerpt').css('display') != 'none' ? $('#post_excerpt').val() : $('#excerpt-area iframe').contents().find('body').html();
            post_content = $('#post_content').css('display') != 'none' ? $('#post_content').val() : $('#content-area iframe').contents().find('body').html();
            var params = {
                xd_check: dotclear.nonce,
                f: 'validatePostMarkup',
                excerpt: excerpt_content,
                content: post_content,
                format: $('#post_format').get(0).value,
                lang: $('#post_lang').get(0).value
            };
            $.post('services.php', params, function (data) {
                if ($(data).find('rsp').attr('status') != 'ok') {
                    alert($(data).find('rsp message').text());
                    return false;
                }
                $('.message, .success, .error, .warning-msg').remove();
                if ($(data).find('valid').text() == 1) {
                    var p = document.createElement('p');
                    p.id = 'markup-validator';
                    $(p).addClass('success');
                    $(p).text(dotclear.msg.xhtml_valid);
                    $('#entry-content h3').after(p);
                    $(p).backgroundFade({
                        sColor: dotclear.fadeColor.beginValidatorMsg,
                        eColor: dotclear.fadeColor.endValidatorMsg,
                        steps: 50
                    }, function () {
                        $(this).backgroundFade({
                            sColor: dotclear.fadeColor.endValidatorMsg,
                            eColor: dotclear.fadeColor.beginValidatorMsg
                        });
                    });
                } else {
                    var div = document.createElement('div');
                    div.id = 'markup-validator';
                    $(div).addClass('error');
                    $(div).html('<p><strong>' + dotclear.msg.xhtml_not_valid + '</strong></p>' + $(data).find('errors').text());
                    $('#entry-content h3').after(div);
                    $(div).backgroundFade({
                        sColor: dotclear.fadeColor.beginValidatorErr,
                        eColor: dotclear.fadeColor.endValidatorErr,
                        steps: 50
                    }, function () {
                        $(this).backgroundFade({
                            sColor: dotclear.fadeColor.endValidatorErr,
                            eColor: dotclear.fadeColor.beginValidatorErr
                        });
                    });
                }
                if ($('#post_excerpt').text() != excerpt_content || $('#post_content').text() != post_content) {
                    var pn = document.createElement('p');
                    $(pn).addClass('warning-msg');
                    $(pn).text(dotclear.msg.warning_validate_no_save_content);
                    $('#entry-content h3').after(pn);
                }
                return false;
            });
            return false;
        });
        a.appendChild(document.createTextNode(dotclear.msg.xhtml_validator));
        $('.format_control > *').addClass('hide');
        $('.format_control:not(.control_no_' + last_post_format + ') > *').removeClass('hide');
        $('#notes-area label').toggleWithLegend($('#notes-area').children().not('label'), {
            user_pref: 'dcx_post_notes',
            legend_click: true,
            hide: $('#post_notes').val() == ''
        });
        $('#post_lang').parent().children('label').toggleWithLegend($('#post_lang'), {
            user_pref: 'dcx_post_lang',
            legend_click: true
        });
        $('#post_password').parent().children('label').toggleWithLegend($('#post_password'), {
            user_pref: 'dcx_post_password',
            legend_click: true,
            hide: $('#post_password').val() == ''
        });
        $('#post_status').parent().children('label').toggleWithLegend($('#post_status'), {
            user_pref: 'dcx_post_status',
            legend_click: true
        });
        $('#post_dt').parent().children('label').toggleWithLegend($('#post_dt').parent().children().not('label'), {
            user_pref: 'dcx_post_dt',
            legend_click: true
        });
        $('#label_format').toggleWithLegend($('#label_format').parent().children().not('#label_format'), {
            user_pref: 'dcx_post_format',
            legend_click: true
        });
        $('#label_cat_id').toggleWithLegend($('#label_cat_id').parent().children().not('#label_cat_id'), {
            user_pref: 'dcx_cat_id',
            legend_click: true
        });
        $('#create_cat').toggleWithLegend($('#create_cat').parent().children().not('#create_cat'), {
            legend_click: true
        });
        $('#label_comment_tb').toggleWithLegend($('#label_comment_tb').parent().children().not('#label_comment_tb'), {
            user_pref: 'dcx_comment_tb',
            legend_click: true
        });
        $('#post_url').parent().children('label').toggleWithLegend($('#post_url').parent().children().not('label'), {
            user_pref: 'post_url',
            legend_click: true
        });
        $('#excerpt-area label').toggleWithLegend($('#excerpt-area').children().not('label'), {
            user_pref: 'dcx_post_excerpt',
            legend_click: true,
            hide: $('#post_excerpt').val() == ''
        });
        contentTb.switchMode(formatField.value);

        $('a.attachment-remove').click(function () {
            this.href = '';
            var m_name = $(this).parents('ul').find('li:first>a').attr('title');
            if (window.confirm(dotclear.msg.confirm_remove_attachment.replace('%s', m_name))) {
                var f = $('#attachment-remove-hide').get(0);
                f.elements['media_id'].value = this.id.substring(11);
                f.submit();
            }
            return false;
        });
        var excerpt = $('#post_excerpt').val();
        var content = $('#post_content').val();
        $('#convert-xhtml').click(function () {
            if (excerpt != $('#post_excerpt').val() || content != $('#post_content').val()) {
                return window.confirm(dotclear.msg.confirm_change_post_format);
            }
        });
    });
});

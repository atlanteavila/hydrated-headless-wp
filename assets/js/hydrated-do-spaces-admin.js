(function ($) {
    const settings = window.HydratedDOSpaces || {};
    const strings = settings.strings || {};
    const ajaxUrl = settings.ajaxUrl || window.ajaxurl || '';

    if (!ajaxUrl) {
        return;
    }

    const $modal = $(
        '<div class="hydrated-do-spaces-modal" aria-hidden="true">' +
            '<div class="hydrated-do-spaces-modal__backdrop" tabindex="-1"></div>' +
            '<div class="hydrated-do-spaces-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="hydrated-do-spaces-modal-title">' +
                '<button type="button" class="hydrated-do-spaces-modal__close" aria-label="' + (strings.close || 'Close') + '">&times;</button>' +
                '<h2 id="hydrated-do-spaces-modal-title">' + (strings.modalTitle || '') + '</h2>' +
                '<p class="hydrated-do-spaces-modal__message">' + (strings.selectFile || '') + '</p>' +
                '<div class="hydrated-do-spaces-modal__actions">' +
                    '<button type="button" class="button button-primary hydrated-do-spaces-modal__choose">' + (strings.selectButton || '') + '</button>' +
                '</div>' +
                '<div class="hydrated-do-spaces-modal__progress" hidden></div>' +
            '</div>' +
        '</div>'
    );

    const $fileInput = $('<input type="file" style="display:none;" />');
    const $message = $modal.find('.hydrated-do-spaces-modal__message');
    const $progress = $modal.find('.hydrated-do-spaces-modal__progress');
    const $chooseButton = $modal.find('.hydrated-do-spaces-modal__choose');

    let currentField = null;

    function appendModal() {
        if (!document.body.contains($modal[0])) {
            $modal.appendTo(document.body);
        }
        if (!document.body.contains($fileInput[0])) {
            $fileInput.appendTo(document.body);
        }
    }

    function getPostId() {
        const input = document.getElementById('post_ID');
        if (!input) {
            return 0;
        }
        const value = parseInt(input.value, 10);
        return Number.isNaN(value) ? 0 : value;
    }

    function resetModal() {
        $message.text(strings.selectFile || '');
        $progress.text('');
        $progress.removeClass('is-error is-success');
        $progress.attr('hidden', 'hidden');
        $chooseButton.prop('disabled', false);
        $fileInput.val('');
    }

    function openModal($field, accept) {
        appendModal();
        currentField = $field;
        setStatus($field, '', '');
        resetModal();
        if (accept) {
            $fileInput.attr('accept', accept);
        } else {
            $fileInput.removeAttr('accept');
        }
        $modal.addClass('is-visible').attr('aria-hidden', 'false');
        setTimeout(() => {
            $fileInput.trigger('click');
        }, 20);
    }

    function closeModal() {
        $modal.removeClass('is-visible').attr('aria-hidden', 'true');
        currentField = null;
        resetModal();
    }

    function setProgress(message, type) {
        if (message) {
            $progress.removeAttr('hidden');
            $progress.text(message);
        } else {
            $progress.attr('hidden', 'hidden');
            $progress.text('');
        }
        $progress.removeClass('is-error is-success');
        if (type === 'success') {
            $progress.addClass('is-success');
        } else if (type === 'error') {
            $progress.addClass('is-error');
        }
    }

    function setStatus($field, message, type) {
        const $status = $field.find('.hydrated-do-space-status');
        $status.removeClass('is-success is-error');
        if (type === 'success') {
            $status.addClass('is-success');
        } else if (type === 'error') {
            $status.addClass('is-error');
        }
        $status.text(message || '');
    }

    function buildCurrentMarkup(data, label) {
        const $wrapper = $('<div />');

        if (!data || !data.url) {
            $('<p />', {
                class: 'description',
                text: strings.noFile || '',
            }).appendTo($wrapper);
            return $wrapper;
        }

        const fileName = data.fileName || data.key || data.url.split('/').pop() || label || '';

        const $linkParagraph = $('<p />', { class: 'hydrated-do-space-current__link' }).appendTo($wrapper);
        $('<a />', {
            href: data.url,
            target: '_blank',
            rel: 'noopener noreferrer',
            text: fileName,
        }).appendTo($linkParagraph);

        const $copy = $('<div />', { class: 'hydrated-do-space-copy' }).appendTo($wrapper);
        $('<input />', {
            type: 'text',
            class: 'hydrated-do-space-copy__input',
            readonly: true,
            value: data.url,
        }).appendTo($copy);

        $('<button />', {
            type: 'button',
            class: 'button hydrated-do-space-copy__button',
            text: strings.copy || 'Copy URL',
        })
            .attr('data-url', data.url)
            .appendTo($copy);

        return $wrapper;
    }

    function updateField($field, data) {
        const label = $field.data('field-label') || '';
        const $current = $field.find('.hydrated-do-space-current');
        const $markup = buildCurrentMarkup(data, label);
        $current.empty().append($markup.children());

        const hasFile = !!(data && data.url);
        $field.find('.hydrated-do-space-remove').toggleClass('is-hidden', !hasFile);
    }

    function uploadFile(file) {
        const $field = currentField;
        if (!$field) {
            return;
        }

        const postId = getPostId();
        if (!postId) {
            setStatus($field, strings.saveFirst || '', 'error');
            closeModal();
            return;
        }

        const fieldKey = $field.data('field-key');
        if (!fieldKey) {
            setStatus($field, strings.error || '', 'error');
            closeModal();
            return;
        }

        const formData = new window.FormData();
        formData.append('action', 'hydrated_do_spaces_upload');
        formData.append('nonce', settings.nonce || '');
        formData.append('post_id', postId);
        formData.append('field_key', fieldKey);
        formData.append('file', file);

        $chooseButton.prop('disabled', true);
        setProgress(strings.uploading || '', '');
        setStatus($field, strings.uploading || '', '');

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
        })
            .done((response) => {
                if (!response || !response.success || !response.data) {
                    const message = response && response.data && response.data.message ? response.data.message : (strings.error || '');
                    setProgress(message, 'error');
                    $chooseButton.prop('disabled', false);
                    setStatus($field, message, 'error');
                    return;
                }

                setProgress(strings.success || '', 'success');
                setStatus($field, response.data.message || strings.success || '', 'success');
                updateField($field, response.data);

                setTimeout(() => {
                    closeModal();
                }, 400);
            })
            .fail(() => {
                const message = strings.error || '';
                setProgress(message, 'error');
                $chooseButton.prop('disabled', false);
                setStatus($field, message, 'error');
            });
    }

    $fileInput.on('change', function () {
        const file = this.files && this.files[0];
        if (!file) {
            return;
        }
        uploadFile(file);
    });

    $modal.on('click', '.hydrated-do-spaces-modal__close', (event) => {
        event.preventDefault();
        closeModal();
    });

    $modal.on('click', '.hydrated-do-spaces-modal__backdrop', () => {
        closeModal();
    });

    $modal.on('click', '.hydrated-do-spaces-modal__choose', (event) => {
        event.preventDefault();
        $fileInput.trigger('click');
    });

    $(document).on('keyup', (event) => {
        if (event.key === 'Escape' && $modal.hasClass('is-visible')) {
            closeModal();
        }
    });

    $(document).on('click', '.hydrated-do-space-upload', function (event) {
        event.preventDefault();
        const $field = $(this).closest('.hydrated-do-space-field');
        const postId = getPostId();
        if (!postId) {
            setStatus($field, strings.saveFirst || '', 'error');
            return;
        }
        openModal($field, $(this).data('accept'));
    });

    $(document).on('click', '.hydrated-do-space-copy__button', function (event) {
        event.preventDefault();
        const url = $(this).attr('data-url');
        if (!url) {
            return;
        }

        const $button = $(this);
        const fallbackInput = $button.siblings('.hydrated-do-space-copy__input');

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(() => {
                $button.blur();
                $button.text(strings.copied || 'Copied');
                setTimeout(() => {
                    $button.text(strings.copy || 'Copy URL');
                }, 2000);
            }).catch(() => {
                if (fallbackInput && fallbackInput.length) {
                    fallbackInput[0].focus();
                    fallbackInput[0].select();
                }
                $button.text(strings.copyFailed || 'Copy failed');
                setTimeout(() => {
                    $button.text(strings.copy || 'Copy URL');
                }, 2000);
            });
            return;
        }

        if (fallbackInput && fallbackInput.length) {
            fallbackInput[0].focus();
            fallbackInput[0].select();
            try {
                const success = document.execCommand('copy');
                if (success) {
                    $button.blur();
                    $button.text(strings.copied || 'Copied');
                } else {
                    $button.text(strings.copyFailed || 'Copy failed');
                }
            } catch (err) {
                $button.text(strings.copyFailed || 'Copy failed');
            }
            setTimeout(() => {
                $button.text(strings.copy || 'Copy URL');
            }, 2000);
        }
    });

    $(document).on('click', '.hydrated-do-space-remove', function (event) {
        event.preventDefault();
        const $button = $(this);
        const $field = $button.closest('.hydrated-do-space-field');
        const fieldKey = $button.data('field-key');
        const postId = getPostId();

        if (!fieldKey || !postId) {
            setStatus($field, strings.saveFirst || '', 'error');
            return;
        }

        if (!window.confirm(strings.removeConfirm || '')) {
            return;
        }

        setStatus($field, strings.removing || '', '');
        $button.prop('disabled', true);

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'hydrated_do_spaces_remove',
                nonce: settings.nonce || '',
                post_id: postId,
                field_key: fieldKey,
            },
        })
            .done((response) => {
                if (!response || !response.success) {
                    const message = response && response.data && response.data.message ? response.data.message : (strings.error || '');
                    setStatus($field, message, 'error');
                    $button.prop('disabled', false);
                    return;
                }

                updateField($field, null);
                setStatus($field, strings.removed || '', 'success');
                $button.prop('disabled', false);
            })
            .fail(() => {
                setStatus($field, strings.error || '', 'error');
                $button.prop('disabled', false);
            });
    });
})(jQuery);

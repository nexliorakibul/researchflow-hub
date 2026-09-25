(() => {
    'use strict';

    const generator = document.querySelector('[data-citation-generator]');
    if (!generator) {
        return;
    }

    const styleSelect = generator.querySelector('[data-citation-style]');
    const preview = generator.querySelector('[data-citation-preview]');
    const copyButton = generator.querySelector('[data-citation-copy]');
    const status = generator.querySelector('[data-citation-status]');
    if (!styleSelect || !preview || !copyButton || !status) {
        return;
    }

    const updatePreview = () => {
        const option = styleSelect.options[styleSelect.selectedIndex];
        preview.value = option ? option.dataset.citation || '' : '';
        status.textContent = '';
    };

    const copyCitation = async () => {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(preview.value);
            } else {
                preview.focus();
                preview.select();
                if (!document.execCommand('copy')) {
                    throw new Error('Copy command failed');
                }
            }
            status.textContent = 'Citation copied.';
        } catch (error) {
            status.textContent = 'Unable to copy automatically. Select the citation and copy it manually.';
        }
    };

    styleSelect.addEventListener('change', updatePreview);
    copyButton.addEventListener('click', copyCitation);
    updatePreview();
})();

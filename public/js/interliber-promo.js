(function () {
    const modal = document.getElementById('interliber-promo-modal');
    const copyButton = document.querySelector('[data-interliber-copy-code]');
    const status = document.getElementById('interliber-copy-status');

    if (!modal || !copyButton || !status) {
        return;
    }

    const label = copyButton.querySelector('[data-interliber-copy-label]');
    const code = copyButton.dataset.interliberCopyCode;

    copyButton.addEventListener('click', async function () {
        try {
            await navigator.clipboard.writeText(code);
            label.textContent = 'Kopirano!';
            status.textContent = 'Kod je kopiran. Zalijepi ga u košarici.';
        } catch (error) {
            status.textContent = 'Tvoj kod: ' + code + '. Upiši ga u košarici.';
        }
    });

    modal.addEventListener('hidden.bs.modal', function () {
        label.textContent = 'Kopiraj kod';
        status.textContent = 'Kod upiši u košarici.';
    });
})();

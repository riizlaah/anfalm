document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-dialog-open]');
    if (opener) {
        event.preventDefault();
        document.getElementById(opener.dataset.dialogOpen)?.showModal();

        return;
    }

    const closer = event.target.closest('[data-dialog-close]');
    if (closer) {
        event.preventDefault();
        closer.closest('dialog')?.close();

        return;
    }

    // Klik area backdrop (di luar kotak dialog) ikut menutup dialog.
    const dialog = event.target.closest('dialog');
    if (dialog && event.target === dialog) {
        const rect = dialog.getBoundingClientRect();
        const diDalam = event.clientX >= rect.left && event.clientX <= rect.right
            && event.clientY >= rect.top && event.clientY <= rect.bottom;

        if (!diDalam) {
            dialog.close();
        }
    }
});

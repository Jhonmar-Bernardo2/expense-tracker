const PRINT_FRAME_ID = 'approval-voucher-print-frame';
const PRINT_COMPLETE_EVENT = 'approval-voucher-print-complete';
const PRINT_FRAME_CLEANUP_TIMEOUT_MS = 60_000;

const buildAutoPrintUrl = (url: string) => {
    const printUrl = new URL(url, window.location.origin);

    printUrl.searchParams.set('auto_print', '1');

    return printUrl.toString();
};

export const openPrintDialog = (url: string) => {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    const existingFrame = document.getElementById(PRINT_FRAME_ID);

    if (existingFrame instanceof HTMLIFrameElement) {
        existingFrame.remove();
    }

    const iframe = document.createElement('iframe');
    let cleanupTimeoutId = 0;

    const cleanup = () => {
        window.clearTimeout(cleanupTimeoutId);
        window.removeEventListener('message', handleMessage);
        iframe.remove();
    };

    const handleMessage = (event: MessageEvent) => {
        if (event.origin !== window.location.origin) {
            return;
        }

        if (event.data?.type !== PRINT_COMPLETE_EVENT) {
            return;
        }

        cleanup();
    };

    iframe.id = PRINT_FRAME_ID;
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.position = 'fixed';
    iframe.style.top = '-10000px';
    iframe.style.left = '-10000px';
    iframe.style.width = '1280px';
    iframe.style.height = '1600px';
    iframe.style.border = '0';
    iframe.style.opacity = '0';
    iframe.style.pointerEvents = 'none';

    cleanupTimeoutId = window.setTimeout(
        cleanup,
        PRINT_FRAME_CLEANUP_TIMEOUT_MS,
    );

    window.addEventListener('message', handleMessage);

    iframe.src = buildAutoPrintUrl(url);

    document.body.appendChild(iframe);
};

export const notifyPrintComplete = () => {
    if (
        typeof window === 'undefined' ||
        window.parent === window ||
        window.location.origin === 'null'
    ) {
        return;
    }

    window.parent.postMessage(
        { type: PRINT_COMPLETE_EVENT },
        window.location.origin,
    );
};

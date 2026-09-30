// Render a QR code into every element carrying a data-qr attribute.
// Library: qrcodejs (davidshimjs), vendored locally in assets/js/qrcode.min.js
// (MIT, David Shim). No CDN, no build step, works offline on the clinic LAN.
(function () {
    'use strict';

    function renderAll() {
        var nodes = document.querySelectorAll('[data-qr]');
        for (var i = 0; i < nodes.length; i++) {
            var el = nodes[i];
            if (el.dataset.qrDone) continue;
            el.dataset.qrDone = '1';

            var url = el.getAttribute('data-qr');
            if (!url || typeof QRCode === 'undefined') continue;

            // quiet zone and a light background are the scanner's friends
            var size = parseInt(el.getAttribute('data-qr-size') || '168', 10);
            try {
                new QRCode(el, {
                    text: url,
                    width: size,
                    height: size,
                    colorDark: '#17231E',
                    colorLight: '#FFFFFF',
                    correctLevel: QRCode.CorrectLevel.M,
                    useSVG: true
                });
            } catch (err) { /* a bad URL must not break the page */ }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderAll);
    } else {
        renderAll();
    }
})();
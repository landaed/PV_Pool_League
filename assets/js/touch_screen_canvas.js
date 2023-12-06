let canvas = document.getElementById('renderCanvas');

function isMobileDevice() {
    return (typeof window.orientation !== "undefined") || (navigator.userAgent.indexOf('IEMobile') !== -1);
}

if (isMobileDevice()) {
    canvas.style.touchAction = 'auto';
    canvas.style.userSelect = 'auto';
}

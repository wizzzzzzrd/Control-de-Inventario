// assets/js/scanner-camera.js
async function startCameraScanner(onCode) {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    const video = document.createElement('video');
    video.autoplay = true;
    video.srcObject = stream;
    document.body.appendChild(video);
    // Para leer con ZXing necesitarías incluir la librería y llamar sus APIs.
    // Este es un stub: muestra el video y no decodifica.
    alert('Scanner de cámara iniciado (implementa ZXing para decodificar frames).');
  } catch (err) {
    alert('No se pudo abrir la cámara: ' + err.message);
  }
}

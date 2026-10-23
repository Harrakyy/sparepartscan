// Bunyi singkat untuk hasil scan. Browser baru mengizinkan suara setelah ada
// interaksi (klik atau ketikan; ketikan scanner juga dihitung).
let ctx = null;

function unlock() {
  try {
    ctx ??= new (window.AudioContext || window.webkitAudioContext)();
    if (ctx.state === 'suspended') ctx.resume();
  } catch { /* suara tidak tersedia */ }
}
document.addEventListener('pointerdown', unlock);
document.addEventListener('keydown', unlock);

function tone(frequency, seconds, type = 'sine') {
  if (!ctx) return;
  try {
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = type;
    osc.frequency.value = frequency;
    gain.gain.value = 0.08;
    osc.connect(gain).connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + seconds);
  } catch { /* suara tidak tersedia */ }
}

export const beepOk = () => tone(880, 0.12);
export const buzzError = () => tone(220, 0.35, 'square');

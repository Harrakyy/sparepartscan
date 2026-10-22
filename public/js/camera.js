// Pemilihan kamera belakang. HP dengan banyak lensa sering membuka lensa ultra wide
// lebih dulu; lensa itu tidak bisa fokus dekat dan biasanya tanpa flash.

const BACK = /back|rear|environment|belakang/i;
const IOS_VIRTUAL = /back (triple|dual wide|dual) camera/i; // iPhone: pindah ke lensa makro otomatis
const ANDROID_MAIN = /camera2 0\b/i;
const SPECIAL_LENS = /ultra|wide|tele|depth|macro/i;

const isBack = cam => BACK.test(cam.label);

export function pickBackCamera(cameras, savedId = null) {
  if (!cameras.length) return null;
  if (savedId && cameras.some(c => c.id === savedId)) return savedId;
  const backs = cameras.filter(isBack);
  const choice =
    backs.find(c => IOS_VIRTUAL.test(c.label)) ||
    backs.find(c => ANDROID_MAIN.test(c.label)) ||
    backs.find(c => !SPECIAL_LENS.test(c.label)) ||
    backs[0];
  if (choice) return choice.id;
  // Tanpa label (izin belum diberikan) biarkan browser memilih lewat facingMode.
  if (cameras.every(c => !c.label)) return null;
  return cameras[cameras.length - 1].id;
}

export function nextCameraId(cameras, currentId) {
  const backs = cameras.filter(isBack);
  const pool = backs.length ? backs : cameras;
  if (!pool.length) return null;
  const i = pool.findIndex(c => c.id === currentId);
  return pool[(i + 1) % pool.length].id;
}

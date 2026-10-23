// Mencari kode part di faktur yang paling mirip dengan kode yang salah di-scan,
// untuk pesan "Mungkin maksudnya …?".

export function editDistance(a, b) {
  let prev = Array.from({ length: b.length + 1 }, (_, j) => j);
  for (let i = 1; i <= a.length; i++) {
    const cur = [i];
    for (let j = 1; j <= b.length; j++) {
      cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
    }
    prev = cur;
  }
  return prev[b.length];
}

export function suggestCode(code, candidates, maxDistance = 3) {
  let best = null;
  let bestDistance = maxDistance + 1;
  for (const candidate of candidates) {
    const d = editDistance(code, candidate);
    if (d < bestDistance) {
      best = candidate;
      bestDistance = d;
    }
  }
  return best;
}

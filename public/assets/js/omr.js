/*
 * omr.js — ตรวจกระดาษคำตอบจากภาพถ่าย (Optical Mark Recognition) ฝั่งเบราว์เซอร์
 * ใช้ทั้งหน้าสแกน (browser) และทดสอบ (Node/headless)
 *
 * เรขาคณิตของกระดาษ (geometry) ต้องตรงกับที่หน้า print (scan_sheet.php) วาดเป๊ะ ๆ
 * พิกัดใช้ระบบ normalized [0,1]×[0,1] วัดจากจุดศูนย์กลางของ fiducial 4 มุม
 */
(function (root) {
  'use strict';

  /** โครงกริดของกระดาษคำตอบ: คอลัมน์ละไม่เกิน 25 ข้อ */
  function geometry(n, m) {
    n = Math.max(1, n | 0);
    m = (m === 5) ? 5 : 4;
    var cols = Math.max(1, Math.ceil(n / 25));
    var rows = Math.ceil(n / cols);
    var padX = 0.06, padY = 0.05;         // เว้นขอบจาก fiducial
    var colW = (1 - 2 * padX) / cols;
    var rowH = (1 - 2 * padY) / rows;
    var labelFrac = 0.30, rightPad = 0.05; // ซ้ายของคอลัมน์ = เลขข้อ, ขวาเว้นเล็กน้อย
    return { n: n, m: m, cols: cols, rows: rows, padX: padX, padY: padY,
             colW: colW, rowH: rowH, labelFrac: labelFrac, rightPad: rightPad };
  }

  /** ศูนย์กลางวงกลมคำตอบข้อ q (1..n) ตัวเลือก mi (0..m-1) → {u,v} */
  function bubble(g, q, mi) {
    var idx = q - 1;
    var c = Math.floor(idx / g.rows);
    var r = idx % g.rows;
    var colLeft = g.padX + c * g.colW;
    var regionLeft = colLeft + g.labelFrac * g.colW;
    var regionW = g.colW * (1 - g.labelFrac - g.rightPad);
    var u = regionLeft + regionW * ((mi + 0.5) / g.m);
    var v = g.padY + (r + 0.5) * g.rowH;
    return { u: u, v: v };
  }

  /** รัศมีสุ่มตัวอย่าง (normalized) ให้เล็กกว่าวงกลม เพื่อเลี่ยงเส้นขอบวง */
  function sampleRadius(g) {
    var bubbleW = g.colW * (1 - g.labelFrac - g.rightPad) / g.m;
    return 0.30 * Math.min(g.rowH, bubbleW);
  }

  // ---------- โฮโมกราฟี (map normalized → พิกัดภาพ, รองรับ perspective) ----------
  function solveLinear(A, b) {
    var nn = b.length, i, j, k;
    for (i = 0; i < nn; i++) {
      var p = i;
      for (j = i + 1; j < nn; j++) if (Math.abs(A[j][i]) > Math.abs(A[p][i])) p = j;
      var t = A[i]; A[i] = A[p]; A[p] = t; var tb = b[i]; b[i] = b[p]; b[p] = tb;
      if (Math.abs(A[i][i]) < 1e-12) return null;
      for (j = i + 1; j < nn; j++) {
        var f = A[j][i] / A[i][i];
        for (k = i; k < nn; k++) A[j][k] -= f * A[i][k];
        b[j] -= f * b[i];
      }
    }
    var x = new Array(nn);
    for (i = nn - 1; i >= 0; i--) {
      var s = b[i];
      for (j = i + 1; j < nn; j++) s -= A[i][j] * x[j];
      x[i] = s / A[i][i];
    }
    return x;
  }

  /** src,dst = [[x,y]×4] (TL,TR,BR,BL) → เมทริกซ์ 8 ค่า (h33=1) */
  function homography(src, dst) {
    var A = [], b = [], i;
    for (i = 0; i < 4; i++) {
      var sx = src[i][0], sy = src[i][1], dx = dst[i][0], dy = dst[i][1];
      A.push([sx, sy, 1, 0, 0, 0, -sx * dx, -sy * dx]); b.push(dx);
      A.push([0, 0, 0, sx, sy, 1, -sx * dy, -sy * dy]); b.push(dy);
    }
    return solveLinear(A, b);
  }
  function mapPt(H, u, v) {
    var d = H[6] * u + H[7] * v + 1;
    return [(H[0] * u + H[1] * v + H[2]) / d, (H[3] * u + H[4] * v + H[5]) / d];
  }

  function lum(img, x, y) {
    x = x | 0; y = y | 0;
    if (x < 0 || y < 0 || x >= img.width || y >= img.height) return 255;
    var o = (y * img.width + x) * 4, d = img.data;
    return 0.299 * d[o] + 0.587 * d[o + 1] + 0.114 * d[o + 2];
  }

  /** ความเข้ม (0..255, มาก=ดำ=ระบาย) เฉลี่ยในดิสก์เล็ก ๆ รอบ (u,v) */
  function darkness(img, H, u, v, rad) {
    var sum = 0, cnt = 0, i, j;
    for (i = -2; i <= 2; i++) {
      for (j = -2; j <= 2; j++) {
        if (i * i + j * j > 5) continue;           // วงกลมโดยประมาณ
        var p = mapPt(H, u + (j / 2) * rad, v + (i / 2) * rad);
        sum += (255 - lum(img, p[0], p[1])); cnt++;
      }
    }
    return cnt ? sum / cnt : 0;
  }

  /**
   * อ่านคำตอบจากภาพ
   * @param img  {data:Uint8ClampedArray, width, height}
   * @param corners {tl:[x,y], tr, br, bl} มุมพื้นที่กระดาษในภาพ
   * @return {answers:[1..m|0], uncertain:[bool], dark:[[..]], geometry}
   */
  function read(img, corners, n, m, opts) {
    opts = opts || {};
    var fillMin = opts.fillMin != null ? opts.fillMin : 55;
    var margin = opts.margin != null ? opts.margin : 22;
    var g = geometry(n, m);
    var H = homography([[0, 0], [1, 0], [1, 1], [0, 1]],
                       [corners.tl, corners.tr, corners.br, corners.bl]);
    if (!H) return { answers: [], uncertain: [], dark: [], geometry: g, error: 'homography' };
    var rad = sampleRadius(g), answers = [], uncertain = [], darkArr = [], q, mi;
    for (q = 1; q <= n; q++) {
      var ds = [];
      for (mi = 0; mi < m; mi++) { var bb = bubble(g, q, mi); ds.push(darkness(img, H, bb.u, bb.v, rad)); }
      darkArr.push(ds);
      var best = -1, bi = -1, second = -1;
      for (mi = 0; mi < m; mi++) {
        if (ds[mi] > best) { second = best; best = ds[mi]; bi = mi; }
        else if (ds[mi] > second) { second = ds[mi]; }
      }
      if (best < fillMin) { answers.push(0); uncertain.push(false); }
      else { answers.push(bi + 1); uncertain.push((best - second) < margin); }
    }
    return { answers: answers, uncertain: uncertain, dark: darkArr, geometry: g };
  }

  // ---------- ตรวจหา fiducial 4 มุมอัตโนมัติ (ถ้าหาไม่เจอ คืน null → ใช้กรอบไกด์แทน) ----------
  /**
   * ค้นหา fiducial (สี่เหลี่ยมทึบ) 4 มุมของพื้นที่ค้นหา rect
   * fiducial อยู่ที่มุมสุด ส่วนวงคำตอบเว้นขอบเข้ามา (padX/padY) → บล็อกดำที่ "ใกล้มุมที่สุด"
   * คือ fiducial เสมอ แล้ว flood-fill หา centroid ของบล็อกนั้น
   */
  function otsu(img, rect, STRIDE) {
    var hist = new Array(256).fill(0), total = 0, x, y, i;
    for (y = rect.y; y < rect.y + rect.h; y += STRIDE)
      for (x = rect.x; x < rect.x + rect.w; x += STRIDE) { hist[lum(img, x, y) | 0]++; total++; }
    if (!total) return null;
    var sum = 0; for (i = 0; i < 256; i++) sum += i * hist[i];
    var sumB = 0, wB = 0, maxV = -1, thr = 128;
    for (i = 0; i < 256; i++) {
      wB += hist[i]; if (!wB) continue; var wF = total - wB; if (!wF) break;
      sumB += i * hist[i];
      var mB = sumB / wB, mF = (sum - sumB) / wF, bet = wB * wF * (mB - mF) * (mB - mF);
      if (bet > maxV) { maxV = bet; thr = i; }
    }
    return Math.min(thr, 160); // กันภาพสว่างจัด
  }

  function autoCorners(img, rect) {
    rect = rect || { x: 0, y: 0, w: img.width, h: img.height };
    var STRIDE = Math.max(1, Math.round(Math.min(img.width, img.height) / 500));
    var thr = otsu(img, rect, STRIDE); if (thr == null) return null;
    var zoneW = rect.w * 0.34, zoneH = rect.h * 0.34;
    var minArea = Math.max(4, Math.round((zoneW * zoneH) / (STRIDE * STRIDE) * 0.004));

    // flood-fill บล็อกดำจากจุดเริ่ม (สุ่มด้วย STRIDE) ในขอบเขต zone → centroid
    function blob(seedX, seedY, zx, zy, zw, zh) {
      var stack = [[seedX, seedY]], seen = {}, sx = 0, sy = 0, c = 0, cap = 20000;
      while (stack.length && c < cap) {
        var p = stack.pop(), px = p[0], py = p[1];
        if (px < zx || py < zy || px >= zx + zw || py >= zy + zh) continue;
        var k = px + ',' + py; if (seen[k]) continue; seen[k] = 1;
        if (lum(img, px, py) > thr) continue;
        sx += px; sy += py; c++;
        stack.push([px + STRIDE, py], [px - STRIDE, py], [px, py + STRIDE], [px, py - STRIDE]);
      }
      return c ? { x: sx / c, y: sy / c, area: c } : null;
    }
    // หา dark pixel ที่ใกล้มุมเป้าหมายที่สุดในโซน แล้ว flood
    function corner(cornerX, cornerY, zx, zy, zw, zh) {
      var best = null, bestD = Infinity, x, y;
      for (y = zy; y < zy + zh; y += STRIDE)
        for (x = zx; x < zx + zw; x += STRIDE)
          if (lum(img, x, y) <= thr) {
            var d = (x - cornerX) * (x - cornerX) + (y - cornerY) * (y - cornerY);
            if (d < bestD) { bestD = d; best = [x, y]; }
          }
      if (!best) return null;
      var b = blob(best[0], best[1], zx, zy, zw, zh);
      return (b && b.area >= minArea) ? [b.x, b.y] : null;
    }
    var R = rect;
    var tl = corner(R.x, R.y, R.x, R.y, zoneW, zoneH);
    var tr = corner(R.x + R.w, R.y, R.x + R.w - zoneW, R.y, zoneW, zoneH);
    var br = corner(R.x + R.w, R.y + R.h, R.x + R.w - zoneW, R.y + R.h - zoneH, zoneW, zoneH);
    var bl = corner(R.x, R.y + R.h, R.x, R.y + R.h - zoneH, zoneW, zoneH);
    if (!tl || !tr || !br || !bl) return null;
    return { tl: tl, tr: tr, br: br, bl: bl };
  }

  var OMR = { geometry: geometry, bubble: bubble, sampleRadius: sampleRadius,
              homography: homography, mapPt: mapPt, read: read, autoCorners: autoCorners };

  if (typeof module !== 'undefined' && module.exports) module.exports = OMR;
  root.OMR = OMR;
})(typeof window !== 'undefined' ? window : this);

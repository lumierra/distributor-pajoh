#!/usr/bin/env node
/**
 * Print Agent — jembatan cetak dot-matrix untuk Distributor Pajoh.
 *
 * Jalan di PC kasir (Windows). Mendengar di http://localhost:9110.
 * Web mengirim payload ESC/P (teks + kode kontrol) → agent kirim langsung ke
 * printer share → hasil cetak native tajam, tanpa dialog, tanpa file download
 * (jadi TIDAK ada penanda ZoneTransfer).
 *
 * Cara pakai:
 *   1. Install Node.js di PC kasir (sekali).
 *   2. Set nama share printer di config.json (mis. "EPSON310").
 *   3. Jalankan:  node agent.js   (atau lewat start-agent.bat)
 *   4. Tombol "Cetak Dot Matrix" di web akan memanggil agent ini.
 */

const http = require('http');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFile } = require('child_process');

// ── Config ────────────────────────────────────────────────────────────────
const DEFAULTS = {
  port: 9110,
  // Nama share printer Windows (Control Panel → printer → Sharing).
  printerShare: 'EPSON310',
  // Origin web yang boleh memanggil agent (CORS). '*' = semua (LAN internal).
  allowOrigin: '*',
};

let config = { ...DEFAULTS };
try {
  const cfgPath = path.join(__dirname, 'config.json');
  if (fs.existsSync(cfgPath)) {
    config = { ...DEFAULTS, ...JSON.parse(fs.readFileSync(cfgPath, 'utf8')) };
  }
} catch (e) {
  console.error('Gagal baca config.json, pakai default:', e.message);
}

// ── Kirim byte ESC/P ke printer ─────────────────────────────────────────────
// Windows: tulis ke file temp lalu `copy /b tmp \\localhost\<share>`.
// (Print langsung ke share = mode teks native, bukan raster driver.)
function sendToPrinter(buffer) {
  return new Promise((resolve, reject) => {
    const tmp = path.join(os.tmpdir(), `pajoh-print-${Date.now()}.prn`);
    fs.writeFile(tmp, buffer, (err) => {
      if (err) return reject(err);

      const target = `\\\\localhost\\${config.printerShare}`;
      // /b = binary, biar byte ESC/P tak diubah.
      execFile('cmd', ['/c', 'copy', '/b', tmp, target], (e, stdout, stderr) => {
        fs.unlink(tmp, () => {});
        if (e) return reject(new Error(stderr || e.message));
        resolve(stdout.trim());
      });
    });
  });
}

// ── HTTP server ─────────────────────────────────────────────────────────────
const server = http.createServer((req, res) => {
  const cors = {
    'Access-Control-Allow-Origin': config.allowOrigin,
    'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type',
  };

  if (req.method === 'OPTIONS') {
    res.writeHead(204, cors);
    return res.end();
  }

  // Cek status agent (dipakai web untuk deteksi agent hidup).
  if (req.method === 'GET' && req.url === '/ping') {
    res.writeHead(200, { ...cors, 'Content-Type': 'application/json' });
    return res.end(JSON.stringify({ ok: true, printer: config.printerShare }));
  }

  // Cetak: body = payload ESC/P mentah.
  if (req.method === 'POST' && req.url === '/print') {
    const chunks = [];
    req.on('data', (c) => chunks.push(c));
    req.on('end', async () => {
      const buffer = Buffer.concat(chunks);
      if (buffer.length === 0) {
        res.writeHead(400, { ...cors, 'Content-Type': 'application/json' });
        return res.end(JSON.stringify({ ok: false, error: 'Payload kosong.' }));
      }
      try {
        await sendToPrinter(buffer);
        console.log(`[${new Date().toLocaleTimeString()}] Cetak OK (${buffer.length} bytes) → ${config.printerShare}`);
        res.writeHead(200, { ...cors, 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ ok: true }));
      } catch (err) {
        console.error('Gagal cetak:', err.message);
        res.writeHead(500, { ...cors, 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ ok: false, error: err.message }));
      }
    });
    return;
  }

  res.writeHead(404, cors);
  res.end('Not found');
});

server.listen(config.port, '127.0.0.1', () => {
  console.log('════════════════════════════════════════════');
  console.log('  Print Agent Distributor Pajoh — AKTIF');
  console.log(`  Dengar : http://localhost:${config.port}`);
  console.log(`  Printer: ${config.printerShare}`);
  console.log('  Biarkan jendela ini terbuka saat mencetak.');
  console.log('════════════════════════════════════════════');
});

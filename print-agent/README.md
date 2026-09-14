# Print Agent — Distributor Pajoh

Jembatan cetak dot-matrix (Epson LX/LQ). Web kirim payload ESC/P → agent
kirim langsung ke printer share → cetak native tajam, tanpa dialog, tanpa
file download (tidak ada penanda ZoneTransfer).

## Instalasi di PC kasir (Windows) — sekali saja

1. **Install Node.js** (LTS) dari https://nodejs.org — sekali per PC.
2. **Share printer**: Control Panel → Devices and Printers → klik kanan printer
   Epson → Printer properties → tab **Sharing** → centang *Share this printer*,
   beri nama (mis. `EPSON310`).
3. **Set nama share** di `config.json` (field `printerShare`) sesuai langkah 2.
4. **Set kertas** printer ke ukuran faktur yang sudah di-plot (User Defined Paper,
   mis. 25.4 × 13.94 cm) supaya form feed berhenti pas di sobekan.

## Menjalankan

- **Testing / lihat log**: klik dua kali **`start-agent.bat`** (jendela terbuka).
- **Pemakaian harian (tanpa jendela)**: klik dua kali **`start-agent-hidden.vbs`**
  — agent jalan diam-diam di background.
- Agent dengar di `http://localhost:9110`.

### Auto-start saat PC nyala

1. `Win + R` → ketik `shell:startup` → Enter (folder Startup terbuka).
2. Buat **shortcut** dari **`start-agent-hidden.vbs`** (klik kanan → Send to →
   Desktop → lalu pindahkan shortcut ke folder Startup).
3. Selesai — tiap PC dinyalakan, agent jalan otomatis tanpa jendela.

> Untuk menghentikan agent: Task Manager → cari **node.exe** → End task.

## Cek agent hidup

Buka browser: http://localhost:9110/ping → harus muncul `{"ok":true,...}`.

## Config (`config.json`)

| Field | Arti |
|---|---|
| `port` | Port lokal agent (default 9110). |
| `printerShare` | Nama share printer Windows. |
| `allowOrigin` | Origin web yang boleh memanggil (default `*`; produksi: kunci ke domain). |

---

## Cara kerja (kenapa web di VPS tetap bisa cetak ke printer kantor)

Printer itu barang fisik di kantor/cabang, **bukan** di server. Jadi di mana pun
web di-host (localhost, VPS `https://distributor.pajoh.id`, cloud), perintah
cetak selalu lewat **PC yang tercolok printer**. Yang menjembatani adalah
**browser di PC kasir**:

```
distributor.pajoh.id (VPS di internet)
      │  kasir buka web, klik "Cetak Dot Matrix"
      │  browser AMBIL data ESC/P dari VPS (pakai sesi login)
      ▼
 Browser di PC kasir  ──POST──►  Print Agent (localhost:9110, PC yang sama)
                                      │
                                      ▼
                                 Epson LX-310 (colok USB di kantor)
```

VPS tidak pernah bicara langsung ke printer — memang tidak bisa & tidak perlu.
Karena tombol memanggil `localhost`, hasil cetak SELALU keluar di printer PC
yang sedang dipakai kasir itu.

## Deploy ke VPS (produksi) — checklist

1. **VPS pakai HTTPS** (Let's Encrypt). Panggilan HTTPS → `http://localhost:9110`
   umumnya diizinkan browser (localhost dikecualikan dari aturan mixed-content).
   Kalau suatu browser tetap memblokir, hubungi dev untuk opsi agent HTTPS lokal.
2. **Kunci CORS**: set `allowOrigin` ke `"https://distributor.pajoh.id"` supaya
   hanya web Anda yang boleh memanggil agent di PC kasir.
3. Tiap PC kasir: install Node.js + copy folder ini + jalankan `start-agent.bat`
   (set auto-start via `shell:startup`).

## Multi-cabang / banyak lokasi

Desain ini skalabel **tanpa ubah kode**. Tiap cabang cukup pasang agent sendiri:

| Yang SAMA di semua cabang | Yang BEDA per cabang |
|---|---|
| `agent.js` (identik) | `config.json` → `printerShare` (nama share printer cabang itu) |
| Port `9110`, endpoint `/print` | Ukuran kertas di driver printer (User Defined Paper) |
| Web (satu VPS) | — |

Contoh: Cabang A set `"printerShare": "EPSON-A"`, Cabang B `"EPSON-B"`. Kasir
Cabang A klik cetak → keluar di printer A; Cabang B → printer B. Otomatis benar
karena `localhost` selalu = PC yang sedang dipakai. Web (VPS) tidak perlu tahu
apa-apa soal printer tiap cabang.

## Troubleshooting

| Gejala | Sebab & solusi |
|---|---|
| Toast "Print Agent tidak terhubung" | Agent belum jalan → buka `start-agent.bat`. Cek `http://localhost:9110/ping`. |
| "Access is denied" saat cetak | Share printer / izin. Coba nama share benar; pastikan printer nyala & online. |
| Kertas ada tulisan `[ZoneTransfer]` | Hanya muncul kalau cetak file yang di-download manual. Lewat tombol web (agent) TIDAK muncul. |
| Font berbayang | Sudah dipaksa mode NLQ. Kalau masih, cek pita printer / setting Draft di driver. |
| Form feed tidak pas sobekan | Set ukuran kertas di driver (User Defined Paper) sesuai continuous form. |

'use strict';

const fs = require('fs');
const path = require('path');
const express = require('express');
const QRCode = require('qrcode');
const pino = require('pino');
const {
  default: makeWASocket,
  useMultiFileAuthState,
  DisconnectReason,
  fetchLatestBaileysVersion,
  getContentType,
} = require('@whiskeysockets/baileys');

const SESSION_DIR = path.join(__dirname, 'sessions', 'default');
const logger = pino({ level: process.env.WA_LOG_LEVEL || 'warn' });

let sock = null;
let connecting = false;
let qrRaw = null;
let qrDataUrl = null;
let connectionStatus = 'disconnect';
let messageCount = 0;

const deviceInfo = {
  device: '',
  name: 'NUMART',
  package: 'numart-local',
  quota: 'unlimited',
  messages: '0',
  expired: 'never',
};

function loadDotEnv() {
  const envPath = path.join(__dirname, '.env');
  if (!fs.existsSync(envPath)) return;
  const lines = fs.readFileSync(envPath, 'utf8').split(/\r?\n/);
  for (const line of lines) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const idx = trimmed.indexOf('=');
    if (idx < 1) continue;
    const key = trimmed.slice(0, idx).trim();
    let val = trimmed.slice(idx + 1).trim();
    if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith("'") && val.endsWith("'"))) {
      val = val.slice(1, -1);
    }
    if (process.env[key] === undefined) process.env[key] = val;
  }
}

loadDotEnv();

const PORT = Number(process.env.WA_ENGINE_PORT || 3920);
const API_SECRET = String(process.env.WA_API_SECRET || 'change-me');
const DEVICE_NAME = String(process.env.WA_DEVICE_NAME || 'NUMART');
const WEBHOOK_URL = String(process.env.WA_WEBHOOK_URL || '').trim();
deviceInfo.name = DEVICE_NAME;

function fonnteOk(payload) {
  return { status: true, ...payload };
}

function fonnteFail(reason, extra = {}) {
  return { status: false, reason: String(reason), ...extra };
}

function normalizeIdPhone(raw) {
  const digits = String(raw || '').replace(/\D+/g, '');
  if (!digits) return '';
  if (digits.startsWith('62')) return digits;
  if (digits.startsWith('0')) return '62' + digits.slice(1);
  return '62' + digits;
}

function toJid(target) {
  const n = normalizeIdPhone(target);
  if (!n) return '';
  return n + '@s.whatsapp.net';
}

async function postWebhook(event, data) {
  if (!WEBHOOK_URL) return;
  try {
    await fetch(WEBHOOK_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ event, data, ts: Date.now() }),
    });
  } catch (err) {
    logger.warn({ err: err.message }, 'webhook failed');
  }
}

async function startWhatsApp() {
  if (connecting) return;
  connecting = true;

  try {
    if (!fs.existsSync(SESSION_DIR)) {
      fs.mkdirSync(SESSION_DIR, { recursive: true });
    }

    const { state, saveCreds } = await useMultiFileAuthState(SESSION_DIR);
    const { version } = await fetchLatestBaileysVersion();

    sock = makeWASocket({
      version,
      auth: state,
      logger,
      printQRInTerminal: false,
      browser: ['NUMART', 'Chrome', '1.0.0'],
      syncFullHistory: false,
      markOnlineOnConnect: false,
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async (update) => {
      const { connection, lastDisconnect, qr } = update;

      if (qr) {
        qrRaw = qr;
        qrDataUrl = await QRCode.toDataURL(qr, { margin: 1, width: 320 });
        connectionStatus = 'qr';
        await postWebhook('device.qr', { device_status: 'qr' });
      }

      if (connection === 'close') {
        const statusCode = lastDisconnect?.error?.output?.statusCode;
        const loggedOut = statusCode === DisconnectReason.loggedOut;
        connectionStatus = 'disconnect';
        qrRaw = null;
        qrDataUrl = null;
        sock = null;
        connecting = false;
        await postWebhook('device.disconnect', { device_status: 'disconnect', logged_out: loggedOut });

        if (!loggedOut) {
          setTimeout(() => startWhatsApp(), 3000);
        }
      } else if (connection === 'open') {
        connectionStatus = 'connect';
        qrRaw = null;
        qrDataUrl = null;
        connecting = false;

        const me = sock?.user;
        const phone = me?.id ? String(me.id).split(':')[0].split('@')[0] : '';
        deviceInfo.device = phone;
        deviceInfo.name = me?.name || DEVICE_NAME;
        deviceInfo.messages = String(messageCount);

        await postWebhook('device.connect', {
          device_status: 'connect',
          device: deviceInfo.device,
          name: deviceInfo.name,
        });
      }
    });

    sock.ev.on('messages.upsert', async (m) => {
      if (!WEBHOOK_URL) return;
      for (const msg of m.messages || []) {
        if (!msg.key || msg.key.fromMe) continue;
        await postWebhook('message.inbound', {
          from: msg.key.remoteJid,
          id: msg.key.id,
          type: getContentType(msg.message),
        });
      }
    });
  } catch (err) {
    connecting = false;
    logger.error({ err: err.message }, 'startWhatsApp failed');
    setTimeout(() => startWhatsApp(), 5000);
  }
}

async function downloadUrlBuffer(url) {
  const res = await fetch(url);
  if (!res.ok) throw new Error('Gagal unduh media: HTTP ' + res.status);
  const ab = await res.arrayBuffer();
  return Buffer.from(ab);
}

function guessMime(url, fallback = 'image/jpeg') {
  const lower = String(url).toLowerCase();
  if (lower.endsWith('.png')) return 'image/png';
  if (lower.endsWith('.webp')) return 'image/webp';
  if (lower.endsWith('.gif')) return 'image/gif';
  if (lower.endsWith('.pdf')) return 'application/pdf';
  if (lower.endsWith('.mp4')) return 'video/mp4';
  return fallback;
}

async function sendWhatsAppMessage(target, message, options = {}) {
  if (connectionStatus !== 'connect' || !sock) {
    return fonnteFail('device disconnected');
  }

  const jid = toJid(target);
  if (!jid) return fonnteFail('invalid target');

  const connectOnly = options.connectOnly === true || options.connectOnly === 'true';
  if (connectOnly) {
    const onWa = await sock.onWhatsApp(jid);
    const exists = Array.isArray(onWa) && onWa[0] && onWa[0].exists;
    if (!exists) {
      return fonnteFail('target not on whatsapp', { target: normalizeIdPhone(target) });
    }
    return fonnteOk({
      detail: 'connected only',
      target: normalizeIdPhone(target),
      id: [],
    });
  }

  try {
    let sent;
    const url = String(options.url || '').trim();
    const filename = String(options.filename || '').trim();

    if (url) {
      const buffer = await downloadUrlBuffer(url);
      const mime = guessMime(url);
      if (mime.startsWith('image/')) {
        sent = await sock.sendMessage(jid, {
          image: buffer,
          caption: message || undefined,
        });
      } else if (mime.startsWith('video/')) {
        sent = await sock.sendMessage(jid, {
          video: buffer,
          caption: message || undefined,
        });
      } else {
        sent = await sock.sendMessage(jid, {
          document: buffer,
          mimetype: mime,
          fileName: filename || path.basename(url) || 'file',
          caption: message || undefined,
        });
      }
    } else if (!message) {
      return fonnteFail('message or url required');
    } else {
      sent = await sock.sendMessage(jid, { text: message });
    }

    messageCount += 1;
    deviceInfo.messages = String(messageCount);

    const delaySec = Math.max(0, Number(options.delay) || 0);
    if (delaySec > 0) {
      await new Promise((r) => setTimeout(r, delaySec * 1000));
    }

    return fonnteOk({
      detail: 'success',
      target: normalizeIdPhone(target),
      id: sent?.key?.id ? [sent.key.id] : [],
    });
  } catch (err) {
    return fonnteFail(err.message || 'send failed');
  }
}

async function validateNumber(target) {
  if (connectionStatus !== 'connect' || !sock) {
    return fonnteFail('device disconnected');
  }
  const jid = toJid(target);
  if (!jid) return fonnteFail('invalid target');

  const onWa = await sock.onWhatsApp(jid);
  const exists = Array.isArray(onWa) && onWa[0] && onWa[0].exists;

  if (exists) {
    return fonnteOk({
      target: normalizeIdPhone(target),
      detail: 'valid',
      registered: true,
    });
  }
  return fonnteFail('not registered', { target: normalizeIdPhone(target), registered: false });
}

function devicePayload() {
  return fonnteOk({
    device: deviceInfo.device,
    device_status: connectionStatus,
    name: deviceInfo.name,
    package: deviceInfo.package,
    quota: deviceInfo.quota,
    messages: deviceInfo.messages,
    expired: deviceInfo.expired,
  });
}

function authMiddleware(req, res, next) {
  const header = String(req.headers.authorization || '').trim();
  const bodyToken = String(req.body?.token || req.query?.token || '').trim();
  const token = header || bodyToken;
  if (!token || token !== API_SECRET) {
    return res.status(403).json(fonnteFail('unauthorized'));
  }
  return next();
}

const app = express();
app.use(express.json({ limit: '2mb' }));
app.use(express.urlencoded({ extended: true }));

app.get('/health', (_req, res) => {
  res.json({
    ok: true,
    engine: 'numart-wa-engine',
    device_status: connectionStatus,
    uptime: process.uptime(),
  });
});

app.get('/qr', authMiddleware, (_req, res) => {
  res.json({
    status: true,
    device_status: connectionStatus,
    device: deviceInfo.device,
    name: deviceInfo.name,
    messages: deviceInfo.messages,
    qr: qrDataUrl,
    has_qr: Boolean(qrDataUrl),
  });
});

app.post('/device', authMiddleware, (_req, res) => {
  res.json(devicePayload());
});

app.get('/device', authMiddleware, (_req, res) => {
  res.json(devicePayload());
});

app.post('/send', authMiddleware, async (req, res) => {
  const target = req.body?.target || req.body?.phone || req.body?.to || '';
  const message = req.body?.message || req.body?.text || '';
  const options = {
    url: req.body?.url || req.body?.image || req.body?.file || '',
    filename: req.body?.filename || '',
    delay: req.body?.delay || '0',
    connectOnly: req.body?.connectOnly,
  };
  const result = await sendWhatsAppMessage(target, message, options);
  res.status(result.status ? 200 : 422).json(result);
});

app.post('/validate', authMiddleware, async (req, res) => {
  const target = req.body?.target || req.body?.phone || '';
  const result = await validateNumber(target);
  res.status(result.status ? 200 : 422).json(result);
});

app.post('/logout', authMiddleware, async (_req, res) => {
  try {
    if (sock) {
      await sock.logout();
    }
  } catch (_e) {
    /* ignore */
  }
  sock = null;
  connectionStatus = 'disconnect';
  qrRaw = null;
  qrDataUrl = null;
  connecting = false;

  try {
    if (fs.existsSync(SESSION_DIR)) {
      fs.rmSync(SESSION_DIR, { recursive: true, force: true });
    }
  } catch (_e) {
    /* ignore */
  }

  setTimeout(() => startWhatsApp(), 1500);

  res.json(fonnteOk({ detail: 'logged out, scan QR again' }));
});

app.post('/restart', authMiddleware, async (_req, res) => {
  try {
    if (sock) sock.end(undefined);
  } catch (_e) {
    /* ignore */
  }
  sock = null;
  connecting = false;
  setTimeout(() => startWhatsApp(), 500);
  res.json(fonnteOk({ detail: 'restarting session' }));
});

app.listen(PORT, '127.0.0.1', () => {
  logger.info({ port: PORT }, 'NUMART WA engine listening');
  startWhatsApp();
});

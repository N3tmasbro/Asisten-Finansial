require('dotenv').config();

const fs = require('fs');
const path = require('path');
const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion } = require('@whiskeysockets/baileys');
const pino = require('pino');
const { Boom } = require('@hapi/boom');
const qrcode = require('qrcode-terminal');
const { forwardToLaravel } = require('./webhook');
const { createSenderApp } = require('./sender');

// ─── File Logger Setup ────────────────────────────────────────────────────────
const logsDir = path.join(__dirname, '..', 'logs');
if (!fs.existsSync(logsDir)) fs.mkdirSync(logsDir, { recursive: true });

function getLogFile() {
    const date = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
    return path.join(logsDir, `bridge-${date}.log`);
}

function writeLog(level, ...args) {
    const timestamp = new Date().toISOString();
    const line = `[${timestamp}] [${level}] ${args.join(' ')}`;
    // Write to log file
    fs.appendFileSync(getLogFile(), line + '\n', 'utf8');
    return line;
}

// Override console methods to tee output to file AND terminal
const _log   = console.log.bind(console);
const _error = console.error.bind(console);
const _warn  = console.warn.bind(console);

console.log = (...args) => {
    _log(...args);
    writeLog('INFO', ...args.map(String));
};
console.error = (...args) => {
    _error(...args);
    writeLog('ERROR', ...args.map(String));
};
console.warn = (...args) => {
    _warn(...args);
    writeLog('WARN', ...args.map(String));
};

// ─────────────────────────────────────────────────────────────────────────────

const logger = pino({ level: 'info' });
const PORT = process.env.PORT || 3001;

let sock = null;


async function connectToWhatsApp() {
    const { state, saveCreds } = await useMultiFileAuthState('./auth_state');
    const { version } = await fetchLatestBaileysVersion();

    sock = makeWASocket({
        version,
        auth: state,
        logger: pino({ level: 'silent' }),
        printQRInTerminal: false,
        browser: ['Asisten Finansial AI', 'Chrome', '120.0.0'],
    });

    // Handle connection updates
    sock.ev.on('connection.update', (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            console.log('\n📱 Scan QR code ini dengan WhatsApp kamu:\n');
            qrcode.generate(qr, { small: true });
            console.log('\n');
        }

        if (connection === 'close') {
            const reason = new Boom(lastDisconnect?.error)?.output?.statusCode;

            if (reason === DisconnectReason.loggedOut) {
                console.log('❌ Logged out from WhatsApp. Delete auth_state folder and restart.');
            } else {
                console.log(`⚠️ Connection closed (reason: ${reason}). Reconnecting...`);
                setTimeout(connectToWhatsApp, 3000);
            }
        } else if (connection === 'open') {
            console.log('✅ Connected to WhatsApp!');
        }
    });

    // Save credentials on update
    sock.ev.on('creds.update', saveCreds);

    // Handle incoming messages
    sock.ev.on('messages.upsert', async ({ messages, type }) => {
        if (type !== 'notify') return;

        for (const msg of messages) {
            // Skip if not a text message or from self
            if (!msg.message || msg.key.fromMe) continue;

            const remoteJid = msg.key.remoteJid || '';

            // Skip group messages
            if (remoteJid.endsWith('@g.us') || remoteJid.endsWith('@broadcast')) {
                continue;
            }

            const text = msg.message.conversation
                || msg.message.extendedTextMessage?.text
                || '';

            if (!text.trim()) continue;

            // Handle both regular JID (@s.whatsapp.net) and LID (@lid) formats
            const isLid = remoteJid.endsWith('@lid');
            const identifier = remoteJid
                .replace('@s.whatsapp.net', '')
                .replace('@lid', '');

            console.log(`📩 Message from ${identifier}${isLid ? ' (LID)' : ''}: ${text}`);

            // Forward to Laravel
            try {
                await forwardToLaravel({
                    from: identifier,
                    reply_jid: remoteJid,
                    message: text,
                    timestamp: msg.messageTimestamp || Math.floor(Date.now() / 1000),
                    message_id: msg.key.id,
                });
                console.log(`✅ Forwarded to Laravel`);
            } catch (error) {
                console.error(`❌ Failed to forward: ${error.message}`);
            }
        }
    });

    return sock;
}

// Start Express server for sending messages
const app = createSenderApp(() => sock);

app.get('/status', (req, res) => {
    const bridgeSecret = process.env.BRIDGE_SECRET;
    if (bridgeSecret && req.query.bridge_secret !== bridgeSecret) {
        return res.status(401).json({ error: 'Unauthorized' });
    }

    res.json({
        status: sock?.user ? 'connected' : 'disconnected',
        user: sock?.user || null,
    });
});

app.get('/health', (req, res) => {
    res.json({ status: 'ok', timestamp: new Date().toISOString() });
});

app.listen(PORT, () => {
    console.log(`\n🌉 WhatsApp Bridge running on port ${PORT}`);
    console.log(`📡 Laravel webhook: ${process.env.LARAVEL_WEBHOOK_URL}`);
    console.log('');
    connectToWhatsApp();
});

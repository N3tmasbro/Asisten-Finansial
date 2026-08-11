require('dotenv').config();

const fs = require('fs');
const path = require('path');
const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, decryptPollVote } = require('@whiskeysockets/baileys');
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

    // Handle message updates (like poll votes)
    sock.ev.on('messages.update', async (updates) => {
        for (const update of updates) {
            const pollUpdates = update.update?.pollUpdates;
            if (!pollUpdates || pollUpdates.length === 0) continue;

            const pollMsgId = update.key.id;
            const remoteJid = update.key.remoteJid;

            console.log(`🗳️ Poll update detected for message ${pollMsgId}`);

            const { getPoll, hashOption } = require('./polls');
            const poll = getPoll(pollMsgId);
            if (!poll) {
                console.log(`⚠️ Poll update received for unknown poll ${pollMsgId}`);
                continue;
            }

            for (const voteUpdate of pollUpdates) {
                try {
                    const voterJid = voteUpdate.pollUpdateMessageKey?.participant
                        || voteUpdate.senderJid
                        || remoteJid;
                    const identifier = voterJid
                        .replace('@s.whatsapp.net', '')
                        .replace('@lid', '');

                    console.log(`🗳️ Decrypting vote from ${identifier}...`);

                    const decryptedVote = decryptPollVote(
                        voteUpdate.vote,
                        {
                            pollCreatorJid: sock.user.id,
                            pollMsgId: pollMsgId,
                            pollEncKey: poll.messageSecret,
                            voterJid: voterJid
                        }
                    );

                    const selectedHashes = (decryptedVote.selectedOptions || []).map(
                        opt => Buffer.isBuffer(opt) ? opt.toString('hex') : opt
                    );

                    console.log(`🗳️ Decrypted hashes: ${JSON.stringify(selectedHashes)}`);

                    if (selectedHashes.length === 0) {
                        console.log(`🗳️ Voter ${identifier} cleared their vote on poll ${pollMsgId}`);
                        continue;
                    }

                    // Match hashes to option text
                    const selectedOptions = [];
                    for (const option of poll.options) {
                        const optionHash = hashOption(option);
                        if (selectedHashes.includes(optionHash)) {
                            selectedOptions.push(option);
                        }
                    }

                    if (selectedOptions.length > 0) {
                        console.log(`🗳️ Poll vote from ${identifier}: [${selectedOptions.join(', ')}]`);

                        const chosenOption = selectedOptions[0];
                        
                        await forwardToLaravel({
                            from: identifier,
                            reply_jid: voterJid,
                            message: chosenOption,
                            is_poll_vote: true,
                            poll_message_id: pollMsgId,
                            timestamp: Math.floor(Date.now() / 1000),
                            message_id: `pollvote-${pollMsgId}-${Date.now()}`
                        });
                        console.log(`✅ Forwarded poll vote to Laravel`);
                    } else {
                        console.log(`⚠️ No matching options found. Option hashes: ${poll.options.map(o => hashOption(o)).join(', ')}`);
                    }

                } catch (error) {
                    console.error(`❌ Failed to process poll vote: ${error.message}`);
                    console.error(error.stack);
                }
            }
        }
    });

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

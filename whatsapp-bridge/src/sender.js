/**
 * Express app that exposes an endpoint for Laravel to send messages through WhatsApp.
 */

const express = require('express');

const BRIDGE_SECRET = process.env.BRIDGE_SECRET || '';

function createSenderApp(getSocket) {
    const app = express();
    app.use(express.json());

    /**
     * POST /send
     * Body: { to: "6281234567890", message: "Hello!", bridge_secret: "..." }
     */
    app.post('/send', async (req, res) => {
        const { to, message, bridge_secret } = req.body;

        // Validate bridge secret
        if (BRIDGE_SECRET && bridge_secret !== BRIDGE_SECRET) {
            return res.status(401).json({ error: 'Unauthorized' });
        }

        if (!to || !message) {
            return res.status(400).json({ error: 'Missing "to" or "message" field' });
        }

        const sock = getSocket();
        if (!sock || !sock.user) {
            return res.status(503).json({ error: 'WhatsApp not connected' });
        }

        try {
            // Support full JID (e.g. 46132327653514@lid) or phone number
            const jid = to.includes('@') ? to : `${to}@s.whatsapp.net`;

            await sock.sendMessage(jid, { text: message });

            console.log(`📤 Message sent to ${to}: ${message.substring(0, 50)}...`);

            res.json({ status: 'sent', to });
        } catch (error) {
            console.error(`❌ Failed to send message to ${to}: ${error.message}`);
            res.status(500).json({ error: 'Failed to send message', details: error.message });
        }
    });

    return app;
}

module.exports = { createSenderApp };

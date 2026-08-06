/**
 * Forward incoming WhatsApp messages to the Laravel webhook.
 */

const LARAVEL_WEBHOOK_URL = process.env.LARAVEL_WEBHOOK_URL || 'http://localhost:8000/api/webhooks/whatsapp';
const BRIDGE_SECRET = process.env.BRIDGE_SECRET || '';

async function forwardToLaravel(payload) {
    const body = {
        ...payload,
        bridge_secret: BRIDGE_SECRET,
    };

    const response = await fetch(LARAVEL_WEBHOOK_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        const text = await response.text();
        throw new Error(`Laravel returned ${response.status}: ${text}`);
    }

    return response.json();
}

module.exports = { forwardToLaravel };

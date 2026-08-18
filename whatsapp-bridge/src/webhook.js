const axios = require('axios');

const LARAVEL_WEBHOOK_URL = process.env.LARAVEL_WEBHOOK_URL || 'http://localhost:8000/api/webhooks/whatsapp';
const BRIDGE_SECRET = process.env.BRIDGE_SECRET || '';

async function forwardToLaravel(payload) {
    const body = {
        ...payload,
        bridge_secret: BRIDGE_SECRET,
    };

    try {
        const response = await axios.post(LARAVEL_WEBHOOK_URL, body, {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Host': 'api.savings.marridho.tech',
            },
        });
        return response.data;
    } catch (error) {
        const status = error.response?.status;
        const data = error.response?.data;
        throw new Error(`Laravel returned ${status || error.message}: ${JSON.stringify(data || error.message)}`);
    }
}

module.exports = { forwardToLaravel };

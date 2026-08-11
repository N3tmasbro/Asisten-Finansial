const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const SECRETS_FILE = path.join(__dirname, '..', 'auth_state', 'poll_secrets.json');

function loadSecrets() {
    try {
        if (fs.existsSync(SECRETS_FILE)) {
            const raw = fs.readFileSync(SECRETS_FILE, 'utf8');
            return JSON.parse(raw);
        }
    } catch (e) {
        console.error('Error loading poll secrets:', e.message);
    }
    return {};
}

function saveSecrets(secrets) {
    try {
        fs.writeFileSync(SECRETS_FILE, JSON.stringify(secrets, null, 2), 'utf8');
    } catch (e) {
        console.error('Error saving poll secrets:', e.message);
    }
}

/**
 * Save a poll metadata.
 * @param {string} pollId 
 * @param {string[]} options 
 * @param {Uint8Array} messageSecret 
 */
function savePoll(pollId, options, messageSecret) {
    const secrets = loadSecrets();
    // Convert Uint8Array/Buffer to base64 for storage
    const secretB64 = Buffer.from(messageSecret).toString('base64');
    secrets[pollId] = {
        options,
        secretB64,
        created: Date.now()
    };
    saveSecrets(secrets);
}

/**
 * Get poll metadata.
 * @param {string} pollId 
 */
function getPoll(pollId) {
    const secrets = loadSecrets();
    const poll = secrets[pollId];
    if (!poll) return null;
    
    return {
        options: poll.options,
        messageSecret: Buffer.from(poll.secretB64, 'base64')
    };
}

/**
 * Clean up old poll secrets (e.g. older than 24 hours).
 */
function pruneOldPolls() {
    const secrets = loadSecrets();
    const now = Date.now();
    const expiry = 24 * 60 * 60 * 1000; // 24 hours
    let changed = false;
    for (const id in secrets) {
        if (now - secrets[id].created > expiry) {
            delete secrets[id];
            changed = true;
        }
    }
    if (changed) {
        saveSecrets(secrets);
    }
}

/**
 * Compute the SHA-256 hash of an option name (hex-encoded).
 * @param {string} text 
 */
function hashOption(text) {
    return crypto.createHash('sha256').update(text).digest('hex');
}

module.exports = {
    savePoll,
    getPoll,
    pruneOldPolls,
    hashOption
};

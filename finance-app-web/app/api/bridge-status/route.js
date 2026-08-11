/**
 * Next.js API Route — WhatsApp Bridge Status Proxy
 * 
 * Proxies requests to the WhatsApp bridge running on localhost:3001
 * to avoid CORS issues when calling from the browser.
 */
export async function GET() {
  const bridgeUrl = process.env.WA_BRIDGE_URL || 'http://localhost:3001';
  const bridgeSecret = process.env.WA_BRIDGE_SECRET || 'your-secret-key-here';

  try {
    const response = await fetch(`${bridgeUrl}/status?bridge_secret=${bridgeSecret}`, {
      signal: AbortSignal.timeout(5000),
    });

    if (!response.ok) {
      return Response.json({ status: 'error', message: 'Bridge returned error' }, { status: 502 });
    }

    const data = await response.json();
    return Response.json(data);
  } catch (err) {
    return Response.json({
      status: 'disconnected',
      user: null,
      error: 'WhatsApp Bridge tidak aktif',
    });
  }
}

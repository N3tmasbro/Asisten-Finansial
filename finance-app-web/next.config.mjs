/** @type {import('next').NextConfig} */
const nextConfig = {
  // Disable Strict Mode to prevent double-invocation of effects in development
  // (which causes duplicate API calls and visual reload flicker)
  reactStrictMode: false,
};

export default nextConfig;

/**
 * Format a number as Indonesian Rupiah.
 * @param {number} amount
 * @returns {string} e.g., "Rp50.000"
 */
export function formatRupiah(amount) {
  if (amount === null || amount === undefined) return 'Rp0';
  const isNegative = amount < 0;
  const formatted = Math.abs(amount).toLocaleString('id-ID');
  return `${isNegative ? '-' : ''}Rp${formatted}`;
}

/**
 * Format a date to Indonesian locale.
 * @param {string|Date} date
 * @param {object} options - Intl.DateTimeFormat options
 * @returns {string}
 */
export function formatDate(date, options = {}) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    ...options,
  });
}

/**
 * Format a percentage with sign.
 * @param {number} value
 * @returns {string} e.g., "+15.2%" or "-3.5%"
 */
export function formatPercent(value) {
  if (value === null || value === undefined) return '0%';
  const sign = value > 0 ? '+' : '';
  return `${sign}${value.toFixed(1)}%`;
}

/**
 * Get a human-readable period label.
 * @param {string} period
 * @returns {string}
 */
export function getPeriodLabel(period) {
  const labels = {
    today: 'Hari Ini',
    this_week: 'Minggu Ini',
    last_week: 'Minggu Lalu',
    this_month: 'Bulan Ini',
    last_month: 'Bulan Lalu',
  };
  return labels[period] || period;
}

/**
 * Truncate text with ellipsis.
 * @param {string} text
 * @param {number} maxLength
 * @returns {string}
 */
export function truncate(text, maxLength = 30) {
  if (!text) return '';
  return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
}

/**
 * Get the transaction type badge color class.
 * @param {string} type - 'expense' or 'income'
 * @returns {string}
 */
export function getTypeBadgeClass(type) {
  return type === 'income' ? 'badge-income' : 'badge-expense';
}

/**
 * Check if a Sanctum token exists.
 * @returns {boolean}
 */
export function isAuthenticated() {
  if (typeof window === 'undefined') return false;
  return !!localStorage.getItem('auth_token');
}

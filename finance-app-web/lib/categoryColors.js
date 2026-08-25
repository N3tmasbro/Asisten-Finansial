/**
 * Category color mappings using CSS variables.
 * Provides theme-aware colors for category icons/backgrounds.
 */

export const CATEGORY_VARS = {
  'Makan & Minum': { bg: 'var(--cat-food-bg)', icon: 'var(--cat-food-icon)' },
  'Belanja': { bg: 'var(--cat-shop-bg)', icon: 'var(--cat-shop-icon)' },
  'Transport': { bg: 'var(--cat-transport-bg)', icon: 'var(--cat-transport-icon)' },
  'Tagihan': { bg: 'var(--cat-bill-bg)', icon: 'var(--cat-bill-icon)' },
  'Hiburan': { bg: 'var(--cat-entertainment-bg)', icon: 'var(--cat-entertainment-icon)' },
  'Kesehatan': { bg: 'var(--cat-health-bg)', icon: 'var(--cat-health-icon)' },
  'Lainnya': { bg: 'var(--cat-other-bg)', icon: 'var(--cat-other-icon)' },
  'Bonus/THR': { bg: 'var(--cat-bonus-bg)', icon: 'var(--cat-bonus-icon)' },
};

export function getCategoryStyle(category) {
  return CATEGORY_VARS[category] ?? { bg: 'var(--cat-other-bg)', icon: 'var(--cat-other-icon)' };
}

/* Resolved hex values for chart libraries (Recharts can't read CSS vars) */
export const CATEGORY_CHART_COLORS_LIGHT = {
  'Makan & Minum': '#FED7AA',
  'Belanja': '#A7F3D0',
  'Transport': '#BAE6FD',
  'Tagihan': '#DDD6FE',
  'Hiburan': '#FBCFE8',
  'Kesehatan': '#CFFAFE',
  'Lainnya': '#FEF3C7',
};

export const CATEGORY_CHART_COLORS_DARK = {
  'Makan & Minum': '#FF8C42',
  'Belanja': '#3B82F6',
  'Transport': '#A855F7',
  'Tagihan': '#EC4899',
  'Hiburan': '#EF4444',
  'Kesehatan': '#06B6D4',
  'Lainnya': '#FBBF24',
};

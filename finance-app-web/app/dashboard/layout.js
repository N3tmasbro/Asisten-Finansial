import ResponsiveLayout from '../../components/ResponsiveLayout';

export const metadata = {
  title: 'Dashboard — Asisten Finansial AI',
};

export default function DashboardLayout({ children }) {
  return (
    <ResponsiveLayout>
      {children}
    </ResponsiveLayout>
  );
}

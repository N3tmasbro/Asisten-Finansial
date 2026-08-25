import Sidebar from '../../components/Sidebar';

export const metadata = {
  title: 'Dashboard — Asisten Finansial AI',
};

export default function DashboardLayout({ children }) {
  return (
    <div style={{ display: 'flex', minHeight: '100vh' }}>
      <Sidebar />
      <main style={{ marginLeft: 200, flex: 1, minHeight: '100vh', backgroundColor: 'var(--bg-base)' }}>
        {children}
      </main>
    </div>
  );
}

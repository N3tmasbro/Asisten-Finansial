import Sidebar from '../../components/Sidebar';
export default function BudgetsLayout({ children }) {
  return (
    <div style={{ display: 'flex', minHeight: '100vh' }}>
      <Sidebar />
      <main style={{ marginLeft: 200, flex: 1, minHeight: '100vh', backgroundColor: 'var(--bg-base)' }}>{children}</main>
    </div>
  );
}

import Sidebar from '../../components/Sidebar';

export const metadata = {
  title: 'Dashboard — Asisten Finansial AI',
};

export default function DashboardLayout({ children }) {
  return (
    <div className="flex min-h-screen">
      <Sidebar />
      <main className="ml-64 flex-1 p-8 min-h-screen">
        {children}
      </main>
    </div>
  );
}

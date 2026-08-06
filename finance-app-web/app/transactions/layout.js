import Sidebar from '../../components/Sidebar';

export default function TransactionsLayout({ children }) {
  return (
    <div className="flex min-h-screen">
      <Sidebar />
      <main className="ml-64 flex-1 p-8 min-h-screen">
        {children}
      </main>
    </div>
  );
}

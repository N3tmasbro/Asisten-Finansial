import "./globals.css";

export const metadata = {
  title: "Asisten Finansial AI — Dashboard",
  description: "Pencatat keuangan cerdas berbasis AI. Catat pengeluaran via WhatsApp, pantau keuangan via dashboard.",
};

export default function RootLayout({ children }) {
  return (
    <html lang="id">
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
      </head>
      <body className="antialiased">
        {children}
      </body>
    </html>
  );
}

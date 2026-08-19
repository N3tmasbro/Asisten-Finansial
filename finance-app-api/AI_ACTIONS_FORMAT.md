# Format Action Chatbot AI

Dokumen ini menjelaskan daftar `action` dan format JSON yang dihasilkan oleh AI (Gemini) ketika memproses pesan dari user (terutama untuk manajemen catatan dan budget keuangan). Format JSON ini digunakan oleh `ManageRecordsHandlerService` dan handler lainnya di backend Laravel.

## Format Dasar JSON

```json
{
  "action": "...",
  "target": {
    "description": null,
    "amount": null,
    "category": null,
    "wallet": null,
    "date": null
  },
  "changes": {
    "amount": null,
    "category": null,
    "wallet": null,
    "description": null,
    "date": null,
    "type": null
  },
  "data": {
    "name": null,
    "type": null,
    "amount": null,
    "category": null,
    "period": null,
    "old_name": null,
    "new_name": null,
    "wallets": null,
    "budgets": null
  },
  "confidence": 0.0
}
```

## Daftar Action yang Tersedia

### 1. Transaksi (Transaction)

*   `update_transaction`: Koreksi transaksi yang sudah ada.
    *   **Contoh User:** "yang kopi tadi harusnya 75 ribu"
    *   **JSON Output:**
        ```json
        {"action":"update_transaction","target":{"description":"kopi"},"changes":{"amount":75000},"data":null,"confidence":0.9}
        ```
*   `delete_transaction`: Hapus transaksi.
    *   **Contoh User:** "hapus transaksi makan tadi"
    *   **JSON Output:**
        ```json
        {"action":"delete_transaction","target":{"description":"makan"},"changes":null,"data":null,"confidence":0.85}
        ```

### 2. Wallet (Dompet)

*   `create_wallet`: Buat wallet baru tanpa saldo awal (saldo = 0).
    *   **Contoh User:** "buat wallet Dana"
    *   **JSON Output:**
        ```json
        {"action":"create_wallet","target":null,"changes":null,"data":{"name":"Dana"},"confidence":0.95}
        ```
*   `create_wallet_with_balance`: Buat wallet baru sekaligus set saldo awal.
    *   **Contoh User:** "buat wallet BCA dengan saldo 5jt"
    *   **JSON Output:**
        ```json
        {"action":"create_wallet_with_balance","target":null,"changes":null,"data":{"name":"BCA","amount":5000000},"confidence":0.95}
        ```
*   `rename_wallet`: Ubah nama wallet.
    *   **Contoh User:** "rename wallet BCA jadi BCA Digital"
    *   **JSON Output:**
        ```json
        {"action":"rename_wallet","target":null,"changes":null,"data":{"old_name":"BCA","new_name":"BCA Digital"},"confidence":0.9}
        ```
*   `delete_wallet`: Hapus wallet yang sudah ada.
    *   **Contoh User:** "hapus wallet pegangan"
    *   **JSON Output:**
        ```json
        {"action":"delete_wallet","target":null,"changes":null,"data":{"name":"pegangan"},"confidence":0.95}
        ```
*   `set_wallet_balance`: Update atau tetapkan saldo sebuah wallet ke nilai tertentu (jika wallet belum ada, sistem akan bertanya apakah ingin dibuatkan).
    *   **Contoh User:** "saldo cash gw 1jt", "cash ge 1jt", "BCA 3jt"
    *   **JSON Output:**
        ```json
        {"action":"set_wallet_balance","target":null,"changes":null,"data":{"name":"Cash","amount":1000000},"confidence":0.9}
        ```
*   `set_multiple_wallet_balances`: Update saldo banyak wallet sekaligus. Jika wallet belum ada, sistem akan otomatis membuatkannya.
    *   **Contoh User:** "cash 1jt sisanya di BCA 3jt", "buat agar cash ge 1jt doang sementara tambah wallet BCA 3jt"
    *   **JSON Output:**
        ```json
        {
          "action": "set_multiple_wallet_balances",
          "target": null,
          "changes": null,
          "data": {
            "wallets": [
              {"name": "Cash", "amount": 1000000},
              {"name": "BCA", "amount": 3000000}
            ]
          },
          "confidence": 0.9
        }
        ```

### 3. Kategori (Category)

*   `create_category`: Buat kategori pengeluaran atau pemasukan baru.
    *   **Contoh User:** "tambah kategori Investasi"
    *   **JSON Output:**
        ```json
        {"action":"create_category","target":null,"changes":null,"data":{"name":"Investasi"},"confidence":0.9}
        ```
*   `rename_category`: Ubah nama kategori yang dibuat oleh user (kategori bawaan tidak bisa diubah).
    *   **Contoh User:** "rename kategori jajan jadi camilan"
    *   **JSON Output:**
        ```json
        {"action":"rename_category","target":null,"changes":null,"data":{"old_name":"jajan","new_name":"camilan"},"confidence":0.9}
        ```

### 4. Budget (Anggaran)

*   `create_budget`: Buat *satu* budget baru.
    *   **Contoh User:** "buat budget makan 2 juta bulan ini"
    *   **JSON Output:**
        ```json
        {"action":"create_budget","target":null,"changes":null,"data":{"category":"Makan & Minum","amount":2000000,"period":"monthly"},"confidence":0.9}
        ```
*   `create_multiple_budgets`: Buat *beberapa* budget sekaligus dalam satu pesan. Terdapat sistem pencocokan *slang* (seperti `konsumsi` -> `Makan & Minum`, `bensin` -> `Transport`, dll).
    *   **Contoh User:** "buat budget utk Konsumsi 800rb\nUtk bensin 200rb"
    *   **JSON Output:**
        ```json
        {
          "action": "create_multiple_budgets",
          "target": null,
          "changes": null,
          "data": {
            "budgets": [
              {"category": "Makan & Minum", "amount": 800000, "period": "monthly"},
              {"category": "Transport", "amount": 200000, "period": "monthly"}
            ]
          },
          "confidence": 0.9
        }
        ```
*   `update_budget`: Ubah jumlah budget yang sudah ada (secara internal ditangani oleh fungsi yang sama dengan `create_budget` pada backend).
*   `delete_budget`: Hapus budget.
    *   **Contoh User:** "hapus budget makan"
    *   **JSON Output:**
        ```json
        {"action":"delete_budget","target":null,"changes":null,"data":{"category":"makan"},"confidence":0.9}
        ```

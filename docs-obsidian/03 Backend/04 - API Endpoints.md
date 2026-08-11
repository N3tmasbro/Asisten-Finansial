---
tags: [backend, api, endpoints]
---

# 04 — API Endpoints

**Base URL:** `http://localhost:8000/api`
**Auth:** Bearer token (Laravel Sanctum) — kecuali endpoint publik

---

## 🔓 Publik (Tidak Perlu Token)

### Auth
| Method | Endpoint | Fungsi |
|---|---|---|
| `POST` | `/auth/register` | Daftar akun baru |
| `POST` | `/auth/login` | Login, mendapat token |
| `POST` | `/auth/logout` | Logout (invalidate token) |

### Webhook
| Method | Endpoint | Fungsi |
|---|---|---|
| `POST` | `/webhook/whatsapp` | Menerima pesan dari WhatsApp Bridge |

---

## 🔒 Memerlukan Auth Token

### Transaksi
| Method | Endpoint | Fungsi |
|---|---|---|
| `GET` | `/transactions` | Daftar transaksi (dengan filter & paginasi) |
| `POST` | `/transactions` | Buat transaksi manual |
| `GET` | `/transactions/{id}` | Detail transaksi |
| `PUT` | `/transactions/{id}` | Edit transaksi |
| `DELETE` | `/transactions/{id}` | Hapus transaksi |

### Wallet
| Method | Endpoint | Fungsi |
|---|---|---|
| `GET` | `/wallets` | Daftar semua dompet user |
| `POST` | `/wallets` | Buat dompet baru |
| `PUT` | `/wallets/{id}` | Edit dompet |
| `DELETE` | `/wallets/{id}` | Hapus dompet |

### Kategori
| Method | Endpoint | Fungsi |
|---|---|---|
| `GET` | `/categories` | Daftar kategori (global + custom user) |
| `POST` | `/categories` | Buat kategori custom |
| `PUT` | `/categories/{id}` | Edit kategori |
| `DELETE` | `/categories/{id}` | Hapus kategori |

### Budget
| Method | Endpoint | Fungsi |
|---|---|---|
| `GET` | `/budgets` | Daftar budget bulan ini |
| `POST` | `/budgets` | Set budget baru |
| `PUT` | `/budgets/{id}` | Update budget |
| `DELETE` | `/budgets/{id}` | Hapus budget |

### Analitik
| Method | Endpoint | Fungsi |
|---|---|---|
| `GET` | `/analytics/summary` | Ringkasan keuangan bulan ini |
| `GET` | `/analytics/trends` | Tren pengeluaran per kategori |
| `GET` | `/analytics/by-category` | Breakdown per kategori |

---

## 📝 Format Request & Response

### Request Header
```
Authorization: Bearer {sanctum_token}
Content-Type: application/json
Accept: application/json
```

### Response Sukses
```json
{
  "success": true,
  "data": { ... },
  "message": "..."
}
```

### Response Error
```json
{
  "success": false,
  "message": "Pesan error",
  "errors": { ... }
}
```

---

## 🔗 Lihat Juga
- [[01 - Struktur Folder]]
- [[05 - Queue & Jobs]]
- [[../04 Frontend/01 - Halaman & Routing]]

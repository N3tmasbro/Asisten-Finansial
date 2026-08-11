---
tags: [ai, provider-pattern, architecture]
updated: 2026-08-10
---

# 01 — AI Provider Pattern

---

## 🎯 Tujuan

Memungkinkan pergantian model AI (Gemini → Claude → OpenAI) **tanpa mengubah business logic** — cukup buat satu class baru yang mengimplementasi interface yang sama.

---

## 🔌 Interface

**File:** `app/Contracts/AIProviderInterface.php`

```php
interface AIProviderInterface
{
    public function classifyIntent(string $message, array $context = []): IntentClassificationDTO;
    
    public function extractTransactions(string $message, array $categories, ?string $defaultWallet = null): array;
    
    public function parseQuery(string $message): QueryParametersDTO;
    
    public function parseFinancialCommand(string $message, array $context = []): FinancialCommandDTO;
    
    public function formatResponse(string $queryType, array $data, string $originalMessage = ''): string;
}
```

---

## 🏗️ Implementasi yang Ada

| Provider | File | Status |
|---|---|---|
| **GeminiProvider** | `Services/AI/Providers/GeminiProvider.php` | ✅ Aktif (default) |
| **ClaudeProvider** | `Services/AI/Providers/ClaudeProvider.php` | ✅ Tersedia (Anthropic API) |
| **MockAIProvider** | `Services/AI/Providers/MockAIProvider.php` | 🧪 Testing only |

---

## ⚙️ Binding (AppServiceProvider)

**File:** `app/Providers/AppServiceProvider.php`

```php
public function register(): void
{
    $this->app->bind(AIProviderInterface::class, function ($app) {
        $provider = config('services.ai.provider', 'gemini');
        
        return match($provider) {
            'gemini' => new GeminiProvider(config('services.ai.gemini_api_key')),
            'claude' => new ClaudeProvider(config('services.ai.claude_api_key')),
            'mock'   => new MockAIProvider(),
            default  => throw new \InvalidArgumentException("Unknown AI provider: {$provider}"),
        };
    });
}
```

---

## 🔄 Cara Menambah Provider Baru

**Contoh: Tambah OpenAI**

1. Buat class baru:
```
app/Services/AI/Providers/OpenAIProvider.php
```

2. Implement interface:
```php
class OpenAIProvider implements AIProviderInterface
{
    public function classifyIntent(string $message, array $context = []): IntentClassificationDTO
    {
        // HTTP call ke OpenAI API
    }
    // ... implement semua method interface
}
```

3. Daftarkan di `AppServiceProvider`:
```php
'openai' => new OpenAIProvider(config('services.ai.openai_api_key')),
```

4. Ubah `.env`:
```env
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...
```

**Tidak ada perubahan di `ChatOrchestratorService` atau business logic lainnya!**

---

## 📦 DTOs yang Digunakan

| DTO | Dipakai oleh | Keterangan |
|---|---|---|
| `IntentClassificationDTO` | `classifyIntent()` | Intent + confidence score |
| `ParsedTransactionDTO` | `extractTransactions()` | Array hasil parsing transaksi |
| `QueryParametersDTO` | `parseQuery()` | Parameter untuk query DB |
| `FinancialCommandDTO` | `parseFinancialCommand()` | Unified command untuk manage/delete/correction |

---

## 🔗 Lihat Juga
- [[02 - Prompt & Pipeline Detail]]
- [[../03 Backend/03 - AI Pipeline (3-Stage)]]
- [[../01 Gambaran Umum/02 - Tech Stack]]

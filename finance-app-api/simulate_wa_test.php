<?php

/**
 * WhatsApp AI Simulation Test Script
 *
 * Simulates sending WhatsApp messages directly to the Laravel webhook,
 * exactly as the bridge would do. Then verifies database state after each step.
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\Category;
use App\Models\Budget;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

// ─── Config ──────────────────────────────────────────────────────────────────
$webhookUrl = 'http://localhost:8000/api/webhooks/whatsapp';
// Get bridge secret directly from Laravel config (matches what webhook validates against)
$bridgeSecret = config('services.whatsapp_bridge.secret', '');

// Get first user for simulation
$user = User::first();
if (!$user) {
    die("❌ No user found. Please seed the database first.\n");
}

$phoneNumber = $user->phone_number ?? '628123456789';
$messageCounter = 1;

// Unique alphabetic suffix per run to avoid confusing AI number parser
$runSuffixes = ['alpha','beta','gamma','delta','sigma','omega','zeta','kappa'];
$runId = $runSuffixes[array_rand($runSuffixes)] . date('i'); // e.g. "gamma42"
// AI will strip numbers from description, so store what AI will save
$kopiMsg    = "beli kopi {$runId}";   // AI stores: "beli kopi {runId}"
$bensinMsg  = "bensin {$runId}";       // AI stores: "bensin {runId}"

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║         🧪 WhatsApp AI Simulation Test                      ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
echo "║  User   : {$user->name}\n";
echo "║  Phone  : {$phoneNumber}\n";
echo "║  Webhook: {$webhookUrl}\n";
echo "║  Run ID : {$runId}\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Clean up any previous simulation transactions to avoid false multi-candidates
echo "🧹 Membersihkan data simulasi sebelumnya...\n";
Transaction::withTrashed()
    ->where('user_id', $user->id)
    ->where(function($q) use ($runSuffixes) {
        foreach ($runSuffixes as $s) {
            $q->orWhere('description', 'LIKE', "%{$s}%");
        }
        $q->orWhere('description', 'LIKE', '%duplikat%');
    })
    ->forceDelete();
echo "   ✅ Selesai\n\n";

// ─── Helper Functions ─────────────────────────────────────────────────────────

function sendMessage(string $text, string $phone, string $webhookUrl, string $secret, int &$counter, int $waitSeconds = 5): array
{
    $messageId = 'SIM_' . strtoupper(uniqid());
    $payload = [
        'from'       => $phone,
        'reply_jid'  => $phone . '@s.whatsapp.net',
        'message'    => $text,
        'timestamp'  => time(),
        'message_id' => $messageId,
    ];
    if ($secret) {
        $payload['bridge_secret'] = $secret;
    }

    echo "┌─ 📤 Pesan #{$counter}: \"{$text}\"\n";

    $response = Http::timeout(30)->post($webhookUrl, $payload);

    if (!$response->successful()) {
        echo "└─ ❌ Webhook error: HTTP " . $response->status() . " — " . $response->body() . "\n\n";
        return ['success' => false];
    }

    echo "└─ ✅ Webhook accepted (queued)\n";
    $counter++;

    // Wait for queue to process
    sleep($waitSeconds);

    return ['success' => true, 'message_id' => $messageId];
}

function getLastBotReply(int $userId): ?string
{
    $msg = ChatMessage::where('user_id', $userId)
        ->where('direction', 'outgoing')
        ->orderByDesc('created_at')
        ->first();

    return $msg?->body;
}

function printBotReply(int $userId): void
{
    $reply = getLastBotReply($userId);
    if ($reply) {
        echo "   🤖 Bot: " . str_replace("\n", "\n         ", $reply) . "\n";
    } else {
        echo "   🤖 Bot: (no reply yet)\n";
    }
    echo "\n";
}

function printWalletBalances(int $userId): void
{
    $wallets = Wallet::where('user_id', $userId)->get();
    echo "   💰 Saldo wallet:\n";
    foreach ($wallets as $w) {
        $balance = number_format($w->balance, 0, ',', '.');
        echo "      • {$w->name}: Rp{$balance}\n";
    }
    echo "\n";
}

function printLastTransactions(int $userId, int $limit = 3): void
{
    $txs = Transaction::where('user_id', $userId)
        ->with(['category:id,name', 'wallet:id,name'])
        ->orderByDesc('created_at')
        ->limit($limit)
        ->get();

    echo "   📋 Transaksi terakhir:\n";
    foreach ($txs as $tx) {
        $amount = number_format($tx->amount, 0, ',', '.');
        $type   = $tx->type->value === 'income' ? '+' : '-';
        $cat    = $tx->category->name ?? '?';
        $wall   = $tx->wallet->name ?? '?';
        echo "      • [{$tx->id}] {$tx->description} — {$type}Rp{$amount} [{$cat}] [{$wall}]\n";
    }
    echo "\n";
}

function separator(string $title): void
{
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  🧪 {$title}\n";
    echo "═══════════════════════════════════════════════════════════════\n";
}

function assertContains(string $haystack, string $needle, string $label): void
{
    if (stripos($haystack, $needle) !== false) {
        echo "   ✅ PASS: {$label}\n";
    } else {
        echo "   ❌ FAIL: {$label}\n";
        echo "      Expected to contain: \"{$needle}\"\n";
        echo "      Got: \"" . substr($haystack, 0, 100) . "...\"\n";
    }
}

// ─── BASELINE ────────────────────────────────────────────────────────────────
separator("BASELINE — Saldo awal");
printWalletBalances($user->id);

$initialCashBalance   = Wallet::where('user_id', $user->id)->where('name', 'LIKE', '%Cash%')->first()?->balance ?? 0;
$initialBCABalance    = Wallet::where('user_id', $user->id)->where('name', 'LIKE', '%BCA%')->first()?->balance ?? 0;

// ─── TAHAP 1: ADD TRANSACTION ─────────────────────────────────────────────────
separator("TAHAP 1: Add Transaction");

sendMessage("{$kopiMsg} 25rb", $phoneNumber, $webhookUrl, $bridgeSecret, $messageCounter);
printBotReply($user->id);
// Capture transaction ID immediately before any other messages are created
$kopiTxId = Transaction::where('user_id', $user->id)->where('description', 'LIKE', "%{$runId}%")
    ->where('description', 'LIKE', '%kopi%')->orderByDesc('created_at')->first()?->id;
printLastTransactions($user->id, 1);

sendMessage("isi {$bensinMsg} 100rb", $phoneNumber, $webhookUrl, $bridgeSecret, $messageCounter);
printBotReply($user->id);
$bensinTxId = Transaction::where('user_id', $user->id)->where('description', 'LIKE', "%{$runId}%")
    ->where('description', 'LIKE', '%bensin%')->orderByDesc('created_at')->first()?->id;
printLastTransactions($user->id, 1);

$kopiTx   = $kopiTxId   ? Transaction::with(['category','wallet'])->find($kopiTxId)   : null;
$bensinTx = $bensinTxId ? Transaction::with(['category','wallet'])->find($bensinTxId) : null;

// Detect which wallet bensin went to
$bensinWalletName = $bensinTx?->wallet->name ?? 'Cash';
$bensinWallet     = Wallet::where('user_id', $user->id)->where('name', $bensinWalletName)->first();

echo "   📊 Verifikasi database:\n";
echo "      • Kopi: " . ($kopiTx   ? "✅ Found (Rp" . number_format($kopiTx->amount, 0, ',', '.') . " [{$kopiTx->category->name}])" : "❌ Not found") . "\n";
echo "      • Bensin: " . ($bensinTx ? "✅ Found (Rp" . number_format($bensinTx->amount, 0, ',', '.') . " [{$bensinTx->wallet->name}])" : "❌ Not found") . "\n\n";

// ─── TAHAP 2: CORRECTION ─────────────────────────────────────────────────────
separator("TAHAP 2: Correction");

sendMessage("yang kopi {$runId} tadi harusnya kategori Hiburan", $phoneNumber, $webhookUrl, $bridgeSecret, $messageCounter, 7);
$reply = getLastBotReply($user->id);
printBotReply($user->id);

$kopiTx?->refresh();
$kopiCategory = $kopiTx?->category->name ?? '?';
echo "   📊 Verifikasi:\n";
echo "      • Kategori kopi sekarang: {$kopiCategory}\n";
echo "      • " . (stripos($kopiCategory, 'Hiburan') !== false ? "✅ PASS" : "❌ FAIL — masih {$kopiCategory}") . "\n\n";

if ($bensinTx) {
    $walletBeforeCorrection = $bensinWallet ? $bensinWallet->fresh()->balance : 0;
    sendMessage("yang bensin {$runId} tadi harusnya 80rb", $phoneNumber, $webhookUrl, $bridgeSecret, $messageCounter, 7);
    printBotReply($user->id);

    $walletAfterCorrection = $bensinWallet ? $bensinWallet->fresh()->balance : 0;
    $bensinTx->refresh();
    echo "   📊 Verifikasi:\n";
    echo "      • Nominal bensin baru: Rp" . number_format($bensinTx->amount, 0, ',', '.') . " (harusnya Rp80.000)\n";
    echo "      • " . ($bensinTx->amount == 80000 ? "✅ PASS" : "❌ FAIL") . "\n";
    echo "      • Saldo {$bensinWalletName} sebelum: Rp" . number_format($walletBeforeCorrection, 0, ',', '.') . "\n";
    echo "      • Saldo {$bensinWalletName} sesudah: Rp" . number_format($walletAfterCorrection, 0, ',', '.') . " (naik Rp20.000)\n";
    echo "      • " . ($walletAfterCorrection == $walletBeforeCorrection + 20000 ? "✅ PASS" : "❌ FAIL") . "\n\n";
}

// ─── TAHAP 3: DELETE + CONFIRM ────────────────────────────────────────────────
separator("TAHAP 3: Delete dengan Konfirmasi");

if ($bensinTx) {
    $walletBeforeDelete = $bensinWallet ? $bensinWallet->fresh()->balance : 0;

    sendMessage("hapus yang bensin {$runId} tadi", $phoneNumber, $webhookUrl, $bridgeSecret, $messageCounter, 7);
    $reply = getLastBotReply($user->id);
    printBotReply($user->id);

    assertContains($reply ?? '', 'yakin', 'Bot meminta konfirmasi');

    // Kirim konfirmasi
    sendMessage('ya', $phoneNumber, $webhookUrl, $bridgeSecret, $messageCounter, 7);
    printBotReply($user->id);

    $walletAfterDelete = $bensinWallet ? $bensinWallet->fresh()->balance : 0;
    $bensinDeleted     = Transaction::withTrashed()->find($bensinTx->id);

    echo "   📊 Verifikasi:\n";
    echo "      • Bensin soft deleted: " . ($bensinDeleted?->deleted_at ? "✅ PASS (deleted_at: {$bensinDeleted->deleted_at})" : "❌ FAIL") . "\n";
    echo "      • Saldo {$bensinWalletName} sebelum hapus: Rp" . number_format($walletBeforeDelete, 0, ',', '.') . "\n";
    echo "      • Saldo {$bensinWalletName} sesudah hapus: Rp" . number_format($walletAfterDelete, 0, ',', '.') . " (+Rp80.000)\n";
    echo "      • " . ($walletAfterDelete == $walletBeforeDelete + 80000 ? "✅ PASS saldo pulih" : "❌ FAIL saldo tidak pulih") . "\n\n";
}

// ─── TAHAP 4: INSPECT (0 AI calls) ────────────────────────────────────────────
separator("TAHAP 4: Inspect Records (tanpa AI call)");

sendMessage('saldo semua wallet', $phoneNumber, $webhookUrl, $bridgeSecret, $messageCounter);
$reply = getLastBotReply($user->id);
printBotReply($user->id);
assertContains($reply ?? '', 'Rp', 'Balasan berisi angka saldo');

sendMessage('transaksi terakhir apa aja?', $phoneNumber, $webhookUrl, $bridgeSecret, $messageCounter);
$reply = getLastBotReply($user->id);
printBotReply($user->id);
assertContains($reply ?? '', 'Rp', 'Balasan berisi transaksi');

sendMessage('daftar wallet', $phoneNumber, $webhookUrl, $bridgeSecret, $messageCounter);
printBotReply($user->id);

// ─── TAHAP 5: MANAGE RECORDS ──────────────────────────────────────────────────
separator("TAHAP 5: Manage Records");

// Buat wallet Dana
sendMessage('buat wallet Dana', $phoneNumber, $webhookUrl, $bridgeSecret, $messageCounter);
$reply = getLastBotReply($user->id);
printBotReply($user->id);

$danaWallet = Wallet::where('user_id', $user->id)->where('name', 'Dana')->first();
echo "   📊 Verifikasi:\n";
echo "      • Wallet Dana dibuat: " . ($danaWallet ? "✅ PASS (type: " . $danaWallet->type->value . ")" : "❌ FAIL") . "\n\n";

// Buat kategori Investasi
sendMessage('buat kategori Investasi', $phoneNumber, $webhookUrl, $bridgeSecret, $messageCounter);
$reply = getLastBotReply($user->id);
printBotReply($user->id);

$investasiCat = Category::where('user_id', $user->id)->where('name', 'Investasi')->first();
echo "   📊 Verifikasi:\n";
echo "      • Kategori Investasi dibuat: " . ($investasiCat ? "✅ PASS" : "❌ FAIL") . "\n\n";

// Buat budget makan
sendMessage('buat budget makan 2 juta', $phoneNumber, $webhookUrl, $bridgeSecret, $messageCounter);
$reply = getLastBotReply($user->id);
printBotReply($user->id);

$makanCat    = Category::where('name', 'LIKE', '%Makan%')->first();
$makanBudget = Budget::where('user_id', $user->id)->where('category_id', $makanCat?->id)->first();
echo "   📊 Verifikasi:\n";
echo "      • Budget makan dibuat: " . ($makanBudget ? "✅ PASS (Rp" . number_format($makanBudget->amount, 0, ',', '.') . ")" : "❌ FAIL") . "\n\n";

// Update budget makan ke 2,5 juta
sendMessage('budget makan naikkan jadi 2,5 juta', $phoneNumber, $webhookUrl, $bridgeSecret, $messageCounter);
$reply = getLastBotReply($user->id);
printBotReply($user->id);

$makanBudget?->refresh();
echo "   📊 Verifikasi:\n";
echo "      • Budget makan diupdate: " . ($makanBudget?->amount == 2500000 ? "✅ PASS (Rp2.500.000)" : "❌ FAIL (Rp" . number_format($makanBudget?->amount ?? 0, 0, ',', '.') . ")") . "\n\n";

// ─── EDGE CASES ───────────────────────────────────────────────────────────────
separator("EDGE CASE: Idempotency (duplikat webhook)");

$dupId = 'SIM_DUPLICATE_' . uniqid();
$dupPayload = [
    'from'       => $phoneNumber,
    'reply_jid'  => $phoneNumber . '@s.whatsapp.net',
    'message'    => 'beli duplikat 10rb',
    'timestamp'  => time(),
    'message_id' => $dupId,
];
if ($bridgeSecret) $dupPayload['bridge_secret'] = $bridgeSecret;

$countBefore = Transaction::where('user_id', $user->id)->where('description', 'LIKE', '%duplikat%')->count();
Http::timeout(15)->post($webhookUrl, $dupPayload); // First
sleep(2);
Http::timeout(15)->post($webhookUrl, $dupPayload); // Duplicate
sleep(4);
$countAfter = Transaction::where('user_id', $user->id)->where('description', 'LIKE', '%duplikat%')->count();

echo "   📊 Verifikasi:\n";
echo "      • Transaksi duplikat sebelum: {$countBefore}\n";
echo "      • Transaksi duplikat sesudah (2 webhook): {$countAfter}\n";
echo "      • " . ($countAfter <= 1 ? "✅ PASS — hanya 1 record (idempotent)" : "❌ FAIL — ada duplikat!") . "\n\n";

// ─── SUMMARY ──────────────────────────────────────────────────────────────────
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║               ✅ Simulasi Selesai                           ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
printWalletBalances($user->id);
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

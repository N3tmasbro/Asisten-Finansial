<?php

namespace App\Providers;

use App\Contracts\AIProviderInterface;
use App\Contracts\WhatsAppProviderInterface;
use App\Services\AI\Providers\ClaudeProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\MockAIProvider;
use App\Services\WhatsApp\BaileysProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind AI Provider — switch between Mock, Claude, and Gemini based on config
        $this->app->bind(AIProviderInterface::class, function () {
            $provider = config('services.ai.provider', 'mock');

            return match ($provider) {
                'claude' => new ClaudeProvider(),
                'gemini' => new GeminiProvider(),
                default => new MockAIProvider(),
            };
        });

        // Bind WhatsApp Provider
        $this->app->bind(WhatsAppProviderInterface::class, function () {
            return new BaileysProvider();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

<?php

namespace Tests\Unit\Chat;

use App\Models\ChatMessage;
use App\Services\Chat\UnregisteredUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnregisteredUserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UnregisteredUserService $service;
    private string $phone = '628123456789';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UnregisteredUserService::class);
    }

    /** @test */
    public function first_time_contact_should_send_greeting(): void
    {
        $result = $this->service->shouldSendGreeting($this->phone);
        $this->assertEquals('send', $result);
    }

    /** @test */
    public function second_message_within_24h_should_be_ignored(): void
    {
        ChatMessage::create([
            'sender_phone'               => $this->phone,
            'user_status'                => 'unregistered',
            'direction'                  => 'incoming',
            'body'                       => 'halo',
            'last_unregistered_reply_at' => now(),
        ]);

        $result = $this->service->shouldSendGreeting($this->phone);
        $this->assertEquals('ignore', $result);
    }

    /** @test */
    public function message_after_24h_window_should_send_greeting_again(): void
    {
        ChatMessage::create([
            'sender_phone'               => $this->phone,
            'user_status'                => 'unregistered',
            'direction'                  => 'incoming',
            'body'                       => 'halo',
            'last_unregistered_reply_at' => now()->subHours(25),
        ]);

        $result = $this->service->shouldSendGreeting($this->phone);
        $this->assertEquals('send', $result);
    }

    /** @test */
    public function record_greeting_sent_creates_db_entry_with_timestamp(): void
    {
        $this->service->recordGreetingSent($this->phone, 'halo bot');

        $entry = ChatMessage::where('sender_phone', $this->phone)
            ->where('user_status', 'unregistered')
            ->first();

        $this->assertNotNull($entry);
        $this->assertNotNull($entry->last_unregistered_reply_at);
    }

    /** @test */
    public function record_ignored_does_not_update_greeting_timestamp(): void
    {
        $originalTime = now()->subHours(2);

        ChatMessage::create([
            'sender_phone'               => $this->phone,
            'user_status'                => 'unregistered',
            'direction'                  => 'incoming',
            'body'                       => 'first message',
            'last_unregistered_reply_at' => $originalTime,
        ]);

        // Ignored message — should NOT create a new record with updated timestamp
        $this->service->recordIgnoredMessage($this->phone, 'second message within 24h');

        // Only 1 record should exist (the original greeting record)
        $count = ChatMessage::where('sender_phone', $this->phone)
            ->where('user_status', 'unregistered')
            ->count();
        $this->assertEquals(1, $count);

        // Timestamp must remain unchanged
        $entry = ChatMessage::where('sender_phone', $this->phone)->first();
        $this->assertEquals(
            $originalTime->timestamp,
            $entry->last_unregistered_reply_at->timestamp
        );
    }
}

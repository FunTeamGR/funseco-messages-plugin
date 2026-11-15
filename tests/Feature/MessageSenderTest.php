<?php

namespace Funseco\Messages\Tests\Feature;

use Funseco\Core\Models\Player;
use Funseco\Core\Services\Cache\CurrentPlayers;
use Funseco\Core\Services\Messages\MessageSender;
use Funseco\Core\Services\Messages\MessageService;
use Funseco\Core\SharedKernel\Messages\Message;
use Funseco\Core\SharedKernel\Messages\MessageRegistry;
use Funseco\Core\SharedKernel\Messages\Msg;
use Funseco\Core\SharedKernel\Messages\Recipients;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Funseco\Core\Testing\FakesServer;
use Funseco\Messages\Tests\TestCase;

class MessageSenderTest extends TestCase
{
    use FakesServer;
    use RefreshDatabase;

    /** @var list<array{0: float, 1: \Closure}> */
    private array $timers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeServer();
        CurrentPlayers::clear();
        config(['adminops.masteradmins' => ['boss']]);
        CurrentPlayers::add(Player::factory()->create(['login' => 'alice', 'nickname' => 'Alice']));
        CurrentPlayers::add(Player::factory()->create(['login' => 'boss', 'nickname' => 'Boss']));
        $this->app->singleton(MessageSender::class, fn ($app) => new MessageSender(
            $app->make(MessageRegistry::class), $app->make(MessageService::class),
            $app->make(\Funseco\Core\Services\Messages\MessageFormatter::class),
            function (float $s, \Closure $f) { $this->timers[] = [$s, $f]; }));
        app(MessageRegistry::class)->declare('t', [
            Message::make('hi', 'Hi', 'Hello {who}', ['who' => 'Name'], recipients: Recipients::Player,
                allowedRecipients: [Recipients::Player, Recipients::Everyone, Recipients::Staff]),
        ]);
    }

    private function alice(): Player
    {
        return CurrentPlayers::get('alice');
    }

    public function test_recipient_player_targets_login(): void
    {
        Msg::send('t.hi', $this->alice(), ['who' => 'Alice']);
        $sent = $this->sent('ChatSendServerMessageToLogin');
        $this->assertCount(1, $sent);
        $this->assertSame('alice', $sent[0][1]);
        $this->assertStringContainsString('Hello Alice', $sent[0][0]);
    }

    public function test_disabled_sends_nothing(): void
    {
        app(MessageService::class)->set('t.hi', 'enabled', false);
        Msg::send('t.hi', $this->alice(), ['who' => 'x']);
        $this->assertSame([], $this->sent('ChatSendServerMessageToLogin'));
        $this->assertSame([], $this->sent('ChatSendServerMessage'));
    }

    public function test_recipient_staff_skips_players(): void
    {
        app(MessageService::class)->set('t.hi', 'recipients', 'staff');
        Msg::send('t.hi', null, ['who' => 'x']);
        $sent = $this->sent('ChatSendServerMessageToLogin');
        $this->assertSame(['boss'], array_column($sent, 1));
    }

    public function test_everyone_broadcasts(): void
    {
        app(MessageService::class)->set('t.hi', 'recipients', 'everyone');
        Msg::send('t.hi', null, ['who' => 'x']);
        $this->assertCount(1, $this->sent('ChatSendServerMessage'));
    }

    public function test_delay_uses_timer(): void
    {
        app(MessageService::class)->set('t.hi', 'delay', 3);
        Msg::send('t.hi', $this->alice(), ['who' => 'x']);
        $this->assertSame([], $this->sent('ChatSendServerMessageToLogin'));
        $this->assertSame(3.0, $this->timers[0][0]);
        ($this->timers[0][1])();
        $this->assertCount(1, $this->sent('ChatSendServerMessageToLogin'));
    }

    public function test_player_left_during_delay_skips(): void
    {
        app(MessageService::class)->set('t.hi', 'delay', 3);
        Msg::send('t.hi', $this->alice(), ['who' => 'x']);
        CurrentPlayers::forget('alice');
        ($this->timers[0][1])();
        $this->assertSame([], $this->sent('ChatSendServerMessageToLogin'));
    }

    public function test_edit_text_applies_without_restart(): void
    {
        app(MessageService::class)->set('t.hi', 'text', 'Yo {who}');
        Msg::send('t.hi', $this->alice(), ['who' => 'A']);
        $this->assertStringContainsString('Yo A', $this->sent('ChatSendServerMessageToLogin')[0][0]);
    }

    public function test_to_override_outside_allowed_list_rejected(): void
    {
        app(MessageRegistry::class)->declare('t', [Message::make('only', 'Only', 'x', recipients: Recipients::Player,
            allowedRecipients: [Recipients::Player])]);
        Msg::send('t.only', $this->alice(), [], Recipients::Everyone);
        $this->assertSame([], $this->sent('ChatSendServerMessage'));
        $this->assertSame([], $this->sent('ChatSendServerMessageToLogin'));
    }

    public function test_unknown_key_is_ignored(): void
    {
        Msg::send('t.nope', $this->alice());
        $this->assertSame([], $this->sent('ChatSendServerMessageToLogin'));
    }

    public function test_a_value_cannot_inject_a_colour_token(): void
    {
        Msg::send('t.hi', $this->alice(), ['who' => 'x{#error}y']);
        $text = $this->sent('ChatSendServerMessageToLogin')[0][0];
        $this->assertStringContainsString('x{#error}y', $text, 'literal, not expanded');
        $this->assertStringNotContainsString(config('colors.error'), $text);
    }

    public function test_a_raw_value_keeps_its_codes_and_tokens(): void
    {
        app(MessageRegistry::class)->declare('t', [Message::make('rawy', 'R', 'Hi {s}', ['s' => 'S'],
            recipients: Recipients::Player, allowedRecipients: [Recipients::Player], raw: ['s'])]);
        Msg::send('t.rawy', $this->alice(), ['s' => '$f00Fun{#server}Seco']);
        $text = $this->sent('ChatSendServerMessageToLogin')[0][0];
        $this->assertStringContainsString('$f00Fun'.config('colors.server').'Seco', $text);
    }

    public function test_a_second_delayed_send_to_the_same_player_waits_for_the_first(): void
    {
        app(MessageService::class)->set('t.hi', 'delay', 3);
        Msg::send('t.hi', $this->alice(), ['who' => 'x']);
        Msg::send('t.hi', $this->alice(), ['who' => 'x']);
        $this->assertCount(1, $this->timers);
        ($this->timers[0][1])();
        $this->assertCount(1, $this->sent('ChatSendServerMessageToLogin'));
        Msg::send('t.hi', $this->alice(), ['who' => 'x']);
        $this->assertCount(2, $this->timers, 'free again once the first fired');
    }

    public function test_a_throw_inside_a_delayed_send_does_not_escape_the_timer(): void
    {
        $formatter = new class(app(\Funseco\Core\Services\Messages\StyleStore::class), app(\Funseco\Core\Services\Messages\ChatColors::class)) extends \Funseco\Core\Services\Messages\MessageFormatter {
            public function render(string $template, array $values, array $raw = []): string
            {
                throw new \RuntimeException('boom');
            }
        };
        $sender = new MessageSender(app(MessageRegistry::class), app(MessageService::class), $formatter,
            function (float $s, \Closure $f) { $this->timers[] = [$s, $f]; });
        app(MessageService::class)->set('t.hi', 'delay', 3);
        $sender->send('t.hi', $this->alice(), ['who' => 'x']);
        ($this->timers[0][1])();
        $this->addToAssertionCount(1); // reaching here: nothing thrown out of the timer
        $sender->send('t.hi', $this->alice(), ['who' => 'x']);
        $this->assertCount(2, $this->timers, 'a failed send frees the slot');
    }
}

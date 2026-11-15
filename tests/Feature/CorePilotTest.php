<?php

namespace Funseco\Messages\Tests\Feature;

use Funseco\Core\Servers\TmForever\Callbacks\PlayerConnect;
use Funseco\Core\Servers\TmForever\Facades\Server;
use Funseco\Core\Servers\TmForever\Listeners\PlayerConnectListener;
use Funseco\Core\Services\Cache\CurrentPlayers;
use Funseco\Core\Services\Messages\MessageService;
use Funseco\Core\SharedKernel\Events\PlayerConnected;
use Funseco\Core\SharedKernel\Events\PlayerReady;
use Funseco\Core\SharedKernel\Messages\Msg;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Funseco\Core\Testing\FakesServer;
use React\EventLoop\Loop;
use React\EventLoop\StreamSelectLoop;
use Funseco\Messages\Tests\TestCase;

class CorePilotTest extends TestCase
{
    use FakesServer;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Loop::set(new StreamSelectLoop());   // the listener's PlayerReady timer must not outlive the app (it would run at shutdown)
        $this->fakeServer();
        CurrentPlayers::clear();
        Event::fake([PlayerConnected::class, PlayerReady::class]);
        Server::shouldReceive('get')->with('name')->andReturn('MyServer');
        Server::query()->shouldReceive('GetPlayerInfo')->andReturnUsing(function (...$args) {
            $cb = $args['callbacks'] ?? end($args);
            $cb([['Login' => $args[0], 'NickName' => 'Alice', 'PlayerId' => 1]]);
        });
    }

    private function connect(): void
    {
        (new PlayerConnectListener())->handle(new PlayerConnect('alice', false));
    }

    public function test_welcome_sent_to_new_player_only(): void
    {
        $this->connect();
        $this->assertCount(1, $this->sent('ChatSendServerMessageToLogin'));
        $this->assertStringContainsString('Welcome to MyServer', $this->sent('ChatSendServerMessageToLogin')[0][0]);
        $this->connect();   // already in CurrentPlayers
        $this->assertCount(1, $this->sent('ChatSendServerMessageToLogin'));
    }

    public function test_welcome_text_edit_changes_output(): void
    {
        app(MessageService::class)->set('core.welcome', 'text', 'Hey, this is {server}');
        $this->connect();
        $this->assertStringContainsString('Hey, this is MyServer', $this->sent('ChatSendServerMessageToLogin')[0][0]);
    }

    public function test_welcome_disabled_silent(): void
    {
        app(MessageService::class)->set('core.welcome', 'enabled', false);
        $this->connect();
        $this->assertSame([], $this->sent('ChatSendServerMessageToLogin'));
    }

    public function test_refund_uses_config_default_and_coppers(): void
    {
        $player = \Funseco\Core\Models\Player::factory()->create(['login' => 'bob']);
        CurrentPlayers::add($player);
        Msg::send('core.billing.refund', $player, ['coppers' => 100]);
        $text = $this->sent('ChatSendServerMessageToLogin')[0][0];
        $this->assertStringContainsString('100', $text);
        $this->assertStringContainsString('paid back to you', $text);
    }

    public function test_welcome_keeps_the_colour_codes_of_the_server_name(): void
    {
        $player = \Funseco\Core\Models\Player::factory()->create(['login' => 'bob']);
        Msg::send('core.welcome', $player, ['server' => '$f00Fun$fffSeco']);
        $this->assertStringContainsString('$f00Fun$fffSeco', $this->sent('ChatSendServerMessageToLogin')[0][0]);
    }
}

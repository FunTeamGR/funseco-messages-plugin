<?php
namespace Funseco\Messages\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use React\Http\Message\Response;
use Funseco\Core\Testing\FakesServer;
use Funseco\Messages\Tests\TestCase;
use Funseco\Core\Models\Player;
use Funseco\Core\Services\Cache\CurrentPlayers;
use Funseco\Core\Services\Cache\CurrentSpectators;
use Funseco\Core\Services\Cache\ServerInfo;
use Funseco\Core\Services\Chat\Facades\Chat;
use Funseco\Core\Services\Http\AsyncHttpClient;
use Funseco\Core\Services\Http\PublicAddressConnector;
use Funseco\Core\Services\ManiaLink\Facades\ManiaLink;
use Funseco\Core\SharedKernel\ChatArgumentExpander;
use Funseco\Core\SharedKernel\Events\PlayerChatted;
use Funseco\Core\SharedKernel\Events\SystemMessagePosted;
use Funseco\Messages\ChatLog;
use Funseco\Core\SharedKernel\GuardState;
use Funseco\Messages\InfoMessages;
use Funseco\Messages\LoginAutocomplete;
use Funseco\Messages\MessageHistory;
use Funseco\Messages\MsgLogButton;
use Funseco\Messages\UrlBot\LinkTitles;
use Funseco\Messages\UrlBot\UrlBot;

use function React\Promise\reject;
use function React\Promise\resolve;

class MessagesTest extends TestCase
{
    use FakesServer;
    use RefreshDatabase;

    private Player $alice;
    private Player $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeServer();
        CurrentPlayers::clear();
        CurrentSpectators::clear();
        state()->forget('messages:chat_log');
        state()->forget('messages:history');
        state()->forget('messages:info_counter');
        config(['funseco.master_server.login' => 'myserver', 'funseco.chatpmlog_times' => true]);
        ServerInfo::set(['server_name' => '$f00My Server']);

        $this->alice = Player::factory()->create(['login' => 'alice', 'nickname' => '$wAl$Wice']);
        $this->bob = Player::factory()->create(['login' => 'bobby', 'nickname' => '$00fBob']);
        CurrentPlayers::add($this->alice);
        CurrentPlayers::add($this->bob);
    }

    private function fakeHttp(): Mockery\MockInterface
    {
        $http = Mockery::mock(AsyncHttpClient::class);
        $this->app->instance(AsyncHttpClient::class, $http);
        $this->app->forgetInstance(LinkTitles::class);
        $this->app->forgetInstance(UrlBot::class);

        return $http;
    }

    // --- periodic info message ----------------------------------------------

    public function test_periodic_info_message_after_interval()
    {
        config(['messages.info.interval' => 3, 'messages.info.messages' => ['Hello there']]);
        $info = app(InfoMessages::class);

        $info->tick();
        $info->tick();
        $this->assertSame([], $this->messages('ChatSendServerMessage'));

        $info->tick();
        $this->assertSame([format_colors('{#message}').'$ff0>> $f00[$c00INFO$f00] $z$sHello there'], $this->messages('ChatSendServerMessage'));

        // counter restarts
        $info->tick();
        $info->tick();
        $this->assertCount(1, $this->messages('ChatSendServerMessage'));
    }

    public function test_periodic_info_picks_one_line_of_the_pool_and_keeps_its_tokens()
    {
        config(['messages.info.interval' => 1, 'messages.info.messages' => ['A {#server}x', 'B {#server}x']]);
        app(InfoMessages::class)->tick();
        $sent = $this->messages('ChatSendServerMessage');
        $this->assertCount(1, $sent);
        $this->assertMatchesRegularExpression('/(A|B) '.preg_quote(config('colors.server'), '/').'x$/', $sent[0]);
    }

    public function test_periodic_info_toggle_off_sends_nothing()
    {
        config(['messages.info.interval' => 1, 'messages.info.messages' => ['Hello']]);
        app(\Funseco\Core\Services\Messages\MessageService::class)->set('messages.info_tip', 'enabled', false);
        app(InfoMessages::class)->tick();
        $this->assertSame([], $this->messages('ChatSendServerMessage'));
    }

    public function test_periodic_info_with_empty_list_sends_nothing()
    {
        config(['messages.info.interval' => 1, 'messages.info.messages' => []]);
        app(InfoMessages::class)->tick();
        $this->assertSame([], $this->messages('ChatSendServerMessage'));
    }

    public function test_owner_periodic_interval_is_two_hours()
    {
        $this->assertSame(7200, config('messages.info.interval'));
        $this->assertCount(41, config('messages.map_end.messages'));
        foreach (config('messages.map_end.messages') as $line) {
            $this->assertStringNotContainsStringIgnoringCase('xaseco', $line);
        }
    }

    // --- map end info message / message ------------------------------------

    /** The /jfreu infomessages setting, as the messages plugin sees it through GuardState. */
    private function infoMode(int $mode): void
    {
        $this->app->instance(GuardState::class, new class($mode) implements GuardState {
            public function __construct(private int $mode)
            {
            }

            public function setting(string $key): int
            {
                return $key === 'infomessages' ? $this->mode : 0;
            }

            public function isSpecOnly(string $login): bool
            {
                return false;
            }
        });
    }

    public function test_map_end_message_in_chat()
    {
        config(['messages.map_end.messages' => ['Use {#highlite}/help']]);
        app(InfoMessages::class)->random(atEndOfTrack: true);

        $this->assertSame([format_colors('$z$s$ff0>> [$f00INFO$ff0] $fffUse {#highlite}/help')], $this->messages('ChatSendServerMessage'));
    }

    public function test_map_end_mode_zero_is_off_but_message_command_still_works()
    {
        $this->infoMode(0);
        config(['messages.map_end.messages' => ['Tip']]);
        app(InfoMessages::class)->random(atEndOfTrack: true);
        $this->assertSame([], $this->messages('ChatSendServerMessage'));

        Chat::resolve('/message', 'alice');
        $this->assertSame(['$z$s$ff0>> [$f00INFO$ff0] $fffTip'], $this->messages('ChatSendServerMessage'));
    }

    public function test_map_end_mode_two_goes_to_message_window()
    {
        $this->infoMode(2);
        config(['messages.map_end.messages' => ['Tip']]);
        $this->responses['GetChatTime'] = ['CurrentValue' => 10000, 'NextValue' => 10000];

        app(InfoMessages::class)->random(atEndOfTrack: true);

        $this->assertSame([], $this->messages('ChatSendServerMessage'));
        $sent = $this->sent('SendDisplayManialinkPage');
        $this->assertCount(1, $sent);
        $this->assertStringContainsString('INFO', $sent[0][0]);
        $this->assertSame(15000, $sent[0][1], 'chat time + 5 s');
    }

    // --- chat log -------------------------------------------------------------

    public function test_chatlog_empty()
    {
        Chat::resolve('/chatlog', 'alice');
        $this->assertSame([format_colors('{#server}> {#error}No chat history found!')], $this->messages());
    }

    public function test_chatlog_window_lines()
    {
        Carbon::setTestNow('2026-10-27 13:05:09');
        event(new PlayerChatted($this->alice, '$f00hi $l[http://x.org]there$l $$5'));
        Chat::resolve('/chatlog', 'bobby');

        $window = $this->window();
        $this->assertStringContainsString('Recent chat history:', $window);
        $this->assertStringContainsString('Outbox', $window);
        $expected = format_colors('$z&lt;{#server}13:05:09$z&gt; [').config('funseco.window_style.window.blackcolor').'Alice$z] hi there $5';
        $this->assertStringContainsString($expected, $window);
    }

    public function test_chatlog_without_timestamps_and_wrapping()
    {
        config(['funseco.chatpmlog_times' => false]);
        $text = str_repeat('word ', 20).str_repeat('x', 100);
        app(ChatLog::class)->record($this->bob, $text);

        $lines = app(ChatLog::class)->lines();
        $this->assertCount(3, $lines);
        $prefix = '$z[{#black}$00fBob$z] ';
        $this->assertSame($prefix.rtrim(str_repeat('word ', 14)), $lines[0][0]);
        $this->assertSame($prefix.'...'.rtrim(str_repeat('word ', 6)), $lines[1][0]);
        $this->assertSame($prefix.'...'.str_repeat('x', 70), $lines[2][0], 'long word chopped at 73');
    }

    public function test_chatlog_keeps_last_30_and_skips_commands()
    {
        $log = app(ChatLog::class);
        for ($i = 1; $i <= 32; $i++) {
            $log->record($this->alice, "line $i");
        }
        $log->record($this->alice, '/help');
        $log->record($this->alice, '');

        $all = $log->all();
        $this->assertCount(30, $all);
        $this->assertSame('line 3', $all[0]['text']);
        $this->assertSame('line 32', $all[29]['text']);
        $this->assertSame('Alice', $all[0]['nickname'], 'wide codes removed');
    }

    public function test_chatlog_pages_15_lines()
    {
        for ($i = 1; $i <= 20; $i++) {
            app(ChatLog::class)->record($this->alice, "line $i");
        }
        Chat::resolve('/chatlog', 'alice');
        $this->assertStringContainsString('(1/2)', $this->window());
    }

    // --- system messages ---------------------------------------------------

    public function test_msglog_empty()
    {
        Chat::resolve('/msglog', 'alice');
        $this->assertSame([format_colors('{#server}> {#error}No system message history found!')], $this->messages());
    }

    public function test_system_message_window_and_history()
    {
        config(['funseco.window_timeout' => 6]);
        event(new SystemMessagePosted("{#server}First\nSecond"));

        $sent = $this->sent('SendDisplayManialinkPage');
        $this->assertCount(1, $sent);
        [$xml, $timeout] = $sent[0];
        $this->assertSame(6000, $timeout);
        $id = ManiaLink::getAction('message_window');
        $this->assertSame('<manialink id="'.$id.'"><frame posn="-49 43.5 0"><quad sizen="93 6.5" style="Bgs1" substyle="NavButton"/>'
            .'<label posn="1 -1 1" sizen="91 1" style="TextRaceChat" text="'.format_colors('$z$s{#server}First').'"/>'
            .'<label posn="1 -3.5 1" sizen="91 1" style="TextRaceChat" text="$z$sSecond"/></frame></manialink>', $xml);

        Chat::resolve('/msglog', 'alice');
        $this->assertStringContainsString('Recent system message history:', $this->window());
        $this->assertStringContainsString('NewMessage', $this->window());
        $this->assertStringContainsString('$z$sSecond', $this->window());
    }

    public function test_history_keeps_21_lines_and_window_shows_last_5()
    {
        for ($i = 1; $i <= 25; $i++) {
            event(new SystemMessagePosted("msg $i"));
        }
        $history = app(MessageHistory::class)->all();
        $this->assertCount(21, $history);
        $this->assertSame('$z$smsg 5', $history[0]);

        $sent = $this->sent('SendDisplayManialinkPage');
        $xml = end($sent)[0];
        $this->assertSame(5, substr_count($xml, '<label'));
        $this->assertStringContainsString('sizen="93 14"', $xml);
        $this->assertStringContainsString('posn="1 -11 1"', $xml);
        $this->assertStringContainsString('msg 25', $xml);
        $this->assertStringNotContainsString('msg 20"', $xml);

        Chat::resolve('/msglog', 'alice');
        $this->assertStringNotContainsString('(1/2)', $this->window(), 'single page');
    }

    public function test_long_history_line_is_wrapped_at_800()
    {
        app(MessageHistory::class)->add(str_repeat('a', 1000));
        $history = app(MessageHistory::class)->all();
        $this->assertCount(2, $history);
        $this->assertSame(800, strlen($history[0]));
        $this->assertStringStartsWith('$z$s$n', $history[1]);
    }

    public function test_msglog_button_shown_and_runs_msglog()
    {
        app(MsgLogButton::class)->show($this->alice);
        $id = ManiaLink::getAction('msglog_button');
        $this->assertSame('<manialink id="'.$id.'"><frame posn="-63.9 -33.5 0"><quad sizen="1.65 1.65" style="Icons64x64_1" substyle="ArrowUp" action="'.$id.'"/></frame></manialink>', $this->window());

        ManiaLink::dispatchAnswer($this->alice, $id);
        $this->assertSame([format_colors('{#server}> {#error}No system message history found!')], $this->messages());
    }

    public function test_msglog_button_can_be_disabled()
    {
        config(['messages.msglog_button.enabled' => false]);
        app(MsgLogButton::class)->show($this->alice);
        $this->assertSame([], $this->sent('SendDisplayManialinkPageToLogin'));
    }

    // --- URL bot --------------------------------------------------------------

    public function test_url_bot_posts_page_title()
    {
        $http = $this->fakeHttp();
        $http->shouldReceive('getUntrusted')->once()
            ->withArgs(fn ($url, $headers) => $url === 'http://example.com/page' && str_contains($headers['User-Agent'], 'Firefox/84.0'))
            ->andReturn(resolve(new Response(200, [], "<html><head>\n<TITLE>Tom &amp; Jerry \u{1F600} \$f00red</TITLE>")));

        event(new \Funseco\Core\SharedKernel\Events\PlayerChatLine($this->alice, 'look $lexample.com/page now'));

        $this->assertSame(['$fff[$f00URL Bot$fff] Tom & Jerry red'], $this->messages('ChatSendServerMessage'));
    }

    public function test_url_bot_answers_pm_lines_privately_to_both()
    {
        $http = $this->fakeHttp();
        $http->shouldReceive('getUntrusted')->once()->andReturn(resolve(new Response(200, [], '<title>Hello</title>')));

        event(new \Funseco\Core\SharedKernel\Events\PlayerChatLine($this->alice, '/pm bobby see $lexample.com'));

        $this->assertSame([], $this->messages('ChatSendServerMessage'));
        $this->assertSame([
            ['$i$f00-pm-$fff[$f00URL Bot$fff] Hello', 'bobby'],
            ['$i$f00-pm-$fff[$f00URL Bot$fff] Hello', 'alice'],
        ], $this->sent('ChatSendServerMessageToLogin'));
    }

    public function test_url_bot_ignores_lines_without_links_and_failed_fetches()
    {
        $http = $this->fakeHttp();
        $http->shouldReceive('getUntrusted')->once()->andReturn(reject(new \RuntimeException('timeout')));

        event(new \Funseco\Core\SharedKernel\Events\PlayerChatLine($this->alice, 'no links here http://example.com'));
        event(new \Funseco\Core\SharedKernel\Events\PlayerChatLine($this->alice, '$lhttps://down.example'));

        $this->assertSame([], $this->messages('ChatSendServerMessage'));
    }

    public function test_url_bot_title_on_multiple_lines_is_ignored()
    {
        $http = $this->fakeHttp();
        $http->shouldReceive('getUntrusted')->andReturn(resolve(new Response(200, [], "<title>\nSplit\n</title>")));
        event(new \Funseco\Core\SharedKernel\Events\PlayerChatLine($this->alice, '$lhttp://a.example'));
        $this->assertSame([], $this->messages('ChatSendServerMessage'));
    }

    public function test_url_bot_youtube_with_api_key()
    {
        config(['messages.url_bot.youtube_api_key' => 'test-key']);
        $http = $this->fakeHttp();
        $http->shouldReceive('getUntrusted')->once()
            ->withArgs(fn ($url) => str_starts_with($url, 'https://www.googleapis.com/youtube/v3/videos?id=abc123&part=snippet&key=test-key'))
            ->andReturn(resolve(new Response(200, [], json_encode(['items' => [['snippet' => ['title' => 'Cool $$5 $ivideo']]]]))));

        event(new \Funseco\Core\SharedKernel\Events\PlayerChatLine($this->alice, '$lhttps://www.youtube.com/watch?v=abc123'));

        $this->assertSame(['$fff[$f00YouTube Bot$fff] Cool $$5 video'], $this->messages('ChatSendServerMessage'));
    }

    public function test_url_bot_youtube_without_key_uses_page_title()
    {
        config(['messages.url_bot.youtube_api_key' => null]);
        $http = $this->fakeHttp();
        $http->shouldReceive('getUntrusted')->once()->with('https://youtu.be/xyz', Mockery::any(), Mockery::any(), Mockery::any())
            ->andReturn(resolve(new Response(200, [], '<title>Video - YouTube</title>')));

        event(new \Funseco\Core\SharedKernel\Events\PlayerChatLine($this->alice, '$lhttps://youtu.be/xyz'));

        $this->assertSame(['$fff[$f00YouTube Bot$fff] Video - YouTube'], $this->messages('ChatSendServerMessage'));
    }

    public function test_url_bot_schemeless_youtube_is_a_plain_link_and_non_http_is_skipped()
    {
        $http = $this->fakeHttp();
        $http->shouldReceive('getUntrusted')->once()->with('http://www.youtube.com/watch?v=1', Mockery::any(), Mockery::any(), Mockery::any())
            ->andReturn(resolve(new Response(200, [], '<title>YT</title>')));

        event(new \Funseco\Core\SharedKernel\Events\PlayerChatLine($this->alice, '$lftp://files.example $lwww.youtube.com/watch?v=1'));

        $this->assertSame(['$fff[$f00URL Bot$fff] YT'], $this->messages('ChatSendServerMessage'));
    }

    public function test_url_bot_keeps_link_order()
    {
        $http = $this->fakeHttp();
        $http->shouldReceive('getUntrusted')->with('http://one.example', Mockery::any(), Mockery::any(), Mockery::any())->andReturn(resolve(new Response(200, [], '<title>One</title>')));
        $http->shouldReceive('getUntrusted')->with('http://two.example', Mockery::any(), Mockery::any(), Mockery::any())->andReturn(resolve(new Response(200, [], '<title>Two</title>')));

        event(new \Funseco\Core\SharedKernel\Events\PlayerChatLine($this->alice, '$lone.example and $l[http://two.example]two$l'));

        $this->assertSame(['$fff[$f00URL Bot$fff] One', '$fff[$f00URL Bot$fff] Two'], $this->messages('ChatSendServerMessage'));
    }

    public function test_untrusted_fetch_refuses_private_addresses()
    {
        foreach (['127.0.0.1', '10.0.0.5', '192.168.1.1', '169.254.169.254', '::1', 'fd00::1', 'localhost'] as $host) {
            $this->assertFalse(PublicAddressConnector::isPublic($host), $host);
        }
        $this->assertTrue(PublicAddressConnector::isPublic('93.184.216.34'));

        $inner = Mockery::mock(\React\Socket\ConnectorInterface::class);
        $inner->shouldNotReceive('connect');
        $error = null;
        (new PublicAddressConnector($inner))->connect('127.0.0.1:80?hostname=evil.example')->catch(function ($e) use (&$error) {
            $error = $e;
        });
        $this->assertInstanceOf(\RuntimeException::class, $error);

        $rejected = null;
        (new AsyncHttpClient)->getUntrusted('file:///etc/passwd')->catch(function ($e) use (&$rejected) {
            $rejected = $e;
        });
        $this->assertInstanceOf(\InvalidArgumentException::class, $rejected);
    }

    // --- login autocomplete -----------------------------------------------------

    public function test_autocomplete_is_bound_as_core_expander()
    {
        $this->assertInstanceOf(LoginAutocomplete::class, app(ChatArgumentExpander::class));
    }

    public function test_autocomplete_partial_login_and_nickname()
    {
        $spec = Player::factory()->create(['login' => 'bobcat', 'nickname' => 'Cat']);
        CurrentSpectators::add($spec);
        $auto = app(LoginAutocomplete::class);

        $this->assertSame('bobby', $auto->expand($this->alice, 'bob%'), 'first candidate in list order');
        $this->assertSame('bobcat', $auto->expand($this->alice, 'bobc%'), 'spectators are candidates');
        $this->assertSame('$00fBob$z$g$s', $auto->expand($this->alice, '%bobby'));
        $this->assertSame('$00fBob$z$g$s', $auto->expand($this->alice, '%bo%'), 'A then B');
        $this->assertSame('myserver', $auto->expand($this->alice, 'server%'));
        $this->assertSame('$f00My Server$z$g$s', $auto->expand($this->alice, '%myserver'));
        $this->assertSame('zz%', $auto->expand($this->alice, 'zz%'), 'no match');
        $this->assertSame('%nobody', $auto->expand($this->alice, '%nobody'));
        $this->assertSame('Bob%', $auto->expand($this->alice, 'Bob%'), 'case-sensitive');
        $this->assertSame('100%', $auto->expand($this->alice, '100%'));
    }

    public function test_autocomplete_in_typed_commands_only()
    {
        // /chatlog shows the expanded argument nowhere, so use a recording command.
        $seen = null;
        Chat::register(new class($seen) implements \Funseco\Core\SharedKernel\Commands\Command {
            public function __construct(private &$seen) {}
            public function name(): string { return 'echoargs'; }
            public function handle(Player $player, array $args): void { $this->seen = $args; }
        });

        Chat::resolve('/echoargs ali% says %bobby hi', 'bobby');
        $this->assertSame(['alice', 'says', '$00fBob$z$g$s', 'hi'], $seen);

        Chat::run($this->alice, 'echoargs', ['ali%']);
        $this->assertSame(['ali%'], $seen, 'buttons pass arguments unchanged');

        // plain chat lines are not rewritten in the chat log
        event(new PlayerChatted($this->alice, 'hi bob%'));
        $this->assertSame('hi bob%', app(ChatLog::class)->all()[0]['text']);
    }

    public function test_plain_lines_with_a_percent_are_rewritten_and_forwarded_once_routing_is_on()
    {
        $this->app->instance(\Funseco\Core\SharedKernel\ChatPrefix::class, new \Funseco\Core\Services\Defaults\NullChatPrefix());   // prestige would decorate the line
        $line = fn (string $text) => new \Funseco\Core\Servers\TmForever\Callbacks\PlayerChat(5, 'alice', $text, false);
        $listener = app(\Funseco\Core\Services\Chat\Listeners\PlayerChatListener::class);

        $listener->handle($line('hi bob% and %bobby!'));
        $this->assertSame([], $this->sent('ChatForwardToLogin'), 'not routing yet: the server shows the line itself');

        \Chat::enableManualRouting();
        $this->assertSame([[true, true]], $this->sent('ChatEnableManualRouting'));
        $this->assertTrue(\Chat::routing());

        $listener->handle($line('hi bob%  and %bobby!'));
        $this->assertSame([['hi bobby  and $00fBob$z$g$s!', 'alice', '']], $this->sent('ChatForwardToLogin'));
        $this->assertSame('hi bob%  and %bobby!', ($log = app(ChatLog::class)->all())[array_key_last($log)]['text'], 'listeners see the original');
    }

    public function test_server_name_missing_expands_to_nothing_instead_of_failing()
    {
        ServerInfo::set([]);
        $this->assertSame('$z$g$s', app(LoginAutocomplete::class)->expand($this->alice, '%myserver'));
    }

    private function deniedTo(string $login): string
    {
        foreach ($this->sent('ChatSendServerMessageToLogin') as [$message, $to]) {
            if ($to === $login) {
                return $message;
            }
        }

        return '';
    }

    public function test_permission_denied_text_differs_for_staff_and_players()
    {
        Chat::register(new class implements \Funseco\Core\SharedKernel\Commands\Command, \Funseco\Core\SharedKernel\Commands\Guarded {
            public function name(): string { return 'secret'; }
            public function handle(Player $player, array $args): void {}
            public function role(): ?\Funseco\Core\SharedKernel\Enums\Role { return \Funseco\Core\SharedKernel\Enums\Role::MasterAdmin; }
            public function ability(): ?string { return null; }
        });
        $op = Player::factory()->create(['login' => 'opguy', 'nickname' => 'Op', 'role' => 'operator']);
        CurrentPlayers::add($op);

        Chat::resolve('/secret', 'alice');
        Chat::resolve('/secret', 'opguy');
        $this->assertStringContainsString('You have to be in admin list to do that!', $this->deniedTo('alice'));
        $this->assertStringContainsString("You don't have the required admin rights to do that!", $this->deniedTo('opguy'));
    }
}

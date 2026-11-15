<?php
namespace Funseco\Messages;

use Funseco\Core\SharedKernel\PublishesPluginConfig;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Funseco\Core\Services\Cache\CurrentPlayers;
use Funseco\Core\Services\ManiaLink\ManiaLinkService;
use Funseco\Core\SharedKernel\ChatArgumentExpander;
use Funseco\Core\SharedKernel\Events\ControllerShutdownRequested;
use Funseco\Core\SharedKernel\Events\EverySecondEvent;
use Funseco\Core\SharedKernel\Events\MapEnded;
use Funseco\Core\SharedKernel\Events\PlayerChatLine;
use Funseco\Core\SharedKernel\Events\PlayerChatted;
use Funseco\Core\SharedKernel\Events\PlayerConnected;
use Funseco\Core\SharedKernel\Events\StartupEvent;
use Funseco\Core\SharedKernel\Events\SystemMessagePosted;
use Funseco\Messages\Commands as C;
use Funseco\Messages\UrlBot\UrlBot;
use Funseco\Core\SharedKernel\Settings\Setting;
use Funseco\Core\SharedKernel\Settings\SettingsRegistry;

class MessagesServiceProvider extends ServiceProvider
{
    use PublishesPluginConfig;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/messages.php', 'messages');
        $this->publishPluginConfig(__DIR__.'/..');

        // The periodic tip: toggle, delay and prefix/text are the message's; the line itself is picked from messages.info.messages.
        $this->app->make(\Funseco\Core\SharedKernel\Messages\MessageRegistry::class)->declare('messages', [
            \Funseco\Core\SharedKernel\Messages\Message::make('info_tip', 'Periodic info tip',
                (string) config('messages.info.prefix').'{line}', ['line' => 'The tip picked from the list'], raw: ['line']),
        ]);

        // autocomplete.* decide at StartupEvent whether chat routing is switched to manual: restart.
        $this->app->make(SettingsRegistry::class)->declare('messages', 'messages', [
            Setting::int('info.interval', 'Info message interval', min: 1, unit: 's'),
            Setting::bool('funny_tips.enabled', 'Funny tips at map end'),
            Setting::bool('anti_preach.enabled', 'Anti-preach chat filter'),
            Setting::bool('anti_preach.name_gate', 'Anti-preach only for matching names'),
            Setting::int('chat_log.size', 'Chat log lines kept', min: 1, max: 1000),
            Setting::int('chat_log.wrap', 'Chat log wrap width', min: 10, max: 300, unit: 'chars'),
            Setting::int('chat_log.per_page', 'Chat log lines per page', min: 1, max: 100),
            Setting::int('message_log.size', 'System message log size', min: 1, max: 1000),
            Setting::int('message_log.window_lines', 'System message window lines', min: 1, max: 50),
            Setting::bool('msglog_button.enabled', 'Message log button'),
            Setting::bool('url_bot.enabled', 'URL title bot'),
            Setting::float('url_bot.timeout', 'URL bot timeout', min: 0.5, max: 60, unit: 's'),
            Setting::int('url_bot.max_bytes', 'URL bot max download', min: 1024, max: 10485760, unit: 'bytes'),
            Setting::int('url_bot.max_length', 'URL bot max title length', min: 10, max: 500, unit: 'chars'),
            Setting::string('url_bot.user_agent', 'URL bot user agent'),
            Setting::string('url_bot.youtube_api_key', 'YouTube API key', secret: true),
            Setting::bool('autocomplete.enabled', 'Login autocomplete (%)', restart: true),
            Setting::bool('autocomplete.manual_routing', 'Manual chat routing', restart: true),
        ]);
        foreach ([InfoMessages::class, ChatLog::class, MessageHistory::class, MessageWindow::class, MsgLogButton::class, UrlBot::class, LoginAutocomplete::class, FunnyTips::class, AntiPreach::class] as $class) {
            $this->app->singleton($class);
        }
        $this->app->singleton(ChatArgumentExpander::class, fn ($app) => $app->make(LoginAutocomplete::class));
    }

    public function boot(): void
    {
        app(ManiaLinkService::class)->addAction('message_window');
        app(ManiaLinkService::class)->addAction('msglog_button', MsgLogButton::class);

        foreach ([C\ChatLogCommand::class, C\MsgLogCommand::class, C\MessageCommand::class] as $command) {
            \Chat::register($command);
        }

        Event::listen(EverySecondEvent::class, fn () => app(InfoMessages::class)->tick());
        Event::listen(MapEnded::class, fn () => app(InfoMessages::class)->random(atEndOfTrack: true));
        Event::listen(MapEnded::class, fn () => app(FunnyTips::class)->mapEnded());
        \Chat::addLineFilter(fn ($author, string $text) => app(AntiPreach::class)->filter($author, $text));

        Event::listen(\Funseco\Core\SharedKernel\Events\ChatLogClearRequested::class, fn () => app(ChatLog::class)->clear());
        Event::listen(PlayerChatted::class, function (PlayerChatted $e) {
            app(ChatLog::class)->record($e->player, $e->text);
        });
        // Every typed line, /pm included (the bot answers privately to those)
        Event::listen(PlayerChatLine::class, fn (PlayerChatLine $e) => app(UrlBot::class)->chatted($e->player, $e->text));

        Event::listen(SystemMessagePosted::class, fn (SystemMessagePosted $e) => app(MessageWindow::class)->posted($e));

        Event::listen(StartupEvent::class, function () {
            if (config('messages.autocomplete.enabled') && config('messages.autocomplete.manual_routing')) {
                \Chat::enableManualRouting();
            }
            foreach (CurrentPlayers::all() as $player) {
                app(MsgLogButton::class)->show($player);
            }
        });
        Event::listen(ControllerShutdownRequested::class, function () {
            \Chat::routing() && \Chat::disableManualRouting();   // never leave the server without chat
        });
        Event::listen(PlayerConnected::class, fn (PlayerConnected $e) => app(MsgLogButton::class)->show($e->player));
    }
}

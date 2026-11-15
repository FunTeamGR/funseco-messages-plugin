<?php
namespace Funseco\Messages;

use Funseco\Core\SharedKernel\Events\InfoLinesCollecting;
use Funseco\Core\SharedKernel\Events\SystemMessagePosted;
use Funseco\Core\SharedKernel\GuardState;
use Funseco\Core\SharedKernel\Messages\Msg;

/**
 * Random info messages: the periodic one (every `messages.info.interval` seconds) and the
 * map-end one (also on /message).
 */
class InfoMessages
{
    private const COUNTER = 'messages:info_counter';

    /** Every second: when the counter reaches the interval, one random periodic message. */
    public function tick(): void
    {
        $counter = (int) state()->get(self::COUNTER, 0) + 1;
        if ($counter < (int) config('messages.info.interval')) {
            state()->forever(self::COUNTER, $counter);
            return;
        }

        state()->forever(self::COUNTER, 0);
        $messages = (array) config('messages.info.messages', []);
        if ($messages) {
            Msg::send('messages.info_tip', null, ['line' => $messages[array_rand($messages)]]);
        }
    }

    /** One random map-end message, to chat or to the message window (by the guard's infomessages setting). */
    public function random(bool $atEndOfTrack): void
    {
        $messages = array_values((array) config('messages.map_end.messages', []));
        // other plugins' extra lines (Milenco's addinfo) join the pool
        event($extra = new InfoLinesCollecting());
        $messages = [...$messages, ...$extra->lines()];
        $mode = (int) app(GuardState::class)->setting('infomessages');   // /jfreu infomessages: 0 off, 1 chat, 2 window
        if (!$messages || ($atEndOfTrack && $mode === 0)) {
            return;
        }

        $text = config('messages.map_end.prefix').$messages[array_rand($messages)];
        if ($mode === 2) {
            event(new SystemMessagePosted($text, $atEndOfTrack));
            return;
        }

        Text::broadcast(format_colors($text));
    }
}

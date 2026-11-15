<?php
namespace Funseco\Messages;

use Funseco\Core\Models\Player;

/** Last N player chat lines (global, memory only). */
class ChatLog
{
    private const KEY = 'messages:chat_log';

    public function record(Player $player, string $text): void
    {
        if ($text === '' || str_starts_with($text, '/')) {
            return;
        }

        $log = $this->all();
        $log[] = ['time' => now()->format('H:i:s'), 'nickname' => Text::narrow($player->nickname), 'text' => $text];
        state()->forever(self::KEY, array_slice($log, -max(1, (int) config('messages.chat_log.size'))));
    }

    public function clear(): void
    {
        state()->forever(self::KEY, []);
    }

    /** @return array<int, array{time: string, nickname: string, text: string}> oldest first */
    public function all(): array
    {
        return state()->get(self::KEY, []);
    }

    /** Window lines: each entry stripped, wrapped, and prefixed with time and nick per piece. */
    public function lines(): array
    {
        $wrap = (int) config('messages.chat_log.wrap');
        $times = (bool) config('funseco.chatpmlog_times');
        $lines = [];

        foreach ($this->all() as $entry) {
            $prefix = ($times ? '$z<{#server}'.$entry['time'].'$z> ' : '$z').'[{#black}'.$entry['nickname'].'$z] ';
            foreach (explode("\n", wordwrap(Text::strip($entry['text']), $wrap, "\n...")) as $piece) {
                $lines[] = [$prefix.mb_substr($piece, 0, $wrap + 3)];
            }
        }

        return $lines;
    }
}

<?php
namespace Funseco\Messages;

/** System messages sent to the message window (global, memory only), for /msglog. */
class MessageHistory
{
    private const KEY = 'messages:history';
    private const MAX_LINE = 800;

    public function add(string $message): void
    {
        $history = $this->all();
        foreach (explode("\n", $message) as $line) {
            foreach (explode("\n", wordwrap('$z$s'.$line, self::MAX_LINE, "\n".'$z$s$n', true)) as $piece) {
                $history[] = format_colors($piece);
            }
        }

        state()->forever(self::KEY, array_slice($history, -max(1, (int) config('messages.message_log.size'))));
    }

    /** @return string[] oldest first */
    public function all(): array
    {
        return state()->get(self::KEY, []);
    }

    /** @return string[] */
    public function last(int $count): array
    {
        return array_slice($this->all(), -$count);
    }
}

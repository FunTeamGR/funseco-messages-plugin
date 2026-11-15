<?php
namespace Funseco\Messages;

use Funseco\Core\Services\ManiaLink\Facades\ManiaLink;
use Funseco\Core\SharedKernel\Events\SystemMessagePosted;

/** Small window with the latest system messages, shown to everyone on each new one. */
class MessageWindow
{
    public function __construct(private MessageHistory $history)
    {
    }

    public function posted(SystemMessagePosted $event): void
    {
        $this->history->add($event->message);
        $xml = $this->xml();

        if (!$event->atEndOfTrack) {
            ManiaLink::displayAll($xml, (int) config('funseco.window_timeout', 6) * 1000, false);
            return;
        }

        // Shown during the podium: stay up for the server's chat time.
        \Server::query()->GetChatTime(function ($result) use ($xml) {
            ManiaLink::displayAll($xml, (int) ($result[0]['CurrentValue'] ?? 0) + 5000, false);
        });
    }

    public function xml(): string
    {
        $lines = $this->history->last(max(1, (int) config('messages.message_log.window_lines')));
        $pos = config('manialinks.message_window.pos_x', -49).' '.config('manialinks.message_window.pos_y', 43.5);

        $xml = '<manialink id="'.ManiaLink::getAction('message_window').'"><frame posn="'.$pos.' 0">'
            .'<quad sizen="93 '.(1.5 + count($lines) * 2.5).'" style="Bgs1" substyle="NavButton"/>';
        foreach (array_values($lines) as $i => $line) {
            $xml .= '<label posn="1 '.(-1 - $i * 2.5).' 1" sizen="91 1" style="TextRaceChat" text="'
                .htmlspecialchars(clean_utf8($line)).'"/>';
        }

        return $xml.'</frame></manialink>';
    }
}

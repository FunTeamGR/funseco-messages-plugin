<?php
namespace Funseco\Messages;

/**
 * funny_tips: a second map-end INFO line, from its own list and prefix. Independent of the
 * map-end message (both go out) and not gated by /jfreu infomessages.
 */
class FunnyTips
{
    public function mapEnded(): void
    {
        $tips = array_values((array) config('messages.funny_tips.messages', []));
        if (!config('messages.funny_tips.enabled') || !$tips) {
            return;
        }

        Text::broadcast(format_colors((string) config('messages.funny_tips.prefix').$tips[array_rand($tips)]));
    }
}

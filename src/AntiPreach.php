<?php
namespace Funseco\Messages;

use Funseco\Core\Models\Player;

/**
 * anti-preach: rewrites forwarded plain chat of players whose name contains a needle, replacing
 * the needle words in the whole line (case-insensitive substring, so "godzilla" changes too).
 * One random pick per replacement group and message.
 */
class AntiPreach
{
    public function filter(Player $author, string $text): string
    {
        if (!config('messages.anti_preach.enabled') || $author->login === (string) config('funseco.master_server.login')) {
            return $text;
        }

        $needles = array_map('strtolower', (array) config('messages.anti_preach.needles', []));
        if (!array_intersect(explode(' ', strtolower($text)), $needles)) {
            return $text;
        }
        if (config('messages.anti_preach.name_gate') && !$this->nameHasNeedle($author, $needles)) {
            return $text;
        }

        foreach ((array) config('messages.anti_preach.replacements', []) as $group) {
            $choices = array_values((array) ($group['with'] ?? []));
            if ($choices && ($group['match'] ?? [])) {
                $text = str_ireplace((array) $group['match'], $choices[array_rand($choices)], $text);
            }
        }

        return $text;
    }

    /** The colour-stripped nickname or the login holds a needle (case-insensitive substring). */
    private function nameHasNeedle(Player $author, array $needles): bool
    {
        foreach ([Text::strip((string) $author->nickname), $author->login] as $name) {
            foreach ($needles as $needle) {
                if ($needle !== '' && stripos($name, $needle) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}

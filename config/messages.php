<?php

return [
    // Periodic info message: every `interval` seconds after controller start, one random
    // line to everyone. Prefix is sent literally (raw $ codes). Empty list = nothing.
    'info' => [
        'interval' => 7200,
        'prefix'   => '$ff0>> $f00[$c00INFO$f00] $z$s',
        'messages' => [
            'Nations player don\'t have to /afk on this server to play! Going afk will roast yomama.',
        ],
    ],

    // Random info message at every map end, and on /message.
    // The mode (0 = off, 1 = chat, 2 = message window, SystemMessagePosted; /msglog history) is
    // the guard's `/jfreu infomessages` setting, read through the core GuardState contract.
    // The prefix and lines may hold {#colour} tokens.
    'map_end' => [
        'prefix'   => '$z$s$ff0>> [$f00INFO$ff0] $fff',
        'messages' => [
            'Use "/list" -> "/jukebox ##" to add a track in the jukebox.',
            'Please don\'t sound your horn throughout the entire track.',
            'When going AFK, please set your car to Spectator mode.',
            'Don\'t use Enter to skip intros - instead use Space & Enter',
            'For player & server info use the "/stats" and "/server" commands.',
            'Looking for the name of this server?  Use the "/server" command.',
            'Use "/list nofinish" to find tracks you haven\'t completed yet, then /jukebox them!',
            'Use "/list norank" to find tracks you aren\'t ranked on, then /jukebox them!',
            'Can you beat the Gold time on all tracks?  Use "/list nogold" to find out!',
            'Can you beat the Author time on all tracks?  Use "/list noauthor" to find out!',
            'Wondering which tracks you haven\'t played recently?  Use "/list norecent" to find out!',
            'Use the "/best" & "/worst" commands to find your best and worst records!',
            'Use the "/clans" & "/topclans" commands to see clan members and ranks!',
            'Use the "/ranks" commands to see the server ranks of all online players!',
            'Who is the most victorious player?  Use "/topwins" to find out!',
            'Who has the most ranked records?  Use "/toprecs" to find out!',
            'Wondering what tracks were played recently?  Use the "/history" command.',
            'Looking for the next better ranked record to beat?  Use "/nextrec"!',
            'Find the difference between your personal best and the track record with the "/diffrec" command!',
            'Check how many records were driven on the current track with the "/newrecs" command!',
            'Check how many records, and the 3 best ones, you have with the "/summary" command!',
            'Who has the most top-3 ranked records?  Use "/topsums" to find out!',
            'Jukeboxed the wrong track?  Use "/jukebox drop" to remove it!',
            'Forgot what someone said?  Use "/chatlog" to check the chat history!',
            'Forgot what someone pm-ed you?  Use "/pmlog" to check your PM history!',
            'Looking for the next better ranked player to beat?  Use "/nextrank"!',
            'Use "/list newest <#>" to find the newest tracks added to the server, then /jukebox them!',
            'Find the longest and shortest tracks with the "/list longest / shortest" commands!',
            'Use "/mute" and "/unmute" to mute / unmute other players, and "/mutelist" to list them!',
            'Wondering when a player was last online?  Use "/laston <login>" to find out!',
            'Looking for any player\'s world stats?  Use the "/statsall <login>" command!',
            'Use checkpoints tracking in Rounds/Team/Cup modes with the "/cps" command!',
            'Find the TMX info & records for a track with the "/tmxinfo" & "/tmxrecs" commands!',
            'Looking for the name of the current track\'s song?  Use the "/song" command!',
            'Looking for the name of the current track\'s mod?  Use the "/mod" command!',
            'Use the "/style" command to select your personal window style!',
            'Use the "/recpanel" command to select your personal records panel!',
            'Use the "/votepanel" command to select your personal vote panel!',
            'Find out all about the Dedimania world records system with "/helpdedi"!',
            'Find out all about the chat-based voting commands with "/helpvote"!',
            'Missed a system message?  Use "/msglog" to check the message history!',
        ],
    ],

    // funny_tips: a second, independent line at every map end (not gated by /jfreu infomessages).
    // The tips list is owner data (tips.xml of the owner's XAseco, verbatim).
    'funny_tips' => [
        'enabled'  => true,
        'prefix'   => '$z$s$ff0>> [$f00INFO$ff0] $z',
        'messages' => [
            '$0ffWant to extend the time of your life? We can only do for maps. Just type /replay.',
            '$0ffIf you feel helpless use /helpme for all the available server commands. No one cares about you.',
            '$0ffWonder who voted what on the current track? I am not a snitch... just try /helpme',
            '$0ffDon\'t be a naughty boy or else the staff will f... I mean ban you.',
            '$0ffThe server invokes the right to remain silent.',
            '$0ffWant to become top player of the Server? Just type /bootme.',
            '$0ffUse /donate to support the server. We accept Credit Cards, your sisters and your soul.',
            '$0ffDon\'t like this track? We dont care, but your can try /skip.',
            '$0ffWant to apply for the Team? Patience is the key. Some people waited for months. Some are still waiting.',
            '$0ffUse /spec to watch better players and feel bad about yourself.',
            '$0ffIf an admin is online, behave. If not, behave anyway. Logs exist.',
            '$0ffDon\'t forget to vote the maps. Maps with 20+ votes and red karma will be erased automatically(I hope)',
            '$0ffThis tip was carefully crafted to waste 3 seconds of your life.',
            '$0ffServer rule #1: Dont be a dick!',
            '$0ffIf this map feels impossible, remember: someone finished it.',
            '$0ffWant to be rich? Vote maps to earn coppers :D',
        ],
    ],

    // anti-preach: plain chat lines of players whose name (or login) holds one of the needles
    // get these words swapped (name-driven joke). Replacement is substring based on purpose.
    'anti_preach' => [
        'enabled'   => true,
        'name_gate' => true,
        'needles'   => ['god', 'g0d', 'jesus'],
        'replacements' => [
            ['match' => ['god', 'g0d', 'g o d', 'g 0 d'], 'with' => [
                'Asmo', 'Flying Spaghetti Monster', 'Big Beard in the Sky', 'Invisible Pink Unicorn', 'Mighty Cabbage',
                'Cosmic Muffin', 'Holy Banana', 'Zeus Jr.', 'The Almighty Toaster',
            ]],
            ['match' => ['jesus', 'j e s u s'], 'with' => [
                'Banana', 'Spiderman', 'Black Widow', 'Captain Obvious', 'Magic Carpenter', 'Water-Walking Wizard',
                'Zombie Messiah', 'Superman with Sandals', 'Bearded Avenger', 'The Original Influencer', 'RNGesus',
            ]],
        ],
    ],

    // /chatlog: last `size` player chat lines (memory only).
    'chat_log' => [
        'size'     => 30,
        'wrap'     => 70,
        'per_page' => 15,
    ],

    // System message history (/msglog) and the message window (last `window_lines`).
    'message_log' => [
        'size'         => 21,
        'window_lines' => 5,
    ],

    // Permanent bottom-left button that runs /msglog (position: manialinks.msglog_button).
    'msglog_button' => [
        'enabled' => true,
    ],

    // Posts the page title of links ($l...) players write in chat.
    'url_bot' => [
        'enabled'    => true,
        'timeout'    => 5.0,
        'max_bytes'  => 1048576,
        'max_length' => 150,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:84.0) Gecko/20100101 Firefox/84.0',
        // YouTube Data API v3 key; unset = YouTube links use the page title.
        'youtube_api_key' => env('YOUTUBE_API_KEY'),
        'prefix' => [
            'url'     => '$fff[$f00URL Bot$fff] ',
            'youtube' => '$fff[$f00YouTube Bot$fff] ',
        ],
    ],

    // "abc%" → login, "%login" → nickname in every typed chat line. Plain lines can only be
    // rewritten when the controller routes chat itself (ChatEnableManualRouting, server
    // messages auto-forwarded); commands work without it.
    'autocomplete' => [
        'enabled'        => true,
        'manual_routing' => true,
    ],

    'messages' => [
        'no_chat'     => '{#server}> {#error}No chat history found!',
        'no_messages' => '{#server}> {#error}No system message history found!',
    ],
];

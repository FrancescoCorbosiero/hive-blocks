<?php

use Hive\Core\Hero;

// Choices, texts, ids, switches.
check(Hero::choice('split', Hero::LAYOUTS, 'cover'), 'split', 'a known layout');
check(Hero::choice('slider', Hero::LAYOUTS, 'cover'), 'cover', 'an unknown layout falls back to the default');
check(Hero::choice(['split'], Hero::LAYOUTS, 'cover'), 'cover', 'not a string');
check(Hero::text('  Nati a Piacenza '), 'Nati a Piacenza', 'texts are trimmed');
check(Hero::text(['x']), '', 'not a text');
check(Hero::id('42'), 42, 'an id as a string');
check(Hero::id(-3), 0, 'no negative ids');
check(Hero::id([7]), 0, 'not an id');
check(Hero::flag(null, true), true, 'missing: the default');
check(Hero::flag(false, true), false, 'a boolean as it is');
check(Hero::flag('false', true), false, 'a string that says no');

// Focal points.
check(Hero::position('left center'), 'left center', 'a preset');
check(Hero::position('left'), 'left center', 'one word: the other half is the centre');
check(Hero::position('top'), 'center top', 'one vertical word');
check(Hero::position(' TOP   Right '), 'right top', 'either word order, any case and spacing');
check(Hero::position('30% 70%'), 'center center', 'not a preset');
check(Hero::position('left; background:red'), 'center center', 'nothing else gets into the style attribute');

// The title's accent.
check(Hero::accent('BORN in PIACENZA', 'piacenza'), ['BORN in ', 'PIACENZA', ''], 'found ignoring case, in the title’s own spelling');
check(Hero::accent('Perché noi', 'PERCHÉ'), ['', 'Perché', ' noi'], 'accented letters');
check(Hero::accent('Sneakers premium', 'Milano'), null, 'not in the title');
check(Hero::accent('Sneakers premium', '  '), null, 'no accent');

// Buttons and values.
check(Hero::button(' Acquista ', '/shop/'), ['text' => 'Acquista', 'url' => '/shop/'], 'a button');
check(Hero::button('Acquista', ''), null, 'no link, no button');
check(Hero::button('', '/shop/'), null, 'no label, no button');
check(
    Hero::values([['title' => ' Autenticità ', 'text' => ' Verificata. '], ['title' => '', 'text' => 'Senza titolo'], 'junk', ['title' => 'Passione']]),
    [['title' => 'Autenticità', 'text' => 'Verificata.'], ['title' => 'Passione', 'text' => '']],
    'values: trimmed, those without a title left out'
);
check(Hero::values('nope'), [], 'values: not a list');

// hive/hero.
$hive_hero = Hero::hero([]);
check(
    [$hive_hero['layout'], $hive_hero['height'], $hive_hero['side'], $hive_hero['dark'], $hive_hero['heading'], $hive_hero['overlay'], $hive_hero['eager'], $hive_hero['scroll']],
    ['cover', 'tall', 'left', false, 'h2', 0.5, true, false],
    'hero: the defaults'
);
check($hive_hero['buttons'], [], 'hero: no buttons without links');

$hive_hero = Hero::hero(
    [
        'layout'              => 'cover',
        'title'               => 'BORN in PIACENZA',
        'titleAccent'         => 'PIACENZA',
        'headingLevel'        => 'h1',
        'buttonText'          => 'Vieni a trovarci',
        'buttonUrl'           => '#contatti',
        'buttonStyle'         => 'outline',
        'secondaryButtonText' => 'Chi siamo',
        'secondaryButtonUrl'  => '#chi-siamo',
        'videoUrl'            => ' https://example.com/intro.mp4 ',
        'imageUrl'            => 'https://example.com/poster.jpg',
        'foregroundId'        => 12,
        'overlay'             => 140,
        'tone'                => 'dark',
        'showScrollIndicator' => true,
    ]
);
check($hive_hero['heading'], 'h1', 'hero: h1 on request');
check($hive_hero['accent'], ['BORN in ', 'PIACENZA', ''], 'hero: the accent');
check(
    $hive_hero['buttons'],
    [['text' => 'Vieni a trovarci', 'url' => '#contatti', 'outline' => true], ['text' => 'Chi siamo', 'url' => '#chi-siamo', 'outline' => true]],
    'hero: the main button (outlined on request), then the second, always outlined'
);
check($hive_hero['video'], 'https://example.com/intro.mp4', 'hero: the video');
check($hive_hero['image'], ['id' => 0, 'url' => 'https://example.com/poster.jpg'], 'hero: an image known only by its URL');
check($hive_hero['foreground'], ['id' => 12, 'url' => ''], 'hero: the cover’s foreground layer');
check($hive_hero['overlay'], 0.9, 'hero: the overlay stops at 90%');
check($hive_hero['dark'], false, 'hero: the cover is always dark, "tone" is for the two columns');
check($hive_hero['scroll'], true, 'hero: the scroll hint on a cover');

$hive_hero = Hero::hero(['layout' => 'about', 'tone' => 'dark', 'mediaSide' => 'right', 'height' => 'huge', 'foregroundId' => 12, 'showScrollIndicator' => true, 'aboveFold' => false, 'overlay' => -5]);
check(
    [$hive_hero['layout'], $hive_hero['dark'], $hive_hero['side'], $hive_hero['height'], $hive_hero['foreground']['id'], $hive_hero['scroll'], $hive_hero['eager'], $hive_hero['overlay']],
    ['about', true, 'right', 'tall', 0, false, false, 0.0],
    'hero: two columns, dark, image on the right; no foreground layer or scroll hint outside the cover'
);

// hive/hero-carousel.
$hive_carousel = Hero::carousel(
    [
        'slides'     => [
            ['image' => 'https://example.com/ap.png', 'imageId' => 338363, 'objectPosition' => 'left', 'eyebrow' => 'LUXURY MEETS POP', 'title' => 'AP x SWATCH', 'buttonText' => 'Scopri', 'buttonUrl' => '/orologi/'],
            ['image' => '', 'title' => '  ', 'subtitle' => 'Nothing to show'],
            ['title' => 'Solo testo', 'buttonText' => 'Senza link'],
            'junk',
        ],
        'autoplay'   => 3000,
        'layout'     => 'centered',
        'showArrows' => false,
    ]
);
check(count($hive_carousel['slides']), 2, 'carousel: slides with neither an image nor a title are left out');
check(
    $hive_carousel['slides'][0],
    [
        'image'    => ['id' => 338363, 'url' => 'https://example.com/ap.png'],
        'position' => 'left center',
        'eyebrow'  => 'LUXURY MEETS POP',
        'title'    => 'AP x SWATCH',
        'subtitle' => '',
        'button'   => ['text' => 'Scopri', 'url' => '/orologi/'],
    ],
    'carousel: a slide'
);
check($hive_carousel['slides'][1]['button'], null, 'carousel: a button needs a link');
check([$hive_carousel['autoplay'], $hive_carousel['centered'], $hive_carousel['dots'], $hive_carousel['arrows']], [3000, true, true, false], 'carousel: the options');
check(Hero::carousel([])['autoplay'], 6000, 'carousel: autoplay every 6 seconds by default');
check(Hero::autoplay(0), 0, 'autoplay off');
check(Hero::autoplay(500), 2000, 'autoplay: not faster than every 2 seconds');
check(Hero::autoplay(99999), 20000, 'autoplay: not slower than every 20 seconds');
check(Hero::autoplay('soon'), 0, 'autoplay: not a number');

// hive/cta.
$hive_cta = Hero::cta(['title' => ' Resta in contatto ', 'buttonText' => 'Seguici', 'buttonUrl' => 'https://instagram.com/resellpiacenza/', 'reverse' => true]);
check(
    [$hive_cta['layout'], $hive_cta['title'], $hive_cta['button'], $hive_cta['reverse'], $hive_cta['glow']],
    ['banner', 'Resta in contatto', ['text' => 'Seguici', 'url' => 'https://instagram.com/resellpiacenza/'], false, true],
    'cta: a banner (no reverse there), glowing by default'
);
$hive_cta = Hero::cta(['layout' => 'split', 'reverse' => true, 'showGlow' => true, 'imageId' => '9']);
check([$hive_cta['layout'], $hive_cta['reverse'], $hive_cta['glow'], $hive_cta['image']['id']], ['split', true, false, 9], 'cta: image and text, reversed; the glow is the banner’s');

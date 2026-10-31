<?php

// The WhatsApp button's number and link (blocks/whatsapp-button).

use Hive\Core\WhatsApp;

check(WhatsApp::number('+39 351 760 3869', '393331234567'), '393517603869', 'the block’s own number, written with + and spaces');
check(WhatsApp::number('', '393331234567'), '393331234567', 'no number on the block: the store’s (Hive → Impostazioni)');
check(WhatsApp::number('0039 351-760-3869'), '393517603869', '00 before the country code goes');
check(WhatsApp::number('12345', '393331234567'), '393331234567', 'too short to be a number: the next one');
check(WhatsApp::number('1234567890123456'), '', 'too long to be a number');
check(WhatsApp::number('scrivici', ''), '', 'no number anywhere');
check(WhatsApp::number(), '', 'nothing to choose from');

check(
    WhatsApp::link('393331234567', 'Ciao! Vorrei avere informazioni su...'),
    'https://wa.me/393331234567?text=Ciao%21%20Vorrei%20avere%20informazioni%20su...',
    'the message, encoded for the address'
);
check(
    WhatsApp::link('393331234567', 'Taglia 42 & 43? Sì · 150 €'),
    'https://wa.me/393331234567?text=Taglia%2042%20%26%2043%3F%20S%C3%AC%20%C2%B7%20150%20%E2%82%AC',
    'symbols and accents encoded'
);
check(WhatsApp::link('393331234567', "  \n "), 'https://wa.me/393331234567', 'a blank message: just the chat');
check(WhatsApp::link('393331234567'), 'https://wa.me/393331234567', 'no message: just the chat');

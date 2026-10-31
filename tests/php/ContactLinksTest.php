<?php

// The links of the contact blocks: phone, email, WhatsApp (the block's number, else the setting's).

use Hive\Core\ContactLinks;

check(ContactLinks::tel('+39 0523 123 456'), 'tel:+390523123456', 'international, spaces out');
check(ContactLinks::tel('0523/12.34.56'), 'tel:0523123456', 'national, separators out');
check(ContactLinks::tel(' 3 '), '', 'too short');
check(ContactLinks::tel(''), '', 'no number');

check(ContactLinks::mailto(' info@resellpiacenza.it '), 'mailto:info@resellpiacenza.it', 'an address, trimmed');
check(ContactLinks::mailto('info at resellpiacenza'), '', 'not an address');
check(ContactLinks::mailto('a@b.it?cc=x@y.it'), '', 'no extra headers');

check(ContactLinks::whatsapp_number('+39 333 123 4567'), '393331234567', 'digits only');
check(ContactLinks::whatsapp_number('0039 333 1234567'), '393331234567', '00 in front is the international prefix');
check(ContactLinks::whatsapp_number('', '393517603869'), '393517603869', 'the block has none: the setting’s');
check(ContactLinks::whatsapp_number('12', '393517603869'), '393517603869', 'the block’s isn’t a number: the setting’s');
check(ContactLinks::whatsapp_number('+39 333 1234567', '393517603869'), '393331234567', 'the block’s wins');
check(ContactLinks::whatsapp_number('', ''), '', 'neither');
check(ContactLinks::whatsapp_number('1234567890123456'), '', 'more than 15 digits');

check(
    ContactLinks::whatsapp('393331234567', ' Ciao! Vorrei passare in negozio. '),
    'https://wa.me/393331234567?text=Ciao%21%20Vorrei%20passare%20in%20negozio.',
    'the chat with its message'
);
check(ContactLinks::whatsapp('+39 333 1234567'), 'https://wa.me/393331234567', 'no message');
check(ContactLinks::whatsapp('', 'Ciao'), '', 'no number, no link');

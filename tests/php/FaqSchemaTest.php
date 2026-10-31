<?php

// The FAQ block's questions and the page's one FAQPage.

use Hive\Core\FaqSchema;

check(
    FaqSchema::items([
        ['question' => ' Spedite all’estero? ', 'answer' => ' Sì, in tutta Europa. '],
        ['question' => 'Senza risposta', 'answer' => ''],
        ['question' => '', 'answer' => 'Senza domanda'],
        ['question' => ['x'], 'answer' => 'Non è testo'],
        'junk',
    ]),
    [['question' => 'Spedite all’estero?', 'answer' => 'Sì, in tutta Europa.']],
    'only questions with an answer, trimmed'
);
check(FaqSchema::items('nope'), [], 'not a list: no questions');

check(
    FaqSchema::text("<p>Sì, con <strong>DHL</strong>&nbsp;o UPS.</p><p>In 2&ndash;5 giorni &amp; con tracking.</p><script>alert(1)</script>"),
    'Sì, con DHL o UPS. In 2–5 giorni & con tracking.',
    'plain text: tags and scripts out, entities decoded, paragraphs apart'
);
check(FaqSchema::text("Riga uno<br>riga\n\n  due"), 'Riga uno riga due', 'one line');

$hive_page = FaqSchema::page([
    ['question' => 'I prodotti sono autentici?', 'answer' => 'Sì, <em>tutti</em>.'],
    ['question' => 'Posso effettuare un reso?', 'answer' => 'Entro 14 giorni.'],
    ['question' => 'I prodotti sono autentici?', 'answer' => 'Un’altra risposta.'],
]);
check(
    $hive_page,
    [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => [
            ['@type' => 'Question', 'name' => 'I prodotti sono autentici?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Sì, tutti.']],
            ['@type' => 'Question', 'name' => 'Posso effettuare un reso?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Entro 14 giorni.']],
        ],
    ],
    'one FAQPage; a question twice (two blocks, or a page rendered twice) is there once, with its first answer'
);
check(FaqSchema::page([['question' => 'Solo tag?', 'answer' => '<p> </p>']]), null, 'an answer that is only tags: no data');
check(FaqSchema::page([]), null, 'no questions: no data');

<?php

// A shipping city as the social proof shows it.

use Hive\Core\City;

check(City::name('  PIACENZA '), 'Piacenza', 'capitalized and trimmed');
check(City::name("reggio nell'emilia"), "Reggio nell'Emilia", 'a particle inside a name stays lowercase');
check(City::name("sant'angelo lodigiano"), "Sant'Angelo Lodigiano", 'each part of an apostrophe name');
check(City::name('castel san giovanni'), 'Castel San Giovanni', 'every word');
check(City::name('reggio di calabria'), 'Reggio di Calabria', 'small words stay small');
check(City::name('29121 Piacenza'), '', 'a postcode: nothing');
check(City::name(''), '', 'empty');
check(mb_strlen(City::name(str_repeat('a', 60))), 40, 'at most 40 characters');

<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'WLP ProSPI',
    'description' => 'ProSPI: Kursliste des SPI-Orientierungsplans (Liste, Filter, Detail)',
    'category' => 'plugin',
    'author' => 'Robert Kraemer',
    'author_email' => 'robert.kraemer@blx.partners',
    'author_company' => 'BLX Partners',
    'state' => 'beta',
    'version' => '0.2.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'php' => '8.2.0-8.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];

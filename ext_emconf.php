<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'WLP ProSPI',
    'description' => 'SPI Orientierungsplan courses integration (list + detail + filters)',
    'category' => 'plugin',
    'author' => 'Robert Kraemer',
    'author_email' => 'robert.kraemer@blx.partners',
    'state' => 'beta',
    'version' => '0.1.1',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.99.99',
            'php' => '8.1.0-8.3.99'
        ],
        'conflicts' => [],
        'suggests' => []
    ]
];

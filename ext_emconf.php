<?php

/** @var string $_EXTKEY */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Index',
    'description' => 'Smart and flexible indexing of pages and documents for e.h. search engines or AI provider',
    'version' => '0.0.1',
    'category' => 'be',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'webhooks' => '13.4.0-13.4.99',
            'reactions' => '13.4.0-13.4.99',
            'php' => '8.2.0-8.99.99',
        ],
    ],
    'state' => 'stable',
    'author' => 'Tim Lochmüller',
    'author_email' => 'tim@fruit-lab.de',
    'author_company' => 'HDNET GmbH & Co. KG',
    'autoload' => [
        'psr-4' => [
            'Lochmueller\\Index\\' => 'Classes',
        ],
    ],
];
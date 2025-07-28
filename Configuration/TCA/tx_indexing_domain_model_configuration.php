<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:indexing/Resources/Private/Language/locallang.xlf:tx_indexing_domain_model_index_page',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'ORDER BY uid',
    ],
    'columns' => [
        'title' => [
            'exclude' => 0,
            'title' => 'LLL:EXT:indexing/Resources/Private/Language/locallang.xlf:tx_indexing_domain_model_index_page.title',
            'config' => [
                'type' => 'input',
                'size' => '30',
            ],
        ],
        'language' => [
            'exclude' => 0,
            'title' => 'LLL:EXT:indexing/Resources/Private/Language/locallang.xlf:tx_indexing_domain_model_index_page.languages',
            'config' => [
                'type' => 'languages',
            ],
        ],
        'tags' => [
            'exclude' => 0,
            'title' => 'LLL:EXT:indexing/Resources/Private/Language/locallang.xlf:tx_indexing_domain_model_index_page.tags',
            'config' => [
                'type' => 'text',
            ],
        ],
    ],
    'types' => [
        '0' => ['showitem' => 'title,languages,tags'], // @todo Add pages & files Tabe
    ],
    'palettes' => [
        '1' => ['showitem' => ''],
    ],
];
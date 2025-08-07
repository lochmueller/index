<?php

use Lochmueller\Index\Enums\IndexTechnology;

return [
    'ctrl' => [
        'title' => 'LLL:EXT:index/Resources/Private/Language/locallang.xlf:tx_index_domain_model_configuration',
        'iconfile' => 'EXT:index/Resources/Public/Icons/Extension.svg',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'ORDER BY uid',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ]
    ],
    'columns' => [
        'title' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:index/Resources/Private/Language/locallang.xlf:tx_index_domain_model_configuration.title',
            'description' => 'LLL:EXT:index/Resources/Private/Language/locallang.xlf:tx_index_domain_model_configuration.title.description',
            'config' => [
                'type' => 'input',
                'size' => '30',
            ],
        ],
        'technology' => [
            'label' => 'LLL:EXT:index/Resources/Private/Language/locallang.xlf:tx_index_domain_model_configuration.technology',
            'description' => 'LLL:EXT:index/Resources/Private/Language/locallang.xlf:tx_index_domain_model_configuration.technology.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => array_map(function ($enum) {
                    return [
                        'label' => 'LLL:EXT:index/Resources/Private/Language/locallang.xlf:tx_index_domain_model_configuration.technology.type.'.$enum->value,
                        'value' => $enum->value,
                    ];
                }, IndexTechnology::cases()),
            ],
        ],
        'configuration' => [
            'label' => 'Index configuration',
            'description' => 'Configuration of the index process via YAML.',
            'config' => [
                'type' => 'text',
            ],
        ],
        'language' => [
            'exclude' => 0,
            'title' => 'LLL:EXT:index/Resources/Private/Language/locallang.xlf:tx_index_domain_model_index_page.languages',
            'config' => [
                'type' => 'languages',
            ],
        ],
        'tags' => [
            'exclude' => 0,
            'title' => 'LLL:EXT:index/Resources/Private/Language/locallang.xlf:tx_index_domain_model_index_page.tags',
            'config' => [
                'type' => 'text',
            ],
        ],
        'skip_no_search_pages' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:index/Resources/Private/Language/locallang.xlf:tx_index_domain_model_configuration.skip_no_search_pages',
            'description' => 'LLL:EXT:index/Resources/Private/Language/locallang.xlf:tx_index_domain_model_configuration.skip_no_search_pages.description',
            'config' => [
                'type' => 'check',
            ],
        ],
    ],
    'types' => [
        '0' => ['showitem' => 'title,technology,skip_no_search_pages'], // @todo Add pages & files Tabe
    ],
    'palettes' => [
        '1' => ['showitem' => ''],
    ],
];
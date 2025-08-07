<?php

use Lochmueller\Index\Enums\IndexTechnology;
use Lochmueller\Index\FileExtraction\FileExtractor;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$lll = 'LLL:EXT:index/Resources/Private/Language/locallang.xlf:';


#$extractor = GeneralUtility::makeInstance(FileExtractor::class);

return [
    'ctrl' => [
        'title' => $lll . 'tx_index_domain_model_configuration',
        'iconfile' => 'EXT:index/Resources/Public/Icons/Extension.svg',
        'label' => 'title',
        'type' => 'technology',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'ORDER BY uid',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'columns' => [
        'title' => [
            'exclude' => 0,
            'label' => $lll . 'tx_index_domain_model_configuration.title',
            'description' => $lll . 'tx_index_domain_model_configuration.title.description',
            'config' => [
                'type' => 'input',
                'size' => '30',
            ],
        ],
        'technology' => [
            'label' => $lll . 'tx_index_domain_model_configuration.technology',
            'description' => $lll . 'tx_index_domain_model_configuration.technology.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => IndexTechnology::None->value,
                'items' => array_map(function ($enum) use ($lll) {
                    return [
                        'label' => $lll . 'tx_index_domain_model_configuration.technology.type.' . $enum->value,
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
        'languages' => [
            'exclude' => 0,
            'title' => $lll . 'tx_index_domain_model_index_page.languages',
            'config' => [
                'type' => 'languages',
            ],
        ],
        'tags' => [
            'exclude' => 0,
            'title' => $lll . 'tx_index_domain_model_index_page.tags',
            'config' => [
                'type' => 'text',
            ],
        ],
        'skip_no_search_pages' => [
            'exclude' => 0,
            'label' => $lll . 'tx_index_domain_model_configuration.skip_no_search_pages',
            'description' => $lll . 'tx_index_domain_model_configuration.skip_no_search_pages.description',
            'config' => [
                'type' => 'check',
            ],
        ],
        'file_mounts' => [
            'exclude' => 0,
            'label' => $lll . 'tx_index_domain_model_configuration.file_mounts',
            'description' => $lll . 'tx_index_domain_model_configuration.file_mounts.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'size' => 3,
                'foreign_table' => 'sys_filemounts',
                'foreign_table_where' => 'AND sys_filemounts.pid=0',
            ],
        ],
        'file_types' => [
            'exclude' => 0,
            'label' => $lll . 'tx_index_domain_model_configuration.file_types',
            'description' => $lll . 'tx_index_domain_model_configuration.file_types.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectCheckBox',
                'items' => [
                    [
                        'label' => 'Images',
                        'value' => 'images',
                        'icon' => 'mimetypes-media-image'
                    ],
                    [
                        'label' => 'Videos',
                        'value' => 'videos',
                        'icon' => 'mimetypes-media-video',
                    ],
                    [
                        'label' => 'Audio',
                        'value' => 'audio',
                        'icon' => 'mimetypes-media-audio',
                    ],
                    [
                        'label' => 'Archives',
                        'value' => 'archives',
                        'icon' => 'mimetypes-compressed',
                    ],

                    [
                        'label' => 'PDF',
                        'value' => 'pdf',
                        'icon' => 'mimetypes-pdf',
                    ],
                    [
                        'label' => 'Word',
                        'value' => 'word',
                        'icon' => 'mimetypes-word',
                    ],
                    [
                        'label' => 'Excel',
                        'value' => 'excel',
                        'icon' => 'mimetypes-excel',
                    ],
                    [
                        'label' => 'Powerpoint',
                        'value' => 'powerpoint',
                        'icon' => 'mimetypes-powerpoint',
                    ],
                ],
            ],
        ],
    ],
    'types' => [
        IndexTechnology::None->value => ['showitem' => '--div--;' . $lll . 'tx_index_domain_model_configuration.tab.general, 
                    title,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.pages,
                    technology'],
        IndexTechnology::Cache->value => ['showitem' => '--div--;' . $lll . 'tx_index_domain_model_configuration.tab.general, 
                    title,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.pages,
                    technology,skip_no_search_pages'],
        IndexTechnology::Database->value => ['showitem' => '--div--;' . $lll . 'tx_index_domain_model_configuration.tab.general, 
                    title,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.pages,
                    technology,skip_no_search_pages,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.files,
                    file_mounts,file_types
                    '],
        IndexTechnology::Frontend->value => ['showitem' => '--div--;' . $lll . 'tx_index_domain_model_configuration.tab.general, 
                    title,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.pages,
                    technology,skip_no_search_pages,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.files,
                    file_mounts,file_types
                    '],
    ],
    'palettes' => [
        '1' => ['showitem' => ''],
    ],
];
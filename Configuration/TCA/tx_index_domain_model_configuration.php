<?php

declare(strict_types=1);

use Lochmueller\Index\Backend\TcaSelection;
use Lochmueller\Index\Enums\IndexPartialTrigger;
use Lochmueller\Index\Enums\IndexTechnology;

$lll = 'LLL:EXT:index/Resources/Private/Language/locallang.xlf:';

return [
    'ctrl' => [
        'title' => $lll . 'tx_index_domain_model_configuration',
        'iconfile' => 'EXT:index/Resources/Public/Icons/Extension.svg',
        'label' => 'title',
        'type' => 'technology',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'default_sortby' => 'ORDER BY uid',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
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
                'items' => array_map(fn($enum) => [
                    'label' => $lll . 'tx_index_domain_model_configuration.technology.type.' . $enum->value,
                    'value' => $enum->value,
                ], array_filter(IndexTechnology::cases(), fn($item) => $item !== IndexTechnology::External)),
            ],
        ],
        'partial_indexing' => [
            'label' => $lll . 'tx_index_domain_model_configuration.partial_indexing',
            'description' => $lll . 'tx_index_domain_model_configuration.partial_indexing.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectCheckBox',
                'items' => array_map(fn($enum) => [
                    'label' => $lll . 'tx_index_domain_model_configuration.partial_indexing.type.' . $enum->value,
                    'value' => $enum->value,
                ], IndexPartialTrigger::cases()),
            ],
        ],
        'configuration' => [
            'label' => $lll . 'tx_index_domain_model_configuration.configuration',
            'description' => $lll . 'tx_index_domain_model_configuration.configuration.description',
            'config' => [
                'type' => 'json',
                'default' => '{}',
            ],
        ],
        'languages' => [
            'exclude' => 0,
            'label' => $lll . 'tx_index_domain_model_configuration.languages',
            'description' => $lll . 'tx_index_domain_model_configuration.languages.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectCheckBox',
                'itemsProcFunc' => TcaSelection::class . '->countrySelection',
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
        'content_indexing' => [
            'exclude' => 0,
            'label' => $lll . 'tx_index_domain_model_configuration.content_indexing',
            'description' => $lll . 'tx_index_domain_model_configuration.content_indexing.description',
            'config' => [
                'type' => 'check',
            ],
        ],
        'levels' => [
            'exclude' => 0,
            'label' => $lll . 'tx_index_domain_model_configuration.levels',
            'description' => $lll . 'tx_index_domain_model_configuration.levels.description',
            'config' => [
                'type' => 'number',
                'default' => 30,
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
                'itemsProcFunc' => Lochmueller\Index\FileExtraction\FileExtractor::class . '->getBackendItems',
            ],
        ],
    ],
    'palettes' => [
        'paletteHidden' => [
            'showitem' => '
                hidden
            ',
        ],
        'paletteAccess' => [
            'label' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:palette.access',
            'showitem' => '
                starttime;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:starttime_formlabel,
                endtime;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:endtime_formlabel,
            ',
        ],
    ],
    'types' => [
        IndexTechnology::None->value => ['showitem' => '--div--;' . $lll . 'tx_index_domain_model_configuration.tab.general,
                    title,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.pages,
                    technology,
                    --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                    --palette--;;paletteHidden,
                    --palette--;;paletteAccess,
                    '],
        IndexTechnology::Cache->value => ['showitem' => '--div--;' . $lll . 'tx_index_domain_model_configuration.tab.general,
                    title,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.pages,
                    technology,languages,skip_no_search_pages,levels,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.files,
                    file_mounts,file_types,
                    --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                    --palette--;;paletteHidden,
                    --palette--;;paletteAccess,
                    '],
        IndexTechnology::Database->value => ['showitem' => '--div--;' . $lll . 'tx_index_domain_model_configuration.tab.general,
                    title,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.pages,
                    technology,languages,skip_no_search_pages,content_indexing,levels,partial_indexing,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.files,
                    file_mounts,file_types,
                    --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                    --palette--;;paletteHidden,
                    --palette--;;paletteAccess,
                    '],
        IndexTechnology::Frontend->value => ['showitem' => '--div--;' . $lll . 'tx_index_domain_model_configuration.tab.general,
                    title,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.pages,
                    technology,languages,skip_no_search_pages,levels,configuration,partial_indexing,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.files,
                    file_mounts,file_types,
                    --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                    --palette--;;paletteHidden,
                    --palette--;;paletteAccess,
                    '],
        IndexTechnology::Http->value => ['showitem' => '--div--;' . $lll . 'tx_index_domain_model_configuration.tab.general,
                    title,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.pages,
                    technology,languages,skip_no_search_pages,levels,configuration,partial_indexing,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.files,
                    file_mounts,file_types,
                    --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                    --palette--;;paletteHidden,
                    --palette--;;paletteAccess,
                    '],
    ],
];

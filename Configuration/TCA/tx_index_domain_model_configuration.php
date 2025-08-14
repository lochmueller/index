<?php

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
                }, array_filter(IndexTechnology::cases(), function ($item) {
                    return $item !== IndexTechnology::External;
                })),
            ],
        ],
        'partial_indexing' => [
            'label' => $lll . 'tx_index_domain_model_configuration.partial_indexing',
            'description' => $lll . 'tx_index_domain_model_configuration.partial_indexing.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectCheckBox',
                'items' => array_map(function ($enum) use ($lll) {
                    return [
                        'label' => $lll . 'tx_index_domain_model_configuration.partial_indexing.type.' .$enum->value,
                        'value' => $enum->value,
                    ];
                }, IndexPartialTrigger::cases()),
            ],
        ],
        'configuration' => [
            'label' => 'Index configuration',
            'description' => 'Configuration of the index process via YAML.',
            'config' => [
                'type' => 'json',
                'default' => '{}',
            ],
        ],
        'languages' => [ // @todo integrate
            'exclude' => 0,
            'title' => $lll . 'tx_index_domain_model_index_page.languages',
            'config' => [
                'type' => 'languages',
            ],
        ],
        'tags' => [ // @todo integrate
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
    'types' => [
        IndexTechnology::None->value => ['showitem' => '--div--;' . $lll . 'tx_index_domain_model_configuration.tab.general, 
                    title,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.pages,
                    technology'],
        IndexTechnology::Cache->value => ['showitem' => '--div--;' . $lll . 'tx_index_domain_model_configuration.tab.general, 
                    title,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.pages,
                    technology,skip_no_search_pages,levels,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.files,
                    file_mounts,file_types'],
        IndexTechnology::Database->value => ['showitem' => '--div--;' . $lll . 'tx_index_domain_model_configuration.tab.general, 
                    title,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.pages,
                    technology,skip_no_search_pages,levels,partial_indexing,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.files,
                    file_mounts,file_types
                    '],
        IndexTechnology::Frontend->value => ['showitem' => '--div--;' . $lll . 'tx_index_domain_model_configuration.tab.general, 
                    title,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.pages,
                    technology,skip_no_search_pages,levels,configuration,partial_indexing,
                    --div--;' . $lll . 'tx_index_domain_model_configuration.tab.files,
                    file_mounts,file_types
                    '],
    ],
];
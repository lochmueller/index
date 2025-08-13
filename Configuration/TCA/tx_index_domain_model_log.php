<?php

use Lochmueller\Index\Enums\IndexTechnology;

$lll = 'LLL:EXT:index/Resources/Private/Language/locallang.xlf:';

return [
    'ctrl' => [
        'title' => $lll . 'tx_index_domain_model_log',
        'iconfile' => 'EXT:index/Resources/Public/Icons/Extension.svg',
        'label' => 'start_time',
        'tstamp' => 'tstamp',
        'rootLevel' => 1,
        'crdate' => 'crdate',
        'default_sortby' => 'ORDER BY start_time DESC',
    ],
    'columns' => [
        'index_process_id' => [
            'exclude' => 0,
            'label' => $lll . 'tx_index_domain_model_log.index_process_id',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'readOnly' => true,
            ],
        ],
        'start_time' => [
            'exclude' => 0,
            'label' => $lll . 'tx_index_domain_model_log.start_time',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'size' => '30',
                'readOnly' => true,
            ],
        ],
        'end_time' => [
            'exclude' => 0,
            'label' => $lll . 'tx_index_domain_model_log.end_time',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'size' => '30',
                'readOnly' => true,
            ],
        ],
        'pages_counter' => [
            'exclude' => 0,
            'label' => $lll . 'tx_index_domain_model_log.pages_counter',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'files_counter' => [
            'exclude' => 0,
            'label' => $lll . 'tx_index_domain_model_log.files_counter',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
    ],
    'types' => [
        '0' => ['showitem' => '--div--;' . $lll . 'tx_index_domain_model_configuration.tab.general, 
                    index_process_id, start_time, end_time, pages_counter, files_counter'],
    ],
];
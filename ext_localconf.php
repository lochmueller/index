<?php

use Lochmueller\Index\Queue\Message\CachePageMessage;
use Lochmueller\Index\Queue\Message\DatabaseIndexMessage;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Lochmueller\Index\Queue\Message\FrontendIndexMessage;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\Writer\FileWriter;
use TYPO3\CMS\Core\Utility\GeneralUtility;


// @todo why this do not work?!
$GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing']['Lochmueller\\Index\\Queue\\Message\\*'] = ['doctrine'];

// @todo Workarround
/*$GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'] = [
    StartProcessMessage::class => 'doctrine',
    FinishProcessMessage::class => 'doctrine',
    CachePageMessage::class => 'doctrine',
    FrontendIndexMessage::class => 'doctrine',
    DatabaseIndexMessage::class => 'doctrine',
    '*' => 'default',
];
*/


/** @var Environment $context */
$environment = GeneralUtility::makeInstance(Environment::class);
if ($environment->getContext()->isDevelopment()) {
    $GLOBALS['TYPO3_CONF_VARS']['LOG']['Lochmueller']['Index']['EventListener']['writerConfiguration'] = [
        LogLevel::DEBUG => [
            FileWriter::class => [
                'logFileInfix' => 'index'
            ],
        ],
    ];
}
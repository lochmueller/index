<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'ext-index-icon' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:seal/Resources/Public/Icons/Extension.svg',
    ],
];

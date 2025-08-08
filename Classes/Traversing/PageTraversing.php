<?php

declare(strict_types=1);

namespace Lochmueller\Index\Traversing;

class PageTraversing
{
    public function getaPageInformationByPageId(int $pageId): iterable {}

    public function getPageRecordsByPageId(int $pageId): iterable {}
    // @todo handling of traversing the page tree and find valid pages

}

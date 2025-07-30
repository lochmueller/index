<?php

declare(strict_types=1);

namespace Lochmueller\Index\Index\Database\Extender;

class News extends AbstractExtender
{
    public function getItems(): iterable
    {
        // @todo index configuration
        // Type (News, Address)
        // Index configuration:
        // yaml? // json
        // index:
        //     type: news
        //     page: 123
        //     storagePids: 123,1231,324
        //
        //      type: file
        //     storages: 0:templates/(pdf|doc),1:otherpath/(pdf)

        // TODO: Implement getItems() method.
    }
}

<?php

declare(strict_types=1);

namespace Lochmueller\Index\Queue\Message;

final class DatabaseIndexMessage
{
    public function __construct(
        public string $test,
    ) {}
    // @todo fill with usefull information

}

<?php

declare(strict_types=1);

namespace App\Domains\Integration\Messenger;

use App\Domains\Integration\Entity\Source;

interface MessengerProviderFactoryInterface
{
    public function forSource(Source $source): MessengerProviderInterface;
}

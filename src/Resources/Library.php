<?php

declare(strict_types=1);

namespace HeroShots\Resources;

use HeroShots\Client;

/**
 * Library aggregate: backgrounds, assets, collections, categories.
 * JWT-or-API-key (/api/v1/...). Brand/admin gated; not for model accounts.
 */
class Library
{
    public Backgrounds $backgrounds;
    public Assets $assets;
    public Collections $collections;
    public Categories $categories;

    public function __construct(Client $client)
    {
        $this->backgrounds = new Backgrounds($client);
        $this->assets = new Assets($client);
        $this->collections = new Collections($client);
        $this->categories = new Categories($client);
    }
}

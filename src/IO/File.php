<?php

declare(strict_types=1);

namespace PokemonGoLingen\PogoAPI\IO;

use function file_put_contents;

final class File
{
    private string $content;

    public function __construct(string $content)
    {
        $this->content = $content;
    }

    public function saveTo(string $fileName): void
    {
        file_put_contents($fileName, $this->content);
    }
}
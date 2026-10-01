<?php

declare(strict_types=1);

namespace Vwork\Web\Router;

enum RouterTypes: string
{
    case Trie = 'trie';
    case HashTable = 'hash-table';
}

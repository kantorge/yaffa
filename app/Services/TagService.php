<?php

namespace App\Services;

use App\Models\Tag;

class TagService
{
    public function delete(Tag $tag): void
    {
        $tag->delete();
    }
}

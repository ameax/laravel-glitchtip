<?php

namespace Ameax\Glitchtip\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class FakeTenant extends Model
{
    protected $guarded = [];

    public static ?self $current = null;

    public static function current(): ?self
    {
        return static::$current;
    }
}

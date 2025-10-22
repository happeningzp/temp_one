<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceData extends Model
{
    use HasFactory;

    protected $table = 'services';

    protected $fillable = ['id', 'name', 'data'];
}

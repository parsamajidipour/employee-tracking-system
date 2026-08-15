<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppRelease extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'version_code',
        'version_name',
        'file_path',
        'file_size',
        'release_notes',
        'is_mandatory',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_code' => 'integer',
            'file_size' => 'integer',
            'is_mandatory' => 'boolean',
        ];
    }
}

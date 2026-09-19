<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundDisclosure extends Model
{
    protected $table = 'fund_disclosures';
    protected $primaryKey = 'disclosure_index';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'disclosure_index', 'fund_code', 'fund_title', 'subject', 'published_at',
        'is_late', 'disclosure_url', 'pdf_url', 'pdf_name', 'attachment_count',
    ];

    protected function casts(): array
    {
        return [
            'disclosure_index' => 'integer',
            'published_at' => 'datetime',
            'is_late' => 'boolean',
            'attachment_count' => 'integer',
            'discovered_at' => 'datetime',
        ];
    }

    public function fund()
    {
        return $this->belongsTo(Fund::class, 'fund_code', 'code');
    }
}

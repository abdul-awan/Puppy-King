<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\TemplateLayout;
use App\Support\Documents\Templates\TemplateDefinition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A company's own template for quotations, invoices and credit notes, made
 * with the template builder. "layout" is the ready-made template it started
 * from; "design" holds its page style and rows of elements.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property TemplateLayout $layout
 * @property array<string, mixed> $design
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 */
#[Fillable(['name', 'layout', 'design', 'created_by'])]
class DocumentTemplate extends Model
{
    use BelongsToTeam;

    /**
     * The prefix of the keys of a company's own templates ("custom:12"); ready-made templates use the layout ("modern").
     */
    public const KEY_PREFIX = 'custom:';

    /**
     * Get the key used to choose this template in the settings.
     */
    public function key(): string
    {
        return self::KEY_PREFIX.$this->id;
    }

    /**
     * Get the template: rows of elements made with the builder (a template saved before the builder
     * existed becomes the ready-made template of its layout with its colour, type and table).
     */
    public function toDefinition(): TemplateDefinition
    {
        return TemplateDefinition::stored($this->layout, $this->design);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'layout' => TemplateLayout::class,
            'design' => 'array',
        ];
    }
}

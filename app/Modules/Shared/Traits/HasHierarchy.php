<?php

namespace App\Modules\Shared\Traits;

trait HasHierarchy
{
    /**
     * Boot the trait
     */
    protected static function bootHasHierarchy(): void
    {
        static::saving(function ($model) {
            if ($model->isDirty('parent_id') || ! $model->path) {
                $model->updatePath();
            }
        });
    }

    /**
     * Relationship ke parent
     */
    public function parent()
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * Relationship ke children
     */
    public function children()
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    /**
     * Get ancestors (parent, grandparent, etc)
     */
    public function ancestors()
    {
        $ancestors = collect();
        $parent = $this->parent;

        while ($parent) {
            $ancestors->push($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Get descendants (children, grandchildren, etc)
     */
    public function descendants()
    {
        return static::where('path', 'like', $this->path.'%')
            ->where('id', '!=', $this->id)
            ->get();
    }

    /**
     * Get tree untuk select dropdown
     */
    public static function getTree($exceptId = null, $prefix = '— ')
    {
        return static::whereNull('parent_id')
            ->when($exceptId, function ($q) use ($exceptId) {
                $q->where('id', '!=', $exceptId);
            })
            ->with('children')
            ->get()
            ->map(function ($item) use ($prefix) {
                return static::formatTreeOptions($item, $prefix);
            })
            ->flatten()
            ->pluck('name', 'id');
    }

    /**
     * Format tree options recursively
     */
    protected static function formatTreeOptions($item, $prefix = '', $level = 0)
    {
        $result = collect([
            $item->id => str_repeat($prefix, $level).$item->name,
        ]);

        foreach ($item->children as $child) {
            $result = $result->merge(static::formatTreeOptions($child, $prefix, $level + 1));
        }

        return $result;
    }

    /**
     * Update materialized path
     */
    public function updatePath()
    {
        if (! $this->parent_id) {
            $this->path = '/'.$this->id;
        } else {
            $parent = static::find($this->parent_id);
            $this->path = $parent->path.'/'.$this->id;
        }

        $this->level = substr_count($this->path, '/');
    }

    /**
     * Scope untuk root nodes
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope untuk nodes pada level tertentu
     */
    public function scopeWhereLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope descendants of given node
     */
    public function scopeDescendantsOf($query, $nodeId)
    {
        $node = static::find($nodeId);

        if (! $node) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('path', 'like', $node->path.'%')
            ->where('id', '!=', $nodeId);
    }
}

<?php

namespace MrNamra\AutoGraphQL;

use GraphQL\Language\AST\SelectionSetNode;

class EagerLoadingAnalyzer
{
    private int $maxDepth;

    public function __construct()
    {
        $this->maxDepth = config('autographql.eager_load_depth', 5);
    }

    /**
     * Analyze the GraphQL selection set and return the eager load array.
     */
    public function analyze(
        string $modelClass,
        array  $requestedFields,
        int    $depth = 0
    ): array {
        if ($depth >= $this->maxDepth || !class_exists($modelClass)) {
            return [];
        }

        $detector  = app(RelationshipDetector::class);
        $relations = $detector->detect($modelClass);
        $eagerLoad = [];

        foreach ($requestedFields as $field => $subFields) {
            // Is this field a relationship on the current model?
            if (!isset($relations[$field])) {
                continue;
            }

            // Add this relationship to eager loads
            $eagerLoad[] = $field;

            // Recurse into nested relationships if requested
            if (!empty($subFields)) {
                $relatedModel = $relations[$field]['related_model'];
                $nested       = $this->analyze($relatedModel, $subFields, $depth + 1);

                foreach ($nested as $nestedRelation) {
                    $eagerLoad[] = "{$field}.{$nestedRelation}";
                }
            }
        }

        return array_unique($eagerLoad);
    }

    /**
     * Parse GraphQL AST selection set into a nested array for easier analysis.
     */
    public function parseSelectionSet(SelectionSetNode $selectionSet): array
    {
        $fields = [];

        foreach ($selectionSet->selections as $selection) {
            /** @var \GraphQL\Language\AST\FieldNode $selection */
            $fieldName = $selection->name->value;
            $fields[$fieldName] = $selection->selectionSet
                ? $this->parseSelectionSet($selection->selectionSet)
                : [];
        }

        return $fields;
    }
}

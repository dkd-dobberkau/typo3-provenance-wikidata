<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceWikidata\Value;

/**
 * The predicate a fact gets for a Wikidata property: the schema.org term
 * where one exists, so facts read the same with or without Wikidata, else
 * the property's URI. The reverse of PROPERTIES in check_wikidata.py of the
 * conformance suite, so facts made here can be checked there.
 */
final class PropertyMapping
{
    private const SCHEMA_ORG = [
        'P571' => 'foundingDate',
        'P577' => 'datePublished',
        'P569' => 'birthDate',
        'P570' => 'deathDate',
        'P22' => 'parent',
        'P25' => 'parent',
        'P131' => 'containedInPlace',
        'P276' => 'location',
        'P112' => 'founder',
        'P50' => 'author',
    ];

    public static function predicateFor(string $propertyId): string
    {
        return self::SCHEMA_ORG[$propertyId] ?? 'http://www.wikidata.org/prop/direct/' . $propertyId;
    }
}

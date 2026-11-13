<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceWikidata\Wikidata;

use CmsProvenance\ProvenanceWikidata\Value\PropertyMapping;
use CmsProvenance\ProvenanceWikidata\Value\WikidataTime;

/**
 * The statements of a Wikidata item as rows for the fact dialog: what an
 * editor reads, and what "Use" puts into the fact's fields.
 */
final class StatementPresenter
{
    private const ENTITY = 'http://www.wikidata.org/entity/';
    private const REFERENCE_URL = 'P854';
    private const RANKS = ['preferred' => 0, 'normal' => 1];
    // Identifiers in other databases and image file names state nothing a fact would.
    private const HIDDEN_DATATYPES = ['external-id', 'commonsMedia'];

    public function __construct(private readonly WikidataClient $client) {}

    /**
     * @return array{item: array{id: string, label: string, description: string, uri: string}, rows: list<array{property: string, propertyLabel: string, value: string, reference: string, fill: array{predicate: string, objectLiteral: string, objectUri: string, sourceUrl: string}}>}|null
     *         null if Wikidata has no such item
     */
    public function rows(string $itemId, string $language): ?array
    {
        $entity = $this->client->getEntities([$itemId], $language)[$itemId] ?? null;
        if ($entity === null) {
            return null;
        }

        $statements = [];
        foreach ($entity['claims'] ?? [] as $property => $propertyStatements) {
            $kept = array_filter($propertyStatements, fn(array $statement): bool => isset(self::RANKS[$statement['rank'] ?? '']) && $this->isShown($statement['mainsnak'] ?? []));
            // Stable sort: Wikidata's own order within a rank stays.
            usort($kept, static fn(array $a, array $b): int => self::RANKS[$a['rank']] <=> self::RANKS[$b['rank']]);
            foreach ($kept as $statement) {
                $statements[] = [(string)$property, $statement];
            }
        }

        try {
            $labels = $this->client->getLabels($this->referencedIds($statements), $language);
        } catch (WikidataUnavailable) {
            // Labels only make the rows readable; the ids stand in for them.
            $labels = [];
        }
        $rows = [];
        foreach ($statements as [$property, $statement]) {
            try {
                $rows[] = $this->row($property, $statement, $labels);
            } catch (\InvalidArgumentException) {
                // A value Wikidata stores in a form this extension does not read.
            }
        }

        return [
            'item' => [
                'id' => $itemId,
                'label' => $this->text($entity['labels'] ?? [], $language) ?? $itemId,
                'description' => $this->text($entity['descriptions'] ?? [], $language) ?? '',
                'uri' => self::ENTITY . $itemId,
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $snak
     */
    private function isShown(array $snak): bool
    {
        if (($snak['snaktype'] ?? '') !== 'value' || in_array($snak['datatype'] ?? '', self::HIDDEN_DATATYPES, true)) {
            return false;
        }

        return in_array($snak['datavalue']['type'] ?? '', ['wikibase-entityid', 'time', 'quantity', 'string', 'monolingualtext'], true);
    }

    /**
     * Properties, item values and units, to be labelled in one request.
     *
     * @param list<array{string, array<string, mixed>}> $statements
     * @return list<string>
     */
    private function referencedIds(array $statements): array
    {
        $ids = [];
        foreach ($statements as [$property, $statement]) {
            $ids[$property] = true;
            $value = $statement['mainsnak']['datavalue']['value'];
            if (($statement['mainsnak']['datavalue']['type']) === 'wikibase-entityid') {
                $ids[(string)$value['id']] = true;
            }
            if (($statement['mainsnak']['datavalue']['type']) === 'quantity' && str_starts_with((string)$value['unit'], self::ENTITY)) {
                $ids[substr((string)$value['unit'], strlen(self::ENTITY))] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @param array<string, mixed> $statement
     * @param array<string, string> $labels
     * @return array{property: string, propertyLabel: string, value: string, reference: string, fill: array{predicate: string, objectLiteral: string, objectUri: string, sourceUrl: string}}
     */
    private function row(string $property, array $statement, array $labels): array
    {
        $datavalue = $statement['mainsnak']['datavalue'];
        $value = $datavalue['value'];
        $objectUri = '';
        switch ($datavalue['type']) {
            case 'wikibase-entityid':
                $text = $labels[$value['id']] ?? (string)$value['id'];
                $literal = $text;
                $objectUri = self::ENTITY . $value['id'];
                break;
            case 'time':
                $time = WikidataTime::fromValue($value);
                $text = $time->display();
                $literal = $time->factValue();
                break;
            case 'quantity':
                $text = ltrim((string)$value['amount'], '+');
                $unit = (string)$value['unit'];
                if (str_starts_with($unit, self::ENTITY)) {
                    $unitId = substr($unit, strlen(self::ENTITY));
                    $text .= ' ' . ($labels[$unitId] ?? $unitId);
                }
                $literal = $text;
                break;
            case 'monolingualtext':
                $text = $literal = (string)$value['text'];
                break;
            default:
                $text = $literal = (string)$value;
        }
        $reference = $this->referenceUrl($statement);

        return [
            'property' => $property,
            'propertyLabel' => $labels[$property] ?? $property,
            'value' => $text,
            'reference' => $reference,
            'fill' => [
                'predicate' => PropertyMapping::predicateFor($property),
                'objectLiteral' => $literal,
                'objectUri' => $objectUri,
                'sourceUrl' => $reference,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $statement
     */
    private function referenceUrl(array $statement): string
    {
        foreach ($statement['references'] ?? [] as $reference) {
            foreach ($reference['snaks'][self::REFERENCE_URL] ?? [] as $snak) {
                $url = $snak['datavalue']['value'] ?? null;
                if (is_string($url) && preg_match('#^https?://#', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) !== false) {
                    return $url;
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, array{value?: string}> $texts labels or descriptions by language
     */
    private function text(array $texts, string $language): ?string
    {
        return $texts[$language]['value'] ?? $texts['en']['value'] ?? null;
    }
}

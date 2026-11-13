<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceWikidata\Wikidata;

/**
 * Wikidata did not answer in time or not with what was asked for.
 */
final class WikidataUnavailable extends \RuntimeException {}

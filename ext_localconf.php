<?php

declare(strict_types=1);

defined('TYPO3') or die();

// Wikidata answers, so the dialog does not ask Wikidata twice for the same item.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['provenance_wikidata'] ??= [];

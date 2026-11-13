<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceWikidata\Value;

/**
 * A Wikidata time value, shown no more precisely than Wikidata states it.
 * Wikidata stores "1683" as +1683-01-01 with precision 9 (year); printing
 * the full date would invent a day.
 */
final readonly class WikidataTime
{
    private const MILLENNIUM = 6;
    private const CENTURY = 7;
    private const DECADE = 8;
    private const YEAR = 9;
    private const MONTH = 10;

    private function __construct(
        private int $year,
        private int $month,
        private int $day,
        private int $precision,
    ) {}

    /**
     * @param array<string, mixed> $value the "value" of a Wikidata datavalue of type "time"
     */
    public static function fromValue(array $value): self
    {
        if (preg_match('/^([+-])(\d+)-(\d{2})-(\d{2})T/', (string)($value['time'] ?? ''), $match) !== 1) {
            throw new \InvalidArgumentException('Not a Wikidata time: ' . ($value['time'] ?? ''), 1794700001);
        }
        $year = (int)$match[2] * ($match[1] === '-' ? -1 : 1);

        return new self($year, (int)$match[3], (int)$match[4], (int)($value['precision'] ?? self::YEAR));
    }

    /**
     * For reading, for example "10 August 1793", "1650s" or "3rd century BCE".
     */
    public function display(): string
    {
        if ($this->precision > self::MONTH && $this->month > 0 && $this->day > 0) {
            return $this->day . ' ' . $this->monthName() . ' ' . $this->yearText();
        }
        if ($this->precision === self::MONTH && $this->month > 0) {
            return $this->monthName() . ' ' . $this->yearText();
        }
        if ($this->precision === self::DECADE) {
            return $this->era(intdiv(abs($this->year), 10) * 10 . 's');
        }
        if ($this->precision === self::CENTURY) {
            return $this->era($this->ordinal($this->span(100)) . ' century');
        }
        if ($this->precision === self::MILLENNIUM) {
            return $this->era($this->ordinal($this->span(1000)) . ' millennium');
        }
        if ($this->precision < self::MILLENNIUM) {
            return $this->era($this->years());
        }

        return $this->yearText();
    }

    /**
     * For the fact's value: ISO 8601 where Wikidata is at least as precise as
     * a year (what check_wikidata.py compares), otherwise the displayed text.
     */
    public function factValue(): string
    {
        if ($this->year < 0 || $this->precision < self::YEAR) {
            return $this->display();
        }
        if ($this->precision > self::MONTH && $this->month > 0 && $this->day > 0) {
            return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
        }
        if ($this->precision === self::MONTH && $this->month > 0) {
            return sprintf('%04d-%02d', $this->year, $this->month);
        }

        return (string)$this->year;
    }

    private function yearText(): string
    {
        return $this->era((string)abs($this->year));
    }

    private function era(string $text): string
    {
        return $this->year < 0 ? $text . ' BCE' : $text;
    }

    private function monthName(): string
    {
        return \DateTimeImmutable::createFromFormat('!n', (string)$this->month)->format('F');
    }

    /**
     * The century or millennium a year falls in, counted as Wikidata counts:
     * 1700 is in the 17th century, 1750 in the 18th.
     */
    private function span(int $length): int
    {
        return intdiv(abs($this->year) - 1, $length) + 1;
    }

    /**
     * Below millennium precision Wikidata gives a number of years, rounded to
     * the precision: 10000 years at precision 5, 14 billion at precision 0.
     */
    private function years(): string
    {
        $unit = 10 ** (self::YEAR - max(0, $this->precision));
        $years = (int)round(abs($this->year) / $unit) * $unit;
        foreach ([1_000_000_000 => 'billion', 1_000_000 => 'million'] as $size => $word) {
            if ($years >= $size) {
                return round($years / $size, 3) . ' ' . $word . ' years';
            }
        }

        return $years . ' years';
    }

    private function ordinal(int $number): string
    {
        if (in_array($number % 100, [11, 12, 13], true)) {
            return $number . 'th';
        }

        return $number . match ($number % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}

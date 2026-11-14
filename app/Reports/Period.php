<?php

declare(strict_types=1);

namespace App\Reports;

use App\Support\BsDate;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * A from–to date range (inclusive) in the restaurant's time zone.
 */
final class Period
{
    public function __construct(public readonly CarbonImmutable $from, public readonly CarbonImmutable $to) {}

    public static function make(?string $from, ?string $to): self
    {
        $f = self::parse($from) ?? CarbonImmutable::today();
        $t = self::parse($to) ?? $f;
        if ($t->lessThan($f)) {
            [$f, $t] = [$t, $f];
        }
        // Keep reports fast: at most about 13 months.
        if ($f->diffInDays($t) > 400) {
            $f = $t->subDays(400);
        }

        return new self($f->startOfDay(), $t->endOfDay());
    }

    public function fromDate(): string
    {
        return $this->from->toDateString();
    }

    public function toDate(): string
    {
        return $this->to->toDateString();
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function between(): array
    {
        return [$this->from->toDateTimeString(), $this->to->toDateTimeString()];
    }

    public function label(): string
    {
        $ad = $this->fromDate() === $this->toDate() ? $this->from->format('d M Y') : $this->from->format('d M Y').' – '.$this->to->format('d M Y');

        return $this->bsLabel().' BS ('.$ad.')';
    }

    public function bsLabel(): string
    {
        return $this->fromDate() === $this->toDate() ? BsDate::long($this->from) : BsDate::long($this->from).' – '.BsDate::long($this->to);
    }

    private static function parse(?string $value): ?CarbonImmutable
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}

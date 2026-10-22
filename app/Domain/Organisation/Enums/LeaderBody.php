<?php

namespace App\Domain\Organisation\Enums;

/**
 * The bodies the leadership directory is grouped by (PLAN.md §4 About:
 * Executive Committee, Central Committee, Secretariat, office heads), plus
 * league and forum leaders, and the region's government and councils
 * (ADR-0063).
 */
enum LeaderBody: string
{
    case ExecutiveCommittee = 'executive_committee';
    case CentralCommittee = 'central_committee';
    case Secretariat = 'secretariat';
    case OfficeHeads = 'office_heads';
    case Leagues = 'leagues';
    case Government = 'government';

    /**
     * The order of the leadership page (ADR-0066): the region's government
     * and councils first, then the party's bodies.
     *
     * @return list<self>
     */
    public static function displayOrder(): array
    {
        return [self::Government, self::ExecutiveCommittee, self::CentralCommittee, self::Secretariat, self::OfficeHeads, self::Leagues];
    }

    public function description(?string $locale = null): string
    {
        return __("organisation.leader_body_desc.{$this->value}", [], $locale);
    }

    public function label(?string $locale = null): string
    {
        return __("organisation.leader_body.{$this->value}", [], $locale);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}

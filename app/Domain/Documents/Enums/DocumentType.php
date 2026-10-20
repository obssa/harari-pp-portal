<?php

namespace App\Domain\Documents\Enums;

/**
 * Kinds of document (PLAN.md §4: programme, bylaw, report, policy, speech,
 * form; vacancies and tenders with deadlines). Notices are listed on their
 * own page with their deadlines, not in the library (ADR-0026).
 */
enum DocumentType: string
{
    case Programme = 'programme';
    case Manifesto = 'manifesto';
    case Bylaw = 'bylaw';
    case Report = 'report';
    case Policy = 'policy';
    case Speech = 'speech';
    case Form = 'form';
    case Publication = 'publication';
    case Vacancy = 'vacancy';
    case Tender = 'tender';
    case Notice = 'notice';

    public function label(): string
    {
        return __("documents.type.{$this->value}");
    }

    public function isNotice(): bool
    {
        return in_array($this, self::notices(), true);
    }

    /**
     * @return list<self>
     */
    public static function notices(): array
    {
        return [self::Vacancy, self::Tender, self::Notice];
    }

    /**
     * @return list<self>
     */
    public static function library(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type): bool => ! $type->isNotice()));
    }

    /**
     * @param  list<self>  $types
     * @return array<string, string>
     */
    public static function options(array $types): array
    {
        $options = [];
        foreach ($types as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }
}

<?php

namespace App\Domain\Forms\Enums;

/**
 * What a form is for (PLAN.md §7 forms builder: contact, feedback,
 * petition, vacancy application; press enquiries for the press kit).
 */
enum FormType: string
{
    case Contact = 'contact';
    case Feedback = 'feedback';
    case Petition = 'petition';
    case Press = 'press';
    case Vacancy = 'vacancy';
    case General = 'general';

    public function label(): string
    {
        return __("forms.type.{$this->value}");
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }
}

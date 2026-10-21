<?php

namespace App\Domain\Membership\Support;

use Illuminate\Support\Str;

/**
 * The details an imported roll can carry (ADR-0047), and the headings they
 * usually have in English, Amharic and Afaan Oromo, so the columns of a
 * file are matched before the officer checks them.
 */
final class ImportColumns
{
    /** @var array<string, list<string>> field => headings that usually mean it */
    public const FIELDS = [
        'name' => ['name', 'full name', 'member name', 'ስም', 'ሙሉ ስም', 'maqaa', 'maqaa guutuu'],
        'sex' => ['sex', 'gender', 'ፆታ', 'ጾታ', 'saala', 'koorniyaa'],
        'birth_date' => ['date of birth', 'birth date', 'dob', 'birth year', 'year of birth', 'የትውልድ ቀን', 'የትውልድ ዘመን', 'የትውልድ ዓመት', 'guyyaa dhalootaa', 'bara dhalootaa'],
        'id_number' => ['id', 'id number', 'id no', 'fayda', 'national id', 'kebele id', 'የመታወቂያ ቁጥር', 'መታወቂያ', 'lakkoofsa eenyummaa', 'eenyummaa'],
        'phone' => ['phone', 'mobile', 'telephone', 'phone number', 'ስልክ', 'ስልክ ቁጥር', 'bilbila', 'lakkoofsa bilbilaa'],
        'email' => ['email', 'e-mail', 'ኢሜይል', 'imeelii'],
        'woreda' => ['woreda', 'district', 'ወረዳ', 'aanaa'],
        'kebele' => ['kebele', 'ቀበሌ', 'ganda'],
        'basic_org' => ['basic organisation', 'basic organization', 'cell', 'መሠረታዊ ድርጅት', 'መሰረታዊ ድርጅት', "jaarmiyaa bu'uuraa"],
        'address_note' => ['address', 'አድራሻ', 'teessoo'],
        'education' => ['education', 'education level', 'ትምህርት', 'የትምህርት ደረጃ', 'barnoota', 'sadarkaa barnootaa'],
        'occupation' => ['occupation', 'job', 'profession', 'ሥራ', 'ስራ', 'የሥራ ዘርፍ', 'hojii'],
        'joined_on' => ['joined', 'date joined', 'membership date', 'joined on', 'የተቀላቀለበት ቀን', 'የአባልነት ቀን', 'guyyaa makame', 'guyyaa miseensa ta\'e'],
        'old_number' => ['member number', 'member no', 'membership number', 'number', 'no', 'የአባልነት ቁጥር', 'lakkoofsa miseensummaa'],
    ];

    /**
     * Which column each field probably is.
     *
     * @param  list<string>  $headings
     * @return array<string, int|null>
     */
    public static function guess(array $headings): array
    {
        $normal = array_map(self::normalise(...), $headings);
        $guess = [];
        foreach (self::FIELDS as $field => $names) {
            $index = null;
            foreach ($names as $name) {
                $found = array_search(self::normalise($name), $normal, true);
                if ($found !== false && ! in_array($found, $guess, true)) {
                    $index = (int) $found;

                    break;
                }
            }
            $guess[$field] = $index;
        }

        return $guess;
    }

    public static function normalise(string $text): string
    {
        return Str::of(str_replace("\u{FEFF}", '', $text))->lower()->replaceMatches('/[\s_.:#*-]+/u', ' ')->trim()->toString();
    }
}

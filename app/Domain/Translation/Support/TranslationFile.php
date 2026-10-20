<?php

namespace App\Domain\Translation\Support;

use App\Domain\Translation\Models\UiTranslation;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Throwable;

/**
 * Translation files in and out (ADR-0033): flat JSON as in the repository,
 * or a sheet (CSV or Excel) with the columns key, en, the language and
 * context, so translators can work in a spreadsheet. Every cell is text.
 */
final class TranslationFile
{
    public const FORMATS = ['xlsx', 'csv', 'json'];

    /** Largest file read. */
    public const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(private readonly TranslationCatalog $catalog) {}

    /** Writes the language to a new file and returns its path. */
    public function export(string $locale, string $format): string
    {
        abort_unless(in_array($format, self::FORMATS, true) && in_array($locale, Locales::all(), true), 404);

        $english = $this->catalog->values('en');
        $values = $this->catalog->values($locale);
        $contexts = UiTranslation::query()->where('group', '*')->whereNotNull('context')->pluck('context', 'key')->all();
        $directory = storage_path('app/translations/exports');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $path = $directory.DIRECTORY_SEPARATOR."{$locale}-".now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).".{$format}";

        $rows = [];
        foreach ($this->catalog->sourceKeys() as $key) {
            $rows[$key] = [$key, (string) ($english[$key] ?? ''), (string) ($values[$key] ?? ''), (string) ($contexts[$key] ?? '')];
        }

        match ($format) {
            'json' => file_put_contents($path, self::encode(array_map(fn (array $row): string => $row[2], $rows))),
            'csv' => $this->writeCsv($path, $locale, $rows),
            default => $this->writeXlsx($path, $locale, $rows),
        };

        return $path;
    }

    /**
     * The layout of the files in the repository, so exports diff cleanly.
     *
     * @param  array<string, string>  $values
     */
    public static function encode(array $values): string
    {
        ksort($values);

        return json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * Reads a file into key => text for the language.
     *
     * @return array<string, string>
     */
    public function read(string $path, string $originalName, string $locale): array
    {
        $format = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (! in_array($format, self::FORMATS, true)) {
            throw ValidationException::withMessages(['file' => __('translations.import.validation.format')]);
        }
        if (! is_file($path) || filesize($path) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => __('translations.import.validation.size')]);
        }

        try {
            $values = match ($format) {
                'json' => $this->readJson($path),
                'csv' => $this->readSheet($this->csvRows($path), $locale),
                default => $this->readSheet($this->xlsxRows($path), $locale),
            };
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => __('translations.import.validation.unreadable')]);
        }

        foreach ($values as $text) {
            if (! mb_check_encoding($text, 'UTF-8')) {
                throw ValidationException::withMessages(['file' => __('translations.import.validation.encoding')]);
            }
        }

        return $values;
    }

    /**
     * @param  array<string, list<string>>  $rows
     */
    private function writeCsv(string $path, string $locale, array $rows): void
    {
        $out = fopen($path, 'wb');
        if ($out === false) {
            throw new \RuntimeException("Cannot write {$path}");
        }
        // A byte-order mark so spreadsheet programs open the file as UTF-8 (Ethiopic script).
        fwrite($out, "\u{FEFF}");
        fputcsv($out, ['key', 'en', $locale, 'context'], escape: '');
        foreach ($rows as $row) {
            fputcsv($out, $row, escape: '');
        }
        fclose($out);
    }

    /**
     * @param  array<string, list<string>>  $rows
     */
    private function writeXlsx(string $path, string $locale, array $rows): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['key', 'en', $locale, 'context']));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();
    }

    /**
     * @return array<string, string>
     */
    private function readJson(string $path): array
    {
        try {
            $data = json_decode((string) file_get_contents($path), true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['file' => __('translations.import.validation.unreadable')]);
        }
        if (! is_array($data)) {
            throw ValidationException::withMessages(['file' => __('translations.import.validation.unreadable')]);
        }

        $values = [];
        foreach ($data as $key => $text) {
            if (is_string($key) && (is_string($text) || is_int($text) || is_float($text))) {
                $values[$key] = (string) $text;
            }
        }

        return $values;
    }

    /**
     * The key column and the language column, found by their headings.
     *
     * @param  iterable<list<mixed>>  $rows
     * @return array<string, string>
     */
    private function readSheet(iterable $rows, string $locale): array
    {
        $keyColumn = null;
        $textColumn = null;
        $values = [];
        foreach ($rows as $cells) {
            $cells = array_map(fn (mixed $cell): string => is_scalar($cell) ? (string) $cell : '', $cells);
            if ($keyColumn === null) {
                $headings = array_map(fn (string $h): string => strtolower(trim(str_replace("\u{FEFF}", '', $h))), $cells);
                $keyColumn = array_search('key', $headings, true);
                $textColumn = array_search($locale, $headings, true);
                if ($keyColumn === false || $textColumn === false) {
                    throw ValidationException::withMessages(['file' => __('translations.import.validation.columns', ['language' => $locale])]);
                }

                continue;
            }
            $key = trim($cells[$keyColumn] ?? '');
            if ($key !== '') {
                $values[$key] = str_replace("\r\n", "\n", $cells[$textColumn] ?? '');
            }
        }

        return $values;
    }

    /**
     * @return \Generator<list<mixed>>
     */
    private function csvRows(string $path): \Generator
    {
        $in = fopen($path, 'rb');
        if ($in === false) {
            return;
        }
        while (($row = fgetcsv($in, escape: '')) !== false) {
            yield $row;
        }
        fclose($in);
    }

    /**
     * @return \Generator<list<mixed>>
     */
    private function xlsxRows(string $path): \Generator
    {
        $reader = new XlsxReader;
        $reader->open($path);
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                yield $row->toArray();
            }

            break;
        }
        $reader->close();
    }
}

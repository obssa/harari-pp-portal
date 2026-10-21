<?php

namespace App\Domain\Api\Enums;

/**
 * What an API key may read (ADR-0056). Each key gets only what its app needs.
 */
enum ApiScope: string
{
    case Content = 'content';
    case Events = 'events';
    case Documents = 'documents';
    case Directory = 'directory';
    case Projects = 'projects';
    case Search = 'search';

    public function label(): string
    {
        return __('admin.api_keys.scope.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $scope) {
            $options[$scope->value] = $scope->label();
        }

        return $options;
    }

    /**
     * Known scopes among `$values`, in the order above.
     *
     * @param  array<mixed>  $values
     * @return list<string>
     */
    public static function clean(array $values): array
    {
        $given = array_map('strval', array_filter($values, 'is_scalar'));

        return array_values(array_filter(
            array_map(fn (self $scope): string => $scope->value, self::cases()),
            fn (string $scope): bool => in_array($scope, $given, true),
        ));
    }
}

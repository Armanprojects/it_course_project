<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\Locale;
use App\Enum\Theme;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Language and theme of the interface.
 *
 * Both fields are optional: the settings switcher changes one at a time, and
 * an absent field means "leave as is" rather than "reset to default".
 */
final class UpdateSettingsRequest
{
    public function __construct(
        #[Assert\Choice(callback: [self::class, 'locales'], message: 'Unsupported language.')]
        public readonly ?string $locale = null,

        #[Assert\Choice(callback: [self::class, 'themes'], message: 'Unsupported theme.')]
        public readonly ?string $theme = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function locales(): array
    {
        return array_column(Locale::cases(), 'value');
    }

    /**
     * @return list<string>
     */
    public static function themes(): array
    {
        return array_column(Theme::cases(), 'value');
    }

    public function localeEnum(): ?Locale
    {
        return null === $this->locale ? null : Locale::from($this->locale);
    }

    public function themeEnum(): ?Theme
    {
        return null === $this->theme ? null : Theme::from($this->theme);
    }
}
